<?php

namespace App\Http\Requests;

use App\Enums\ImageType;
use App\Http\Requests\Concerns\ValidatesImageOwnership;
use Illuminate\Foundation\Http\FormRequest;

class UpdateContactRequest extends FormRequest
{
    use ValidatesImageOwnership;

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'initial_amount' => ['required', 'numeric'],
            'picture_id' => ['nullable', 'integer', $this->usableImageRule(ImageType::Picture)],
        ];
    }
}
