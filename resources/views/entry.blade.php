@extends('periscope::layout', ['title' => config('periscope.name', 'Periscope').' Entry'])

@php
    $content = $entry->content;

    $pretty = fn ($value) => json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $stringify = function ($value) use ($pretty) {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }

        return $pretty($value);
    };
    $relativePath = function (?string $path) {
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
    };
    $relativizePaths = function ($value) use (&$relativizePaths, $relativePath) {
        if (is_array($value)) {
            return collect($value)
                ->map(fn ($item) => $relativizePaths($item))
                ->all();
        }

        if (is_string($value)) {
            return $relativePath($value);
        }

        return $value;
    };

    $user = $content['user'] ?? null;
    $userName = is_array($user) ? ($user['name'] ?? trim(($user['first_name'] ?? '').' '.($user['last_name'] ?? '')) ?: null) : null;
    $userEmail = is_array($user) ? ($user['email'] ?? null) : null;
    $userId = is_array($user) ? ($user['id'] ?? null) : $user;
    $rootRequest = $entry->type === 'request'
        ? $entry
        : $relatedEntries->first(fn ($relatedEntry) => $relatedEntry->type === 'request');

    $payload = $content['payload'] ?? $content['data'] ?? $content['bindings'] ?? [];
    $requestHeaders = $content['headers'] ?? [];
    $normalizedRequestHeaders = is_array($requestHeaders) ? array_change_key_case($requestHeaders, CASE_LOWER) : [];
    $headerValue = function (array $headers, string $key): ?string {
        $value = $headers[strtolower($key)] ?? null;

        if (is_array($value)) {
            $value = collect($value)
                ->flatten()
                ->filter(fn ($item) => is_scalar($item))
                ->implode(', ');
        }

        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    };
    $referrer = $headerValue($normalizedRequestHeaders, 'referer')
        ?? $headerValue($normalizedRequestHeaders, 'referrer');
    $session = $content['session'] ?? [];
    $response = $content['response'] ?? $content['raw'] ?? $content['html'] ?? null;
    $responseHeaders = $content['response_headers'] ?? [];
    $context = $content['context'] ?? null;
    $trace = $content['trace'] ?? null;
    $displayContent = $relativizePaths($entry->content);
    $displayContext = $relativizePaths($context);
    $displayTrace = $relativizePaths($trace);
    $file = $content['file'] ?? null;
    $fileWithLine = isset($content['file'], $content['line']) ? $relativePath($content['file']).':'.$content['line'] : $relativePath($file);
    $entryLabel = \TortoiseIT\LaravelPeriscope\Support\EntryType::labelFor($entry->type);
    $mailHtml = $content['html'] ?? $content['preview'] ?? null;
    $mailSubject = $content['subject'] ?? null;
    $mailAddressList = function ($addresses) {
        if (! is_array($addresses)) {
            return is_scalar($addresses) ? (string) $addresses : null;
        }

        $formatted = collect($addresses)
            ->map(function ($address) {
                if (is_array($address)) {
                    $email = $address['address'] ?? $address['email'] ?? null;
                    $name = $address['name'] ?? null;

                    return $name && $email ? "{$name} <{$email}>" : ($email ?? $name);
                }

                return is_scalar($address) ? (string) $address : null;
            })
            ->filter()
            ->values();

        return $formatted->isNotEmpty() ? $formatted->implode(', ') : null;
    };
    $mailTo = $mailAddressList($content['to'] ?? null);
    $mailFrom = $mailAddressList($content['from'] ?? null);
@endphp

@section('page-title')
    {{ \Illuminate\Support\Str::limit($entry->summary['title'], 120) }}
@endsection

@section('page-subtitle')
    {{ $entryLabel }} captured {{ \Illuminate\Support\Carbon::parse($entry->created_at)->diffForHumans() }}
    @if ($entry->type === 'log' && $entry->summary['preview'])
        - {{ \Illuminate\Support\Str::limit($entry->summary['preview'], 180) }}
    @endif
@endsection

@section('topbar-actions')
@endsection

@section('content')
    <div class="detail-stack">
        <div class="breadcrumb">
            <a href="{{ route('periscope.entries.index', request()->except('uuid')) }}">Entries</a>
            @if ($rootRequest && $rootRequest->uuid !== $entry->uuid)
                <span>/</span>
                <a href="{{ route('periscope.entries.show', ['uuid' => $rootRequest->uuid] + request()->query()) }}">Request</a>
            @endif
            <span>/</span>
            <span>{{ $entryLabel }}</span>
        </div>

        <div class="detail-summary">
            <section class="panel summary-card">
                <div class="summary-card-head">
                    <h3>{{ $entryLabel }} Details</h3>
                    <div class="buttons">
                        <a class="button secondary" href="{{ route('periscope.entries.lifecycle', ['uuid' => $entry->uuid] + request()->query()) }}">Lifecycle</a>
                        @if ($rootRequest && $rootRequest->uuid !== $entry->uuid)
                            <a class="button secondary" href="{{ route('periscope.entries.show', ['uuid' => $rootRequest->uuid] + request()->query()) }}">Request</a>
                        @endif
                    </div>
                </div>
                <div class="summary-grid">
                    @if ($entry->type === 'mail' && ($mailFrom || $mailTo || $mailSubject))
                        <div class="mail-header-block">
                            <div class="mail-participants">
                                @if ($mailFrom)
                                    <div>
                                        <span class="summary-label">From</span>
                                        <span class="mail-address">{{ $mailFrom }}</span>
                                    </div>
                                @endif
                                @if ($mailTo)
                                    <div>
                                        <span class="summary-label">To</span>
                                        <span class="mail-address">{{ $mailTo }}</span>
                                    </div>
                                @endif
                            </div>
                            @if ($mailSubject)
                                <div class="mail-subject">
                                    <div class="summary-label">Subject</div>
                                    <div>{{ $mailSubject }}</div>
                                </div>
                            @endif
                        </div>
                    @endif
                    <div class="summary-item">
                        <div class="summary-label">Time</div>
                        <div class="summary-value">{{ \Illuminate\Support\Carbon::parse($entry->created_at)->format('Y-m-d H:i:s') }}</div>
                    </div>
                    @if ($content['method'] ?? null)
                        <div class="summary-item">
                            <div class="summary-label">Method</div>
                            <div class="summary-value"><span class="type">{{ $content['method'] }}</span></div>
                        </div>
                    @endif
                    @if ($entry->summary['status'] !== null)
                        <div class="summary-item">
                            <div class="summary-label">Status</div>
                            <div class="summary-value">@include('periscope::partials.status-badge', ['status' => $entry->summary['status']])</div>
                        </div>
                    @endif
                    @if ($content['uri'] ?? $content['url'] ?? null)
                        <div class="summary-item">
                            <div class="summary-label">Path</div>
                            <div class="summary-value">{{ $content['uri'] ?? $content['url'] }}</div>
                        </div>
                    @endif
                    @if ($entry->summary['duration'])
                        <div class="summary-item">
                            <div class="summary-label">Duration</div>
                            <div class="summary-value">{{ $entry->summary['duration'] }} ms</div>
                        </div>
                    @endif
                    @if ($fileWithLine)
                        <div class="summary-item">
                            <div class="summary-label">Caller</div>
                            <div class="summary-value" title="{{ $file }}">{{ $fileWithLine }}</div>
                        </div>
                    @elseif ($entry->summary['caller'])
                        <div class="summary-item">
                            <div class="summary-label">Caller</div>
                            <div class="summary-value">{{ $entry->summary['caller'] }}</div>
                        </div>
                    @endif
                    @if ($content['memory'] ?? null)
                        <div class="summary-item">
                            <div class="summary-label">Memory</div>
                            <div class="summary-value">{{ $content['memory'] }} MB</div>
                        </div>
                    @endif
                    @if ($content['ip_address'] ?? null)
                        <div class="summary-item">
                            <div class="summary-label">IP</div>
                            <div class="summary-value">{{ $content['ip_address'] }}</div>
                        </div>
                    @endif
                    @if ($entry->type === 'request' && $referrer)
                        <div class="summary-item">
                            <div class="summary-label">Referrer</div>
                            <div class="summary-value">
                                @if (str_starts_with($referrer, 'http://') || str_starts_with($referrer, 'https://'))
                                    <a href="{{ $referrer }}" target="_blank" rel="noreferrer">{{ $referrer }}</a>
                                @else
                                    {{ $referrer }}
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </section>

            <section class="panel summary-card">
                <h3>User & Tags</h3>
                <div class="user-tags-block">
                    @if ($userId || $userName || $userEmail)
                        <div class="summary-value">
                            <div>{{ $userName ?: ($userId ? '#'.$userId : 'User') }}</div>
                            @if ($userEmail)
                                <div class="muted">{{ $userEmail }}</div>
                            @endif
                            @if ($userId)
                                <div class="muted">#{{ $userId }}</div>
                                <a class="badge tag-link" href="{{ route('periscope.entries.index', request()->except('before') + ['tag' => 'Auth:'.$userId]) }}">Filter Auth:{{ $userId }}</a>
                            @endif
                        </div>
                    @else
                        <div class="summary-value muted">Guest or not captured</div>
                    @endif

                    <div class="tags">
                        @forelse ($entry->tags as $tag)
                            <a class="badge" href="{{ route('periscope.entries.index', request()->except('before') + ['tag' => $tag]) }}">{{ $tag }}</a>
                        @empty
                            <span class="hint">No tags</span>
                        @endforelse
                    </div>
                </div>
            </section>
        </div>

        @if ($context)
            <section class="panel code-card context-card">
                <div class="panel-title">
                    <span>Context</span>
                </div>
                <pre>{{ $pretty($displayContext) }}</pre>
            </section>
        @endif

        @if ($errorTrail->isNotEmpty())
            <section class="panel error-flow-panel">
                <div class="panel-title">
                    <span>Related Errors</span>
                    <span class="hint">{{ $errorTrail->count() }} related error {{ \Illuminate\Support\Str::plural('entry', $errorTrail->count()) }} in this batch</span>
                </div>

                <div class="error-flow">
                    @foreach ($errorTrail as $errorEvent)
                        @php
                            $firstFrame = $errorEvent['trace']['first_app_frame'] ?? null;
                            $isCurrentEntry = $errorEvent['uuid'] === $entry->uuid;
                        @endphp
                        <article @class(['error-flow-item', 'current' => $isCurrentEntry])>
                            <div class="error-flow-marker">{{ str_pad((string) $errorEvent['position'], 2, '0', STR_PAD_LEFT) }}</div>
                            <div class="error-flow-card">
                                <div class="error-trail-head">
                                    <a href="{{ route('periscope.entries.show', ['uuid' => $errorEvent['uuid']] + request()->query()) }}">
                                        {{ $errorEvent['label'] }}
                                        @if ($isCurrentEntry)
                                            <span class="badge info">Current</span>
                                        @endif
                                    </a>
                                    @if ($errorEvent['status'] !== null && $errorEvent['status'] !== '')
                                        @include('periscope::partials.status-badge', ['status' => $errorEvent['status']])
                                    @endif
                                </div>
                                <div class="error-trail-title">{{ $errorEvent['title'] }}</div>
                                @if ($errorEvent['message'])
                                    <div class="node-subtitle">{{ \Illuminate\Support\Str::limit($errorEvent['message'], 220) }}</div>
                                @endif
                                @if ($firstFrame)
                                    <div class="trace-list compact">
                                        @include('periscope::partials.stack-trace', ['frames' => [$firstFrame]])
                                    </div>
                                @elseif ($errorEvent['caller'])
                                    <div class="node-meta"><span>{{ $errorEvent['caller'] }}</span></div>
                                @endif
                                @if (($errorEvent['trace']['app_frames'] ?? []) && count($errorEvent['trace']['app_frames']) > 1)
                                    <details class="trace-details compact">
                                        <summary>Show application trace</summary>
                                        <div class="trace-list compact">
                                            @include('periscope::partials.stack-trace', ['frames' => $errorEvent['trace']['app_frames']])
                                        </div>
                                    </details>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($entry->type === 'exception' && ($stackTrace['has_trace'] ?? false))
            <section class="panel stack-card">
                <div class="panel-title">
                    <span>Current Exception Trace</span>
                    @if (($stackTrace['hidden_core_count'] ?? 0) > 0)
                        <span class="hint">{{ $stackTrace['hidden_core_count'] }} vendor/core {{ \Illuminate\Support\Str::plural('frame', $stackTrace['hidden_core_count']) }} hidden</span>
                    @endif
                </div>
                <div class="trace-list">
                    @include('periscope::partials.stack-trace', [
                        'frames' => $stackTrace['app_frames'] ?: $stackTrace['frames'],
                        'empty' => 'No application stack frames were captured for this exception.',
                    ])
                </div>
                @if (($stackTrace['hidden_core_count'] ?? 0) > 0)
                    <details class="trace-details">
                        <summary>Show full trace</summary>
                        <div class="trace-list">
                            @include('periscope::partials.stack-trace', ['frames' => $stackTrace['frames']])
                        </div>
                    </details>
                @endif
            </section>
        @endif

        @if ($entry->type === 'query')
            <section class="panel code-card">
                <div class="panel-title">
                    <span>Query</span>
                    @if ($fileWithLine)
                        <span class="hint" title="{{ $file }}">{{ $fileWithLine }}</span>
                    @endif
                </div>
                <pre>{{ $content['sql'] ?? $pretty($content) }}</pre>
            </section>
        @endif

        @if ($entry->type === 'mail')
            <section class="panel">
                <div class="panel-title">
                    <span>Mail Preview</span>
                </div>
                @if ($mailHtml)
                    <iframe class="mail-preview" srcdoc="{{ $mailHtml }}"></iframe>
                @else
                    <div class="empty">No mail preview HTML was captured for this entry.</div>
                @endif
            </section>
        @endif

        <section class="panel code-card" data-tabs>
            <div class="tabbar">
                <button type="button" class="active" data-tab-target="payload">Payload</button>
                <button type="button" data-tab-target="headers">Headers</button>
                <button type="button" data-tab-target="session">Session</button>
            </div>
            <div class="tab-panel" data-tab-panel="payload">
                <pre>{{ $pretty($payload) }}</pre>
            </div>
            <div class="tab-panel" data-tab-panel="headers" hidden>
                <pre>{{ $pretty($requestHeaders) }}</pre>
            </div>
            <div class="tab-panel" data-tab-panel="session" hidden>
                <pre>{{ $pretty($session) }}</pre>
            </div>
        </section>

        @if ($response !== null || $responseHeaders || $trace)
            <section class="panel code-card" data-tabs>
                <div class="tabbar">
                    @if ($response !== null)
                        <button type="button" class="active" data-tab-target="response">Response</button>
                    @endif
                    @if ($responseHeaders)
                        <button type="button" @class(['active' => $response === null]) data-tab-target="response-headers">Headers</button>
                    @endif
                    @if ($trace)
                        <button type="button" @class(['active' => $response === null && ! $responseHeaders]) data-tab-target="trace">Trace</button>
                    @endif
                </div>
                @if ($response !== null)
                    <div class="tab-panel" data-tab-panel="response">
                        <pre>{{ is_array($response) ? $pretty($response) : $response }}</pre>
                    </div>
                @endif
                @if ($responseHeaders)
                    <div class="tab-panel" data-tab-panel="response-headers" @if ($response !== null) hidden @endif>
                        <pre>{{ $pretty($responseHeaders) }}</pre>
                    </div>
                @endif
                @if ($trace)
                    <div class="tab-panel" data-tab-panel="trace" @if ($response !== null || $responseHeaders) hidden @endif>
                        <pre>{{ $pretty($displayTrace) }}</pre>
                    </div>
                @endif
            </section>
        @endif

        <section class="panel code-card raw-scroll">
            <div class="panel-title">
                <span>Raw</span>
            </div>
            <pre>{{ $pretty($displayContent) }}</pre>
        </section>

        <section class="panel">
            <div class="panel-title">
                <span>Related Entries</span>
                <span class="hint">{{ $relatedEntries->count() }} in this batch</span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Duration</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($relatedEntries as $relatedEntry)
                            <tr class="clickable-row" data-href="{{ route('periscope.entries.show', ['uuid' => $relatedEntry->uuid] + request()->query()) }}">
                                <td>
                                    <div class="title">
                                        <a href="{{ route('periscope.entries.show', ['uuid' => $relatedEntry->uuid] + request()->query()) }}">
                                            {{ \Illuminate\Support\Str::limit($relatedEntry->summary['title'], 150) }}
                                        </a>
                                    </div>
                                    @if ($relatedEntry->summary['subtitle'])
                                        <div class="subtitle">{{ \Illuminate\Support\Str::limit($relatedEntry->summary['subtitle'], 170) }}</div>
                                    @endif
                                    @if ($relatedEntry->type === 'log' && $relatedEntry->summary['preview'])
                                        <div class="subtitle">{{ \Illuminate\Support\Str::limit($relatedEntry->summary['preview'], 220) }}</div>
                                    @endif
                                </td>
                                <td><span class="type">{{ $relatedEntry->summary['label'] }}</span></td>
                                <td class="meta">{{ $relatedEntry->summary['duration'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3">
                                    <div class="empty">No other entries were captured in this batch.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
