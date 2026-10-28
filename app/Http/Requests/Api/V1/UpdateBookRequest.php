<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Validation\Rule;

class UpdateBookRequest extends StoreBookRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('book')) ?? false;
    }

    public function rules(): array
    {
        $rules = parent::rules();
        $rules['isbn'] = [
            'nullable',
            'digits:13',
            Rule::unique('books', 'isbn')->ignore($this->route('book')),
        ];

        return $rules;
    }
}
