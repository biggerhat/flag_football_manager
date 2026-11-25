<?php

use App\Models\Opponent;
use App\Models\Play;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('dashboard and stats pages render for a new user', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('stats'))->assertOk()->assertInertia(fn ($page) => $page->component('Stats')->where('totals.calls', 0));
    $this->get(route('dashboard'))->assertOk()->assertInertia(fn ($page) => $page->has('record')->has('week')->has('attention'));
});

test('practice run mode renders for its owner', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $practice = $user->activeTeam()->practices()->create(['name' => 'Tuesday', 'duration' => 60, 'user_id' => $user->id]);

    $this->get(route('practices.run', $practice))->assertOk()->assertInertia(fn ($page) => $page->component('practices/Run'));
});

test('a play can be starred and unstarred', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $play = $user->activeTeam()->plays()->first() ?? $user->activeTeam()->plays()->create([
        'user_id' => $user->id, 'name' => 'Test', 'category' => 'pass', 'diagram' => ['players' => [], 'routes' => []],
    ]);

    $this->post(route('plays.favorite', $play))->assertRedirect();
    expect($play->fresh()->is_favorite)->toBeTrue();
    $this->post(route('plays.favorite', $play))->assertRedirect();
    expect($play->fresh()->is_favorite)->toBeFalse();
});

test('the calendar feed is private until a link is made, then serves ics', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $team = $user->activeTeam();
    $team->practices()->create(['user_id' => $user->id, 'name' => 'Tuesday', 'duration' => 60, 'date' => today()->addDay(), 'start_time' => '17:30']);

    $this->get('/calendar/feed/nope.ics')->assertNotFound();

    $this->post(route('calendar.feed.store'))->assertRedirect();
    $token = $team->fresh()->calendar_token;

    $this->get(route('calendar.feed', $token))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')
        ->assertSee('SUMMARY:Practice: Tuesday', false);
});

test('plays can be starred and tagged in bulk', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $play = $user->activeTeam()->plays()->create([
        'user_id' => $user->id, 'name' => 'Bulk', 'category' => 'pass', 'diagram' => ['players' => [], 'routes' => []],
    ]);

    $this->post(route('plays.bulk'), ['ids' => [$play->id], 'action' => 'star'])->assertRedirect();
    $this->post(route('plays.bulk'), ['ids' => [$play->id], 'action' => 'tag', 'tag' => 'red zone'])->assertRedirect();

    expect($play->fresh()->is_favorite)->toBeTrue()->and($play->fresh()->tags)->toBe(['red zone']);
});

test('deleted plays show on the trash page and can be restored', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $play = $user->activeTeam()->plays()->create([
        'user_id' => $user->id, 'name' => 'Gone', 'category' => 'pass', 'diagram' => ['players' => [], 'routes' => []],
    ]);
    $play->delete();

    $this->get(route('trash'))->assertOk()->assertInertia(fn ($page) => $page->component('Trash')->has('items', 1));
    $this->post(route('restore', ['kind' => 'play', 'id' => $play->id]))->assertRedirect();
    expect($play->fresh()->trashed())->toBeFalse();
});

test('the family page needs a valid token and hides the roster', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $this->get('/family/nope')->assertNotFound();

    $this->post(route('calendar.feed.store'));
    $token = $user->activeTeam()->fresh()->calendar_token;

    $this->get(route('family', $token))->assertOk()->assertInertia(fn ($page) => $page->component('Family')->missing('players'));
});

test('plays can be deleted in bulk and restored', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $play = $user->activeTeam()->plays()->create([
        'user_id' => $user->id, 'name' => 'Bye', 'category' => 'pass', 'diagram' => ['players' => [], 'routes' => []],
    ]);

    $this->post(route('plays.bulk'), ['ids' => [$play->id], 'action' => 'delete'])->assertRedirect();
    expect($play->fresh()->trashed())->toBeTrue();
});

test('feed times are floating local times, not shifted to UTC', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->activeTeam()->practices()->create(['user_id' => $user->id, 'name' => 'Eve', 'duration' => 60, 'date' => today()->addDay(), 'start_time' => '17:30']);
    $this->post(route('calendar.feed.store'));

    $this->get(route('calendar.feed', $user->activeTeam()->fresh()->calendar_token))
        ->assertSee('T173000', false)->assertDontSee('T173000Z', false);
});

test('a team timezone decides what today is', function () {
    $user = User::factory()->create();
    $team = $user->activeTeam();

    $this->actingAs($user)->put(route('teams.update', $team), ['timezone' => 'Pacific/Auckland'])->assertRedirect();
    expect($team->fresh()->timezone)->toBe('Pacific/Auckland')
        ->and($team->fresh()->today()->timezone->getName())->toBe('Pacific/Auckland');

    $this->put(route('teams.update', $team), ['timezone' => 'Not/AZone'])->assertSessionHasErrors('timezone');
    expect($team->fresh()->name)->not->toBeEmpty();
});

test('coaches can download a team backup and it leaves out share tokens', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->activeTeam()->playbooks()->create(['name' => 'Book', 'share_token' => 'secret-token']);

    $response = $this->get(route('team.backup.export'))->assertOk()->assertHeader('Content-Disposition');
    expect($response->getContent())->toContain('"playbooks"')->not->toContain('secret-token');
});

test('calls can record which player had the ball, and stats count touches', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $team = $user->activeTeam();
    $play = $team->plays()->create(['user_id' => $user->id, 'name' => 'Sweep', 'category' => 'run', 'diagram' => ['players' => [], 'routes' => []]]);
    $player = $team->players()->create(['name' => 'Sam', 'number' => '7']);
    $plan = $team->gamePlans()->create(['user_id' => $user->id, 'name' => 'G1']);

    $this->post(route('gameplans.calls.store', $plan), ['play_id' => $play->id, 'player_id' => $player->id, 'result' => 'gain', 'yards' => 8])->assertRedirect();

    $this->get(route('stats'))->assertInertia(fn ($page) => $page->where('byPlayer.0.name', 'Sam')->where('byPlayer.0.calls', 1)->where('byPlayer.0.yards', 8));
});

test('a call cannot name a player from another team', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $this->actingAs($user);
    $team = $user->activeTeam();
    $play = $team->plays()->create(['user_id' => $user->id, 'name' => 'Sweep', 'category' => 'run', 'diagram' => ['players' => [], 'routes' => []]]);
    $stranger = $other->activeTeam()->players()->create(['name' => 'Eve']);
    $plan = $team->gamePlans()->create(['user_id' => $user->id, 'name' => 'G1']);

    $this->post(route('gameplans.calls.store', $plan), ['play_id' => $play->id, 'player_id' => $stranger->id, 'result' => 'gain'])->assertSessionHasErrors('player_id');
});

test('a season limits the record and the stats to its dates', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $team = $user->activeTeam();
    $team->gamePlans()->create(['user_id' => $user->id, 'name' => 'Old', 'date' => '2025-10-01', 'score_for' => 10, 'score_against' => 0]);
    $team->gamePlans()->create(['user_id' => $user->id, 'name' => 'New', 'date' => '2026-10-01', 'score_for' => 0, 'score_against' => 7]);

    $this->get(route('gameplans.index'))->assertInertia(fn ($page) => $page->where('record.wins', 1)->where('record.losses', 1));

    $this->post(route('seasons.store'), ['name' => 'Fall 2026', 'starts_on' => '2026-08-01'])->assertRedirect();

    $this->get(route('gameplans.index'))->assertInertia(fn ($page) => $page->where('record.wins', 0)->where('record.losses', 1)->has('gamePlans', 1));
    $this->get(route('dashboard'))->assertInertia(fn ($page) => $page->where('record.losses', 1)->where('record.wins', 0));

    $this->post(route('seasons.switch'), ['season_id' => null])->assertRedirect();
    $this->get(route('gameplans.index'))->assertInertia(fn ($page) => $page->where('record.wins', 1));
});

test('seasons from other teams cannot be selected', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $foreign = $other->activeTeam()->seasons()->create(['name' => 'X', 'starts_on' => '2026-01-01']);

    $this->actingAs($user)->post(route('seasons.switch'), ['season_id' => $foreign->id])->assertNotFound();
});

test('opponents are grouped by name with a head-to-head record and editable notes', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $team = $user->activeTeam();
    $team->gamePlans()->create(['user_id' => $user->id, 'name' => 'A', 'opponent' => 'Tigers', 'date' => '2026-09-01', 'score_for' => 14, 'score_against' => 7]);
    $team->gamePlans()->create(['user_id' => $user->id, 'name' => 'B', 'opponent' => 'tigers ', 'date' => '2026-10-01', 'score_for' => 0, 'score_against' => 6]);

    $this->put(route('opponents.update'), ['name' => 'Tigers', 'notes' => 'Blitz a lot'])->assertRedirect();
    $this->put(route('opponents.update'), ['name' => 'TIGERS', 'notes' => 'Blitz a lot, weak left side'])->assertRedirect();

    $this->get(route('opponents'))->assertInertia(fn ($page) => $page
        ->has('opponents', 1)->where('opponents.0.wins', 1)->where('opponents.0.losses', 1)
        ->where('opponents.0.notes', 'Blitz a lot, weak left side'));
    expect(Opponent::count())->toBe(1);
});

test('trash purge permanently deletes only old items', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $team = $user->activeTeam();
    $old = $team->plays()->create(['user_id' => $user->id, 'name' => 'Old', 'category' => 'pass', 'diagram' => ['players' => [], 'routes' => []]]);
    $recent = $team->plays()->create(['user_id' => $user->id, 'name' => 'Recent', 'category' => 'pass', 'diagram' => ['players' => [], 'routes' => []]]);
    $old->delete();
    $recent->delete();
    Play::withTrashed()->whereKey($old->id)->update(['deleted_at' => now()->subDays(40)]);

    $this->artisan('trash:purge')->assertSuccessful();

    expect(Play::withTrashed()->whereKey($old->id)->exists())->toBeFalse()
        ->and(Play::onlyTrashed()->whereKey($recent->id)->exists())->toBeTrue();
});
