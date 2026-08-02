<?php

namespace App\Http\Requests\Concerns;

use App\Enums\ImageType;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

trait ValidatesImageOwnership
{
    /**
     * Image must be of the expected type and either owned by the user or shared.
     */
    protected function usableImageRule(ImageType $type): Exists
    {
        $userId = $this->user()?->id;

        return Rule::exists('images', 'id')
            ->where('type', $type->value)
            ->where(function ($query) use ($userId) {
                $query->where('user_id', $userId)->orWhereNull('user_id');
            });
    }
}
