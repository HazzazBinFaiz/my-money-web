<?php

namespace App\Http\Requests;

use App\Enums\ImageType;
use App\Http\Requests\Concerns\ValidatesImageOwnership;
use Illuminate\Foundation\Http\FormRequest;

class StoreAccountRequest extends FormRequest
{
    use ValidatesImageOwnership;

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'initial_amount' => ['required', 'numeric'],
            'icon_id' => ['nullable', 'integer', $this->usableImageRule(ImageType::Icon)],
        ];
    }
}
