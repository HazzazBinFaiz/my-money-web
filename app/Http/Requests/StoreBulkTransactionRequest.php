<?php

namespace App\Http\Requests;

use App\Enums\AccountStatus;
use App\Enums\CategoryStatus;
use App\Enums\CategoryType;
use App\Enums\TransactionType;
use App\Lib\MathExpression;
use App\Lib\Util;
use App\Models\Account;
use App\Models\Category;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class StoreBulkTransactionRequest extends FormRequest
{
    /** @var array<int, int>|null */
    private ?array $activeAccountIds = null;

    /** @var array<int, CategoryType>|null */
    private ?array $activeCategoryTypes = null;

    /**
     * Rows the user never filled in are dropped rather than reported as errors.
     */
    protected function prepareForValidation(): void
    {
        $rows = collect($this->input('rows', []))
            ->reject(fn ($row) => $this->isBlankRow((array) $row))
            ->values()
            ->all();

        $this->merge(['rows' => $rows]);
    }

    public function rules(): array
    {
        return [
            'rows' => ['required', 'array', 'min:1', 'max:200'],
            'rows.*.type' => ['required', 'integer', Rule::enum(TransactionType::class)],
            'rows.*.account_id' => ['required', 'integer'],
            'rows.*.to_account_id' => ['nullable', 'integer'],
            'rows.*.category_id' => ['nullable', 'integer'],
            'rows.*.amount' => ['required', 'string', 'max:100'],
            'rows.*.charge' => ['nullable', 'string', 'max:100'],
            'rows.*.note' => ['nullable', 'string', 'max:1000'],
            'rows.*.date' => ['required', 'date_format:Y-m-d'],
            'rows.*.time' => ['required', 'date_format:H:i'],
        ];
    }

    public function messages(): array
    {
        return [
            'rows.required' => 'Add at least one row before saving.',
            'rows.*.account_id.required' => 'Pick an account.',
            'rows.*.amount.required' => 'Enter an amount.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                foreach ((array) $this->input('rows', []) as $index => $row) {
                    $this->validateRow($validator, $index, (array) $row);
                }
            },
        ];
    }

    /**
     * Rows shaped for the recorder, once validation has passed.
     *
     * @return list<array{type: TransactionType, account_id: int, to_account_id: ?int, category_id: ?int, amount: int, charge: int, note: ?string, occurred_at: CarbonImmutable}>
     */
    public function transactionRows(): array
    {
        return collect($this->input('rows', []))
            ->map(function (array $row) {
                $type = TransactionType::from((int) $row['type']);
                $charge = trim((string) ($row['charge'] ?? ''));

                return [
                    'type' => $type,
                    'account_id' => (int) $row['account_id'],
                    'to_account_id' => $type === TransactionType::Transfer ? (int) $row['to_account_id'] : null,
                    'category_id' => $type === TransactionType::Transfer ? null : (int) $row['category_id'],
                    'amount' => Util::toMinorUnits(MathExpression::evaluate((string) $row['amount'])),
                    'charge' => $charge === '' ? 0 : Util::toMinorUnits(MathExpression::evaluate($charge)),
                    'note' => $row['note'] ?? null,
                    'occurred_at' => CarbonImmutable::createFromFormat('Y-m-d H:i', $row['date'].' '.$row['time']),
                ];
            })
            ->all();
    }

    private function validateRow(Validator $validator, int $index, array $row): void
    {
        if ($validator->errors()->hasAny([
            "rows.$index.type", "rows.$index.account_id", "rows.$index.amount",
        ])) {
            return;
        }

        $type = TransactionType::tryFrom((int) Arr::get($row, 'type'));
        $accountId = (int) Arr::get($row, 'account_id');
        $toAccountId = (int) Arr::get($row, 'to_account_id');
        $categoryId = (int) Arr::get($row, 'category_id');

        if (! in_array($accountId, $this->activeAccountIds(), true)) {
            $validator->errors()->add("rows.$index.account_id", 'Pick an active account.');
        }

        if ($type === TransactionType::Transfer) {
            if (! in_array($toAccountId, $this->activeAccountIds(), true)) {
                $validator->errors()->add("rows.$index.to_account_id", 'Pick an active destination account.');
            } elseif ($toAccountId === $accountId) {
                $validator->errors()->add("rows.$index.to_account_id", 'Transfer needs two different accounts.');
            }
        } else {
            $expected = $type === TransactionType::Income ? CategoryType::Income : CategoryType::Expense;

            if (($this->activeCategoryTypes()[$categoryId] ?? null) !== $expected) {
                $validator->errors()->add("rows.$index.category_id", 'Pick an active '.strtolower($expected->label()).' category.');
            }
        }

        $amount = MathExpression::tryEvaluate((string) Arr::get($row, 'amount', ''));

        if ($amount === null) {
            $validator->errors()->add("rows.$index.amount", 'Not a valid amount or expression.');
        } elseif (Util::toMinorUnits($amount) <= 0) {
            $validator->errors()->add("rows.$index.amount", 'Amount must be greater than zero.');
        }

        $charge = trim((string) Arr::get($row, 'charge', ''));

        if ($charge !== '') {
            $value = MathExpression::tryEvaluate($charge);

            if ($value === null || Util::toMinorUnits($value) < 0) {
                $validator->errors()->add("rows.$index.charge", 'Not a valid charge.');
            }
        }
    }

    private function isBlankRow(array $row): bool
    {
        $filled = collect(['account_id', 'to_account_id', 'category_id', 'amount', 'note'])
            ->contains(fn (string $key) => trim((string) ($row[$key] ?? '')) !== '');

        if ($filled) {
            return false;
        }

        // The charge column defaults to 0, so only a real charge counts as used.
        $charge = trim((string) ($row['charge'] ?? ''));

        return $charge === '' || (is_numeric($charge) && (float) $charge === 0.0);
    }

    /**
     * @return array<int, int>
     */
    private function activeAccountIds(): array
    {
        return $this->activeAccountIds ??= Account::where('status', AccountStatus::Active)
            ->pluck('id')
            ->all();
    }

    /**
     * @return array<int, CategoryType>
     */
    private function activeCategoryTypes(): array
    {
        return $this->activeCategoryTypes ??= Category::where('status', CategoryStatus::Active)
            ->pluck('type', 'id')
            ->all();
    }
}
