@extends('periscope::layout', ['title' => config('periscope.name', 'Periscope')])

@php
    $overview = $requestOverview ?? [];
    $overviewErrors = collect($overview['error_requests'] ?? []);
    $durationBuckets = collect($overview['duration_buckets'] ?? []);
    $users = collect($overview['users'] ?? []);
    $statusGroups = $overview['status_groups'] ?? [];
    $jobs = $overview['jobs'] ?? ['total' => 0, 'succeeded' => 0, 'failed' => 0];
    $scheduledCommands = collect($overview['scheduled_commands'] ?? [])->take(10);
    $slowestRequest = $overview['slowest_request'] ?? null;
    $overviewQuery = request()->except('before');
    $windowFrom = $overview['window_from'] ?? null;
    $windowTo = $overview['window_to'] ?? null;
    $formatRequestCount = fn ($count) => number_format((int) $count).' '.\Illuminate\Support\Str::plural('request', (int) $count);
    $cleanRequestTitle = function (string $title, ?string $method): string {
        if (! $method) {
            return $title;
        }

        return trim(preg_replace('/^'.preg_quote($method, '/').'\s+/i', '', $title) ?? $title);
    };
@endphp

@section('page-title', 'Overview')
@section('page-subtitle')
    Request activity from {{ $windowFrom ? \Illuminate\Support\Carbon::parse($windowFrom)->format('Y-m-d H:i') : 'the beginning' }} to {{ $windowTo ? \Illuminate\Support\Carbon::parse($windowTo)->format('Y-m-d H:i') : 'now' }}.
@endsection

@section('content')
    <div class="overview-board">
        <div class="overview-column">
            <section class="panel overview-panel">
                <div class="panel-title">
                    <span>Request Snapshot</span>
                    <span class="hint">
                        @if ($overview['is_capped'] ?? false)
                            Sampled {{ number_format((int) ($overview['inspected_requests'] ?? 0)) }} of {{ number_format((int) ($overview['total_requests'] ?? 0)) }} requests
                        @else
                            {{ number_format((int) ($overview['total_requests'] ?? 0)) }} requests inspected
                        @endif
                    </span>
                </div>
                <div class="overview-stats overview-stats-wide">
                    <div class="overview-stat">
                        <strong>{{ number_format((int) ($overview['total_requests'] ?? 0)) }}</strong>
                        <span>requests</span>
                    </div>
                    <div class="overview-stat">
                        <strong>{{ number_format((int) ($overview['error_request_count'] ?? 0)) }}</strong>
                        <span>flagged</span>
                    </div>
                    <div class="overview-stat">
                        <strong>{{ number_format((int) (($statusGroups['server_error'] ?? 0) + ($statusGroups['client_error'] ?? 0))) }}</strong>
                        <span>HTTP errors</span>
                    </div>
                    <div class="overview-stat">
                        <strong>{{ isset($overview['avg_duration']) ? number_format((float) $overview['avg_duration'], 2) : '-' }}</strong>
                        <span>avg ms</span>
                    </div>
                    @if ($slowestRequest)
                        <a class="overview-stat linked" href="{{ route('periscope.entries.lifecycle', ['uuid' => $slowestRequest['uuid']] + $overviewQuery) }}">
                            <strong>{{ number_format((float) $slowestRequest['duration'], 2) }}</strong>
                            <span>slowest ms</span>
                        </a>
                    @else
                        <div class="overview-stat">
                            <strong>-</strong>
                            <span>slowest ms</span>
                        </div>
                    @endif
                </div>
            </section>

            <section class="panel snapshot-card">
                <div class="panel-title">
                    <span>Issues Detected</span>
                    <span class="hint">{{ number_format((int) ($overview['error_request_count'] ?? 0)) }} flagged</span>
                </div>
                <div class="snapshot-list issue-list">
                    @forelse ($overviewErrors as $errorRequest)
                        <article class="overview-error-item">
                            <div class="overview-error-main">
                                <a href="{{ route('periscope.entries.lifecycle', ['uuid' => $errorRequest['uuid']] + $overviewQuery) }}">
                                    {{ \Illuminate\Support\Str::limit($cleanRequestTitle($errorRequest['title'], $errorRequest['method']), 130) }}
                                </a>
                                <div class="overview-error-meta">
                                    @if ($errorRequest['method'])
                                        <span class="type">{{ $errorRequest['method'] }}</span>
                                    @endif
                                    @if ($errorRequest['status'] !== null)
                                        @include('periscope::partials.status-badge', ['status' => $errorRequest['status']])
                                    @endif
                                    @if ($errorRequest['error_label'])
                                        <span class="badge error">{{ $errorRequest['error_label'] }}</span>
                                    @endif
                                </div>
                                @if ($errorRequest['error_title'])
                                    <div class="subtitle">{{ \Illuminate\Support\Str::limit($errorRequest['error_title'], 170) }}</div>
                                @endif
                            </div>
                            <a class="button secondary overview-error-action" href="{{ route('periscope.entries.lifecycle', ['uuid' => $errorRequest['uuid']] + $overviewQuery) }}">Examine</a>
                        </article>
                    @empty
                        <div class="empty">No failing requests were found in this timeframe.</div>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="overview-column">
            <section class="panel snapshot-card">
                <div class="panel-title">
                    <span>Status Mix</span>
                    <span class="hint">Sampled requests</span>
                </div>
                <div class="snapshot-list compact">
                    <div class="snapshot-row">
                        <strong>OK</strong>
                        <span class="badge ok">{{ $formatRequestCount($statusGroups['ok'] ?? 0) }}</span>
                    </div>
                    <div class="snapshot-row">
                        <strong>Redirects</strong>
                        <span class="badge">{{ $formatRequestCount($statusGroups['redirect'] ?? 0) }}</span>
                    </div>
                    <div class="snapshot-row">
                        <strong>Client errors</strong>
                        <span class="badge warn">{{ $formatRequestCount($statusGroups['client_error'] ?? 0) }}</span>
                    </div>
                    <div class="snapshot-row">
                        <strong>Server errors</strong>
                        <span class="badge error">{{ $formatRequestCount($statusGroups['server_error'] ?? 0) }}</span>
                    </div>
                    <a class="button secondary" href="{{ route('periscope.entries.index', $overviewQuery) }}">Open full entry list</a>
                </div>
            </section>

            <section class="panel snapshot-card">
                <div class="panel-title">
                    <span>Jobs</span>
                    <span class="hint">{{ number_format((int) ($jobs['total'] ?? 0)) }} in timeframe</span>
                </div>
                <div class="snapshot-list compact">
                    <a class="snapshot-row linked" href="{{ route('periscope.entries.index', array_merge($overviewQuery, ['type' => 'job', 'status' => 'processed'])) }}">
                        <strong>Succeeded</strong>
                        <span class="badge ok">{{ number_format((int) ($jobs['succeeded'] ?? 0)) }} {{ \Illuminate\Support\Str::plural('job', (int) ($jobs['succeeded'] ?? 0)) }}</span>
                    </a>
                    <a class="snapshot-row linked" href="{{ route('periscope.entries.index', array_merge($overviewQuery, ['type' => 'job', 'status' => 'failed'])) }}">
                        <strong>Failed</strong>
                        <span class="badge error">{{ number_format((int) ($jobs['failed'] ?? 0)) }} {{ \Illuminate\Support\Str::plural('job', (int) ($jobs['failed'] ?? 0)) }}</span>
                    </a>
                    <a class="button secondary" href="{{ route('periscope.entries.index', array_merge($overviewQuery, ['type' => 'job'])) }}">Open all jobs</a>
                </div>
            </section>

            <section class="panel snapshot-card">
                <div class="panel-title">
                    <span>Scheduled Commands</span>
                    <span class="hint">{{ number_format((int) collect($overview['scheduled_commands'] ?? [])->count()) }} in timeframe</span>
                </div>
                <div class="snapshot-list compact">
                    @forelse ($scheduledCommands as $command)
                        <a class="snapshot-row linked" href="{{ route('periscope.schedule.show', ['commandKey' => $command['command_key'], 'label' => $command['command_label']] + $overviewQuery) }}">
                            <div>
                                <strong>{{ \Illuminate\Support\Str::limit($command['command_label'], 84) }}</strong>
                                @if ($command['expression'])
                                    <div class="subtitle">{{ $command['expression'] }}</div>
                                @endif
                            </div>
                            <span class="snapshot-actions">
                                @if ((int) ($command['failed_count'] ?? 0) > 0)
                                    <span class="badge error">{{ number_format((int) $command['failed_count']) }} failed</span>
                                @endif
                                @if (is_numeric($command['last_duration'] ?? null))
                                    <span class="check-count">{{ number_format((float) $command['last_duration'], 3) }} ms</span>
                                @endif
                                <span class="check-count">{{ number_format((int) ($command['run_count'] ?? 0)) }} {{ \Illuminate\Support\Str::plural('run', (int) ($command['run_count'] ?? 0)) }}</span>
                            </span>
                        </a>
                    @empty
                        <div class="empty">No scheduled command runs were found in this timeframe.</div>
                    @endforelse

                    @if (collect($overview['scheduled_commands'] ?? [])->count() > $scheduledCommands->count())
                        <div class="hint">Showing the most recent {{ $scheduledCommands->count() }} commands.</div>
                    @endif

                    <a class="button secondary" href="{{ route('periscope.entries.index', array_merge($overviewQuery, ['type' => 'schedule'])) }}">Open all schedule entries</a>
                </div>
            </section>

            <section class="panel snapshot-card">
                <div class="panel-title">
                    <span>Slow Requests</span>
                    <span class="hint">Duration ranges</span>
                </div>
                <div class="snapshot-list compact">
                    @forelse ($durationBuckets as $bucket)
                        <details class="snapshot-disclosure">
                            <summary>
                                <span class="snapshot-disclosure-caret" aria-hidden="true"></span>
                                <span class="snapshot-disclosure-title">
                                    <strong>{{ $bucket['label'] }}</strong>
                                    <span class="subtitle">
                                        @if ($bucket['avg'] !== null)
                                            avg {{ $bucket['avg'] }} ms
                                        @endif
                                        @if ($bucket['max'] !== null)
                                            @if ($bucket['avg'] !== null) / @endif max {{ $bucket['max'] }} ms
                                        @endif
                                    </span>
                                </span>
                                <span class="check-count">{{ $formatRequestCount($bucket['count']) }}</span>
                            </summary>
                            <div class="snapshot-disclosure-body">
                                @foreach ($bucket['requests'] as $request)
                                    <a class="snapshot-mini-row" href="{{ route('periscope.entries.lifecycle', ['uuid' => $request['uuid']] + $overviewQuery) }}">
                                        <span>{{ \Illuminate\Support\Str::limit($cleanRequestTitle($request['title'], $request['method']), 96) }}</span>
                                        <span>{{ $request['duration'] }} ms</span>
                                    </a>
                                @endforeach
                                @if ($bucket['count'] > count($bucket['requests']))
                                    <div class="hint">Showing the slowest {{ count($bucket['requests']) }} in this range.</div>
                                @endif
                            </div>
                        </details>
                    @empty
                        <div class="empty">No request durations were captured.</div>
                    @endforelse
                </div>
            </section>

            <section class="panel snapshot-card">
                <div class="panel-title">
                    <span>Users Referenced</span>
                    <span class="hint">{{ number_format($users->count()) }} shown</span>
                </div>
                <div class="snapshot-list compact">
                    @forelse ($users as $user)
                        <div class="snapshot-row">
                            <div>
                                <strong>{{ $user['label'] }}</strong>
                                @if ($user['meta'])
                                    <div class="subtitle">{{ $user['meta'] }}</div>
                                @endif
                            </div>
                            <div class="snapshot-actions">
                                <span class="check-count">{{ $formatRequestCount($user['count']) }}</span>
                                @if ($user['id'] !== null && $user['id'] !== '')
                                    <a class="text-button" href="{{ route('periscope.entries.index', $overviewQuery + ['tag' => 'Auth:'.$user['id']]) }}">Filter</a>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="empty">No user references were captured in request entries.</div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
@endsection
