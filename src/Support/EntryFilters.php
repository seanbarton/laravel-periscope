<?php

namespace TortoiseIT\LaravelPeriscope\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class EntryFilters
{
    public function __construct(
        public readonly ?string $type,
        public readonly ?string $query,
        public readonly ?string $tag,
        public readonly ?string $method,
        public readonly ?string $status,
        public readonly ?string $path,
        public readonly ?CarbonImmutable $from,
        public readonly ?CarbonImmutable $to,
        public readonly int $perPage,
        public readonly ?int $beforeSequence,
        public readonly bool $errorsOnly,
        public readonly array $types = [],
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $defaultFrom = now()->subHours((int) config('periscope.default_hours', 24))->startOfMinute();
        $maxPerPage = (int) config('periscope.max_per_page', 200);
        $perPage = min(max((int) $request->integer('per_page', config('periscope.per_page', 50)), 1), $maxPerPage);

        return new self(
            type: self::blankToNull($request->query('type')),
            query: self::blankToNull($request->query('q')),
            tag: self::blankToNull($request->query('tag')),
            method: self::blankToNull($request->query('method')),
            status: self::blankToNull($request->query('status')),
            path: self::blankToNull($request->query('path')),
            from: self::parseDate($request->query('from')) ?? CarbonImmutable::instance($defaultFrom),
            to: self::parseDate($request->query('to')),
            perPage: $perPage,
            beforeSequence: $request->integer('before') ?: null,
            errorsOnly: $request->boolean('errors'),
            types: self::arrayOfStrings($request->query('types', [])),
        );
    }

    public function activeCount(): int
    {
        return count(array_filter([
            $this->type,
            $this->query,
            $this->tag,
            $this->method,
            $this->status,
            $this->path,
            $this->from,
            $this->to,
            $this->errorsOnly,
            $this->type ? null : $this->types,
        ]));
    }

    private static function parseDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function blankToNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private static function arrayOfStrings(mixed $value): array
    {
        if (is_string($value)) {
            $value = [$value];
        }

        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->filter(fn ($item) => is_string($item) && trim($item) !== '')
            ->map(fn (string $item) => trim($item))
            ->unique()
            ->values()
            ->all();
    }
}
