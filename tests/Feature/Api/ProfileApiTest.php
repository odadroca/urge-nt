<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patchJson('/api/v1/profile', [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.name', 'Updated Name')
            ->assertJsonPath('data.user.email', 'updated@example.com');

        $user->refresh();
        $this->assertSame('Updated Name', $user->name);
        $this->assertSame('updated@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_email_unchanged(): void
    {
        $user = User::factory()->create();
        $originalVerifiedAt = $user->email_verified_at;
        $this->assertNotNull($originalVerifiedAt);

        $response = $this->actingAs($user)->patchJson('/api/v1/profile', [
            'name' => 'Updated Name',
            'email' => $user->email,
        ]);

        $response->assertOk();
        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_profile_update_validates_email_uniqueness(): void
    {
        $existing = User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patchJson('/api/v1/profile', [
            'name' => $user->name,
            'email' => 'taken@example.com',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_profile_update_requires_authentication(): void
    {
        $response = $this->patchJson('/api/v1/profile', [
            'name' => 'X',
            'email' => 'x@example.com',
        ]);

        $response->assertStatus(401);
    }

    public function test_password_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->putJson('/api/v1/profile/password', [
            'current_password' => 'password',
            'password' => 'new-password-1',
            'password_confirmation' => 'new-password-1',
        ]);

        $response->assertNoContent();
        $this->assertTrue(Hash::check('new-password-1', $user->refresh()->password));
    }

    public function test_password_update_requires_correct_current_password(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->putJson('/api/v1/profile/password', [
            'current_password' => 'wrong',
            'password' => 'new-password-1',
            'password_confirmation' => 'new-password-1',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['current_password']);
    }

    public function test_password_update_requires_confirmation_match(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->putJson('/api/v1/profile/password', [
            'current_password' => 'password',
            'password' => 'new-password-1',
            'password_confirmation' => 'different',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['password']);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->deleteJson('/api/v1/profile', [
            'password' => 'password',
        ]);

        $response->assertNoContent();
        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_account_deletion_requires_correct_password(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->deleteJson('/api/v1/profile', [
            'password' => 'wrong',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['password']);
        $this->assertNotNull($user->fresh());
    }

    public function test_legacy_profile_route_redirects_to_settings_tab(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/profile');

        $response->assertRedirect('/app/settings?tab=profile');
    }
}
