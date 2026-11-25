<?php

use App\Actions\SeedStarterContent;
use App\Models\Drill;
use App\Models\Play;
use App\Models\Practice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

test('guests cannot reach the coaching pages', function () {
    $this->get(route('playbooks.index'))->assertRedirect(route('login'));
    $this->get(route('practices.index'))->assertRedirect(route('login'));
    $this->get(route('drills.index'))->assertRedirect(route('login'));
});

test('a play round-trips its diagram unchanged', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('plays.store'), playPayload())->assertRedirect();

    $play = $user->plays()->firstOrFail();
    expect($play->diagram)->toEqual(diagramPayload());

    $this->actingAs($user)->get(route('plays.edit', $play))->assertOk();
});

test('an invalid diagram is rejected', function () {
    $user = User::factory()->create();
    $payload = playPayload();
    $payload['diagram']['players'][0]['x'] = 4;

    $this->actingAs($user)->post(route('plays.store'), $payload)->assertSessionHasErrors('diagram.players.0.x');
});

test('plays belong to their owner', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $play = $owner->plays()->create(playPayload());

    $this->actingAs($other)->get(route('plays.edit', $play))->assertNotFound();
    $this->actingAs($other)->put(route('plays.update', $play), playPayload())->assertNotFound();
    $this->actingAs($other)->delete(route('plays.destroy', $play))->assertNotFound();
    expect(Play::count())->toBe(1);
});

test('a play can be duplicated and deleted', function () {
    $user = User::factory()->create();
    $play = $user->plays()->create(playPayload());

    $this->actingAs($user)->post(route('plays.duplicate', $play))->assertRedirect();
    expect($user->plays()->count())->toBe(2);

    $this->actingAs($user)->delete(route('plays.destroy', $play))->assertRedirect(route('playbooks.show', $play->playbook_id));
    expect($user->plays()->count())->toBe(1);
});

test('starter drills are seeded once', function () {
    $user = User::factory()->create();

    app(SeedStarterContent::class)->handle($user);
    $count = $user->drills()->count();
    app(SeedStarterContent::class)->handle($user);

    expect($count)->toBeGreaterThan(5)->and($user->drills()->count())->toBe($count);
});

test('a practice saves ordered segments and replaces them on update', function () {
    $user = User::factory()->create();
    $drill = $user->drills()->create(['name' => 'Warm-up', 'category' => 'Warm-up', 'duration' => 8]);
    $play = $user->plays()->create(playPayload());

    $payload = [
        'name' => 'Tuesday',
        'date' => '2026-11-03',
        'start_time' => '17:30',
        'duration' => 60,
        'segments' => [
            ['title' => 'Warm-up', 'duration' => 8, 'drill_id' => $drill->id, 'play_id' => null, 'notes' => null],
            ['title' => 'Run: Quick slant', 'duration' => 12, 'drill_id' => null, 'play_id' => $play->id, 'notes' => 'Two reps each'],
        ],
    ];

    $this->actingAs($user)->post(route('practices.store'), $payload)->assertRedirect();
    $practice = $user->practices()->firstOrFail();
    expect($practice->segments->pluck('title')->all())->toBe(['Warm-up', 'Run: Quick slant']);

    $payload['segments'] = array_reverse($payload['segments']);
    $this->actingAs($user)->put(route('practices.update', $practice), $payload)->assertRedirect();

    expect($practice->fresh()->segments->pluck('title')->all())->toBe(['Run: Quick slant', 'Warm-up'])
        ->and($practice->segments()->count())->toBe(2);
});

test('segments cannot reference another user\'s drills', function () {
    $user = User::factory()->create();
    $foreign = User::factory()->create()->drills()->create(['name' => 'Secret', 'category' => 'x', 'duration' => 5]);

    $this->actingAs($user)->post(route('practices.store'), [
        'name' => 'Practice',
        'duration' => 60,
        'segments' => [['title' => 'Steal', 'duration' => 5, 'drill_id' => $foreign->id]],
    ])->assertSessionHasErrors('segments.0.drill_id');
});

test('saving a practice as a template copies segments independently', function () {
    $user = User::factory()->create();
    $practice = $user->practices()->create(['name' => 'Standard', 'duration' => 60, 'date' => '2026-11-03']);
    $practice->segments()->create(['title' => 'Warm-up', 'duration' => 8, 'sort_order' => 0]);

    $this->actingAs($user)->post(route('practices.duplicate', $practice), ['template' => true])->assertRedirect();

    $template = Practice::where('is_template', true)->firstOrFail();
    expect($template->date)->toBeNull()
        ->and($template->segments)->toHaveCount(1);

    $template->segments()->first()->update(['title' => 'Changed']);
    expect($practice->segments()->first()->title)->toBe('Warm-up');
});

test('print pages render for the owner only', function () {
    $user = User::factory()->create();
    $play = $user->plays()->create(playPayload());
    $practice = $user->practices()->create(['name' => 'Standard', 'duration' => 60]);

    $this->actingAs($user)->get(route('print.plays', ['ids' => $play->id]))->assertOk();
    $this->actingAs($user)->get(route('print.practice', $practice))->assertOk();
    $this->actingAs(User::factory()->create())->get(route('print.practice', $practice))->assertNotFound();
});

test('the dashboard and index pages load', function () {
    $user = User::factory()->create();
    $user->drills()->create(['name' => 'D', 'category' => 'x', 'duration' => 5]);

    foreach (['dashboard', 'formations.index', 'formations.create', 'playbooks.index', 'plays.create', 'drills.index', 'drills.create', 'practices.index', 'practices.create'] as $name) {
        $this->actingAs($user)->get(route($name))->assertOk();
    }

    expect(Drill::count())->toBe(1);
});

test('local auto-login signs in a single coach with starter drills', function () {
    config(['coach.auto_login' => true]);

    $this->get(route('playbooks.index'))->assertOk();
    $this->get(route('home'))->assertRedirect(route('dashboard'));
    $this->get(route('dashboard'))->assertOk();
    $this->get(route('playbooks.index'))->assertOk();

    expect(User::count())->toBe(1)
        ->and(User::first()->drills()->count())->toBeGreaterThan(5);
});

test('a route can be saved with a dotted line', function () {
    $user = User::factory()->create();
    $payload = playPayload();
    $payload['diagram']['routes'][0]['style']['dash'] = 'dotted';

    $this->actingAs($user)->post(route('plays.store'), $payload)->assertSessionHasNoErrors();

    expect($user->plays()->firstOrFail()->diagram['routes'][0]['style']['dash'])->toBe('dotted');
});

function formationPayload(array $overrides = []): array
{
    $diagram = diagramPayload();

    return [
        'name' => 'Trips right',
        'description' => 'Three wide to one side',
        'diagram' => $diagram,
        ...$overrides,
    ];
}

test('new users get the built-in formations', function () {
    $user = User::factory()->create();

    app(SeedStarterContent::class)->handle($user);
    app(SeedStarterContent::class)->handle($user);

    expect($user->formations()->pluck('name')->sort()->values()->all())
        ->toBe(['Blitz', 'Bunch', 'Man', 'Single Back', 'Split Back', 'Spread', 'Zone 2-3']);
});

test('a formation is saved without routes or annotations', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('formations.store'), formationPayload())->assertRedirect();

    $formation = $user->formations()->firstOrFail();
    expect($formation->name)->toBe('Trips right')
        ->and($formation->diagram['players'])->toHaveCount(2)
        ->and($formation->diagram['routes'])->toBe([]);
});

test('a formation needs at least one player', function () {
    $user = User::factory()->create();
    $payload = formationPayload();
    $payload['diagram']['players'] = [];

    $this->actingAs($user)->post(route('formations.store'), $payload)->assertSessionHasErrors('diagram.players');
});

test('formations can be edited, duplicated and deleted by their owner only', function () {
    $owner = User::factory()->create();
    $formation = $owner->formations()->create(formationPayload());

    $this->actingAs($owner)->put(route('formations.update', $formation), formationPayload(['name' => 'Renamed']))->assertRedirect();
    expect($formation->fresh()->name)->toBe('Renamed');

    $this->actingAs($owner)->post(route('formations.duplicate', $formation))->assertRedirect();
    expect($owner->formations()->count())->toBe(2);

    $stranger = User::factory()->create();
    $this->actingAs($stranger)->get(route('formations.edit', $formation))->assertNotFound();
    $this->actingAs($stranger)->delete(route('formations.destroy', $formation))->assertNotFound();

    $this->actingAs($owner)->delete(route('formations.destroy', $formation))->assertRedirect(route('formations.index'));
    expect($owner->formations()->count())->toBe(1);
});

test('the play editor receives the saved formations', function () {
    $user = User::factory()->create();
    $user->formations()->create(formationPayload());

    $this->actingAs($user)->get(route('plays.create'))
        ->assertInertia(fn ($page) => $page->component('plays/Editor')->has('formations', 1));
});

test('formations print through the shared print page', function () {
    $user = User::factory()->create();
    $formation = $user->formations()->create(formationPayload());

    $this->actingAs($user)->get(route('print.formations', ['ids' => $formation->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('print/Plays')->where('kind', 'formation')->has('plays', 1));

    $this->actingAs(User::factory()->create())->get(route('print.formations', ['ids' => $formation->id]))
        ->assertInertia(fn ($page) => $page->has('plays', 0));
});

test('using a template creates a normal practice named without the template suffix', function () {
    $user = User::factory()->create();
    $template = $user->practices()->create(['name' => 'Standard (template)', 'duration' => 60, 'is_template' => true]);
    $template->segments()->create(['title' => 'Warm-up', 'duration' => 8, 'sort_order' => 0]);

    $this->actingAs($user)->post(route('practices.duplicate', $template))->assertRedirect();

    $practice = Practice::where('is_template', false)->firstOrFail();
    expect($practice->name)->toBe('Standard')
        ->and($practice->segments)->toHaveCount(1);
});

test('play tags are saved and the defense category is accepted', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('plays.store'), playPayload(['category' => 'defense', 'tags' => ['zone', 'goal line']]))
        ->assertSessionHasNoErrors();

    $play = $user->plays()->firstOrFail();
    expect($play->category)->toBe('defense')->and($play->tags)->toBe(['zone', 'goal line']);
});

test('existing and new coaches get defensive formations', function () {
    $user = User::factory()->create();
    app(SeedStarterContent::class)->handle($user);

    expect($user->formations()->pluck('name')->all())->toContain('Zone 2-3', 'Man', 'Blitz');
});

test('a play can be filed in a folder and branched into a variation', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('plays.store'), playPayload(['folder' => 'Red zone']))->assertRedirect();
    $original = $user->plays()->firstOrFail();
    expect($original->folder)->toBe('Red zone');

    $this->actingAs($user)->post(route('plays.variation', $original))->assertRedirect();
    $variation = $user->plays()->whereKeyNot($original->id)->firstOrFail();
    expect($variation->parent_id)->toBe($original->id)
        ->and($variation->folder)->toBe('Red zone')
        ->and($variation->name)->toBe('Quick slant (variation)');

    // A variation of a variation still points at the original.
    $this->actingAs($user)->post(route('plays.variation', $variation))->assertRedirect();
    expect($user->plays()->latest('id')->first()->parent_id)->toBe($original->id);

    $other = User::factory()->create();
    $this->actingAs($other)->post(route('plays.variation', $original))->assertNotFound();
});

test('drills and plays report when they were last run', function () {
    $user = User::factory()->create();
    $drill = $user->drills()->create(['name' => 'Flag pulling', 'category' => 'defense', 'duration' => 10]);
    $play = $user->plays()->create(playPayload());

    $past = $user->practices()->create(['name' => 'Tuesday', 'date' => today()->subDays(3), 'duration' => 60]);
    $past->segments()->create(['drill_id' => $drill->id, 'play_id' => $play->id, 'title' => 'Mix', 'duration' => 10, 'sort_order' => 0]);
    $future = $user->practices()->create(['name' => 'Next week', 'date' => today()->addDays(5), 'duration' => 60]);
    $future->segments()->create(['drill_id' => $drill->id, 'title' => 'Again', 'duration' => 10, 'sort_order' => 0]);
    $template = $user->practices()->create(['name' => 'Template', 'date' => today()->subDay(), 'duration' => 60, 'is_template' => true]);
    $template->segments()->create(['drill_id' => $drill->id, 'title' => 'T', 'duration' => 10, 'sort_order' => 0]);

    $expected = today()->subDays(3)->toDateString();

    $this->actingAs($user)->get(route('drills.index'))
        ->assertInertia(fn ($page) => $page->where('drills.0.last_run', $expected));
    $this->actingAs($user)->get(route('playbooks.show', $play->playbook_id))
        ->assertInertia(fn ($page) => $page->where('plays.0.last_run', $expected));
});

test('practice review notes are saved and not copied into a duplicate', function () {
    $user = User::factory()->create();
    $practice = $user->practices()->create(['name' => 'Tuesday', 'duration' => 60, 'review_notes' => 'Tackle drill ran long']);

    $this->actingAs($user)->put(route('practices.update', $practice), [
        'name' => 'Tuesday', 'duration' => 60, 'review_notes' => 'Great energy', 'segments' => [],
    ])->assertRedirect();
    expect($practice->fresh()->review_notes)->toBe('Great energy');

    $this->actingAs($user)->post(route('practices.duplicate', $practice))->assertRedirect();
    expect($user->practices()->whereKeyNot($practice->id)->first()->review_notes)->toBeNull();
});

test('game plans keep their plays in order and are private to their owner', function () {
    $user = User::factory()->create();
    $a = $user->plays()->create(playPayload(['name' => 'A']));
    $b = $user->plays()->create(playPayload(['name' => 'B']));
    $c = $user->plays()->create(playPayload(['name' => 'C']));

    $this->actingAs($user)->post(route('gameplans.store'), [
        'name' => 'Vs Tigers', 'opponent' => 'Tigers', 'play_ids' => [$c->id, $a->id],
    ])->assertRedirect();

    $plan = $user->gamePlans()->firstOrFail();
    expect($plan->plays->pluck('id')->all())->toBe([$c->id, $a->id]);

    $this->actingAs($user)->put(route('gameplans.update', $plan), [
        'name' => 'Vs Tigers', 'play_ids' => [$a->id, $b->id, $c->id],
    ])->assertRedirect();
    expect($plan->fresh()->plays->pluck('id')->all())->toBe([$a->id, $b->id, $c->id]);

    $this->actingAs($user)->get(route('gameplans.edit', $plan))
        ->assertInertia(fn ($page) => $page->where('gamePlan.play_ids', [$a->id, $b->id, $c->id]));

    $other = User::factory()->create();
    $this->actingAs($other)->get(route('gameplans.edit', $plan))->assertNotFound();
    $this->actingAs($other)->post(route('gameplans.store'), ['name' => 'Steal', 'play_ids' => [$a->id]])
        ->assertSessionHasErrors('play_ids.0');
});

test('the pdf endpoint only exports print pages', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('pdf', ['to' => '/plays']))->assertStatus(422);
    $this->actingAs($user)->get(route('pdf', ['to' => '//evil.test/print/x']))->assertStatus(422);
});

test('guests cannot request a pdf', function () {
    $this->get(route('pdf', ['to' => '/print/plays']))->assertRedirect(route('login'));
});

test('the pdf session link needs a valid signature', function () {
    $user = User::factory()->create();

    $this->get(route('pdf.session', ['user' => $user->id, 'to' => '/print/plays']))->assertForbidden();

    $url = URL::temporarySignedRoute('pdf.session', now()->addMinute(), ['user' => $user->id, 'to' => '/print/plays?ids=1'], absolute: false);
    $this->get($url)->assertRedirect('/print/plays?ids=1');
    $this->assertAuthenticatedAs($user);

    // A second use of the same link is refused.
    auth()->logout();
    $this->get($url)->assertForbidden();
});

test('position name suggestions include names used in formations', function () {
    $user = User::factory()->create();
    $user->formations()->create(['name' => 'Odd', 'diagram' => [
        'schema_version' => 1, 'los' => 0.68, 'routes' => [], 'annotations' => [],
        'players' => [['id' => 'a', 'type' => 'receiver', 'label' => 'Jet', 'x' => 0.5, 'y' => 0.7, 'color' => '#2563eb']],
    ]]);

    $this->actingAs($user)->get(route('plays.create'))
        ->assertInertia(fn ($page) => $page->where('positionNames', fn ($names) => collect($names)->contains('Jet') && collect($names)->contains('QB')));
});

test('plays live in playbooks and a play without a choice lands in the default one', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('plays.store'), playPayload())->assertRedirect();
    $play = $user->plays()->firstOrFail();
    $default = $user->playbooks()->firstOrFail();
    expect($play->playbook_id)->toBe($default->id)
        ->and($default->name)->toBe('My playbook');

    $this->actingAs($user)->post(route('playbooks.store'), ['name' => 'Red zone'])->assertRedirect();
    $red = $user->playbooks()->where('name', 'Red zone')->firstOrFail();

    // Choosing a playbook files the play there; editing without one leaves it in place.
    $this->actingAs($user)->post(route('plays.store'), playPayload(['name' => 'Fade', 'playbook_id' => $red->id]))->assertRedirect();
    $fade = $user->plays()->where('name', 'Fade')->firstOrFail();
    expect($fade->playbook_id)->toBe($red->id);

    $this->actingAs($user)->put(route('plays.update', $fade), playPayload(['name' => 'Fade 2']))->assertRedirect();
    expect($fade->fresh()->playbook_id)->toBe($red->id);

    $this->actingAs($user)->get(route('playbooks.show', $red))
        ->assertInertia(fn ($page) => $page->has('plays', 1)->where('plays.0.name', 'Fade 2'));

    $this->actingAs($user)->post(route('plays.move'), ['ids' => [$fade->id], 'playbook_id' => $default->id])->assertRedirect();
    expect($fade->fresh()->playbook_id)->toBe($default->id);
});

test('playbooks are private, renamable and only deletable when empty', function () {
    $user = User::factory()->create();
    $book = $user->playbooks()->create(['name' => 'Old']);
    $play = $user->plays()->create(playPayload(['playbook_id' => $book->id]));

    $this->actingAs($user)->put(route('playbooks.update', $book), ['name' => 'New name'])->assertRedirect();
    expect($book->fresh()->name)->toBe('New name');

    $this->actingAs($user)->delete(route('playbooks.destroy', $book))->assertRedirect();
    expect($book->fresh())->not->toBeNull();

    $play->delete();
    $this->actingAs($user)->delete(route('playbooks.destroy', $book))->assertRedirect(route('playbooks.index'));
    expect($book->fresh())->toBeNull();

    $other = User::factory()->create();
    $mine = $user->playbooks()->create(['name' => 'Mine']);
    $this->actingAs($other)->get(route('playbooks.show', $mine))->assertNotFound();
    $this->actingAs($other)->post(route('plays.store'), playPayload(['playbook_id' => $mine->id]))->assertSessionHasErrors('playbook_id');
    $this->actingAs($other)->post(route('plays.move'), ['ids' => [1], 'playbook_id' => $mine->id])->assertSessionHasErrors('playbook_id');
});
