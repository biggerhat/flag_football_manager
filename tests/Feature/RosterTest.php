<?php

use App\Models\Player;
use App\Models\Practice;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a team keeps a roster that other teams cannot touch', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('players.store'), ['name' => 'Marcus', 'number' => '7', 'position' => 'QB'])->assertRedirect();
    $player = Player::firstOrFail();

    $this->actingAs($user)->get(route('players.index'))->assertInertia(fn ($page) => $page->component('players/Index')->has('players', 1));
    $this->actingAs($user)->put(route('players.update', $player), ['name' => 'Marcus J', 'number' => '7', 'position' => 'QB', 'active' => true])->assertRedirect();
    expect($player->fresh()->name)->toBe('Marcus J');

    $other = User::factory()->create();
    $this->actingAs($other)->put(route('players.update', $player), ['name' => 'Hijack'])->assertNotFound();
    $this->actingAs($other)->delete(route('players.destroy', $player))->assertNotFound();

    $this->actingAs($user)->post(route('players.store'), ['name' => ''])->assertSessionHasErrors('name');
});

test('the lineup names the first active player at each position', function () {
    $user = User::factory()->create();
    $team = $user->activeTeam();
    $team->players()->create(['name' => 'Ava', 'number' => '12', 'position' => 'X']);
    $team->players()->create(['name' => 'Ben', 'position' => 'X']);
    $team->players()->create(['name' => 'Cy', 'position' => 'QB', 'active' => false]);
    $team->players()->create(['name' => 'Di']);

    expect($team->lineup())->toBe(['X' => '#12 Ava']);

    $this->actingAs($user)->post(route('plays.store'), playPayload());
    $this->actingAs($user)->get(route('print.plays', ['ids' => (string) $team->plays()->value('id')]))
        ->assertInertia(fn ($page) => $page->where('lineup', ['X' => '#12 Ava'])->where('team', $team->name));
});

test('practice attendance is saved, scoped to the team, and read-only for viewers', function () {
    $user = User::factory()->create();
    $team = $user->activeTeam();
    $ava = $team->players()->create(['name' => 'Ava']);
    $stranger = User::factory()->create()->activeTeam()->players()->create(['name' => 'Zed']);

    $payload = ['name' => 'Tuesday', 'duration' => 60, 'segments' => [], 'present' => [$ava->id]];
    $this->actingAs($user)->post(route('practices.store'), $payload)->assertRedirect();
    $practice = Practice::firstOrFail();
    expect($practice->attendees()->pluck('players.id')->all())->toBe([$ava->id]);

    $this->actingAs($user)->put(route('practices.update', $practice), [...$payload, 'present' => [$stranger->id]])->assertSessionHasErrors('present.0');

    $this->actingAs($user)->get(route('practices.edit', $practice))
        ->assertInertia(fn ($page) => $page->where('present', [$ava->id])->has('roster', 1));

    $viewer = User::factory()->create();
    $team->members()->attach($viewer, ['role' => Team::VIEWER]);
    $viewer->switchTeam($team);
    $this->actingAs($viewer)->post(route('players.store'), ['name' => 'Nope'])->assertForbidden();
});

test('a drill can carry an optional diagram, validated like a play', function () {
    $user = User::factory()->create();
    $drill = ['name' => 'Cone weave', 'category' => 'Routes', 'duration' => 8];

    $this->actingAs($user)->post(route('drills.store'), $drill)->assertRedirect();
    $this->actingAs($user)->post(route('drills.store'), [...$drill, 'name' => 'With picture', 'diagram' => diagramPayload()])->assertRedirect();

    expect($user->activeTeam()->drills()->where('name', 'Cone weave')->value('diagram'))->toBeNull()
        ->and($user->activeTeam()->drills()->where('name', 'With picture')->first()->diagram)->toEqual(diagramPayload());

    $bad = diagramPayload();
    $bad['players'][0]['x'] = 5;
    $this->actingAs($user)->post(route('drills.store'), [...$drill, 'diagram' => $bad])->assertSessionHasErrors('diagram.players.0.x');
});
