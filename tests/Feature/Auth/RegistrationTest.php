<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('registration is by invitation once an account exists', function () {
    $owner = User::factory()->create();
    $details = fn (string $email) => ['name' => 'New Coach', 'email' => $email, 'password' => 'password', 'password_confirmation' => 'password'];

    $this->post(route('register.store'), $details('stranger@example.com'))->assertSessionHasErrors('email');
    $this->assertGuest();

    $owner->activeTeam()->invitations()->create(['email' => 'friend@example.com', 'role' => 'coach', 'invited_by' => $owner->id]);
    $this->post(route('register.store'), $details('Friend@Example.com'))->assertSessionHasNoErrors();
    $this->assertAuthenticated();
});

test('registration can be opened to everyone', function () {
    User::factory()->create();
    config(['coach.registration' => 'open']);

    $this->post(route('register.store'), ['name' => 'Anyone', 'email' => 'anyone@example.com', 'password' => 'password', 'password_confirmation' => 'password'])->assertSessionHasNoErrors();
    $this->assertAuthenticated();
});
