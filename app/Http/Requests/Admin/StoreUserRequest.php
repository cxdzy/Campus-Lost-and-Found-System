<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'matric_number' => ['required', 'string', 'max:64', 'unique:users,matric_number'],
            'role' => ['required', 'in:Admin,Security'],
            'telegram_chat_id' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:6'],
        ];
    }
}
