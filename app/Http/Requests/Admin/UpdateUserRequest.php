<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->route('user');

        return $user instanceof User
            ? ($this->user()?->can('update', $user) ?? false)
            : false;
    }

    public function rules(): array
    {
        $user = $this->route('user');
        $userId = $user instanceof User ? $user->id : null;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'matric_number' => ['sometimes', 'string', 'max:64', Rule::unique('users', 'matric_number')->ignore($userId)],
            'role' => ['sometimes', 'in:Admin,Security'],
            'telegram_chat_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'password' => ['sometimes', 'nullable', 'string', 'min:6'],
        ];
    }
}
