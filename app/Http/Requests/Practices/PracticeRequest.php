<?php

namespace App\Http\Requests\Practices;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PracticeRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->user();

        return [
            'name' => ['required', 'string', 'max:120'],
            'date' => ['nullable', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'duration' => ['required', 'integer', 'between:5,300'],
            'location' => ['nullable', 'string', 'max:120'],
            'objectives' => ['nullable', 'string', 'max:3000'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'review_notes' => ['nullable', 'string', 'max:3000'],

            'present' => ['nullable', 'array', 'max:200'],
            'present.*' => ['integer', Rule::exists('players', 'id')->where('team_id', $user?->activeTeam()->id)],

            'segments' => ['present', 'array', 'max:60'],
            'segments.*.title' => ['required', 'string', 'max:120'],
            'segments.*.duration' => ['required', 'integer', 'between:1,180'],
            'segments.*.notes' => ['nullable', 'string', 'max:2000'],
            'segments.*.drill_id' => ['nullable', Rule::exists('drills', 'id')->where('team_id', $user?->activeTeam()->id)],
            'segments.*.play_id' => ['nullable', Rule::exists('plays', 'id')->where('team_id', $user?->activeTeam()->id)],
        ];
    }
}
