<?php

namespace TortoiseIT\LaravelPeriscope\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class EntryLifecycle
{
    public function __construct(private readonly TraceFormatter $traceFormatter)
    {
    }

    public function build(object $selectedEntry, Collection $entries): array
    {
        $requestEntry = $selectedEntry->type === 'request'
            ? $selectedEntry
            : $entries->first(fn (object $entry) => $entry->type === 'request');
        $events = $entries
            ->values()
            ->map(fn (object $entry, int $index) => $this->eventFor($entry, $selectedEntry, $requestEntry, $index + 1))
            ->reject(fn (array $event) => $this->isVendorEvent($event))
            ->values()
            ->map(fn (array $event, int $index) => array_replace($event, ['position' => $index + 1]));

        return [
            'request' => $requestEntry,
            'classification' => $requestEntry ? $this->classifyRequest($requestEntry) : null,
            'request_context' => $requestEntry ? $this->requestContextFor($requestEntry) : null,
            'summary' => $this->summarize($requestEntry, $entries),
            'error_trail' => $this->traceFormatter->errorTrail($entries),
            'query_health' => $this->queryHealthFor($entries),
            'external_health' => $this->externalHealthFor($entries),
            'pre_error_context' => $this->preErrorContextFor($entries),
            'debug_bundle' => $this->debugBundleFor($requestEntry, $entries),
            'timeline' => $this->timelineFor($events),
            'phases' => $events
                ->groupBy('phase')
                ->map(fn (Collection $events, string $phase) => [
                    'key' => $phase,
                    'label' => $this->phaseLabel($phase),
                    'events' => $events->values(),
                ])
                ->sortBy(fn (array $phase) => array_search($phase['key'], $this->phaseOrder(), true))
                ->values(),
        ];
    }

    private function requestContextFor(object $entry): array
    {
        $content = $entry->content;
        $headers = array_change_key_case($content['headers'] ?? [], CASE_LOWER);
        $payload = $content['payload'] ?? [];
        $middleware = $content['middleware'] ?? [];

        return [
            'method' => $this->stringValue($content['method'] ?? null),
            'path' => $this->stringValue($content['uri'] ?? $content['url'] ?? null),
            'controller' => $this->stringValue($content['controller_action'] ?? null),
            'status' => $content['response_status'] ?? $content['status'] ?? null,
            'user' => $this->userFor($content),
            'ip' => $this->stringValue($content['ip_address'] ?? $content['ip'] ?? null),
            'payload_keys' => is_array($payload) ? collect(array_keys($payload))->take(8)->implode(', ') : null,
            'middleware_count' => is_array($middleware) ? count($middleware) : null,
            'accept' => $this->headerValue($headers, 'accept') ?: null,
        ];
    }

    private function queryHealthFor(Collection $entries): array
    {
        $queries = $entries->where('type', 'query')->values();
        $queryRows = $queries->map(function (object $entry): array {
            $sql = $this->stringValue($entry->content['sql'] ?? null) ?? $entry->summary['title'];

            return [
                'sql' => $sql,
                'fingerprint' => $this->queryFingerprint($sql),
                'duration' => $this->durationMs($entry->summary['duration']),
                'caller' => $entry->summary['caller'],
                'is_slow' => (bool) ($entry->content['slow'] ?? false),
            ];
        });
        $duplicates = $queryRows
            ->groupBy('fingerprint')
            ->map(fn (Collection $group) => [
                'count' => $group->count(),
                'sql' => $group->first()['sql'],
                'total_duration' => round((float) $group->sum('duration'), 2),
            ])
            ->filter(fn (array $group) => $group['count'] > 1)
            ->sortByDesc('count')
            ->take(5)
            ->values();
        $slowest = $queryRows->sortByDesc('duration')->first();

        return [
            'total' => $queries->count(),
            'slow' => $queryRows->where('is_slow', true)->count(),
            'total_duration' => round((float) $queryRows->sum('duration'), 2),
            'duplicate_groups' => $duplicates,
            'slowest' => $slowest,
        ];
    }

    private function externalHealthFor(Collection $entries): array
    {
        $calls = $entries->where('type', 'client_request')->values();
        $rows = $calls->map(function (object $entry): array {
            $url = $this->stringValue($entry->content['url'] ?? null) ?? $entry->summary['title'];
            $status = $entry->summary['status'];

            return [
                'host' => parse_url($url, PHP_URL_HOST) ?: 'unknown host',
                'url' => $url,
                'method' => $this->stringValue($entry->content['method'] ?? null),
                'status' => $status,
                'duration' => $this->durationMs($entry->summary['duration']),
                'failed' => is_numeric($status) && (int) $status >= 400,
            ];
        });

        return [
            'total' => $calls->count(),
            'failed' => $rows->where('failed', true)->count(),
            'hosts' => $rows->groupBy('host')->map->count()->sortDesc()->take(5),
            'slowest' => $rows->sortByDesc('duration')->first(),
        ];
    }

    private function preErrorContextFor(Collection $entries): Collection
    {
        $entries = $entries->sortBy('sequence')->values();

        return $entries
            ->filter(fn (object $entry) => $this->severityFor($entry) === 'error')
            ->map(function (object $error) use ($entries): array {
                $before = $entries
                    ->filter(fn (object $entry) => $entry->sequence < $error->sequence)
                    ->filter(fn (object $entry) => in_array($entry->type, ['query', 'log', 'client_request', 'view', 'mail', 'job'], true))
                    ->take(-5)
                    ->values()
                    ->map(fn (object $entry) => [
                        'uuid' => $entry->uuid,
                        'type' => $entry->type,
                        'label' => EntryType::labelFor($entry->type),
                        'title' => $entry->summary['title'],
                        'duration' => $entry->summary['duration'],
                        'status' => $entry->summary['status'],
                        'caller' => $entry->summary['caller'],
                    ]);

                return [
                    'error_uuid' => $error->uuid,
                    'error_title' => $error->summary['title'],
                    'items' => $before,
                ];
            })
            ->filter(fn (array $group) => $group['items']->isNotEmpty())
            ->values();
    }

    private function debugBundleFor(?object $requestEntry, Collection $entries): string
    {
        $errors = $this->traceFormatter->errorTrail($entries);
        $firstError = $errors->first();
        $firstFrame = $firstError['trace']['first_app_frame'] ?? null;
        $context = $requestEntry ? $this->requestContextFor($requestEntry) : [];
        $lastQuery = $entries->where('type', 'query')->sortBy('sequence')->last();

        return collect([
            'Periscope Debug Bundle',
            'Request: '.trim(($context['method'] ?? '').' '.($context['path'] ?? 'Unknown')),
            'Status: '.($context['status'] ?? 'Unknown'),
            'User: '.($context['user'] ?? 'Guest/unknown'),
            'Controller: '.($context['controller'] ?? 'Unknown'),
            'Error: '.($firstError['title'] ?? 'None'),
            'First app frame: '.($firstFrame ? ($firstFrame['relative_file'].($firstFrame['line'] ? ':'.$firstFrame['line'] : '')) : 'None'),
            'Last query: '.($lastQuery ? $this->queryBundleSummary($lastQuery->summary['title']) : 'None'),
        ])->implode("\n");
    }

    private function queryBundleSummary(string $sql): string
    {
        $normalized = strtolower($sql);

        if (str_contains($normalized, 'update `sessions` set `payload`')
            || str_contains($normalized, 'update sessions set payload')) {
            return 'Session write query';
        }

        return Str::limit($sql, 220);
    }

    private function timelineFor(Collection $events): array
    {
        $startedAt = $events
            ->pluck('started_at')
            ->filter()
            ->map(fn ($value) => Carbon::parse($value))
            ->sort()
            ->first();
        $maxDuration = max(1, (int) $events->max(fn (array $event) => $this->durationMs($event['duration']) ?? 0));

        return [
            'max_duration' => $maxDuration,
            'events' => $events
                ->map(function (array $event) use ($startedAt, $maxDuration): array {
                    $duration = $this->durationMs($event['duration']);
                    $offset = $startedAt && $event['started_at']
                        ? max(0, $startedAt->diffInMilliseconds(Carbon::parse($event['started_at'])))
                        : null;

                    return $event + [
                        'duration_ms' => $duration,
                        'duration_width' => $duration ? max(5, min(100, (int) round(($duration / $maxDuration) * 100))) : 0,
                        'offset_ms' => $offset,
                        'offset_label' => $offset === null ? '--' : '+'.$offset.' ms',
                    ];
                })
                ->values(),
        ];
    }

    private function durationMs(mixed $duration): ?float
    {
        if ($duration === null || $duration === '') {
            return null;
        }

        if (is_numeric($duration)) {
            return round((float) $duration, 2);
        }

        if (is_string($duration) && preg_match('/[\d.]+/', $duration, $matches) === 1) {
            return round((float) $matches[0], 2);
        }

        return null;
    }

    private function queryFingerprint(string $sql): string
    {
        $sql = strtolower(preg_replace('/\s+/', ' ', trim($sql)) ?: $sql);
        $sql = preg_replace("/'(?:[^'\\\\]|\\\\.)*'/", '?', $sql) ?? $sql;
        $sql = preg_replace('/\b\d+(?:\.\d+)?\b/', '?', $sql) ?? $sql;

        return $sql;
    }

    private function stringValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if (is_array($value)) {
            $values = collect($value)
                ->flatten()
                ->filter(fn ($item) => is_scalar($item) && $item !== '')
                ->map(fn ($item) => (string) $item)
                ->values();

            return $values->isNotEmpty() ? $values->implode(', ') : null;
        }

        return null;
    }

    private function isVendorEvent(array $event): bool
    {
        if ($event['type'] === 'request') {
            return false;
        }

        $content = $event['content'] ?? [];
        $haystack = collect([
            $event['title'] ?? null,
            $event['subtitle'] ?? null,
            $event['caller'] ?? null,
            $event['output'] ?? null,
            $content['file'] ?? null,
            $content['path'] ?? null,
            $content['view'] ?? null,
            $content['class'] ?? null,
            $content['name'] ?? null,
        ])
            ->flatten()
            ->filter(fn ($value) => is_scalar($value))
            ->map(fn ($value) => str_replace('\\', '/', strtolower((string) $value)))
            ->implode(' ');

        return str_contains($haystack, '/vendor/')
            || str_starts_with($haystack, 'vendor/');
    }

    private function classifyRequest(object $entry): array
    {
        $content = $entry->content;
        $headers = array_change_key_case($content['headers'] ?? [], CASE_LOWER);
        $responseHeaders = array_change_key_case($content['response_headers'] ?? [], CASE_LOWER);
        $middleware = collect($content['middleware'] ?? [])->implode(' ');
        $path = (string) ($content['uri'] ?? $content['url'] ?? '');
        $accept = strtolower($this->headerValue($headers, 'accept'));
        $requestContentType = strtolower($this->headerValue($headers, 'content-type'));
        $responseContentType = strtolower($this->headerValue($responseHeaders, 'content-type'));

        $isApi = str_starts_with(ltrim($path, '/'), 'api/')
            || str_contains(strtolower($middleware), 'api')
            || str_contains($accept, 'application/json')
            || str_contains($requestContentType, 'application/json')
            || str_contains($responseContentType, 'application/json');

        $isInertia = str_contains($responseContentType, 'inertia')
            || array_key_exists('x-inertia', $responseHeaders)
            || str_contains(strtolower($this->headerValue($headers, 'x-inertia')), 'true');

        return [
            'channel' => $isApi ? 'API' : ($isInertia ? 'Inertia web' : 'Web'),
            'expected' => $isApi ? 'JSON response' : ($isInertia ? 'Inertia page payload or redirect' : 'HTML, redirect, file, or streamed response'),
            'method' => $content['method'] ?? null,
            'path' => $path ?: null,
            'query' => $this->querySummaryFor($path, $content),
            'query_data' => $this->queryDataFor($path, $content),
            'user' => $this->userFor($content),
            'status' => $content['response_status'] ?? $content['status'] ?? null,
            'response' => $this->responseDescription($content),
            'response_detail' => $this->responseDetailFor($content),
            'response_json' => $this->responseJsonFor($content),
        ];
    }

    private function summarize(?object $requestEntry, Collection $entries): array
    {
        return [
            'total' => $entries->count(),
            'duration' => $requestEntry?->summary['duration'],
            'errors' => $entries->filter(fn (object $entry) => $this->severityFor($entry) === 'error')->count(),
            'warnings' => $entries->filter(fn (object $entry) => $this->severityFor($entry) === 'warn')->count(),
            'queries' => $entries->where('type', 'query')->count(),
            'logs' => $entries->where('type', 'log')->count(),
            'mail' => $entries->where('type', 'mail')->count(),
            'external' => $entries->where('type', 'client_request')->count(),
        ];
    }

    private function eventFor(object $entry, object $selectedEntry, ?object $requestEntry, int $position): array
    {
        $content = $entry->content;
        $duration = $entry->summary['duration'];
        $title = $entry->summary['title'];

        return [
            'uuid' => $entry->uuid,
            'position' => $position,
            'sequence' => $entry->sequence,
            'type' => $entry->type,
            'label' => EntryType::labelFor($entry->type),
            'title' => $title,
            'subtitle' => $entry->summary['subtitle'],
            'status' => $entry->summary['status'],
            'duration' => $duration,
            'phase' => $this->phaseFor($entry),
            'severity' => $this->severityFor($entry),
            'is_selected' => $entry->uuid === $selectedEntry->uuid,
            'is_request' => $requestEntry && $entry->uuid === $requestEntry->uuid,
            'note' => $this->noteFor($entry),
            'output' => $this->outputFor($entry),
            'caller' => $entry->summary['caller'],
            'started_at' => $entry->created_at,
            'short_title' => $this->shortTitleFor($entry, $title),
            'content' => $content,
        ];
    }

    private function shortTitleFor(object $entry, string $title): string
    {
        if ($entry->type !== 'query') {
            return Str::limit($title, 180);
        }

        $title = preg_replace('/\s+/', ' ', trim($title)) ?: 'Database query';

        return Str::limit($title, 72);
    }

    private function phaseFor(object $entry): string
    {
        return match ($entry->type) {
            'request' => 'request',
            'query', 'model', 'cache', 'redis', 'gate' => 'data',
            'client_request' => 'external',
            'view', 'mail', 'notification' => 'output',
            'log', 'exception', 'dump', 'debugbar' => 'diagnostics',
            default => 'application',
        };
    }

    private function severityFor(object $entry): string
    {
        $status = $entry->summary['status'];

        if ($entry->type === 'exception') {
            return 'error';
        }

        if (is_string($status) && ! is_numeric($status)) {
            return match (strtolower($status)) {
                'emergency', 'alert', 'critical', 'error' => 'error',
                'warning', 'warn' => 'warn',
                default => 'info',
            };
        }

        if ($status !== null && $status !== '') {
            $status = (int) $status;

            if ($status >= 500) {
                return 'error';
            }

            if ($status >= 400) {
                return 'warn';
            }

            if ($status >= 200 && $status < 400) {
                return 'ok';
            }
        }

        if (($entry->content['slow'] ?? false) === true) {
            return 'warn';
        }

        return 'info';
    }

    private function noteFor(object $entry): ?string
    {
        $content = $entry->content;

        return match ($entry->type) {
            'request' => $this->responseDescription($content),
            'query' => ($content['slow'] ?? false) ? 'Slow query' : ($content['connection'] ?? null),
            'log' => isset($content['level']) ? ucfirst((string) $content['level']) : null,
            'client_request' => $content['method'] ?? null,
            'mail' => $content['subject'] ?? null,
            'view' => $content['name'] ?? null,
            default => null,
        };
    }

    private function outputFor(object $entry): ?string
    {
        $content = $entry->content;

        return match ($entry->type) {
            'request' => $this->responseDescription($content),
            'view' => $content['name'] ?? null,
            'mail' => $content['mailable'] ?? $content['subject'] ?? null,
            'client_request' => $content['url'] ?? null,
            default => null,
        };
    }

    private function responseDescription(array $content): ?string
    {
        $headers = array_change_key_case($content['response_headers'] ?? [], CASE_LOWER);

        if (isset($headers['location'])) {
            return 'Redirect';
        }

        if (isset($content['response']) && is_string($content['response'])) {
            $decoded = json_decode($content['response'], true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return 'JSON response';
            }

            return Str::limit($content['response'], 140);
        }

        if (isset($content['response']) && is_array($content['response'])) {
            return 'JSON response';
        }

        return $this->headerValue($headers, 'content-type') ?: null;
    }

    private function responseDetailFor(array $content): ?string
    {
        $headers = array_change_key_case($content['response_headers'] ?? [], CASE_LOWER);

        if (isset($headers['location'])) {
            return $this->headerValue($headers, 'location');
        }

        return null;
    }

    private function responseJsonFor(array $content): mixed
    {
        $response = $content['response'] ?? null;

        if (is_array($response)) {
            return $response;
        }

        if (! is_string($response)) {
            return null;
        }

        $decoded = json_decode($response, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }

    private function querySummaryFor(string $path, array $content): ?string
    {
        $data = $this->queryDataFor($path, $content);

        if ($data === []) {
            return null;
        }

        return count($data).' '.Str::plural('parameter', count($data));
    }

    private function queryDataFor(string $path, array $content): array
    {
        $query = parse_url($path, PHP_URL_QUERY);

        if (is_string($query) && $query !== '') {
            parse_str($query, $parsed);

            return is_array($parsed) ? $parsed : [];
        }

        if (isset($content['payload']) && is_array($content['payload']) && $content['payload']) {
            return $content['payload'];
        }

        return [];
    }

    private function userFor(array $content): ?string
    {
        $user = $content['user'] ?? null;

        if (! is_array($user)) {
            return is_scalar($user) ? (string) $user : null;
        }

        return $user['name']
            ?? $user['email']
            ?? (isset($user['id']) ? '#'.$user['id'] : null);
    }

    private function headerValue(array $headers, string $key): string
    {
        $value = $headers[$key] ?? '';

        if (is_array($value)) {
            return implode(', ', array_filter($value, fn ($item) => is_scalar($item)));
        }

        return is_scalar($value) ? (string) $value : '';
    }

    private function phaseLabel(string $phase): string
    {
        return match ($phase) {
            'request' => 'Request',
            'data' => 'Data & State',
            'external' => 'External Calls',
            'output' => 'Output',
            'diagnostics' => 'Diagnostics',
            default => 'Application',
        };
    }

    private function phaseOrder(): array
    {
        return ['request', 'application', 'data', 'external', 'output', 'diagnostics'];
    }
}
