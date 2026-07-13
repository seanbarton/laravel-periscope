@php
    $encoded = is_array($value) ? json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
    $isLongString = is_string($value) && strlen($value) > 900;
    $count = is_array($value) ? count($value) : null;
@endphp

@if (is_array($value))
    <details>
        <summary>{{ $count }} {{ \Illuminate\Support\Str::plural('item', $count) }}</summary>
        <pre>{{ $encoded }}</pre>
    </details>
@elseif ($isLongString)
    <details>
        <summary>{{ number_format(strlen($value)) }} characters</summary>
        <pre>{{ $value }}</pre>
    </details>
@elseif (is_bool($value))
    {{ $value ? 'true' : 'false' }}
@elseif ($value === null)
    <span class="hint">null</span>
@else
    {{ $value }}
@endif
