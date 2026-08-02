<?php

namespace App\Http\Requests;

use App\Enums\CategoryType;
use App\Enums\ImageType;
use App\Http\Requests\Concerns\ValidatesImageOwnership;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    use ValidatesImageOwnership;

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(CategoryType::class)],
            'name' => ['required', 'string', 'max:255'],
            'icon_id' => ['nullable', 'integer', $this->usableImageRule(ImageType::Icon)],
        ];
    }
}
