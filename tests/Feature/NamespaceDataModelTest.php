<?php

namespace Tests\Feature;

use App\Models\Prompt;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NamespaceDataModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_creation_with_auto_slug(): void
    {
        $user = User::factory()->create();
        $team = Team::create([
            'name' => 'My Family',
            'created_by' => $user->id,
        ]);

        $this->assertEquals('my-family', $team->slug);
        $this->assertEquals($user->id, $team->created_by);
    }

    public function test_team_slug_collision_counter(): void
    {
        $user = User::factory()->create();
        $team1 = Team::create(['name' => 'Work', 'created_by' => $user->id]);
        $team2 = Team::create(['name' => 'Work', 'created_by' => $user->id]);

        $this->assertEquals('work', $team1->slug);
        $this->assertEquals('work-1', $team2->slug);
    }

    public function test_team_member_relationships(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $team = Team::create(['name' => 'Test Team', 'created_by' => $owner->id]);
        $team->members()->attach($owner->id, ['role' => 'owner']);
        $team->members()->attach($member->id, ['role' => 'member']);

        $this->assertCount(2, $team->members);
        $this->assertCount(1, $team->owners);
        $this->assertEquals($owner->id, $team->owners->first()->id);

        $this->assertCount(1, $owner->teams);
        $this->assertCount(1, $member->teams);
        $this->assertCount(1, $owner->ownedTeams);
        $this->assertCount(0, $member->ownedTeams);
    }

    public function test_prompt_team_sharing(): void
    {
        $user = User::factory()->create();
        $team = Team::create(['name' => 'Shared', 'created_by' => $user->id]);

        $prompt = Prompt::create([
            'name' => 'Test Prompt',
            'type' => 'prompt',
            'visibility' => 'shared',
            'created_by' => $user->id,
        ]);

        $prompt->teams()->attach($team->id);

        $this->assertCount(1, $prompt->teams);
        $this->assertCount(1, $team->prompts);
        $this->assertEquals($prompt->id, $team->prompts->first()->id);
    }

    public function test_prompt_visibility_scope_owner_sees_own(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        Prompt::create([
            'name' => 'Private Prompt',
            'type' => 'prompt',
            'visibility' => 'private',
            'created_by' => $owner->id,
        ]);

        $this->assertCount(1, Prompt::visibleTo($owner)->get());
        $this->assertCount(0, Prompt::visibleTo($other)->get());
    }

    public function test_prompt_visibility_scope_team_member_sees_shared(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();

        $team = Team::create(['name' => 'Team', 'created_by' => $owner->id]);
        $team->members()->attach($owner->id, ['role' => 'owner']);
        $team->members()->attach($member->id, ['role' => 'member']);

        $prompt = Prompt::create([
            'name' => 'Shared Prompt',
            'type' => 'prompt',
            'visibility' => 'shared',
            'created_by' => $owner->id,
        ]);
        $prompt->teams()->attach($team->id);

        // Owner sees it (own prompt)
        $this->assertCount(1, Prompt::visibleTo($owner)->get());
        // Team member sees it (shared via team)
        $this->assertCount(1, Prompt::visibleTo($member)->get());
        // Outsider does not see it
        $this->assertCount(0, Prompt::visibleTo($outsider)->get());
    }

    public function test_prompt_visibility_scope_non_member_cannot_see(): void
    {
        $owner = User::factory()->create();
        $nonMember = User::factory()->create();

        $team = Team::create(['name' => 'Secret Team', 'created_by' => $owner->id]);
        $team->members()->attach($owner->id, ['role' => 'owner']);

        $prompt = Prompt::create([
            'name' => 'Secret Prompt',
            'type' => 'prompt',
            'visibility' => 'shared',
            'created_by' => $owner->id,
        ]);
        $prompt->teams()->attach($team->id);

        $this->assertCount(0, Prompt::visibleTo($nonMember)->get());
    }

    public function test_admin_sees_all_prompts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create();

        Prompt::create([
            'name' => 'Private Prompt',
            'type' => 'prompt',
            'visibility' => 'private',
            'created_by' => $owner->id,
        ]);

        // Admin sees everything regardless of visibility
        $this->assertCount(1, Prompt::visibleTo($admin)->get());
    }

    public function test_prompt_slug_unique_per_user(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $prompt1 = Prompt::create([
            'name' => 'Email Template',
            'type' => 'prompt',
            'created_by' => $user1->id,
        ]);

        $prompt2 = Prompt::create([
            'name' => 'Email Template',
            'type' => 'prompt',
            'created_by' => $user2->id,
        ]);

        // Both users can have the same slug
        $this->assertEquals('email-template', $prompt1->slug);
        $this->assertEquals('email-template', $prompt2->slug);
        $this->assertNotEquals($prompt1->id, $prompt2->id);
    }

    public function test_prompt_slug_collision_within_same_user(): void
    {
        $user = User::factory()->create();

        $prompt1 = Prompt::create([
            'name' => 'My Prompt',
            'type' => 'prompt',
            'created_by' => $user->id,
        ]);

        $prompt2 = Prompt::create([
            'name' => 'My Prompt',
            'type' => 'prompt',
            'created_by' => $user->id,
        ]);

        $this->assertEquals('my-prompt', $prompt1->slug);
        $this->assertEquals('my-prompt-1', $prompt2->slug);
    }

    public function test_prompt_is_private_and_is_shared(): void
    {
        $user = User::factory()->create();

        $private = Prompt::create([
            'name' => 'Private',
            'type' => 'prompt',
            'visibility' => 'private',
            'created_by' => $user->id,
        ]);

        $shared = Prompt::create([
            'name' => 'Shared',
            'type' => 'prompt',
            'visibility' => 'shared',
            'created_by' => $user->id,
        ]);

        $this->assertTrue($private->isPrivate());
        $this->assertFalse($private->isShared());
        $this->assertTrue($shared->isShared());
        $this->assertFalse($shared->isPrivate());
    }

    public function test_prompt_default_visibility_is_private(): void
    {
        $user = User::factory()->create();

        $prompt = Prompt::create([
            'name' => 'New Prompt',
            'type' => 'prompt',
            'created_by' => $user->id,
        ]);

        // New prompts default to 'private' (per DB default)
        $this->assertEquals('private', $prompt->fresh()->visibility);
    }

    public function test_team_cascade_delete_cleans_pivots(): void
    {
        $user = User::factory()->create();
        $team = Team::create(['name' => 'Temp', 'created_by' => $user->id]);
        $team->members()->attach($user->id, ['role' => 'owner']);

        $prompt = Prompt::create([
            'name' => 'Shared',
            'type' => 'prompt',
            'visibility' => 'shared',
            'created_by' => $user->id,
        ]);
        $prompt->teams()->attach($team->id);

        $teamId = $team->id;
        $team->delete();

        // Pivot records should be cleaned up
        $this->assertDatabaseMissing('team_user', ['team_id' => $teamId]);
        $this->assertDatabaseMissing('prompt_team', ['team_id' => $teamId]);
        // Prompt still exists
        $this->assertDatabaseHas('prompts', ['id' => $prompt->id]);
    }

    public function test_prompt_shared_with_multiple_teams(): void
    {
        $owner = User::factory()->create();
        $member1 = User::factory()->create();
        $member2 = User::factory()->create();

        $team1 = Team::create(['name' => 'Family', 'created_by' => $owner->id]);
        $team1->members()->attach($owner->id, ['role' => 'owner']);
        $team1->members()->attach($member1->id, ['role' => 'member']);

        $team2 = Team::create(['name' => 'Work', 'created_by' => $owner->id]);
        $team2->members()->attach($owner->id, ['role' => 'owner']);
        $team2->members()->attach($member2->id, ['role' => 'member']);

        $prompt = Prompt::create([
            'name' => 'Multi Team Prompt',
            'type' => 'prompt',
            'visibility' => 'shared',
            'created_by' => $owner->id,
        ]);
        $prompt->teams()->attach([$team1->id, $team2->id]);

        // Both team members can see it
        $this->assertCount(1, Prompt::visibleTo($member1)->get());
        $this->assertCount(1, Prompt::visibleTo($member2)->get());
    }
}
