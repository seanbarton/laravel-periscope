<?php

namespace TortoiseIT\LaravelPeriscope\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TraceFormatter
{
    public function errorTrail(Collection $entries): Collection
    {
        return $entries
            ->sortBy('sequence')
            ->values()
            ->filter(fn (object $entry) => $this->isErrorTrailEntry($entry))
            ->map(fn (object $entry, int $index) => [
                'uuid' => $entry->uuid,
                'position' => $index + 1,
                'sequence' => $entry->sequence,
                'type' => $entry->type,
                'label' => EntryType::labelFor($entry->type),
                'title' => $entry->summary['title'],
                'message' => $entry->summary['preview'] ?? $entry->summary['subtitle'] ?? null,
                'status' => $entry->summary['status'],
                'caller' => $entry->summary['caller'],
                'trace' => $this->forEntry($entry),
            ])
            ->values();
    }

    public function forEntry(object $entry): array
    {
        $content = $entry->content;
        $frames = $this->framesFrom($content['trace'] ?? []);

        if ($frames === [] && isset($content['file'])) {
            $frames[] = $this->frameFrom([
                'file' => $content['file'],
                'line' => $content['line'] ?? null,
                'class' => $content['class'] ?? null,
            ]);
        }

        $frames = collect($frames)
            ->filter()
            ->values();

        $appFrames = $frames
            ->reject(fn (array $frame) => $frame['is_core'])
            ->values();

        return [
            'frames' => $frames->all(),
            'app_frames' => $appFrames->all(),
            'first_app_frame' => $appFrames->first(),
            'hidden_core_count' => $frames->count() - $appFrames->count(),
            'has_trace' => $frames->isNotEmpty(),
        ];
    }

    private function framesFrom(mixed $trace): array
    {
        if (! is_array($trace)) {
            return [];
        }

        if (array_is_list($trace)) {
            return collect($trace)
                ->map(fn ($frame) => is_array($frame) ? $this->frameFrom($frame) : null)
                ->filter()
                ->values()
                ->all();
        }

        return [$this->frameFrom($trace)];
    }

    private function frameFrom(array $frame): ?array
    {
        $file = isset($frame['file']) && is_scalar($frame['file']) ? (string) $frame['file'] : null;
        $line = isset($frame['line']) && is_scalar($frame['line']) ? (string) $frame['line'] : null;
        $class = isset($frame['class']) && is_scalar($frame['class']) ? (string) $frame['class'] : null;
        $function = isset($frame['function']) && is_scalar($frame['function']) ? (string) $frame['function'] : null;
        $type = isset($frame['type']) && is_scalar($frame['type']) ? (string) $frame['type'] : null;

        if (! $file && ! $class && ! $function) {
            return null;
        }

        $isCore = $this->isCoreFrame($file, $class);

        return [
            'file' => $file,
            'relative_file' => $this->relativePath($file),
            'line' => $line,
            'class' => $class,
            'function' => $function,
            'call' => $this->callFor($class, $type, $function),
            'is_core' => $isCore,
            'source' => $isCore ? null : $this->sourcePreview($file, $line),
        ];
    }

    private function callFor(?string $class, ?string $type, ?string $function): ?string
    {
        if (! $class && ! $function) {
            return null;
        }

        if (! $function) {
            return $class;
        }

        return ($class ? $class.($type ?: '::') : '').$function.'()';
    }

    private function relativePath(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $normalized = str_replace('\\', '/', $path);

        foreach ([base_path(), public_path()] as $root) {
            $root = rtrim(str_replace('\\', '/', (string) $root), '/').'/';

            if (str_starts_with($normalized, $root)) {
                return ltrim(substr($normalized, strlen($root)), '/');
            }
        }

        return $path;
    }

    private function isCoreFrame(?string $file, ?string $class): bool
    {
        $path = strtolower(str_replace('\\', '/', (string) $file));
        $class = strtolower((string) $class);

        return Str::contains($path, [
            '/vendor/',
            '/vendor/laravel/framework/',
            '/vendor/symfony/',
            '/vendor/composer/',
            '/bootstrap/cache/',
            '/public/index.php',
        ]) || Str::startsWith($class, [
            'illuminate\\',
            'laravel\\',
            'symfony\\',
            'composer\\',
        ]);
    }

    private function isErrorTrailEntry(object $entry): bool
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

    private function sourcePreview(?string $file, ?string $line): ?array
    {
        $lineNumber = filter_var($line, FILTER_VALIDATE_INT);

        if (! $file || ! $lineNumber || ! is_file($file) || ! is_readable($file)) {
            return null;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES);

        if (! is_array($lines) || ! array_key_exists($lineNumber - 1, $lines)) {
            return null;
        }

        $snippetLines = $this->lineWindow($lines, $lineNumber, 3);
        $expandedLines = pathinfo($file, PATHINFO_EXTENSION) === 'php'
            ? $this->methodWindow($lines, $lineNumber)
            : null;

        return [
            'language' => pathinfo($file, PATHINFO_EXTENSION) === 'php' ? 'php' : 'text',
            'lines' => $this->highlightLines($snippetLines, $lineNumber),
            'expanded_lines' => $this->highlightLines($expandedLines ?: $this->lineWindow($lines, $lineNumber, 25), $lineNumber),
        ];
    }

    private function methodWindow(array $lines, int $lineNumber): ?array
    {
        for ($index = $lineNumber - 1; $index >= 0; $index--) {
            if (preg_match('/\b(function|fn)\b/', $lines[$index]) !== 1) {
                continue;
            }

            $start = $index + 1;
            $end = $this->methodEndLine($lines, $start);

            if ($end === null || $lineNumber < $start || $lineNumber > $end) {
                continue;
            }

            $start = $this->methodStartWithDocblock($lines, $start);
            $start = max(1, $start - 2);
            $end = min(count($lines), $end + 2);
            $window = [];

            for ($current = $start; $current <= $end; $current++) {
                $window[$current] = $lines[$current - 1];
            }

            return $window;
        }

        return null;
    }

    private function methodStartWithDocblock(array $lines, int $start): int
    {
        $cursor = $start - 1;

        while ($cursor > 1 && trim($lines[$cursor - 2]) === '') {
            $cursor--;
        }

        if ($cursor > 1 && str_starts_with(trim($lines[$cursor - 2]), '*/')) {
            $cursor--;

            while ($cursor > 1) {
                $cursor--;

                if (str_starts_with(trim($lines[$cursor - 1]), '/**')) {
                    return $cursor;
                }
            }
        }

        return $start;
    }

    private function methodEndLine(array $lines, int $start): ?int
    {
        $balance = 0;
        $seenBlock = false;

        for ($current = $start; $current <= count($lines); $current++) {
            $line = $lines[$current - 1];
            $balance += substr_count($line, '{');
            $balance -= substr_count($line, '}');

            if (str_contains($line, '{')) {
                $seenBlock = true;
            }

            if ($seenBlock && $balance <= 0) {
                return $current;
            }

            if (! $seenBlock && str_contains($line, ';')) {
                return $current;
            }
        }

        return null;
    }

    private function lineWindow(array $lines, int $lineNumber, int $context): array
    {
        $start = max(1, $lineNumber - $context);
        $end = min(count($lines), $lineNumber + $context);
        $window = [];

        for ($current = $start; $current <= $end; $current++) {
            $window[$current] = $lines[$current - 1];
        }

        return $window;
    }

    private function highlightLines(array $lines, int $targetLine): array
    {
        $highlighted = [];

        foreach ($lines as $number => $code) {
            $highlighted[] = [
                'number' => $number,
                'code' => $this->highlightPhpLine((string) $code),
                'is_target' => $number === $targetLine,
            ];
        }

        return $highlighted;
    }

    private function highlightPhpLine(string $line): string
    {
        [$code, $comment] = array_pad(explode('//', $line, 2), 2, null);
        $segments = preg_split('/((?:"(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\'))/', $code, -1, PREG_SPLIT_DELIM_CAPTURE);

        $highlighted = collect($segments ?: [$code])
            ->map(function (string $segment): string {
                $escaped = e($segment);

                if (preg_match('/^(&quot;.*&quot;|&#039;.*&#039;)$/', $escaped) === 1) {
                    return '<span class="syntax-string">'.$escaped.'</span>';
                }

                $escaped = preg_replace('/\b(class|function|fn|return|throw|new|if|else|elseif|foreach|for|while|match|try|catch|private|protected|public|static|readonly|use|namespace)\b/', '<span class="syntax-keyword">$1</span>', $escaped) ?? $escaped;

                return preg_replace('/(\$[A-Za-z_][A-Za-z0-9_]*)/', '<span class="syntax-variable">$1</span>', $escaped) ?? $escaped;
            })
            ->implode('');

        if ($comment !== null) {
            $highlighted .= '<span class="syntax-comment">//'.e($comment).'</span>';
        }

        return $highlighted;
    }
}
