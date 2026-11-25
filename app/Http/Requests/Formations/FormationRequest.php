<?php

namespace App\Http\Requests\Formations;

use App\Http\Requests\Concerns\DiagramRules;
use Illuminate\Foundation\Http\FormRequest;

class FormationRequest extends FormRequest
{
    use DiagramRules;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:255'],
            ...$this->diagramPlayerRules(),
            'diagram.players' => ['required', 'array', 'min:1', 'max:30'],
        ];
    }

    /**
     * Validated attributes with a formation-shaped diagram: players only, never routes.
     *
     * @return array<string, mixed>
     */
    public function formation(): array
    {
        $data = $this->validated();

        $data['diagram'] = [
            'schema_version' => $data['diagram']['schema_version'],
            'los' => $data['diagram']['los'],
            'players' => $data['diagram']['players'],
            'routes' => [],
            'annotations' => [],
        ];

        return $data;
    }
}
