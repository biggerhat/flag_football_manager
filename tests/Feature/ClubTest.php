<?php

use App\Models\Organization;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function clubWithTwoTeams(): array
{
    $owner = User::factory()->create();
    $a = $owner->activeTeam();
    $b = Team::startFor($owner, 'Second team');

    test()->actingAs($owner);
    test()->post(route('club.store'), ['name' => 'Wildcats'])->assertRedirect();
    $club = Organization::firstOrFail();
    test()->post(route('club.teams.add'), ['team_id' => $a->id])->assertRedirect();
    test()->post(route('club.teams.add'), ['team_id' => $b->id])->assertRedirect();

    return [$owner, $club, $a, $b];
}

test('a person can start a club and add the teams they own', function () {
    [$owner, $club, $a, $b] = clubWithTwoTeams();

    expect($club->teams()->count())->toBe(2)
        ->and($club->roleOf($owner))->toBe('owner');

    $this->post(route('club.store'), ['name' => 'Another'])->assertStatus(422);
    $this->get(route('club.show'))->assertOk()->assertInertia(fn ($page) => $page->where('club.name', 'Wildcats')->has('club.teams', 2));
});

test('a club administrator becomes owner of every club team and loses it when removed', function () {
    [$owner, $club, $a, $b] = clubWithTwoTeams();

    $this->post(route('club.members.invite'), ['email' => 'Admin@Example.com', 'role' => 'admin'])->assertRedirect();
    expect(TeamInvitation::where('organization_id', $club->id)->count())->toBe(2);
    $token = TeamInvitation::where('organization_id', $club->id)->first()->token;

    $admin = User::factory()->create(['email' => 'admin@example.com']);
    $this->actingAs($admin)->post(route('invitations.accept', $token))->assertRedirect();

    expect($a->roleOf($admin))->toBe('owner')
        ->and($b->roleOf($admin))->toBe('owner')
        ->and($club->roleOf($admin))->toBe('admin')
        ->and(TeamInvitation::where('organization_id', $club->id)->count())->toBe(0);

    // A team added later is handed to the administrator too.
    $c = Team::startFor($owner, 'Third');
    $this->actingAs($owner)->post(route('club.teams.add'), ['team_id' => $c->id])->assertRedirect();
    expect($c->roleOf($admin))->toBe('owner');

    // An administrator cannot remove other administrators, only the owner can.
    $this->actingAs($admin)->delete(route('club.members.remove', $admin))->assertNotFound();

    $this->actingAs($owner)->delete(route('club.members.remove', $admin))->assertRedirect();
    expect($a->roleOf($admin))->toBeNull()->and($c->roleOf($admin))->toBeNull()->and($club->roleOf($admin))->toBeNull();
});

test('taking a team out of the club revokes only the access the club gave', function () {
    [$owner, $club, $a, $b] = clubWithTwoTeams();
    $admin = User::factory()->create();
    $b->members()->attach($admin, ['role' => 'coach']); // already a coach on B before the club
    $club->addMember($admin);

    expect($a->roleOf($admin))->toBe('owner')->and($b->roleOf($admin))->toBe('coach');

    $this->actingAs($owner)->delete(route('club.teams.remove', $a))->assertRedirect();
    expect($a->fresh()->organization_id)->toBeNull()->and($a->roleOf($admin))->toBeNull();

    $this->delete(route('club.teams.remove', $b))->assertRedirect();
    expect($b->roleOf($admin))->toBe('coach'); // their own membership stays
});

test('only a team owner can add a team to a club, and only an unclaimed one', function () {
    [$owner, $club, $a, $b] = clubWithTwoTeams();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->post(route('club.teams.add'), ['team_id' => $a->id])->assertNotFound(); // no club

    $coach = User::factory()->create();
    $mine = $coach->activeTeam();
    $a->members()->attach($coach, ['role' => 'coach']);
    $this->actingAs($coach)->post(route('club.store'), ['name' => 'Other club'])->assertRedirect();
    $this->post(route('club.teams.add'), ['team_id' => $a->id])->assertNotFound(); // coach, not owner
    $this->post(route('club.teams.add'), ['team_id' => $mine->id])->assertRedirect();
    expect($mine->fresh()->organization_id)->not->toBe($club->id);
});

test('copying an item to another team leaves the original and respects roles', function () {
    $coach = User::factory()->create();
    $this->actingAs($coach);
    $from = $coach->activeTeam();
    $to = Team::startFor($coach, 'Other');
    $viewerTeam = Team::startFor(User::factory()->create(), 'Strangers');
    $viewerTeam->members()->attach($coach, ['role' => 'viewer']);

    $play = $from->plays()->create(playPayload(['name' => 'Slant']));
    $play->forceFill(['is_favorite' => true])->save();
    $drill = $from->drills()->create(['name' => 'Cones', 'category' => 'x', 'duration' => 5]);
    $practice = $from->practices()->create(['name' => 'Tue', 'duration' => 30, 'date' => '2026-12-01']);
    $practice->segments()->create(['title' => 'Warm', 'duration' => 5, 'sort_order' => 0, 'drill_id' => $drill->id, 'play_id' => $play->id]);

    $this->post(route('copy', ['kind' => 'play', 'id' => $play->id]), ['team_id' => $to->id])->assertRedirect();
    $this->post(route('copy', ['kind' => 'practice', 'id' => $practice->id]), ['team_id' => $to->id])->assertRedirect();

    $copy = $to->plays()->where('name', 'Slant')->first();
    expect($copy)->not->toBeNull()
        ->and($copy->is_favorite)->toBeFalse()
        ->and($copy->playbook_id)->toBe($to->defaultPlaybook()->id)
        ->and($from->plays()->count())->toBe(1);

    $copiedPractice = $to->practices()->first();
    $segment = $copiedPractice->segments()->first();
    expect($copiedPractice->date)->toBeNull()
        ->and($segment->drill->team_id)->toBe($to->id)
        ->and($segment->play->team_id)->toBe($to->id);

    // Not into a team you only view, a team you are not on, or from another team's data.
    $this->post(route('copy', ['kind' => 'drill', 'id' => $drill->id]), ['team_id' => $viewerTeam->id])->assertForbidden();
    $this->post(route('copy', ['kind' => 'drill', 'id' => $drill->id]), ['team_id' => $from->id])->assertNotFound();
    $outsider = User::factory()->create();
    $this->post(route('copy', ['kind' => 'drill', 'id' => $drill->id]), ['team_id' => $outsider->activeTeam()->id])->assertNotFound();
    $foreignDrill = $to->drills()->first();
    $this->post(route('copy', ['kind' => 'drill', 'id' => $foreignDrill->id]), ['team_id' => $to->id])->assertNotFound(); // not the active team's
});

test('one invitation can cover several teams and is accepted together', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner);
    $a = $owner->activeTeam();
    $b = Team::startFor($owner, 'B');
    $c = Team::startFor($owner, 'C');
    $owner->switchTeam($a);

    $this->post(route('team.invite'), ['email' => 'helper@example.com', 'role' => 'coach', 'team_ids' => [$b->id, $c->id]])->assertRedirect();
    expect(TeamInvitation::count())->toBe(3);

    $helper = User::factory()->create(['email' => 'helper@example.com']);
    $token = $a->invitations()->first()->token;
    $this->actingAs($helper)->post(route('invitations.accept', $token))->assertRedirect();

    expect($a->roleOf($helper))->toBe('coach')->and($b->roleOf($helper))->toBe('coach')->and($c->roleOf($helper))->toBe('coach')
        ->and(TeamInvitation::count())->toBe(0);
});

test('the teams overview lists every team with its record and role', function () {
    $coach = User::factory()->create();
    $this->actingAs($coach);
    $a = $coach->activeTeam();
    $a->gamePlans()->create(['name' => 'G', 'opponent' => 'Tigers', 'date' => '2026-09-01', 'score_for' => 14, 'score_against' => 7]);
    Team::startFor($coach, 'Second');

    $this->get(route('teams.index'))->assertOk()->assertInertia(fn ($page) => $page
        ->has('teams', 2)
        ->where('teams.0.record.wins', 1)
        ->where('teams.0.role', 'owner'));
});

test('coaches and viewers in a club get that role on every team, and can be changed', function () {
    [$owner, $club, $a, $b] = clubWithTwoTeams();
    $coach = User::factory()->create(['email' => 'coach@example.com']);
    $viewer = User::factory()->create(['email' => 'viewer@example.com']);

    foreach ([['coach@example.com', 'coach', $coach], ['viewer@example.com', 'viewer', $viewer]] as [$email, $role, $person]) {
        $this->actingAs($owner)->post(route('club.members.invite'), ['email' => $email, 'role' => $role])->assertRedirect();
        $token = TeamInvitation::where('organization_id', $club->id)->where('email', $email)->first()->token;
        $this->actingAs($person)->post(route('invitations.accept', $token))->assertRedirect();
    }

    expect($a->roleOf($coach))->toBe('coach')->and($b->roleOf($coach))->toBe('coach')->and($club->roleOf($coach))->toBe('coach')
        ->and($a->roleOf($viewer))->toBe('viewer')->and($b->roleOf($viewer))->toBe('viewer');

    // A coach can see the club but cannot manage it or invite anyone.
    $this->actingAs($coach)->get(route('club.show'))->assertOk()->assertInertia(fn ($page) => $page->where('club.canManage', false)->where('club.addable', []));
    $this->post(route('club.teams.add'), ['team_id' => $coach->activeTeam()->id])->assertNotFound();
    $this->delete(route('club.teams.remove', $a))->assertNotFound();
    $this->post(route('club.members.invite'), ['email' => 'x@example.com', 'role' => 'admin'])->assertNotFound();

    // A club coach can edit plays on a club team; a club viewer cannot.
    $coach->switchTeam($a);
    $this->actingAs($coach)->post(route('plays.store'), playPayload(['name' => 'By coach']))->assertRedirect();
    $viewer->switchTeam($a);
    $this->actingAs($viewer)->post(route('plays.store'), playPayload(['name' => 'By viewer']))->assertForbidden();

    // Changing the club role moves the team roles with it; only the owner may.
    $this->actingAs($coach)->put(route('club.members.update', $viewer), ['role' => 'admin'])->assertNotFound();
    $this->actingAs($owner)->put(route('club.members.update', $viewer), ['role' => 'coach'])->assertRedirect();
    expect($a->roleOf($viewer))->toBe('coach')->and($b->roleOf($viewer))->toBe('coach')->and($club->roleOf($viewer))->toBe('coach');

    // Club-granted access cannot be edited team by team.
    $owner->switchTeam($a);
    $this->actingAs($owner)->put(route('team.members.update', $viewer), ['role' => 'viewer'])->assertStatus(422);
    $this->delete(route('team.members.destroy', $viewer))->assertStatus(422);

    $this->delete(route('club.members.remove', $coach))->assertRedirect();
    expect($a->roleOf($coach))->toBeNull();
});
