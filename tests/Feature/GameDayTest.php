<?php

use App\Models\GamePlan;
use App\Models\Play;
use App\Models\PlayCall;
use App\Models\Team;
use App\Models\User;
use App\Support\PlayStats;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function planWithPlay(User $user): array
{
    test()->actingAs($user)->post(route('plays.store'), playPayload());
    $play = Play::firstOrFail();
    test()->actingAs($user)->post(route('gameplans.store'), [
        'name' => 'Week 1', 'opponent' => 'Hawks', 'date' => '2026-11-14', 'kickoff' => '09:30', 'location' => 'Field 2',
        'play_ids' => [$play->id],
    ])->assertRedirect();

    return [GamePlan::firstOrFail(), $play];
}

test('calls are logged with a result and yards, and feed play stats', function () {
    $user = User::factory()->create();
    [$plan, $play] = planWithPlay($user);

    $this->actingAs($user)->post(route('gameplans.calls.store', $plan), ['play_id' => $play->id, 'result' => 'gain', 'yards' => 8])->assertRedirect();
    $this->actingAs($user)->post(route('gameplans.calls.store', $plan), ['play_id' => $play->id, 'result' => 'touchdown', 'yards' => 20]);
    $this->actingAs($user)->post(route('gameplans.calls.store', $plan), ['play_id' => $play->id, 'result' => 'incomplete']);

    expect(PlayStats::forTeam($user->activeTeam())[$play->id])->toBe(['calls' => 3, 'yards' => 28, 'touchdowns' => 1, 'success' => 2]);

    $this->actingAs($user)->get(route('gameplans.gameday', $plan))->assertInertia(fn ($page) => $page
        ->component('gameplans/GameDay')->has('plays', 1)->has('calls', 3));

    $this->actingAs($user)->get(route('playbooks.show', $play->playbook_id))
        ->assertInertia(fn ($page) => $page->where('plays.0.stats.calls', 3));

    $this->actingAs($user)->delete(route('gameplans.calls.destroy', [$plan, PlayCall::first()]))->assertRedirect();
    expect(PlayCall::count())->toBe(2);
});

test('call logging rejects unknown results and plays from other teams', function () {
    $user = User::factory()->create();
    [$plan, $play] = planWithPlay($user);
    $stranger = User::factory()->create();
    $this->actingAs($stranger)->post(route('plays.store'), playPayload());
    $foreign = Play::where('team_id', $stranger->activeTeam()->id)->firstOrFail();

    $this->actingAs($user)->post(route('gameplans.calls.store', $plan), ['play_id' => $play->id, 'result' => 'bogus'])->assertSessionHasErrors('result');
    $this->actingAs($user)->post(route('gameplans.calls.store', $plan), ['play_id' => $foreign->id, 'result' => 'gain'])->assertSessionHasErrors('play_id');
    $this->actingAs($stranger)->get(route('gameplans.gameday', $plan))->assertNotFound();
    $this->actingAs($stranger)->post(route('gameplans.calls.store', $plan), ['play_id' => $foreign->id, 'result' => 'gain'])->assertNotFound();
});

test('scores give a game its result', function () {
    $user = User::factory()->create();
    [$plan] = planWithPlay($user);

    expect($plan->result())->toBeNull();

    $this->actingAs($user)->put(route('gameplans.score', $plan), ['score_for' => 21, 'score_against' => 14])->assertRedirect();
    expect($plan->fresh()->result())->toBe('W');

    $this->actingAs($user)->put(route('gameplans.score', $plan), ['score_for' => 7, 'score_against' => 7]);
    expect($plan->fresh()->result())->toBe('T');

    $this->actingAs($user)->get(route('gameplans.index'))->assertInertia(fn ($page) => $page->where('gamePlans.0.result', 'T'));
});

test('viewers can open game day but not log calls', function () {
    $owner = User::factory()->create();
    [$plan, $play] = planWithPlay($owner);
    $viewer = User::factory()->create();
    $owner->activeTeam()->members()->attach($viewer, ['role' => Team::VIEWER]);
    $viewer->switchTeam($owner->activeTeam());

    $this->actingAs($viewer)->get(route('gameplans.gameday', $plan))->assertOk();
    $this->actingAs($viewer)->post(route('gameplans.calls.store', $plan), ['play_id' => $play->id, 'result' => 'gain'])->assertForbidden();
});

test('the calendar shows a month of practices and games, and schedules templates', function () {
    $user = User::factory()->create();
    $team = $user->activeTeam();
    [$plan] = planWithPlay($user);

    $this->actingAs($user)->post(route('practices.store'), ['name' => 'Tue', 'date' => '2026-11-10', 'duration' => 60, 'segments' => []]);
    $this->actingAs($user)->post(route('practices.store'), ['name' => 'Elsewhere', 'date' => '2026-12-10', 'duration' => 60, 'segments' => []]);
    $template = $team->practices()->forceCreate(['name' => 'Standard', 'duration' => 60, 'is_template' => true, 'user_id' => $user->id]);

    $this->actingAs($user)->get(route('calendar', ['month' => '2026-11']))->assertInertia(fn ($page) => $page
        ->component('calendar/Index')->where('month', '2026-11')
        ->has('practices', 1)->has('games', 1)->has('templates', 1));

    $this->actingAs($user)->get(route('calendar', ['month' => 'garbage']))->assertOk();

    $this->actingAs($user)->post(route('practices.duplicate', $template), ['date' => '2026-11-17'])->assertRedirect();
    $copy = $team->practices()->where('is_template', false)->where('name', 'Standard')->firstOrFail();
    expect($copy->date->toDateString())->toBe('2026-11-17');

    $this->actingAs($user)->get(route('practices.create', ['date' => '2026-11-20']))->assertInertia(fn ($page) => $page->where('defaultDate', '2026-11-20'));
});

test('search finds things across the team and nothing from other teams', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('plays.store'), playPayload(['name' => 'Zebra special']));
    $user->activeTeam()->players()->create(['name' => 'Zed Zebra', 'number' => '9']);
    $other = User::factory()->create();
    $this->actingAs($other)->post(route('plays.store'), playPayload(['name' => 'Zebra secret']));

    $groups = $this->actingAs($user)->getJson(route('search', ['q' => 'zebra']))->assertOk()->json('groups');

    expect(collect($groups)->pluck('label')->all())->toBe(['Plays', 'Players'])
        ->and($groups[0]['items'])->toHaveCount(1)
        ->and($groups[0]['items'][0]['title'])->toBe('Zebra special');

    $this->actingAs($user)->getJson(route('search', ['q' => 'z']))->assertOk()->assertJson(['groups' => []]);
    $this->actingAs($user)->getJson(route('search', ['q' => '%%']))->assertOk()->assertJson(['groups' => []]);
});
