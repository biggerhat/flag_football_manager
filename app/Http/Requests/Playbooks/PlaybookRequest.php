<?php

namespace App\Http\Requests\Playbooks;

use Illuminate\Foundation\Http\FormRequest;

class PlaybookRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:200'],
        ];
    }
}
