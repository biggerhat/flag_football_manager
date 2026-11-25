<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Abort unless the model belongs to the user's active team.
     */
    protected function ensureOwned(Request $request, Model $model): void
    {
        abort_unless($request->user() && $model->getAttribute('team_id') === $request->user()->activeTeam()->id, 404);
    }
}
