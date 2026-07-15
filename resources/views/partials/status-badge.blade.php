@php
    $statusClass = function ($status) {
        if ($status === null || $status === '') {
            return '';
        }

        if (is_string($status) && ! is_numeric($status)) {
            return in_array(strtolower($status), ['error', 'critical', 'alert', 'emergency'], true) ? 'error' : '';
        }

        $status = (int) $status;

        return match (true) {
            $status >= 500 => 'error',
            $status >= 400 => 'warn',
            $status >= 300 => 'info',
            $status >= 200 => 'ok',
            default => '',
        };
    };
@endphp

<span class="badge {{ $statusClass($status) }}">{{ $status }}</span>
