<?php

namespace App\Http\Requests\Plays;

use App\Http\Requests\Concerns\DiagramRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlayRequest extends FormRequest
{
    use DiagramRules;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'playbook_id' => ['nullable', Rule::exists('playbooks', 'id')->where('team_id', $this->user()?->activeTeam()->id)],
            'name' => ['required', 'string', 'max:120'],
            'formation' => ['nullable', 'string', 'max:60'],
            'category' => ['required', Rule::in(['run', 'pass', 'trick', 'defense', 'other'])],
            'difficulty' => ['required', Rule::in(['easy', 'medium', 'hard'])],
            'beginner_friendly' => ['boolean'],
            'tags' => ['nullable', 'array', 'max:10'],
            'tags.*' => ['string', 'max:30'],
            'folder' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:5000'],

            ...$this->diagramFullRules(),
        ];
    }
}
