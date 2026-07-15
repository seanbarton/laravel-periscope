@forelse ($frames as $frame)
    <div class="trace-frame">
        <div class="trace-frame-main">
            <span class="trace-file" @if ($frame['file']) title="{{ $frame['file'] }}" @endif>
                {{ $frame['relative_file'] ?? 'Unknown file' }}@if ($frame['line']):{{ $frame['line'] }}@endif
            </span>
            @if ($frame['is_core'] ?? false)
                <span class="badge">Core</span>
            @endif
        </div>
        @if ($frame['call'])
            <div class="trace-call">{{ $frame['call'] }}</div>
        @endif
        @if ($frame['source'] ?? null)
            <div class="source-preview-wrap" data-source-preview>
                <button class="source-toggle" type="button" data-source-toggle aria-label="Expand source preview" title="Expand source preview" aria-expanded="false">
                    <span>Expand source</span>
                </button>
                <div class="source-preview source-compact">
                    @foreach ($frame['source']['lines'] as $sourceLine)
                        <div @class(['source-line', 'is-target' => $sourceLine['is_target']])>
                            <span class="source-number">{{ $sourceLine['number'] }}</span>
                            <code>{!! $sourceLine['code'] !!}</code>
                        </div>
                    @endforeach
                </div>
                <div class="source-preview source-scroll">
                    @foreach (($frame['source']['expanded_lines'] ?? $frame['source']['lines']) as $sourceLine)
                        <div @class(['source-line', 'is-target' => $sourceLine['is_target']])>
                            <span class="source-number">{{ $sourceLine['number'] }}</span>
                            <code>{!! $sourceLine['code'] !!}</code>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
@empty
    <div class="empty">{{ $empty ?? 'No stack frames were captured for this entry.' }}</div>
@endforelse
