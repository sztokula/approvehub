<?php

use App\Enums\UserRole;
use App\Models\Role;
use App\Models\User;

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'organization_name' => 'ApproveHub Labs',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();

    /** @var User $user */
    $user = User::query()->where('email', 'test@example.com')->firstOrFail();
    $adminRole = Role::query()->where('name', UserRole::Admin)->firstOrFail();

    expect($user->organizations()->count())->toBe(1)
        ->and((int) $user->organizations()->first()->pivot->role_id)->toBe($adminRole->id);
});
