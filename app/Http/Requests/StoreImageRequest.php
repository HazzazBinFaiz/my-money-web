<?php

namespace App\Http\Requests;

use App\Enums\ImageType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreImageRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // The crop tool always hands back a PNG blob.
            'image' => ['required', 'file', 'mimes:png', 'max:4096'],
            'type' => ['required', Rule::enum(ImageType::class)],
        ];
    }
}
