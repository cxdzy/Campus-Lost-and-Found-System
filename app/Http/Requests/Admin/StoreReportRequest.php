<?php

namespace App\Http\Requests\Admin;

use App\Models\Item;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Item::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'type' => ['required', Rule::in(['Lost', 'Found'])],
            'title_description' => ['required', 'string', 'max:255'],
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
            'location_name' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(['Pending', 'Matched', 'Claimed'])],
            'image_path' => [
                Rule::requiredIf(fn () => $this->input('type') === 'Found' && ! $this->hasFile('image_file')),
                'nullable',
                'string',
                'max:255',
            ],
            'image_file' => [
                Rule::requiredIf(fn () => $this->input('type') === 'Found' && ! $this->filled('image_path')),
                'nullable',
                'file',
                'image',
                'max:20480',
            ],
        ];
    }
}
