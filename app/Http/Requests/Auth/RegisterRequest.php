<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255', 'regex:/^[\pL\pM\s\.\'\-]+$/u'],
            'email' => [
                'required', 'string', 'lowercase', 'email:rfc', 'max:255',
                'not_regex:/\.invalid$/i', 'unique:'.User::class,
            ],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }
}
