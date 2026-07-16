@extends('periscope::layout', ['title' => config('periscope.name', 'Periscope')])

@php
    $queryWithoutBefore = request()->except('before');
    $shownTypes = $typeCounts->pluck('type')->unique()->values();
    $typeCountByType = $typeCounts->keyBy('type');
    $isAllEntries = ! $filters->type;
    $watchable = ! $filters->to && ! $filters->beforeSequence;

    $columnsFor = function (?string $type) {
        return match ($type) {
            'cache' => ['time', 'entry'],
            'client_request' => ['time', 'method', 'entry'],
            'gate' => ['time', 'entry', 'result', 'user', 'channel'],
            'job' => ['time', 'status', 'entry', 'duration'],
            'model' => ['time', 'entry', 'action', 'count'],
            'query' => ['time', 'entry', 'duration', 'user'],
            'view' => ['time', 'entry'],
            'log' => ['time', 'status', 'entry'],
            'event' => ['time', 'entry'],
            default => $type ? ['time', 'status', 'entry', 'duration'] : ['time', 'type', 'status', 'entry', 'duration'],
        };
    };

    $columns = $columnsFor($filters->type);
    $columnLabels = [
        'time' => 'Time',
        'method' => 'Method',
        'type' => 'Type',
        'status' => 'Status',
        'entry' => 'Entry',
        'duration' => 'Duration',
        'result' => 'Result',
        'action' => 'Action',
        'count' => 'Count',
        'user' => 'User',
        'path' => 'Path',
        'channel' => 'Channel',
    ];

    $resultLabel = function ($value) {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'Allowed' : 'Denied';
        }

        return (string) $value;
    };

    $rowSearch = function ($entry) {
        return strtolower(collect([
            $entry->type,
            $entry->summary['label'] ?? null,
            $entry->summary['title'] ?? null,
            $entry->summary['subtitle'] ?? null,
            $entry->summary['preview'] ?? null,
            $entry->summary['caller'] ?? null,
            $entry->summary['path'] ?? null,
        ])->filter()->implode(' '));
    };

    $isSameSummaryText = function (?string $first, ?string $second) {
        return trim((string) $first) !== ''
            && trim((string) $first) === trim((string) $second);
    };

@endphp

@section('page-title', $filters->type ? \TortoiseIT\LaravelPeriscope\Support\EntryType::labelFor($filters->type) : 'All entries')
@section('page-subtitle')
    {{ $filters->activeCount() }} active {{ \Illuminate\Support\Str::plural('filter', $filters->activeCount()) }}. Copy the URL to save this search.
@endsection

@section('topbar-actions')
    <button class="button secondary icon-button" type="button" data-watch-toggle data-watch-available="{{ $watchable ? '1' : '0' }}" title="Watch open-ended list">
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M7 7V5a3 3 0 0 1 6 0v2"/>
            <path d="M17 7V5a3 3 0 0 0-6 0v2"/>
            <path d="M5 9h6v8a3 3 0 0 1-6 0Z"/>
            <path d="M13 9h6v8a3 3 0 0 1-6 0Z"/>
            <path d="M11 13h2"/>
        </svg>
        <span>Watch</span>
    </button>
    <a class="button secondary" href="{{ route('periscope.entries.index') }}">Reset</a>
@endsection

@section('content')
    <div class="detail-stack" data-entry-list data-watch-list="{{ $watchable ? '1' : '0' }}">
        <section class="panel">
            <div class="panel-title">
                <span>{{ $filters->type ? \TortoiseIT\LaravelPeriscope\Support\EntryType::labelFor($filters->type) : 'Entries' }}</span>
                <span class="panel-title-actions">
                    <span class="hint"><span data-visible-entry-count>{{ $entries->count() }}</span> shown</span>
                    @if ($isAllEntries)
                        <details class="card-filter-menu" data-local-filter-panel>
                            <summary aria-label="Local view filters" title="Local view filters">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M4 5h16"/>
                                    <path d="M7 12h10"/>
                                    <path d="M10 19h4"/>
                                </svg>
                            </summary>
                            <div class="local-filter-body">
                                <div class="type-filter-grid">
                                    @foreach ($shownTypes as $type)
                                        <label class="check-row">
                                            <input type="checkbox" data-type-filter value="{{ $type }}">
                                            <span>{{ \TortoiseIT\LaravelPeriscope\Support\EntryType::labelFor($type) }}</span>
                                            <span class="check-count">{{ number_format((int) ($typeCountByType[$type]->total ?? 0)) }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                <label class="pattern-filter">
                                    Exclude text patterns
                                    <textarea data-exclude-patterns rows="3" placeholder="OwenIt\\Auditing\\Events\\Audited"></textarea>
                                </label>
                                <div class="filter-actions compact">
                                    <span class="hint" data-local-filter-summary>No local filters applied.</span>
                                    <button class="button secondary" type="button" data-local-filter-reset>Reset local filters</button>
                                </div>
                            </div>
                        </details>
                    @endif
                </span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            @foreach ($columns as $column)
                                <th>{{ $columnLabels[$column] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($entries as $entry)
                            <tr class="clickable-row" data-entry-row data-entry-type="{{ $entry->type }}" data-entry-search="{{ e($rowSearch($entry)) }}" data-href="{{ route('periscope.entries.show', ['uuid' => $entry->uuid] + request()->query()) }}">
                                @foreach ($columns as $column)
                                    @switch($column)
                                        @case('time')
                                            <td class="meta">
                                                {{ \Illuminate\Support\Carbon::parse($entry->created_at)->format('Y-m-d H:i:s') }}
                                                @if ($entry->type === 'query' && $entry->summary['slow'])
                                                    <span class="badge warn">Slow</span>
                                                @endif
                                            </td>
                                            @break

                                        @case('type')
                                            <td><span class="type">{{ $entry->summary['label'] }}</span></td>
                                            @break

                                        @case('method')
                                            <td>
                                                @if ($entry->summary['method'])
                                                    <span class="type">{{ $entry->summary['method'] }}</span>
                                                @endif
                                            </td>
                                            @break

                                        @case('status')
                                            <td>
                                                @if ($entry->summary['status'] !== null)
                                                    @include('periscope::partials.status-badge', ['status' => $entry->summary['status']])
                                                @endif
                                            </td>
                                            @break

                                        @case('entry')
                                            <td>
                                                <div class="title">
                                                    <a href="{{ route('periscope.entries.show', ['uuid' => $entry->uuid] + request()->query()) }}">
                                                        {{ \Illuminate\Support\Str::limit($entry->summary['title'], $entry->type === 'query' ? 150 : 220) }}
                                                    </a>
                                                </div>
                                                @if ($entry->summary['preview'] && ! in_array($entry->type, ['query', 'event', 'gate', 'model', 'view'], true))
                                                    <div class="subtitle">{{ \Illuminate\Support\Str::limit($entry->summary['preview'], 220) }}</div>
                                                @endif
                                                @if ($entry->summary['subtitle'])
                                                    <div class="subtitle">{{ \Illuminate\Support\Str::limit($entry->summary['subtitle'], 220) }}</div>
                                                @endif
                                                @if ($entry->summary['caller'] && ! $isSameSummaryText($entry->summary['subtitle'], $entry->summary['caller']))
                                                    <div class="subtitle">Caller: {{ \Illuminate\Support\Str::limit($entry->summary['caller'], 220) }}</div>
                                                @endif
                                                @if ($entry->type === 'query' && $entry->summary['user'] && ! in_array('user', $columns, true))
                                                    <div class="subtitle">User: {{ $entry->summary['user'] }}</div>
                                                @endif
                                            </td>
                                            @break

                                        @case('duration')
                                            <td class="meta">
                                                {{ $entry->summary['duration'] }}
                                            </td>
                                            @break

                                        @case('result')
                                            <td>
                                                @if ($resultLabel($entry->summary['result']))
                                                    <span @class(['badge', 'ok' => $entry->summary['result'] === true || $entry->summary['result'] === 'allowed', 'error' => $entry->summary['result'] === false || $entry->summary['result'] === 'denied'])>
                                                        {{ $resultLabel($entry->summary['result']) }}
                                                    </span>
                                                @endif
                                            </td>
                                            @break

                                        @case('action')
                                            <td>{{ $entry->summary['action'] }}</td>
                                            @break

                                        @case('count')
                                            <td class="meta">{{ $entry->summary['count'] }}</td>
                                            @break

                                        @case('user')
                                            <td>
                                                @if ($entry->summary['user'])
                                                    <div>{{ $entry->summary['user'] }}</div>
                                                    @if ($entry->summary['user_id'])
                                                        <a class="subtitle" href="{{ route('periscope.entries.index', request()->except('before') + ['tag' => 'Auth:'.$entry->summary['user_id']]) }}">Auth:{{ $entry->summary['user_id'] }}</a>
                                                    @endif
                                                @else
                                                    <span class="hint">Guest</span>
                                                @endif
                                            </td>
                                            @break

                                        @case('path')
                                            <td class="subtitle">{{ $entry->summary['path'] }}</td>
                                            @break

                                        @case('channel')
                                            <td>
                                                @if ($entry->summary['channel'])
                                                    <span class="type">{{ $entry->summary['channel'] }}</span>
                                                @else
                                                    <span class="hint">Unknown</span>
                                                @endif
                                            </td>
                                            @break
                                    @endswitch
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($columns) }}">
                                    <div class="empty">No Telescope entries matched this search.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($hasMore)
                <div class="pager">
                    <a class="button secondary" href="{{ route('periscope.entries.index', $queryWithoutBefore + ['before' => $nextBefore]) }}">Next page</a>
                </div>
            @endif
        </section>
    </div>
@endsection
