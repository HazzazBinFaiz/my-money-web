<?php

namespace App\Http\Requests;

use App\Enums\AccountStatus;
use App\Enums\ImageType;
use App\Http\Requests\Concerns\ValidatesImageOwnership;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountRequest extends FormRequest
{
    use ValidatesImageOwnership;

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'initial_amount' => ['required', 'numeric'],
            'status' => ['required', Rule::enum(AccountStatus::class)],
            'icon_id' => ['nullable', 'integer', $this->usableImageRule(ImageType::Account)],
        ];
    }
}
