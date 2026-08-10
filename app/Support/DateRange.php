<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * The slice of time a dashboard is looking at, plus the equivalent slice
 * immediately before it so figures can be shown as a change.
 */
class DateRange
{
    public const PRESETS = [
        'this_month' => 'This month',
        'last_month' => 'Last month',
        'last_30' => 'Last 30 days',
        'this_year' => 'This year',
        'all' => 'All time',
        'custom' => 'Custom',
    ];

    public function __construct(
        public readonly string $preset,
        public readonly ?CarbonImmutable $start,
        public readonly ?CarbonImmutable $end,
    ) {}

    public static function fromRequest(?string $preset, ?string $from, ?string $to): self
    {
        $today = CarbonImmutable::today();

        if ($from || $to) {
            $start = $from ? CarbonImmutable::parse($from)->startOfDay() : $today->startOfMonth();
            $end = $to ? CarbonImmutable::parse($to)->endOfDay() : $today->endOfDay();

            // A backwards range is a typo, not an empty result.
            return $start->greaterThan($end)
                ? new self('custom', $end->startOfDay(), $start->endOfDay())
                : new self('custom', $start, $end);
        }

        return match ($preset) {
            'last_month' => new self($preset, $today->subMonth()->startOfMonth(), $today->subMonth()->endOfMonth()),
            'last_30' => new self($preset, $today->subDays(29)->startOfDay(), $today->endOfDay()),
            'this_year' => new self($preset, $today->startOfYear(), $today->endOfYear()),
            'all' => new self($preset, null, null),
            default => new self('this_month', $today->startOfMonth(), $today->endOfMonth()),
        };
    }

    public function label(): string
    {
        if ($this->preset === 'custom' && $this->start && $this->end) {
            return $this->start->isoFormat('D MMM YYYY').' – '.$this->end->isoFormat('D MMM YYYY');
        }

        return self::PRESETS[$this->preset] ?? self::PRESETS['this_month'];
    }

    public function isBounded(): bool
    {
        return $this->start !== null && $this->end !== null;
    }

    public function days(): int
    {
        return $this->isBounded() ? $this->start->diffInDays($this->end) + 1 : 0;
    }

    /**
     * Buckets of a day read well up to a couple of months; past that, months.
     */
    public function grouping(): string
    {
        return ! $this->isBounded() || $this->days() > 62 ? 'month' : 'day';
    }

    /**
     * The same length of time, ending where this range starts.
     */
    public function previous(): ?self
    {
        if (! $this->isBounded()) {
            return null;
        }

        $length = $this->days();

        return new self(
            $this->preset,
            $this->start->subDays($length),
            $this->start->subDay()->endOfDay(),
        );
    }

    /**
     * @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable}
     */
    public function bounds(): array
    {
        return [$this->start, $this->end];
    }
}
