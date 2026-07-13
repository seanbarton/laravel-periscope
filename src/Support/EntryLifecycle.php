<?php

namespace TortoiseIT\LaravelPeriscope\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class EntryLifecycle
{
    public function build(object $selectedEntry, Collection $entries): array
    {
        $requestEntry = $selectedEntry->type === 'request'
            ? $selectedEntry
            : $entries->first(fn (object $entry) => $entry->type === 'request');

        return [
            'request' => $requestEntry,
            'classification' => $requestEntry ? $this->classifyRequest($requestEntry) : null,
            'summary' => $this->summarize($requestEntry, $entries),
            'phases' => $entries
                ->values()
                ->map(fn (object $entry, int $index) => $this->eventFor($entry, $selectedEntry, $requestEntry, $index + 1))
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
