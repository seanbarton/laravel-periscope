<?php

namespace TortoiseIT\LaravelPeriscope\Support;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;

class TelescopeEntryRepository
{
    public function __construct(private readonly DatabaseManager $database)
    {
    }

    public function types(): Collection
    {
        return $this->typeCounts()->pluck('type');
    }

    public function typeCounts(?EntryFilters $filters = null): Collection
    {
        return $this->connection()
            ->table('telescope_entries')
            ->selectRaw('type, count(*) as total')
            ->when($this->excludeDebugbarEntries(), fn ($query) => $query->where('type', '!=', 'debugbar'))
            ->when($filters?->from, fn ($query, $from) => $query->where('created_at', '>=', $from))
            ->when($filters?->to, fn ($query, $to) => $query->where('created_at', '<=', $to))
            ->groupBy('type')
            ->orderBy('type')
            ->get();
    }

    public function tags(string $search = ''): Collection
    {
        return $this->connection()
            ->table('telescope_entries_tags')
            ->select('tag')
            ->when($search !== '', fn ($query) => $query->where('tag', 'like', '%'.$this->escapeLike($search).'%'))
            ->distinct()
            ->orderBy('tag')
            ->limit(50)
            ->pluck('tag');
    }

    public function find(string $uuid): ?object
    {
        $entry = $this->connection()
            ->table('telescope_entries')
            ->where('uuid', $uuid)
            ->first();

        if (! $entry) {
            return null;
        }

        if ($this->excludeDebugbarEntries() && $entry->type === 'debugbar') {
            return null;
        }

        $entry->content = $this->decodeContent($entry->content);
        $entry->summary = $this->summary($entry);
        $entry->tags = $this->connection()
            ->table('telescope_entries_tags')
            ->where('entry_uuid', $uuid)
            ->orderBy('tag')
            ->pluck('tag');

        return $entry;
    }

    public function batchEntries(string $batchId, ?string $excludeUuid = null, int $limit = 150): Collection
    {
        return $this->connection()
            ->table('telescope_entries')
            ->select('sequence', 'uuid', 'batch_id', 'family_hash', 'type', 'content', 'created_at')
            ->where('batch_id', $batchId)
            ->when($this->excludeDebugbarEntries(), fn ($query) => $query->where('type', '!=', 'debugbar'))
            ->when($excludeUuid, fn ($query, string $uuid) => $query->where('uuid', '!=', $uuid))
            ->orderBy('sequence')
            ->limit($limit)
            ->get()
            ->map(function (object $entry): object {
                $entry->content = $this->decodeContent($entry->content);
                $entry->summary = $this->summary($entry);

                return $entry;
            });
    }

    public function search(EntryFilters $filters): Collection
    {
        $wanted = $filters->perPage + 1;
        $batchSize = max($wanted, min($wanted * 5, 500));
        $before = $filters->beforeSequence;
        $results = collect();
        $errorBatchCache = [];
        $scanStartedAt = microtime(true);
        $scanned = 0;
        $scanStopped = false;
        $maxAttempts = $filters->errorsOnly ? PHP_INT_MAX : 10;

        for ($attempt = 0; $attempt < $maxAttempts && $results->count() < $wanted; $attempt++) {
            if ($this->errorScanShouldStop($filters, $scanStartedAt, $scanned)) {
                break;
            }

            $entries = $this->connection()
                ->table('telescope_entries as e')
                ->select('e.sequence', 'e.uuid', 'e.batch_id', 'e.family_hash', 'e.type', 'e.created_at')
                ->when($this->excludeDebugbarEntries(), fn ($query) => $query->where('e.type', '!=', 'debugbar'))
                ->when($filters->tag, function ($query, string $tag): void {
                    $query->join('telescope_entries_tags as tag_filter', 'tag_filter.entry_uuid', '=', 'e.uuid')
                        ->where('tag_filter.tag', $tag);
                })
                ->when($filters->type, fn ($query, string $type) => $query->where('e.type', $type))
                ->when(! $filters->type && $filters->types, fn ($query) => $query->whereIn('e.type', $filters->types))
                ->when($filters->from, fn ($query, $from) => $query->where('e.created_at', '>=', $from))
                ->when($filters->to, fn ($query, $to) => $query->where('e.created_at', '<=', $to))
                ->when($before, fn ($query, int $before) => $query->where('e.sequence', '<', $before))
                ->when($filters->query, fn ($query, string $term) => $query->where('e.content', 'like', '%'.$this->escapeLike($term).'%'))
                ->when($filters->method, fn ($query, string $method) => $query->where('e.content', 'like', '%"method":"'.$this->escapeLike(strtoupper($method)).'"%'))
                ->when($filters->status, fn ($query, string $status) => $query->where('e.content', 'like', '%"response_status":'.$this->escapeLike($status).'%'))
                ->when($filters->path, fn ($query, string $path) => $query->where('e.content', 'like', '%'.$this->escapeLike($path).'%'))
                ->orderByDesc('e.sequence')
                ->limit($batchSize)
                ->get();

            if ($entries->isEmpty()) {
                break;
            }

            // Count rows retrieved before PHP-side error filtering so large scans can be capped safely.
            $scanned += $entries->count();

            $contentByUuid = $this->connection()
                ->table('telescope_entries')
                ->select('uuid')
                ->selectRaw('LEFT(content, 100000) as content')
                ->whereIn('uuid', $entries->pluck('uuid')->all())
                ->get()
                ->keyBy('uuid');

            if ($filters->errorsOnly) {
                $this->primeErrorBatchCache($entries, $errorBatchCache);
            }

            foreach ($entries as $entry) {
                if ($this->errorScanShouldStop($filters, $scanStartedAt, $scanned)) {
                    $scanStopped = true;
                    break;
                }

                $entry->content = $contentByUuid[$entry->uuid]->content ?? '{}';

                if ($this->isPeriscopeRequest($entry)) {
                    continue;
                }

                $entry->content = $this->decodeContent($entry->content, true);
                $entry->summary = $this->summary($entry);

                if ($filters->errorsOnly && ! $this->isErrorRelatedEntry($entry, $errorBatchCache)) {
                    continue;
                }

                $results->push($entry);

                if ($results->count() >= $wanted) {
                    break;
                }
            }

            $this->addGateChannels($results);

            if ($scanStopped || $entries->count() < $batchSize) {
                break;
            }

            $before = $entries->last()->sequence;
        }

        return $results;
    }

    private function errorScanShouldStop(EntryFilters $filters, float $startedAt, int $scanned): bool
    {
        if (! $filters->errorsOnly) {
            return false;
        }

        $maxEntries = (int) config('periscope.error_scan_max_entries', 10000);

        if ($maxEntries <= 0 || $scanned > $maxEntries) {
            return true;
        }

        $timeoutMs = (int) config('periscope.error_scan_timeout_ms', 1500);

        return $timeoutMs > 0 && ((microtime(true) - $startedAt) * 1000) >= $timeoutMs;
    }

    private function primeErrorBatchCache(Collection $entries, array &$errorBatchCache): void
    {
        $batchIds = $entries
            ->where('type', 'request')
            ->pluck('batch_id')
            ->filter()
            ->reject(fn (string $batchId) => array_key_exists($batchId, $errorBatchCache))
            ->unique()
            ->values();

        if ($batchIds->isEmpty()) {
            return;
        }

        $batchIds->each(fn (string $batchId) => $errorBatchCache[$batchId] = false);

        // Fetch possible lifecycle errors for the whole chunk at once, then classify in PHP.
        $this->connection()
            ->table('telescope_entries')
            ->select('batch_id', 'type')
            ->selectRaw('LEFT(content, 100000) as content')
            ->whereIn('batch_id', $batchIds->all())
            ->whereIn('type', ['exception', 'log', 'request', 'client_request'])
            ->get()
            ->each(function (object $entry) use (&$errorBatchCache): void {
                $entry->content = $this->decodeContent($entry->content, true);
                $entry->summary = $this->summary($entry);

                if ($this->isErrorEntry($entry)) {
                    $errorBatchCache[$entry->batch_id] = true;
                }
            });
    }

    private function isErrorRelatedEntry(object $entry, array &$errorBatchCache): bool
    {
        if ($this->isErrorEntry($entry)) {
            return true;
        }

        return $entry->type === 'request' && $this->batchHasError($entry->batch_id, $errorBatchCache);
    }

    private function batchHasError(?string $batchId, array &$errorBatchCache): bool
    {
        if (! $batchId) {
            return false;
        }

        if (array_key_exists($batchId, $errorBatchCache)) {
            return $errorBatchCache[$batchId];
        }

        $errorBatchCache[$batchId] = $this->connection()
            ->table('telescope_entries')
            ->select('type')
            ->selectRaw('LEFT(content, 100000) as content')
            ->where('batch_id', $batchId)
            ->whereIn('type', ['exception', 'log', 'request', 'client_request'])
            ->get()
            ->contains(function (object $entry): bool {
                $entry->content = $this->decodeContent($entry->content, true);
                $entry->summary = $this->summary($entry);

                return $this->isErrorEntry($entry);
            });

        return $errorBatchCache[$batchId];
    }

    private function isErrorEntry(object $entry): bool
    {
        if ($entry->type === 'exception') {
            return true;
        }

        $status = $entry->summary['status'] ?? null;

        if ($entry->type === 'log' && is_string($status)) {
            return in_array(strtolower($status), ['emergency', 'alert', 'critical', 'error'], true);
        }

        return is_numeric($status) && (int) $status >= 400;
    }

    private function connection(): ConnectionInterface
    {
        return $this->database->connection(config('periscope.connection'));
    }

    private function excludeDebugbarEntries(): bool
    {
        return (bool) config('periscope.exclude_debugbar_entries', true);
    }

    private function decodeContent(string $content, bool $summaryOnly = false): array
    {
        if ($summaryOnly && strlen($content) > 1_000_000) {
            return $this->extractSummaryContent($content);
        }

        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            return ['raw' => $content];
        }

        if (! $summaryOnly) {
            return $decoded;
        }

        foreach (['headers', 'session', 'response', 'response_headers', 'trace', 'html', 'raw'] as $key) {
            unset($decoded[$key]);
        }

        return $decoded;
    }

    private function extractSummaryContent(string $content): array
    {
        $summary = ['truncated' => true];

        foreach (['uri', 'url', 'method', 'controller_action', 'middleware', 'response_status', 'status', 'level', 'duration', 'time', 'message', 'sql', 'mailable', 'subject', 'class', 'name', 'job', 'command', 'connection', 'queue', 'file', 'line', 'key', 'event', 'ability', 'result', 'action', 'model', 'count', 'path', 'view', 'component', 'slow'] as $key) {
            $value = $this->extractJsonScalar($content, $key);

            if ($value !== null) {
                $summary[$key] = $value;
            }
        }

        return $summary;
    }

    private function extractJsonScalar(string $content, string $key): mixed
    {
        $quotedKey = preg_quote($key, '/');

        if (preg_match('/"'.$quotedKey.'"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/', $content, $matches) === 1) {
            return stripcslashes($matches[1]);
        }

        if (preg_match('/"'.$quotedKey.'"\s*:\s*(-?\d+(?:\.\d+)?)/', $content, $matches) === 1) {
            return str_contains($matches[1], '.') ? (float) $matches[1] : (int) $matches[1];
        }

        return null;
    }

    private function isPeriscopeRequest(object $entry): bool
    {
        if ($entry->type !== 'request') {
            return false;
        }

        $path = trim((string) config('periscope.path', 'periscope'), '/');
        $uri = $this->extractJsonScalar((string) $entry->content, 'uri')
            ?? $this->extractJsonScalar((string) $entry->content, 'url')
            ?? '';
        $requestPath = parse_url((string) $uri, PHP_URL_PATH) ?: (string) $uri;

        return trim($requestPath, '/') === $path
            || str_starts_with(trim($requestPath, '/'), $path.'/');
    }

    private function summary(object $entry): array
    {
        $content = $entry->content;
        $path = $this->stringValue($content['path'] ?? null);
        $file = $this->stringValue($content['file'] ?? null);

        return [
            'title' => $this->titleFor($entry->type, $content),
            'subtitle' => $this->subtitleFor($entry->type, $content),
            'status' => $content['response_status'] ?? $content['status'] ?? $content['level'] ?? null,
            'method' => $content['method'] ?? null,
            'duration' => $content['duration'] ?? $content['time'] ?? null,
            'caller' => $this->callerFor($entry->type, $content),
            'label' => EntryType::labelFor($entry->type),
            'icon' => EntryType::iconFor($entry->type),
            'preview' => $this->previewFor($entry->type, $content),
            'result' => $content['result'] ?? null,
            'connection' => $content['connection'] ?? null,
            'queue' => $content['queue'] ?? null,
            'action' => $content['action'] ?? null,
            'count' => $content['count'] ?? null,
            'user' => $this->userFor($content),
            'user_id' => $this->userIdFor($content),
            'slow' => (bool) ($content['slow'] ?? false),
            'path' => $path ? $this->relativePath($path) : ($file ? $this->relativePath($file) : null),
            'channel' => $this->channelFor($content),
        ];
    }

    private function titleFor(string $type, array $content): string
    {
        return match ($type) {
            'request' => trim(($this->stringValue($content['method'] ?? null) ?? '').' '.($this->stringValue($content['uri'] ?? null) ?? $this->stringValue($content['url'] ?? null) ?? 'Request')),
            'query' => $this->stringValue($content['sql'] ?? null) ?? 'Database query',
            'log' => $this->stringValue($content['message'] ?? null) ?? 'Log entry',
            'mail' => $this->stringValue($content['mailable'] ?? null) ?? $this->stringValue($content['subject'] ?? null) ?? 'Mail',
            'exception' => $this->stringValue($content['class'] ?? null) ?? $this->stringValue($content['message'] ?? null) ?? 'Exception',
            'job' => $this->stringValue($content['name'] ?? null) ?? $this->stringValue($content['job'] ?? null) ?? 'Job',
            'command' => $this->stringValue($content['command'] ?? null) ?? 'Command',
            'cache' => $this->stringValue($content['key'] ?? null) ?? $this->stringValue($content['name'] ?? null) ?? 'Cache',
            'client_request' => $this->componentFor($content) ?? trim(($this->stringValue($content['method'] ?? null) ?? '').' '.($this->stringValue($content['url'] ?? null) ?? 'HTTP client')),
            'event' => $this->stringValue($content['name'] ?? null) ?? $this->stringValue($content['event'] ?? null) ?? $this->stringValue($content['class'] ?? null) ?? 'Event',
            'gate' => $this->stringValue($content['ability'] ?? null) ?? 'Gate',
            'model' => $this->stringValue($content['model'] ?? null) ?? $this->stringValue($content['class'] ?? null) ?? 'Model',
            'view' => $this->stringValue($content['name'] ?? null) ?? $this->stringValue($content['view'] ?? null) ?? 'View',
            default => ucfirst($type),
        };
    }

    private function subtitleFor(string $type, array $content): ?string
    {
        $file = $this->stringValue($content['file'] ?? null);
        $path = $this->stringValue($content['path'] ?? null);

        return match ($type) {
            'request' => $this->stringValue($content['controller_action'] ?? null) ?? $this->stringValue($content['middleware'] ?? null),
            'query' => $file && isset($content['line']) ? $this->relativePath($file).':'.$this->stringValue($content['line']) : null,
            'log', 'exception' => $file ? $this->relativePath($file) : null,
            'mail' => $this->mailAddressFor($content['to'] ?? null),
            'job' => collect([$this->stringValue($content['connection'] ?? null), $this->stringValue($content['queue'] ?? null)])->filter()->implode(' / ') ?: null,
            'view' => $path ? $this->relativePath($path) : null,
            default => $this->stringValue($content['connection'] ?? null) ?? $this->stringValue($content['queue'] ?? null),
        };
    }

    private function callerFor(string $type, array $content): ?string
    {
        $file = $this->stringValue($content['file'] ?? null);

        return match ($type) {
            'request' => $this->stringValue($content['controller_action'] ?? null),
            'query', 'exception' => $file && isset($content['line']) ? $this->relativePath($file).':'.$this->stringValue($content['line']) : ($file ? $this->relativePath($file) : null),
            'job' => $this->stringValue($content['name'] ?? null),
            'command' => $this->stringValue($content['command'] ?? null),
            default => null,
        };
    }

    private function previewFor(string $type, array $content): ?string
    {
        return match ($type) {
            'log' => $this->logContextPreview($content['context'] ?? null),
            'exception' => $this->stringValue($content['message'] ?? null),
            'query' => $this->stringValue($content['sql'] ?? null),
            'event' => $this->stringValue($content['name'] ?? null) ?? $this->stringValue($content['event'] ?? null) ?? $this->stringValue($content['class'] ?? null),
            'gate' => isset($content['result']) ? ($content['result'] ? 'Allowed' : 'Denied') : null,
            'model' => collect([$this->stringValue($content['action'] ?? null), $this->stringValue($content['count'] ?? null)])->filter(fn ($value) => $value !== null && $value !== '')->implode(' / ') ?: null,
            'view' => ($path = $this->stringValue($content['path'] ?? null)) ? $this->relativePath($path) : null,
            default => null,
        };
    }

    private function logContextPreview(mixed $context): ?string
    {
        if (is_string($context)) {
            return $context;
        }

        if (! is_array($context)) {
            return null;
        }

        $values = collect($context)
            ->filter(fn ($value) => is_scalar($value))
            ->map(fn ($value) => (string) $value)
            ->values();

        return $values->isNotEmpty() ? $values->implode(' ') : null;
    }

    private function componentFor(array $content): ?string
    {
        foreach (['component', 'name'] as $key) {
            if (isset($content[$key]) && is_scalar($content[$key])) {
                return (string) $content[$key];
            }
        }

        $payload = $content['payload'] ?? $content['data'] ?? null;

        if (is_array($payload)) {
            foreach (['component', 'name'] as $key) {
                if (isset($payload[$key]) && is_scalar($payload[$key])) {
                    return (string) $payload[$key];
                }
            }
        }

        return null;
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

    private function userIdFor(array $content): mixed
    {
        $user = $content['user'] ?? null;

        if (is_array($user)) {
            return $user['id'] ?? null;
        }

        return is_scalar($user) ? $user : null;
    }

    private function addGateChannels(Collection $entries): void
    {
        $gateEntries = $entries->where('type', 'gate');

        if ($gateEntries->isEmpty()) {
            return;
        }

        $requestByBatch = $this->connection()
            ->table('telescope_entries')
            ->select('batch_id')
            ->selectRaw('LEFT(content, 30000) as content')
            ->where('type', 'request')
            ->whereIn('batch_id', $gateEntries->pluck('batch_id')->unique()->all())
            ->orderBy('sequence')
            ->get()
            ->groupBy('batch_id')
            ->map(fn (Collection $batch) => $this->decodeContent((string) $batch->first()->content));

        $gateEntries->each(function (object $entry) use ($requestByBatch): void {
            $requestContent = $requestByBatch[$entry->batch_id] ?? null;

            if (is_array($requestContent)) {
                $entry->summary['channel'] = $this->channelFor($requestContent);
            }
        });
    }

    private function channelFor(array $content): ?string
    {
        $headers = array_change_key_case($content['headers'] ?? [], CASE_LOWER);
        $responseHeaders = array_change_key_case($content['response_headers'] ?? [], CASE_LOWER);
        $middleware = collect($content['middleware'] ?? [])->implode(' ');
        $path = (string) ($content['uri'] ?? $content['url'] ?? '');
        $accept = strtolower($this->headerValue($headers, 'accept'));
        $requestContentType = strtolower($this->headerValue($headers, 'content-type'));
        $responseContentType = strtolower($this->headerValue($responseHeaders, 'content-type'));

        if (str_starts_with(ltrim($path, '/'), 'api/')
            || str_contains(strtolower($middleware), 'api')
            || str_contains($accept, 'application/json')
            || str_contains($requestContentType, 'application/json')
            || str_contains($responseContentType, 'application/json')) {
            return 'API';
        }

        if ($path || $middleware || $headers || $responseHeaders) {
            return 'Web';
        }

        return null;
    }

    private function headerValue(array $headers, string $key): string
    {
        $value = $headers[$key] ?? '';

        if (is_array($value)) {
            return implode(', ', array_filter($value, fn ($item) => is_scalar($item)));
        }

        return is_scalar($value) ? (string) $value : '';
    }

    private function mailAddressFor(mixed $addresses): ?string
    {
        if (! is_array($addresses)) {
            return $this->stringValue($addresses);
        }

        $first = $addresses[0] ?? $addresses;

        if (is_array($first)) {
            return $this->stringValue($first['address'] ?? null)
                ?? $this->stringValue($first['email'] ?? null)
                ?? $this->stringValue($first['name'] ?? null);
        }

        return $this->stringValue($first);
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

    public function relativePath(?string $path): ?string
    {
        if (! $path) {
            return $path;
        }

        foreach ([base_path(), public_path()] as $root) {
            $root = rtrim(str_replace('\\', '/', (string) $root), '/').'/';
            $normalized = str_replace('\\', '/', $path);

            if (str_starts_with($normalized, $root)) {
                return ltrim(substr($normalized, strlen($root)), '/');
            }
        }

        return $path;
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
