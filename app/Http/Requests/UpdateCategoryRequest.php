<?php

namespace App\Http\Requests;

use App\Enums\CategoryStatus;
use App\Enums\ImageType;
use App\Http\Requests\Concerns\ValidatesImageOwnership;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    use ValidatesImageOwnership;

    /**
     * Type is fixed once the category exists, so it is not accepted here.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'integer', Rule::enum(CategoryStatus::class)],
            'icon_id' => ['nullable', 'integer', $this->usableImageRule(ImageType::Category)],
        ];
    }
}
