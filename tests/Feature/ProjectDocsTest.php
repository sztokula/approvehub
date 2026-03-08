<?php

use App\Models\User;

it('shows project documentation pages for authenticated users', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get(route('project-docs.show', 'documentation'))
        ->assertSuccessful()
        ->assertSee('Documentation', false);

    $this->actingAs($user)
        ->get(route('project-docs.show', 'changelog'))
        ->assertSuccessful()
        ->assertSee('Changelog', false);

    $this->actingAs($user)
        ->get(route('project-docs.show', 'what-i-learn'))
        ->assertSuccessful()
        ->assertSee('What I Learned', false);
});

it('returns 404 for unknown project docs slug', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get('/project-docs/unknown')
        ->assertNotFound();
});
