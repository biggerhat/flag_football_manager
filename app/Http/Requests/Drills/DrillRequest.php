<?php

namespace App\Http\Requests\Drills;

use App\Http\Requests\Concerns\DiagramRules;
use Illuminate\Foundation\Http\FormRequest;

class DrillRequest extends FormRequest
{
    use DiagramRules;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', 'string', 'max:40'],
            'duration' => ['required', 'integer', 'between:1,180'],
            'players' => ['nullable', 'integer', 'between:1,50'],
            'equipment' => ['nullable', 'string', 'max:255'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'coaching_points' => ['nullable', 'string', 'max:5000'],

            // A drill diagram is optional; when drawn it is checked like a play's.
            ...($this->input('diagram') === null ? ['diagram' => ['nullable']] : $this->diagramFullRules()),
        ];
    }
}
