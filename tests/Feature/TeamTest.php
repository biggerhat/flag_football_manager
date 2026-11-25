<?php

use App\Models\Play;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function teamWith(string $role): array
{
    $owner = User::factory()->create();
    $team = $owner->activeTeam();
    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => $role]);
    $member->switchTeam($team);

    return [$owner, $member, $team];
}

test('every user gets a personal team they own', function () {
    $user = User::factory()->create();

    expect($user->activeTeam()->name)->toBe("{$user->name}'s team")
        ->and($user->role())->toBe(Team::OWNER)
        ->and($user->canEdit())->toBeTrue();
});

test('team members share plays and playbooks', function () {
    [$owner, $coach, $team] = teamWith(Team::COACH);

    $this->actingAs($owner)->post(route('plays.store'), playPayload(['name' => 'Shared play']))->assertRedirect();
    $play = Play::firstOrFail();

    expect($play->team_id)->toBe($team->id);

    $this->actingAs($coach)->get(route('plays.edit', $play))->assertOk();
    $this->actingAs($coach)->get(route('playbooks.show', $play->playbook_id))->assertOk();
    $this->actingAs($coach)->put(route('plays.update', $play), playPayload(['name' => 'Edited by coach']))->assertRedirect();

    expect($play->fresh()->name)->toBe('Edited by coach')->and($play->fresh()->user_id)->toBe($owner->id);
});

test('people outside the team cannot see its content', function () {
    [$owner] = teamWith(Team::COACH);
    $this->actingAs($owner)->post(route('plays.store'), playPayload());
    $outsider = User::factory()->create();

    $this->actingAs($outsider)->get(route('plays.edit', Play::firstOrFail()))->assertNotFound();
});

test('viewers can look and print but not change anything', function () {
    [$owner, $viewer] = teamWith(Team::VIEWER);
    $this->actingAs($owner)->post(route('plays.store'), playPayload());
    $play = Play::firstOrFail();

    expect($viewer->canEdit())->toBeFalse();

    $this->actingAs($viewer)->get(route('plays.edit', $play))->assertOk();
    $this->actingAs($viewer)->get(route('print.plays', ['ids' => (string) $play->id]))->assertOk();
    $this->actingAs($viewer)->post(route('plays.store'), playPayload())->assertForbidden();
    $this->actingAs($viewer)->put(route('plays.update', $play), playPayload(['name' => 'Nope']))->assertForbidden();
    $this->actingAs($viewer)->delete(route('plays.destroy', $play))->assertForbidden();

    expect($play->fresh()->name)->toBe('Quick slant');
});

test('only the owner can invite, change roles and remove members', function () {
    [$owner, $coach, $team] = teamWith(Team::COACH);

    $this->actingAs($coach)->post(route('team.invite'), ['email' => 'new@example.com', 'role' => 'coach'])->assertForbidden();
    $this->actingAs($coach)->put(route('team.members.update', $owner), ['role' => 'viewer'])->assertForbidden();

    $this->actingAs($owner)->put(route('team.members.update', $coach), ['role' => 'viewer'])->assertRedirect();
    expect($team->roleOf($coach))->toBe('viewer');

    $this->actingAs($owner)->put(route('team.members.update', $owner), ['role' => 'viewer'])->assertStatus(422);

    $this->actingAs($owner)->delete(route('team.members.destroy', $coach))->assertRedirect();
    expect($team->members()->whereKey($coach->id)->exists())->toBeFalse();
});

test('an invitation link adds the invited person with the chosen role', function () {
    $owner = User::factory()->create();
    $team = $owner->activeTeam();

    $this->actingAs($owner)->post(route('team.invite'), ['email' => 'Helper@Example.com', 'role' => 'viewer'])->assertRedirect();
    $invitation = TeamInvitation::firstOrFail();

    expect($invitation->email)->toBe('helper@example.com');

    auth()->logout();
    $this->get(route('invitations.show', $invitation->token))->assertOk();

    $helper = User::factory()->create(['email' => 'helper@example.com']);
    $this->actingAs($helper)->post(route('invitations.accept', $invitation->token))->assertRedirect(route('dashboard'));

    expect($team->roleOf($helper))->toBe('viewer')
        ->and($helper->fresh()->current_team_id)->toBe($team->id)
        ->and(TeamInvitation::count())->toBe(0);
});

test('an invitation only works for the address it was sent to and before it expires', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner)->post(route('team.invite'), ['email' => 'right@example.com', 'role' => 'coach']);
    $invitation = TeamInvitation::firstOrFail();

    $wrong = User::factory()->create();
    $this->actingAs($wrong)->post(route('invitations.accept', $invitation->token))->assertSessionHasErrors('invitation');

    $invitation->forceFill(['expires_at' => now()->subDay()])->save();
    $right = User::factory()->create(['email' => 'right@example.com']);
    $this->actingAs($right)->post(route('invitations.accept', $invitation->token))->assertSessionHasErrors('invitation');

    expect($owner->activeTeam()->members()->count())->toBe(1);
});

test('users can create, switch and leave teams', function () {
    $user = User::factory()->create();
    $personal = $user->activeTeam();

    $this->actingAs($user)->post(route('teams.store'), ['name' => 'U10 Tigers'])->assertRedirect(route('team.show'));
    $tigers = Team::where('name', 'U10 Tigers')->firstOrFail();

    expect($user->fresh()->current_team_id)->toBe($tigers->id)
        ->and($tigers->formations()->exists())->toBeTrue();

    $this->actingAs($user)->post(route('teams.switch', $personal))->assertRedirect(route('dashboard'));
    expect($user->fresh()->current_team_id)->toBe($personal->id);

    $other = User::factory()->create();
    $this->actingAs($other)->post(route('teams.switch', $personal))->assertNotFound();

    $coach = User::factory()->create();
    $tigers->members()->attach($coach, ['role' => 'coach']);
    $this->actingAs($coach)->post(route('teams.leave', $tigers))->assertRedirect(route('dashboard'));
    expect($tigers->members()->whereKey($coach->id)->exists())->toBeFalse();

    $this->actingAs($user)->post(route('teams.leave', $tigers))->assertStatus(422);
});

test('the owner can rename and delete a team, taking its content with it', function () {
    [$owner, $coach, $team] = teamWith(Team::COACH);
    $this->actingAs($owner)->post(route('plays.store'), playPayload());

    $this->actingAs($coach)->put(route('teams.update', $team), ['name' => 'Hijack'])->assertNotFound();
    $this->actingAs($owner)->put(route('teams.update', $team), ['name' => 'Eagles'])->assertRedirect();
    expect($team->fresh()->name)->toBe('Eagles');

    $this->actingAs($owner)->delete(route('teams.destroy', $team))->assertRedirect(route('dashboard'));

    expect(Team::find($team->id))->toBeNull()->and(Play::count())->toBe(0)
        ->and($owner->fresh()->activeTeam()->id)->not->toBe($team->id);
});

test('registering creates an account, a team and starter content', function () {
    $this->post('/register', [
        'name' => 'New Coach',
        'email' => 'new@example.com',
        'password' => 'password-1234',
        'password_confirmation' => 'password-1234',
    ])->assertRedirect();

    $user = User::where('email', 'new@example.com')->firstOrFail();

    expect($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->activeTeam()->formations()->exists())->toBeTrue()
        ->and($user->activeTeam()->drills()->exists())->toBeTrue();
});

test('the team page lists members, and invitation links are owner-only', function () {
    [$owner, $coach] = teamWith(Team::COACH);
    $this->actingAs($owner)->post(route('team.invite'), ['email' => 'x@example.com', 'role' => 'coach']);

    $this->actingAs($owner)->get(route('team.show'))->assertInertia(fn ($page) => $page
        ->component('teams/Show')
        ->has('members', 2)
        ->has('invitations', 1));

    $this->actingAs($coach)->get(route('team.show'))->assertInertia(fn ($page) => $page
        ->has('members', 2)
        ->has('invitations', 0));
});

test('a shared playbook can be read without an account until sharing is turned off', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('plays.store'), playPayload(['name' => 'Public play']));
    $playbook = $user->activeTeam()->playbooks()->firstOrFail();

    $this->actingAs($user)->post(route('playbooks.share', $playbook), ['enabled' => true])->assertRedirect();
    $token = $playbook->fresh()->share_token;
    expect($token)->toHaveLength(40);

    auth()->logout();
    $this->get(route('share.show', $token))->assertInertia(fn ($page) => $page
        ->component('playbooks/Packet')->where('public', true)->has('plays', 1)->where('plays.0.name', 'Public play'));
    $this->get(route('share.show', 'not-a-token'))->assertNotFound();

    $this->actingAs($user)->post(route('playbooks.share', $playbook), ['enabled' => false]);
    auth()->logout();
    $this->get(route('share.show', $token))->assertNotFound();
});

test('team members can open a playbook packet, others cannot', function () {
    [$owner, $viewer] = teamWith(Team::VIEWER);
    $this->actingAs($owner)->post(route('plays.store'), playPayload());
    $playbook = $owner->activeTeam()->playbooks()->firstOrFail();

    $this->actingAs($viewer)->get(route('playbooks.packet', $playbook))->assertInertia(fn ($page) => $page->component('playbooks/Packet')->where('public', false));
    $this->actingAs($viewer)->post(route('playbooks.share', $playbook))->assertForbidden();
    $this->actingAs(User::factory()->create())->get(route('playbooks.packet', $playbook))->assertNotFound();
});

test('deleting an account hands its team on, or removes it when nobody else is there', function () {
    [$owner, $coach, $team] = teamWith(Team::COACH);
    $solo = User::factory()->create();
    $soloTeam = $solo->activeTeam();

    $this->actingAs($owner)->delete(route('profile.destroy'), ['password' => 'password'])->assertRedirect('/');
    expect(User::find($owner->id))->toBeNull()
        ->and($team->fresh()->roleOf($coach))->toBe(Team::OWNER);

    auth()->logout();
    $this->actingAs($solo)->delete(route('profile.destroy'), ['password' => 'password'])->assertRedirect('/');
    expect(Team::find($soloTeam->id))->toBeNull();
});
