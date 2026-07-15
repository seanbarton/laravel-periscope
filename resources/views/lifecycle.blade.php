@extends('periscope::layout', ['title' => config('periscope.name', 'Periscope').' Lifecycle'])

@php
    $classification = $lifecycle['classification'];
    $summary = $lifecycle['summary'];
    $requestEntry = $lifecycle['request'];
    $errorTrail = $lifecycle['error_trail'];
    $requestContext = $lifecycle['request_context'] ?? [];
    $queryHealth = $lifecycle['query_health'];
    $externalHealth = $lifecycle['external_health'];
    $preErrorContext = $lifecycle['pre_error_context'];
    $debugBundle = $lifecycle['debug_bundle'];
    $timeline = $lifecycle['timeline'];
    $selectedEvent = $lifecycle['phases']
        ->flatMap(fn ($phase) => $phase['events'])
        ->firstWhere('is_selected', true);
    $pretty = fn ($value) => json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
@endphp

@section('page-title')
    Lifecycle
@endsection

@section('page-subtitle')
    {{ $classification['method'] ?? 'Batch' }} {{ $classification['path'] ?? $entry->summary['title'] }}
@endsection

@section('topbar-actions')
    <button class="button secondary" type="button" data-copy-text="{{ base64_encode($debugBundle) }}" data-copy-encoded="base64">Copy Debug Bundle</button>
    <a class="button secondary" href="{{ route('periscope.entries.show', ['uuid' => $entry->uuid] + request()->query()) }}">Entry</a>
    <a class="button secondary" href="{{ route('periscope.index', request()->except('uuid')) }}">Back</a>
@endsection

@section('content')
    <div class="detail-stack">
        <div class="lifecycle-hero">
            <section class="panel summary-card lifecycle-overview">
                <h3>Request Shape</h3>
                <div class="summary-grid">
                    <div class="summary-item">
                        <div class="summary-label">Channel</div>
                        <div class="summary-value">{{ $classification['channel'] ?? 'Unknown' }}</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Expected</div>
                        <div class="summary-value">{{ $classification['expected'] ?? 'Unknown response' }}</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Status</div>
                        <div class="summary-value">
                            @if ($classification['status'] ?? null)
                                @include('periscope::partials.status-badge', ['status' => $classification['status']])
                            @else
                                <span class="hint">None</span>
                            @endif
                        </div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Output</div>
                        <div class="summary-value">
                            {{ $classification['response'] ?? 'No captured response body/header' }}
                            @if ($classification['response_detail'] ?? null)
                                <div class="muted">{{ $classification['response_detail'] }}</div>
                            @endif
                            @if (($classification['response_json'] ?? null) !== null)
                                <button class="text-button" type="button" data-modal-open="response-json">View JSON</button>
                            @endif
                        </div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Query String</div>
                        <div class="summary-value">
                            {{ $classification['query'] ?? 'None' }}
                            @if ($classification['query_data'] ?? null)
                                <button class="text-button" type="button" data-modal-open="query-json">View JSON</button>
                            @endif
                        </div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">User</div>
                        <div class="summary-value">{{ $classification['user'] ?? 'Guest or not captured' }}</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Duration</div>
                        <div class="summary-value">{{ $summary['duration'] ? $summary['duration'].' ms' : 'Unknown' }}</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Selected</div>
                        <div class="summary-value" title="Telescope sequence #{{ $entry->sequence }}">
                            {{ $entry->summary['label'] }} {{ $selectedEvent ? str_pad((string) $selectedEvent['position'], 2, '0', STR_PAD_LEFT) : '' }}
                        </div>
                    </div>
                </div>
            </section>

            <section class="panel summary-card lifecycle-health">
                <h3>Batch Health</h3>
                <div class="health-grid">
                    <div>
                        <strong>{{ number_format($summary['total']) }}</strong>
                        <span>entries</span>
                    </div>
                    <div @class(['has-error' => $summary['errors'] > 0])>
                        <strong>{{ number_format($summary['errors']) }}</strong>
                        <span>errors</span>
                    </div>
                    <div @class(['has-warn' => $summary['warnings'] > 0])>
                        <strong>{{ number_format($summary['warnings']) }}</strong>
                        <span>warnings</span>
                    </div>
                    <div>
                        <strong>{{ number_format($summary['queries']) }}</strong>
                        <span>queries</span>
                    </div>
                    <div>
                        <strong>{{ number_format($summary['logs']) }}</strong>
                        <span>logs</span>
                    </div>
                    <div>
                        <strong>{{ number_format($summary['external']) }}</strong>
                        <span>external</span>
                    </div>
                </div>
            </section>
        </div>

        <section class="panel debug-insights-panel">
            <div class="panel-title">
                <span>Debug Insights</span>
                <span class="hint">Request context, query health, and external call health</span>
            </div>
            <div class="insight-grid">
                <article class="insight-card">
                    <h3>Request Context</h3>
                    <dl class="insight-list">
                        <div><dt>Route</dt><dd>{{ $requestContext['controller'] ?? 'Unknown' }}</dd></div>
                        <div><dt>User</dt><dd>{{ $requestContext['user'] ?? 'Guest/unknown' }}</dd></div>
                        <div><dt>IP</dt><dd>{{ $requestContext['ip'] ?? 'Unknown' }}</dd></div>
                        <div><dt>Payload</dt><dd>{{ ($requestContext['payload_keys'] ?? null) ?: 'No captured keys' }}</dd></div>
                        <div><dt>Middleware</dt><dd>{{ $requestContext['middleware_count'] ?? 0 }}</dd></div>
                    </dl>
                </article>

                <article class="insight-card">
                    <h3>Query Health</h3>
                    <dl class="insight-list">
                        <div><dt>Total</dt><dd>{{ number_format($queryHealth['total']) }}</dd></div>
                        <div><dt>Total Time</dt><dd>{{ $queryHealth['total_duration'] }} ms</dd></div>
                        <div><dt>Slow</dt><dd>{{ number_format($queryHealth['slow']) }}</dd></div>
                        <div><dt>Duplicates</dt><dd>{{ $queryHealth['duplicate_groups']->count() }}</dd></div>
                    </dl>
                    @if ($queryHealth['slowest'])
                        <p class="insight-note">Slowest: {{ \Illuminate\Support\Str::limit($queryHealth['slowest']['sql'], 120) }} @if ($queryHealth['slowest']['duration'])({{ $queryHealth['slowest']['duration'] }} ms)@endif</p>
                    @endif
                    @if ($queryHealth['duplicate_groups']->isNotEmpty())
                        <div class="insight-mini-list">
                            @foreach ($queryHealth['duplicate_groups'] as $duplicate)
                                <span>{{ $duplicate['count'] }}x {{ \Illuminate\Support\Str::limit($duplicate['sql'], 90) }}</span>
                            @endforeach
                        </div>
                    @endif
                </article>

                <article class="insight-card">
                    <h3>External Calls</h3>
                    <dl class="insight-list">
                        <div><dt>Total</dt><dd>{{ number_format($externalHealth['total']) }}</dd></div>
                        <div><dt>Failed</dt><dd>{{ number_format($externalHealth['failed']) }}</dd></div>
                        <div><dt>Hosts</dt><dd>{{ $externalHealth['hosts']->count() }}</dd></div>
                    </dl>
                    @if ($externalHealth['slowest'])
                        <p class="insight-note">Slowest: {{ $externalHealth['slowest']['host'] }} @if ($externalHealth['slowest']['duration'])({{ $externalHealth['slowest']['duration'] }} ms)@endif</p>
                    @endif
                    @if ($externalHealth['hosts']->isNotEmpty())
                        <div class="insight-mini-list">
                            @foreach ($externalHealth['hosts'] as $host => $count)
                                <span>{{ $host }}: {{ $count }}</span>
                            @endforeach
                        </div>
                    @endif
                </article>
            </div>
        </section>

        @if ($errorTrail->isNotEmpty())
            <section class="panel error-trail-panel">
                <div class="panel-title">
                    <span>Error Trail</span>
                    <span class="hint">{{ $errorTrail->count() }} error {{ \Illuminate\Support\Str::plural('entry', $errorTrail->count()) }} in this batch</span>
                </div>

                <div class="error-trail-list">
                    @foreach ($errorTrail as $errorEvent)
                        @php
                            $firstFrame = $errorEvent['trace']['first_app_frame'] ?? null;
                        @endphp
                        <article class="error-trail-item">
                            <div class="error-trail-head">
                                <a href="{{ route('periscope.entries.show', ['uuid' => $errorEvent['uuid']] + request()->query()) }}">
                                    <span class="node-sequence" title="Telescope sequence #{{ $errorEvent['sequence'] }}">{{ str_pad((string) $errorEvent['position'], 2, '0', STR_PAD_LEFT) }}</span>
                                    {{ $errorEvent['label'] }}
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
                        </article>
                    @endforeach
                </div>

                @if ($preErrorContext->isNotEmpty())
                    <div class="pre-error-list">
                        @foreach ($preErrorContext as $group)
                            <article class="pre-error-card">
                                <h3>Before {{ \Illuminate\Support\Str::limit($group['error_title'], 100) }}</h3>
                                <div class="pre-error-items">
                                    @foreach ($group['items'] as $item)
                                        <a href="{{ route('periscope.entries.show', ['uuid' => $item['uuid']] + request()->query()) }}">
                                            <span>{{ $item['label'] }}</span>
                                            <strong>{{ \Illuminate\Support\Str::limit($item['title'], 110) }}</strong>
                                            @if ($item['duration'])
                                                <em>{{ $item['duration'] }} ms</em>
                                            @endif
                                        </a>
                                    @endforeach
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>
        @endif

        <section class="panel lifecycle-tabs" data-tabs>
            <div class="tabbar">
                <button type="button" class="active" data-tab-target="flow">Page Load Flow</button>
                <button type="button" data-tab-target="timeline">Timeline</button>
            </div>

            <div class="tab-panel" data-tab-panel="flow">
                <div class="panel-title">
                    <span>Page Load Flow</span>
                    <span class="hint">Ordered by Telescope sequence within this batch</span>
                </div>

                <div class="flow-board" style="--flow-columns: {{ max(1, $lifecycle['phases']->count()) }}">
                    @foreach ($lifecycle['phases'] as $phase)
                        <section class="flow-phase">
                            <div class="phase-title">
                                <span>{{ $phase['label'] }}</span>
                                <span>{{ $phase['events']->count() }}</span>
                            </div>

                            <div class="flow-lane">
                                @foreach ($phase['events'] as $event)
                                    <article @class([
                                        'flow-node',
                                        'selected' => $event['is_selected'],
                                        'request-node' => $event['is_request'],
                                        'query-node' => $event['type'] === 'query',
                                        'severity-'.$event['severity'],
                                    ])>
                                        <a class="node-link" href="{{ route('periscope.entries.show', ['uuid' => $event['uuid']] + request()->query()) }}">
                                            <span class="node-sequence" title="Telescope sequence #{{ $event['sequence'] }}">{{ str_pad((string) $event['position'], 2, '0', STR_PAD_LEFT) }}</span>
                                            <span class="node-kind">{{ $event['label'] }}</span>
                                            @if ($event['status'] !== null && $event['status'] !== '')
                                                @include('periscope::partials.status-badge', ['status' => $event['status']])
                                            @endif
                                        </a>

                                        <div class="node-title">{{ $event['short_title'] }}</div>

                                        @if ($event['type'] !== 'query' && ($event['subtitle'] || $event['caller']))
                                            <div class="node-subtitle">{{ $event['subtitle'] ?? $event['caller'] }}</div>
                                        @endif

                                        <div class="node-meta">
                                            @if ($event['duration'])
                                                <span>{{ $event['duration'] }} ms</span>
                                            @endif
                                            @if ($event['note'])
                                                <span>{{ $event['note'] }}</span>
                                            @endif
                                            @if ($event['is_selected'])
                                                <span>Selected entry</span>
                                            @endif
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </section>
                    @endforeach
                </div>
            </div>

            <div class="tab-panel" data-tab-panel="timeline" hidden>
                <div class="panel-title">
                    <span>Request Timeline</span>
                    <span class="hint">Ordered by capture sequence with relative offsets and captured durations</span>
                </div>

                <div class="timeline-list">
                    @foreach ($timeline['events'] as $event)
                        <article @class([
                            'timeline-item',
                            'selected' => $event['is_selected'],
                            'severity-'.$event['severity'],
                        ])>
                            <div class="timeline-marker">
                                <span>{{ $event['offset_label'] }}</span>
                            </div>
                            <div class="timeline-card">
                                <div class="timeline-head">
                                    <a href="{{ route('periscope.entries.show', ['uuid' => $event['uuid']] + request()->query()) }}">
                                        <span class="node-sequence" title="Telescope sequence #{{ $event['sequence'] }}">{{ str_pad((string) $event['position'], 2, '0', STR_PAD_LEFT) }}</span>
                                        {{ $event['label'] }}
                                    </a>
                                    @if ($event['status'] !== null && $event['status'] !== '')
                                        @include('periscope::partials.status-badge', ['status' => $event['status']])
                                    @endif
                                </div>
                                <div class="timeline-title">{{ $event['short_title'] }}</div>
                                @if ($event['subtitle'] || $event['caller'])
                                    <div class="node-subtitle">{{ $event['subtitle'] ?? $event['caller'] }}</div>
                                @endif
                                <div class="node-meta">
                                    @if ($event['duration_ms'])
                                        <span>{{ $event['duration_ms'] }} ms</span>
                                    @endif
                                    @if ($event['note'])
                                        <span>{{ $event['note'] }}</span>
                                    @endif
                                    @if ($event['is_selected'])
                                        <span>Selected entry</span>
                                    @endif
                                </div>
                                @if ($event['duration_ms'])
                                    <div class="timeline-duration" title="{{ $event['duration_ms'] }} ms">
                                        <span style="width: {{ $event['duration_width'] }}%"></span>
                                    </div>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>

        </section>

        @if ($requestEntry && $requestEntry->uuid !== $entry->uuid)
            <section class="panel">
                <div class="panel-title">
                    <span>Root Request</span>
                    <a class="button secondary" href="{{ route('periscope.entries.show', ['uuid' => $requestEntry->uuid] + request()->query()) }}">Open Request</a>
                </div>
            </section>
        @endif

        @if ($classification['query_data'] ?? null)
            <dialog class="periscope-modal" data-modal="query-json">
                <div class="modal-card">
                    <div class="modal-head">
                        <h3>Query Parameters</h3>
                        <button class="button secondary" type="button" data-modal-close>Close</button>
                    </div>
                    <pre>{{ $pretty($classification['query_data']) }}</pre>
                </div>
            </dialog>
        @endif

        @if (($classification['response_json'] ?? null) !== null)
            <dialog class="periscope-modal" data-modal="response-json">
                <div class="modal-card">
                    <div class="modal-head">
                        <h3>Response JSON</h3>
                        <button class="button secondary" type="button" data-modal-close>Close</button>
                    </div>
                    <pre>{{ $pretty($classification['response_json']) }}</pre>
                </div>
            </dialog>
        @endif
    </div>
@endsection
