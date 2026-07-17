@extends('periscope::layout', ['title' => config('periscope.name', 'Periscope')])

@php
    $queryWithoutBefore = request()->except('before');
@endphp

@section('page-title', 'Scheduled Command Runs')
@section('page-subtitle')
    {{ $commandLabel }}
@endsection

@section('topbar-actions')
    <a class="button secondary" href="{{ route('periscope.index', request()->query()) }}">Back to overview</a>
@endsection

@section('content')
    <div class="detail-stack">
        <section class="panel">
            <div class="panel-title">
                <span>Runs</span>
                <span class="hint">{{ $entries->count() }} shown</span>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Status</th>
                            <th>Entry</th>
                            <th>Duration</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($entries as $entry)
                            <tr class="clickable-row" data-href="{{ route('periscope.entries.show', ['uuid' => $entry->uuid] + request()->query()) }}">
                                <td class="meta">{{ \Illuminate\Support\Carbon::parse($entry->created_at)->format('Y-m-d H:i:s') }}</td>
                                <td>
                                    @if ($entry->summary['status'] !== null)
                                        @include('periscope::partials.status-badge', ['status' => $entry->summary['status']])
                                    @else
                                        <span class="hint">—</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="title">
                                        <a href="{{ route('periscope.entries.show', ['uuid' => $entry->uuid] + request()->query()) }}">
                                            {{ \Illuminate\Support\Str::limit($entry->summary['title'], 220) }}
                                        </a>
                                    </div>
                                    @if ($entry->summary['subtitle'])
                                        <div class="subtitle">{{ \Illuminate\Support\Str::limit($entry->summary['subtitle'], 220) }}</div>
                                    @endif
                                </td>
                                <td class="meta">{{ $entry->summary['duration'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">
                                    <div class="empty">No runs found for this scheduled command in the selected timeframe.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="snapshot-actions">
                <a class="button secondary" href="{{ route('periscope.entries.index', array_merge($queryWithoutBefore, ['type' => 'schedule'])) }}">Open all schedule entries</a>

                @if ($hasMore && $nextBefore)
                    <a class="button" href="{{ route('periscope.schedule.show', ['commandKey' => $commandKey] + request()->except('before') + ['before' => $nextBefore]) }}">Load older runs</a>
                @endif
            </div>
        </section>
    </div>
@endsection