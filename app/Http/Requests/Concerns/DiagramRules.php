<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rule;

trait DiagramRules
{
    /**
     * Validation for the diagram header and its players, shared by plays and formations.
     *
     * @return array<string, mixed>
     */
    protected function diagramPlayerRules(): array
    {
        return [
            'diagram' => ['required', 'array'],
            'diagram.schema_version' => ['required', 'integer'],
            'diagram.los' => ['required', 'numeric', 'between:0,1'],

            'diagram.players' => ['required', 'array', 'max:30'],
            'diagram.players.*.id' => ['required', 'string', 'max:40'],
            'diagram.players.*.type' => ['required', Rule::in(['center', 'quarterback', 'receiver', 'defender'])],
            'diagram.players.*.label' => ['required', 'string', 'max:6'],
            'diagram.players.*.x' => ['required', 'numeric', 'between:0,1'],
            'diagram.players.*.y' => ['required', 'numeric', 'between:0,1'],
            'diagram.players.*.color' => ['required', 'string', 'max:9'],
        ];
    }

    /**
     * Everything a drawn diagram needs: the header, players, routes and notes.
     *
     * @return array<string, mixed>
     */
    protected function diagramFullRules(): array
    {
        return [
            ...$this->diagramPlayerRules(),

            'diagram.routes' => ['present', 'array', 'max:60'],
            'diagram.routes.*.id' => ['required', 'string', 'max:40'],
            'diagram.routes.*.player_id' => ['required', 'string', 'max:40'],
            'diagram.routes.*.kind' => ['required', Rule::in(['route', 'run', 'handoff', 'block'])],
            'diagram.routes.*.name' => ['nullable', 'string', 'max:30'],
            'diagram.routes.*.points' => ['required', 'array', 'min:2', 'max:40'],
            'diagram.routes.*.points.*.x' => ['required', 'numeric', 'between:0,1'],
            'diagram.routes.*.points.*.y' => ['required', 'numeric', 'between:0,1'],
            'diagram.routes.*.style.dash' => ['required', Rule::in(['solid', 'dashed', 'dotted'])],
            'diagram.routes.*.style.end' => ['required', Rule::in(['arrow', 'block', 'none'])],
            'diagram.routes.*.style.color' => ['required', 'string', 'max:9'],

            'diagram.annotations' => ['present', 'array', 'max:30'],
            'diagram.annotations.*.id' => ['required', 'string', 'max:40'],
            'diagram.annotations.*.text' => ['required', 'string', 'max:80'],
            'diagram.annotations.*.x' => ['required', 'numeric', 'between:0,1'],
            'diagram.annotations.*.y' => ['required', 'numeric', 'between:0,1'],
        ];
    }
}
