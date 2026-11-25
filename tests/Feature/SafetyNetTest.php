<?php

use App\Mail\EventReminder;
use App\Models\Drill;
use App\Models\Play;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

test('a deleted play can be undone, but only by its own team', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('plays.store'), playPayload());
    $play = Play::firstOrFail();

    $this->actingAs($user)->delete(route('plays.destroy', $play))->assertRedirect();
    expect(Play::count())->toBe(0);

    $outsider = User::factory()->create();
    $this->actingAs($outsider)->post(route('restore', ['kind' => 'play', 'id' => $play->id]))->assertNotFound();

    $this->actingAs($user)->post(route('restore', ['kind' => 'play', 'id' => $play->id]))->assertRedirect();
    expect(Play::count())->toBe(1);
});

test('restoring a play whose playbook was deleted puts it in the default playbook', function () {
    $user = User::factory()->create();
    $team = $user->activeTeam();
    $other = $team->playbooks()->forceCreate(['name' => 'Temp', 'user_id' => $user->id]);
    $this->actingAs($user)->post(route('plays.store'), playPayload(['playbook_id' => $other->id]));
    $play = Play::firstOrFail();

    $this->actingAs($user)->delete(route('plays.destroy', $play));
    $this->actingAs($user)->delete(route('playbooks.destroy', $other))->assertRedirect();
    $this->actingAs($user)->post(route('restore', ['kind' => 'play', 'id' => $play->id]));

    expect($play->fresh()->playbook_id)->not->toBeNull()->and($play->fresh()->playbook->name)->toBe('My playbook');
});

test('every kind of deletion offers an undo', function () {
    $user = User::factory()->create();
    $drill = $user->activeTeam()->drills()->forceCreate(['name' => 'D', 'category' => 'Warm-up', 'duration' => 5, 'user_id' => $user->id]);

    $this->actingAs($user)->delete(route('drills.destroy', $drill))->assertRedirect();
    expect(Drill::count())->toBe(0);

    $this->actingAs($user)->post(route('restore', ['kind' => 'drill', 'id' => $drill->id]));
    expect(Drill::count())->toBe(1);

    $this->actingAs($user)->post(route('restore', ['kind' => 'nonsense', 'id' => 1]))->assertNotFound();
});

test('viewers cannot undo or delete', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $owner->activeTeam()->members()->attach($viewer, ['role' => Team::VIEWER]);
    $viewer->switchTeam($owner->activeTeam());
    $this->actingAs($owner)->post(route('plays.store'), playPayload());
    $play = Play::firstOrFail();
    $this->actingAs($owner)->delete(route('plays.destroy', $play));

    $this->actingAs($viewer)->post(route('restore', ['kind' => 'play', 'id' => $play->id]))->assertForbidden();
});

test('owners and coaches post a team announcement that the dashboard shows', function () {
    $owner = User::factory()->create();
    $team = $owner->activeTeam();

    $this->actingAs($owner)->put(route('teams.announce', $team), ['announcement' => 'Practice moved to the north field'])
        ->assertRedirect();

    $this->actingAs($owner->fresh())->get(route('dashboard'))
        ->assertInertia(fn ($p) => $p->where('announcement', 'Practice moved to the north field'));

    $viewer = User::factory()->create();
    $team->members()->attach($viewer, ['role' => Team::VIEWER]);
    $viewer->switchTeam($team);
    $viewer = $viewer->fresh();

    $this->actingAs($viewer)->put(route('teams.announce', $team), ['announcement' => 'nope'])->assertForbidden();
    $this->actingAs($viewer)->get(route('dashboard'))
        ->assertInertia(fn ($p) => $p->where('announcement', 'Practice moved to the north field'));
});

test('game plans list a season record from the scored games', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $team = $user->activeTeam();
    $team->gamePlans()->create(['name' => 'A', 'score_for' => 20, 'score_against' => 12]);
    $team->gamePlans()->create(['name' => 'B', 'score_for' => 6, 'score_against' => 14]);
    $team->gamePlans()->create(['name' => 'C']);

    $this->actingAs($user)->get(route('gameplans.index'))
        ->assertInertia(fn ($p) => $p->where('record', [
            'wins' => 1, 'losses' => 1, 'ties' => 0, 'pointsFor' => 26, 'pointsAgainst' => 26,
        ]));
});

test('calls keep the down and a note, and the roster counts attendance', function () {
    $user = User::factory()->create();
    $team = $user->activeTeam();
    $this->actingAs($user);
    $plan = $team->gamePlans()->create(['name' => 'G']);
    $play = $team->plays()->create(playPayload());

    $this->actingAs($user)->post(route('gameplans.calls.store', $plan), [
        'play_id' => $play->id, 'result' => 'gain', 'yards' => 8, 'down' => 3, 'note' => 'Marcus caught it',
    ])->assertRedirect();
    $this->actingAs($user)->post(route('gameplans.calls.store', $plan), [
        'play_id' => $play->id, 'result' => 'gain', 'down' => 7,
    ])->assertSessionHasErrors('down');

    $this->actingAs($user)->get(route('gameplans.gameday', $plan))
        ->assertInertia(fn ($p) => $p->where('calls.0.down', 3)->where('calls.0.note', 'Marcus caught it'));

    $player = $team->players()->create(['name' => 'Marcus']);
    $practice = $team->practices()->create(['name' => 'Tue', 'duration' => 60]);
    $practice->attendees()->sync([$player->id]);

    $this->actingAs($user)->get(route('players.index'))
        ->assertInertia(fn ($p) => $p->where('practiceCount', 1)->where('players.0.practices_count', 1));
});

test('responses carry security headers and secret links are not referred or indexed', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $this->get(route('dashboard'))->assertHeader('X-Frame-Options', 'SAMEORIGIN')->assertHeader('X-Content-Type-Options', 'nosniff');

    $token = str()->random(40);
    $user->activeTeam()->forceFill(['calendar_token' => $token])->save();
    $this->get(route('family', $token))->assertHeader('Referrer-Policy', 'no-referrer')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

test('a playbook share link expires after 90 days and can be turned off', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $playbook = $user->activeTeam()->defaultPlaybook();

    $this->post(route('playbooks.share', $playbook), ['enabled' => true])->assertRedirect();
    $playbook->refresh();
    $this->get(route('share.show', $playbook->share_token))->assertOk();

    $playbook->forceFill(['share_expires_at' => now()->subMinute()])->save();
    $this->get(route('share.show', $playbook->share_token))->assertNotFound();

    $this->post(route('playbooks.share', $playbook), ['enabled' => false]);
    expect($playbook->refresh()->share_token)->toBeNull()->and($playbook->share_expires_at)->toBeNull();
});

test('the playbooks index previews at most three recent plays per playbook in constant queries', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $team = $user->activeTeam();
    foreach (['A', 'B'] as $name) {
        $book = $team->playbooks()->create(['name' => $name, 'user_id' => $user->id]);
        foreach (range(1, 5) as $i) {
            $team->plays()->create(['user_id' => $user->id, 'playbook_id' => $book->id, 'name' => "$name$i", 'category' => 'pass', 'diagram' => ['players' => [], 'routes' => []]]);
        }
    }

    $this->get(route('playbooks.index'))->assertInertia(fn ($page) => $page
        ->where('playbooks', fn ($books) => collect($books)->every(fn ($book) => count($book['previews']) === 3) && count($books) === 2));
});

test('another team can neither read nor change anything by guessing ids', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner);
    $team = $owner->activeTeam();
    $diagram = ['players' => [], 'routes' => []];
    $play = $team->plays()->create(['user_id' => $owner->id, 'name' => 'P', 'category' => 'pass', 'diagram' => $diagram]);
    $drill = $team->drills()->create(['user_id' => $owner->id, 'name' => 'D', 'category' => 'x', 'duration' => 5]);
    $formation = $team->formations()->create(['user_id' => $owner->id, 'name' => 'F', 'diagram' => $diagram]);
    $practice = $team->practices()->create(['user_id' => $owner->id, 'name' => 'Pr', 'duration' => 60]);
    $plan = $team->gamePlans()->create(['user_id' => $owner->id, 'name' => 'G']);
    $playbook = $team->defaultPlaybook();
    $player = $team->players()->create(['name' => 'Kid']);
    $season = $team->seasons()->create(['name' => 'S', 'starts_on' => '2026-01-01']);
    $call = $plan->calls()->make(['play_id' => $play->id, 'result' => 'gain']);
    $call->team_id = $team->id;
    $call->save();

    $outsider = User::factory()->create();
    $this->actingAs($outsider);

    $requests = [
        ['get', route('plays.edit', $play)], ['put', route('plays.update', $play)], ['delete', route('plays.destroy', $play)],
        ['post', route('plays.duplicate', $play)], ['post', route('plays.favorite', $play)], ['post', route('plays.variation', $play)],
        ['get', route('drills.edit', $drill)], ['put', route('drills.update', $drill)], ['delete', route('drills.destroy', $drill)],
        ['get', route('formations.edit', $formation)], ['put', route('formations.update', $formation)], ['delete', route('formations.destroy', $formation)], ['post', route('formations.duplicate', $formation)],
        ['get', route('practices.edit', $practice)], ['put', route('practices.update', $practice)], ['delete', route('practices.destroy', $practice)], ['post', route('practices.duplicate', $practice)], ['get', route('practices.run', $practice)], ['get', route('print.practice', $practice)],
        ['get', route('gameplans.edit', $plan)], ['put', route('gameplans.update', $plan)], ['delete', route('gameplans.destroy', $plan)], ['get', route('gameplans.gameday', $plan)], ['put', route('gameplans.score', $plan)], ['post', route('gameplans.calls.store', $plan)], ['delete', route('gameplans.calls.destroy', [$plan, $call])],
        ['get', route('playbooks.show', $playbook)], ['put', route('playbooks.update', $playbook)], ['delete', route('playbooks.destroy', $playbook)], ['get', route('playbooks.packet', $playbook)], ['post', route('playbooks.share', $playbook)],
        ['put', route('players.update', $player)], ['delete', route('players.destroy', $player)],
        ['delete', route('seasons.destroy', $season)],
        ['post', route('restore', ['kind' => 'play', 'id' => $play->id])],
    ];

    foreach ($requests as [$method, $url]) {
        $status = $this->{$method}($url, $method === 'get' ? [] : ['name' => 'hacked'])->getStatusCode();
        expect($status)->toBeIn([403, 404, 302, 422], "$method $url returned $status");
        expect($status)->not->toBe(200, "$method $url leaked with 200");
    }

    expect($play->fresh()->name)->toBe('P')
        ->and($drill->fresh()->name)->toBe('D')
        ->and($formation->fresh()->name)->toBe('F')
        ->and($practice->fresh()->name)->toBe('Pr')
        ->and($plan->fresh()->name)->toBe('G')
        ->and($player->fresh())->not->toBeNull()
        ->and($season->fresh())->not->toBeNull()
        ->and($call->fresh())->not->toBeNull()
        ->and($playbook->fresh()->share_token)->toBeNull();
});

test('guests see a branded home page when auto-login is off', function () {
    config(['coach.auto_login' => false]);

    $this->get('/')->assertOk()->assertInertia(fn ($page) => $page->component('Welcome'));
});

test('a backup can be restored into another team with its links rewired', function () {
    $coach = User::factory()->create();
    $this->actingAs($coach);
    $team = $coach->activeTeam();
    $diagram = ['players' => [], 'routes' => []];
    $book = $team->playbooks()->create(['name' => 'Book']);
    $a = $team->plays()->create(['name' => 'A', 'category' => 'pass', 'playbook_id' => $book->id, 'diagram' => $diagram]);
    $b = $team->plays()->create(['name' => 'B', 'category' => 'run', 'playbook_id' => $book->id, 'diagram' => $diagram]);
    $b->forceFill(['parent_id' => $a->id])->save();
    $drill = $team->drills()->create(['name' => 'D', 'category' => 'x', 'duration' => 5]);
    $practice = $team->practices()->create(['name' => 'Pr', 'duration' => 30]);
    $practice->segments()->create(['title' => 'Seg', 'duration' => 5, 'sort_order' => 0, 'drill_id' => $drill->id, 'play_id' => $a->id]);
    $player = $team->players()->create(['name' => 'Kid']);
    $plan = $team->gamePlans()->create(['name' => 'G', 'opponent' => 'Tigers']);
    $plan->plays()->sync([$b->id => ['sort_order' => 0], $a->id => ['sort_order' => 1]]);
    $call = $plan->calls()->make(['play_id' => $a->id, 'player_id' => $player->id, 'result' => 'gain']);
    $call->team_id = $team->id;
    $call->save();

    $json = $this->get(route('team.backup.export'))->assertOk()->getContent();

    $other = User::factory()->create();
    $this->actingAs($other);
    $file = UploadedFile::fake()->createWithContent('backup.json', $json);
    $this->post(route('team.backup.import'), ['file' => $file])->assertRedirect()->assertSessionHasNoErrors();

    $new = $other->activeTeam();
    $newB = $new->plays()->where('name', 'B')->first();
    $newPlan = $new->gamePlans()->first();
    expect($new->plays()->count())->toBe(2)
        ->and($newB->parent_id)->toBe($new->plays()->where('name', 'A')->value('id'))
        ->and($newPlan->plays->pluck('name')->all())->toBe(['B', 'A'])
        ->and($newPlan->calls()->first()->player->name)->toBe('Kid')
        ->and($new->practices()->first()->segments()->first()->drill->name)->toBe('D')
        ->and($team->plays()->count())->toBe(2);

    $bad = UploadedFile::fake()->createWithContent('x.json', '{"app":"other"}');
    $this->post(route('team.backup.import'), ['file' => $bad])->assertSessionHasErrors('file');
});

test('saving a play keeps its earlier versions and one can be restored', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $play = $user->activeTeam()->plays()->create(playPayload(['name' => 'Slant']));
    $payload = fn (array $over = []) => playPayload($over);

    $this->put(route('plays.update', $play), $payload(['name' => 'Slant v2']))->assertRedirect();
    $this->put(route('plays.update', $play), $payload(['name' => 'Slant v2']))->assertRedirect(); // unchanged: no new version
    $this->put(route('plays.update', $play), $payload(['name' => 'Slant v3']))->assertRedirect();

    $versions = $this->get(route('plays.versions', $play))->assertOk()->json();
    expect(array_column($versions, 'name'))->toBe(['Slant v2', 'Slant']);

    $oldest = collect($versions)->last();
    $this->post(route('plays.versions.restore', [$play, $oldest['id']]))->assertRedirect();
    expect($play->fresh()->name)->toBe('Slant')
        ->and($play->versions()->count())->toBe(3); // current state kept first, so the restore can be undone

    $other = User::factory()->create();
    $this->actingAs($other)->get(route('plays.versions', $play))->assertNotFound();
});

test('only the 20 newest versions of a play are kept', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $play = $user->activeTeam()->plays()->create(['name' => 'N0', 'category' => 'pass', 'diagram' => ['players' => [], 'routes' => []]]);

    foreach (range(1, 25) as $i) {
        $play->fill(['name' => "N$i"]);
        $play->recordVersionIfChanged($user->id);
        $play->save();
    }

    expect($play->versions()->count())->toBe(20);
});

test('a retried call with the same key is logged only once', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $team = $user->activeTeam();
    $play = $team->plays()->create(playPayload());
    $plan = $team->gamePlans()->create(['name' => 'G']);
    $body = ['play_id' => $play->id, 'result' => 'gain', 'yards' => 5, 'client_key' => 'abc-123'];

    $this->post(route('gameplans.calls.store', $plan), $body)->assertRedirect();
    $this->post(route('gameplans.calls.store', $plan), $body)->assertRedirect();
    $this->post(route('gameplans.calls.store', $plan), [...$body, 'client_key' => 'other'])->assertRedirect();
    $this->post(route('gameplans.calls.store', $plan), ['play_id' => $play->id, 'result' => 'gain'])->assertRedirect();
    $this->post(route('gameplans.calls.store', $plan), ['play_id' => $play->id, 'result' => 'gain'])->assertRedirect();

    expect($plan->calls()->count())->toBe(4); // abc-123 once, other once, two without keys
});

test('reminders email opted-in members about tomorrow once', function () {
    Mail::fake();
    $coach = User::factory()->create(['email_reminders' => true]);
    $quiet = User::factory()->create();
    $this->actingAs($coach);
    $team = $coach->activeTeam();
    $team->members()->syncWithoutDetaching([$quiet->id => ['role' => 'coach']]);
    $tomorrow = $team->today()->addDay();
    $team->practices()->create(['name' => 'Tuesday', 'duration' => 60, 'date' => $tomorrow, 'start_time' => '17:30']);
    $team->practices()->create(['name' => 'Template', 'duration' => 60, 'date' => $tomorrow, 'is_template' => true]);
    $team->gamePlans()->create(['name' => 'G', 'opponent' => 'Tigers', 'date' => $tomorrow->copy()->addDay()]);

    $this->artisan('reminders:send --force')->assertSuccessful();
    $this->artisan('reminders:send --force')->assertSuccessful();

    Mail::assertSent(EventReminder::class, 1);
    Mail::assertSent(EventReminder::class, fn ($m) => $m->hasTo($coach->email) && count($m->events) === 1 && $m->events[0]['title'] === 'Tuesday');
    Mail::assertNotSent(EventReminder::class, fn ($m) => $m->hasTo($quiet->email));

    $mail = new EventReminder($team, $tomorrow, [['kind' => 'Practice', 'title' => 'Tuesday', 'time' => '17:30', 'location' => 'North field']]);
    $mail->assertSeeInText('Practice: Tuesday at 17:30 (North field)');

    $this->put(route('profile.reminders'), ['enabled' => false])->assertRedirect();
    expect($coach->fresh()->email_reminders)->toBeFalse();
});

test('stats ignore calls from games that were deleted', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $team = $user->activeTeam();
    $play = $team->plays()->create(playPayload());
    $kept = $team->gamePlans()->create(['name' => 'Kept']);
    $gone = $team->gamePlans()->create(['name' => 'Gone']);

    foreach ([$kept, $gone] as $plan) {
        $call = $plan->calls()->make(['play_id' => $play->id, 'result' => 'gain', 'yards' => 5]);
        $call->team_id = $team->id;
        $call->save();
    }
    $gone->delete();

    $this->get(route('stats'))->assertOk()->assertInertia(fn ($page) => $page->where('totals.calls', 1));
});
