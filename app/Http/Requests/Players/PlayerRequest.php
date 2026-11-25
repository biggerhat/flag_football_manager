<?php

namespace App\Http\Requests\Players;

use Illuminate\Foundation\Http\FormRequest;

class PlayerRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'number' => ['nullable', 'string', 'max:4'],
            'position' => ['nullable', 'string', 'max:8'],
            'notes' => ['nullable', 'string', 'max:300'],
            'active' => ['boolean'],
        ];
    }
}
