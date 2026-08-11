<?php

namespace App\Support;

use App\Enums\CategoryType;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\Request;

/**
 * The account and category a report is narrowed to, if any.
 *
 * An account is matched on the side it would actually sit: money out of it for
 * expense, money into it for income. Matching either side would count a
 * transfer's arrival as income, which it is not.
 */
class ReportFilter
{
    public function __construct(
        public readonly ?int $accountId = null,
        public readonly ?int $categoryId = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $filters = $request->validate([
            'account' => ['nullable', 'integer'],
            'category' => ['nullable', 'integer'],
        ]);

        return new self(
            $filters['account'] ?? null,
            $filters['category'] ?? null,
        );
    }

    public function isEmpty(): bool
    {
        return $this->accountId === null && $this->categoryId === null;
    }

    /**
     * The filter as query string parameters, for links that must keep it.
     *
     * @return array<string, int>
     */
    public function toQuery(): array
    {
        return array_filter([
            'account' => $this->accountId,
            'category' => $this->categoryId,
        ]);
    }

    public function apply(Builder $query, CategoryType $side): Builder
    {
        $column = $side === CategoryType::Income ? 'to_account_id' : 'from_account_id';

        return $query
            ->when($this->accountId, fn (Builder $inner, int $id) => $inner->where($column, $id))
            ->when($this->categoryId, fn (Builder $inner, int $id) => $inner->where('category_id', $id));
    }
}
