<?php

namespace App\Http\Controllers;

use App\Models\Playbook;
use Inertia\Inertia;
use Inertia\Response;

class SharedPlaybookController extends Controller
{
    /**
     * Read-only view of a playbook for people without an account (parents, players).
     */
    public function show(string $token): Response
    {
        $playbook = Playbook::with('team')->where('share_token', $token)->firstOrFail();
        abort_unless($playbook->shareIsLive(), 404);

        return Inertia::render('playbooks/Packet', [
            ...$playbook->packet(),
            'shareUrl' => null,
            'public' => true,
        ]);
    }
}
