<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Validation\Rule;

trait ProfileValidationRules
{
    /**
     * Get the validation rules used to validate user profiles.
     *
     * @return array<string, array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>>
     */
    protected function profileRules(?int $userId = null): array
    {
        return [
            'nombres' => ['required', 'string', 'max:255'],
            'apellido_pat' => ['required', 'string', 'max:255'],
            'apellido_mat' => ['nullable', 'string', 'max:255'],
            'rut_numero' => ['nullable', 'digits_between:7,9'],
            'rut_dv' => ['nullable', 'string', 'max:1', 'regex:/^[0-9Kk]$/'],
            'fecha_nacimiento' => ['nullable', 'date'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'email' => $this->emailRules($userId),
        ];
    }

    /**
     * Get the validation rules used to validate user emails.
     *
     * @return array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>
     */
    protected function emailRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'email',
            'max:255',
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
        ];
    }
}
