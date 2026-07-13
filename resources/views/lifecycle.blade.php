@extends('periscope::layout', ['title' => config('periscope.name', 'Periscope').' Lifecycle'])

@php
    $classification = $lifecycle['classification'];
    $summary = $lifecycle['summary'];
    $requestEntry = $lifecycle['request'];
    $selectedEvent = $lifecycle['phases']
        ->flatMap(fn ($phase) => $phase['events'])
        ->firstWhere('is_selected', true);
    $pretty = fn ($value) => json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $statusClass = function ($status) {
        if ($status === null || $status === '') {
            return '';
        }

        if (is_string($status) && ! is_numeric($status)) {
            return in_array(strtolower($status), ['error', 'critical', 'alert', 'emergency'], true) ? 'error' : '';
        }

        $status = (int) $status;
        return $status >= 500 ? 'error' : ($status >= 400 ? 'warn' : ($status >= 200 && $status < 400 ? 'ok' : ''));
    };
@endphp

@section('page-title')
    Lifecycle
@endsection

@section('page-subtitle')
    {{ $classification['method'] ?? 'Batch' }} {{ $classification['path'] ?? $entry->summary['title'] }}
@endsection

@section('topbar-actions')
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
                                <span class="badge {{ $statusClass($classification['status']) }}">{{ $classification['status'] }}</span>
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

        <section class="panel lifecycle-panel">
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
                                            <span class="badge {{ $statusClass($event['status']) }}">{{ $event['status'] }}</span>
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
