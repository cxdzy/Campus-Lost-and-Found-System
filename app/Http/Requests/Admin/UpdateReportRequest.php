<?php

namespace App\Http\Requests\Admin;

use App\Models\Item;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $report = $this->route('report');

        return $report instanceof Item
            ? ($this->user()?->can('update', $report) ?? false)
            : false;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'type' => ['sometimes', Rule::in(['Lost', 'Found'])],
            'title_description' => ['sometimes', 'string', 'max:255'],
            'latitude' => ['sometimes', 'numeric'],
            'longitude' => ['sometimes', 'numeric'],
            'location_name' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(['Pending', 'Matched', 'Claimed', 'Returned'])],
            'image_path' => [
                'sometimes',
                Rule::requiredIf(fn () => $this->input('type') === 'Found' && ! $this->hasFile('image_file')),
                'nullable',
                'string',
                'max:255',
            ],
            'image_file' => [
                'sometimes',
                Rule::requiredIf(fn () => $this->input('type') === 'Found' && ! $this->filled('image_path')),
                'nullable',
                'file',
                'image',
                'max:5120',
            ],
        ];
    }
}
