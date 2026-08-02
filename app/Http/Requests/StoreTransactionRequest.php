<?php

namespace App\Http\Requests;

use App\Enums\AccountStatus;
use App\Enums\CategoryStatus;
use App\Enums\CategoryType;
use App\Enums\TransactionType;
use App\Lib\MathExpression;
use App\Lib\Util;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransactionRequest extends FormRequest
{
    public function rules(): array
    {
        $userId = $this->user()?->id;

        $activeAccount = Rule::exists('accounts', 'id')
            ->where('user_id', $userId)
            ->where('status', AccountStatus::Active->value);

        return [
            'type' => ['required', 'integer', Rule::enum(TransactionType::class)],

            // Source for expense/transfer, destination for income.
            'account_id' => ['required', 'integer', $activeAccount],

            'to_account_id' => [
                Rule::requiredIf(fn () => $this->transactionType() === TransactionType::Transfer),
                'nullable', 'integer', 'different:account_id', $activeAccount,
            ],

            'category_id' => [
                Rule::requiredIf(fn () => $this->transactionType() !== TransactionType::Transfer),
                'nullable', 'integer',
                Rule::exists('categories', 'id')
                    ->where('user_id', $userId)
                    ->where('status', CategoryStatus::Active->value)
                    ->where('type', $this->categoryType()?->value),
            ],

            // Accepts plain numbers or arithmetic such as "(4 * 4)+54".
            'amount' => ['required', 'string', 'max:100'],

            'charge' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:1000'],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.exists' => 'Pick an active category matching the transaction type.',
            'to_account_id.different' => 'Transfer needs two different accounts.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->hasAny(['amount', 'charge'])) {
                    return;
                }

                $amount = MathExpression::tryEvaluate((string) $this->input('amount'));

                if ($amount === null) {
                    $validator->errors()->add('amount', 'That is not a valid amount or expression.');
                } elseif (Util::toMinorUnits($amount) <= 0) {
                    $validator->errors()->add('amount', 'Amount must be greater than zero.');
                }

                $charge = trim((string) $this->input('charge', ''));

                if ($charge !== '') {
                    $value = MathExpression::tryEvaluate($charge);

                    if ($value === null || Util::toMinorUnits($value) < 0) {
                        $validator->errors()->add('charge', 'That is not a valid charge.');
                    }
                }
            },
        ];
    }

    public function transactionType(): ?TransactionType
    {
        return TransactionType::tryFrom((int) $this->input('type'));
    }

    /**
     * Income transactions take income categories, expenses take expense ones.
     */
    public function categoryType(): ?CategoryType
    {
        return match ($this->transactionType()) {
            TransactionType::Income => CategoryType::Income,
            TransactionType::Expense => CategoryType::Expense,
            default => null,
        };
    }

    public function amountInMinorUnits(): int
    {
        return Util::toMinorUnits(MathExpression::evaluate((string) $this->input('amount')));
    }

    public function chargeInMinorUnits(): int
    {
        $charge = trim((string) $this->input('charge', ''));

        return $charge === '' ? 0 : Util::toMinorUnits(MathExpression::evaluate($charge));
    }

    /**
     * The entered date and time become the transaction's created_at.
     */
    public function occurredAt(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat(
            'Y-m-d H:i',
            $this->input('date').' '.$this->input('time'),
        );
    }
}
