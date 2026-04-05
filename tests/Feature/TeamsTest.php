<?php

namespace Tests\Feature;

use App\Models\Prompt;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TeamsTest extends TestCase
{
    use RefreshDatabase;

    public function test_teams_page_lists_user_teams(): void
    {
        $user = User::factory()->create();
        $team = Team::create(['name' => 'My Team', 'created_by' => $user->id]);
        $team->members()->attach($user->id, ['role' => 'owner']);

        $otherTeam = Team::create(['name' => 'Other Team', 'created_by' => $user->id]);
        // Not a member of otherTeam

        $this->actingAs($user);

        Livewire::test(\App\Livewire\Teams::class)
            ->assertSee('My Team')
            ->assertDontSee('Other Team');
    }

    public function test_create_team(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(\App\Livewire\Teams::class)
            ->set('showCreateForm', true)
            ->set('newTeamName', 'New Team')
            ->call('createTeam')
            ->assertDispatched('notify');

        $this->assertDatabaseHas('teams', ['name' => 'New Team', 'created_by' => $user->id]);

        $team = Team::where('name', 'New Team')->first();
        $this->assertTrue($team->members()->where('users.id', $user->id)->wherePivot('role', 'owner')->exists());
    }

    public function test_team_detail_shows_members(): void
    {
        $owner = User::factory()->create(['name' => 'Owner']);
        $member = User::factory()->create(['name' => 'Member']);

        $team = Team::create(['name' => 'Test Team', 'created_by' => $owner->id]);
        $team->members()->attach($owner->id, ['role' => 'owner']);
        $team->members()->attach($member->id, ['role' => 'member']);

        $this->actingAs($owner);

        Livewire::test(\App\Livewire\TeamDetail::class, ['team' => $team])
            ->assertSee('Owner')
            ->assertSee('Member');
    }

    public function test_non_member_cannot_view_team(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create(['role' => 'user']);

        $team = Team::create(['name' => 'Private Team', 'created_by' => $owner->id]);
        $team->members()->attach($owner->id, ['role' => 'owner']);

        $this->actingAs($outsider);

        $this->get(route('team.detail', $team))->assertStatus(404);
    }

    public function test_invite_member(): void
    {
        $owner = User::factory()->create();
        $newMember = User::factory()->create(['name' => 'New Guy', 'email' => 'newguy@example.com']);

        $team = Team::create(['name' => 'Test Team', 'created_by' => $owner->id]);
        $team->members()->attach($owner->id, ['role' => 'owner']);

        $this->actingAs($owner);

        Livewire::test(\App\Livewire\TeamDetail::class, ['team' => $team])
            ->set('inviteQuery', 'newguy@example.com')
            ->call('inviteMember')
            ->assertDispatched('notify');

        $this->assertTrue($team->members()->where('users.id', $newMember->id)->exists());
    }

    public function test_remove_member(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $team = Team::create(['name' => 'Test Team', 'created_by' => $owner->id]);
        $team->members()->attach($owner->id, ['role' => 'owner']);
        $team->members()->attach($member->id, ['role' => 'member']);

        $this->actingAs($owner);

        Livewire::test(\App\Livewire\TeamDetail::class, ['team' => $team])
            ->call('removeMember', $member->id)
            ->assertDispatched('notify');

        $this->assertFalse($team->members()->where('users.id', $member->id)->exists());
    }

    public function test_sole_owner_cannot_leave(): void
    {
        $owner = User::factory()->create();

        $team = Team::create(['name' => 'Test Team', 'created_by' => $owner->id]);
        $team->members()->attach($owner->id, ['role' => 'owner']);

        $this->actingAs($owner);

        Livewire::test(\App\Livewire\TeamDetail::class, ['team' => $team])
            ->call('leaveTeam')
            ->assertDispatched('notify', fn ($name, $data) => $data['type'] === 'error');

        $this->assertTrue($team->members()->where('users.id', $owner->id)->exists());
    }

    public function test_delete_team(): void
    {
        $owner = User::factory()->create();

        $team = Team::create(['name' => 'Doomed Team', 'created_by' => $owner->id]);
        $team->members()->attach($owner->id, ['role' => 'owner']);
        $teamId = $team->id;

        $this->actingAs($owner);

        Livewire::test(\App\Livewire\TeamDetail::class, ['team' => $team])
            ->call('confirmDelete')
            ->assertSet('confirmingDelete', true)
            ->call('deleteTeam')
            ->assertRedirect(route('teams'));

        $this->assertDatabaseMissing('teams', ['id' => $teamId]);
    }
}
