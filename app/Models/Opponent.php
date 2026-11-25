<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Scouting notes about a team you play. Game plans find it by the opponent's name.
 *
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property string|null $notes
 */
#[Fillable(['name', 'notes'])]
class Opponent extends Model {}
