<?php

namespace App\Http\Requests\GamePlans;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GamePlanRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'opponent' => ['nullable', 'string', 'max:120'],
            'date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'location' => ['nullable', 'string', 'max:120'],
            'kickoff' => ['nullable', 'date_format:H:i'],
            'score_for' => ['nullable', 'integer', 'between:0,999'],
            'score_against' => ['nullable', 'integer', 'between:0,999'],
            'play_ids' => ['present', 'array', 'max:100'],
            'play_ids.*' => ['integer', 'distinct', Rule::exists('plays', 'id')->where('team_id', $this->user()?->activeTeam()->id)],
        ];
    }
}
