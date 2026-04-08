<?php

namespace Tests\Feature;

use App\Models\GraphPosition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GraphPositionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'graphtest@example.com',
            'password' => bcrypt('password'),
        ]);
    }

    public function test_bulk_upsert_creates_positions(): void
    {
        $positions = [
            ['node_type' => 'prompt', 'node_id' => 1, 'x' => 100.0, 'y' => 200.0],
            ['node_type' => 'fragment', 'node_id' => 2, 'x' => 300.0, 'y' => 400.0],
        ];

        $count = GraphPosition::bulkUpsert($this->user->id, $positions);

        $this->assertEquals(2, $count);
        $this->assertDatabaseCount('graph_positions', 2);
        $this->assertDatabaseHas('graph_positions', [
            'user_id' => $this->user->id,
            'node_type' => 'prompt',
            'node_id' => 1,
            'x' => 100.0,
            'y' => 200.0,
        ]);
    }

    public function test_bulk_upsert_updates_existing_positions(): void
    {
        GraphPosition::create([
            'user_id' => $this->user->id,
            'node_type' => 'prompt',
            'node_id' => 1,
            'x' => 100.0,
            'y' => 200.0,
        ]);

        $positions = [
            ['node_type' => 'prompt', 'node_id' => 1, 'x' => 500.0, 'y' => 600.0],
        ];

        GraphPosition::bulkUpsert($this->user->id, $positions);

        $this->assertDatabaseCount('graph_positions', 1);
        $this->assertDatabaseHas('graph_positions', [
            'user_id' => $this->user->id,
            'node_type' => 'prompt',
            'node_id' => 1,
            'x' => 500.0,
            'y' => 600.0,
        ]);
    }

    public function test_bulk_upsert_scoped_to_user(): void
    {
        $otherUser = User::create([
            'name' => 'Other User',
            'email' => 'other@example.com',
            'password' => bcrypt('password'),
        ]);

        GraphPosition::create([
            'user_id' => $otherUser->id,
            'node_type' => 'prompt',
            'node_id' => 1,
            'x' => 10.0,
            'y' => 20.0,
        ]);

        $positions = [
            ['node_type' => 'prompt', 'node_id' => 1, 'x' => 500.0, 'y' => 600.0],
        ];

        GraphPosition::bulkUpsert($this->user->id, $positions);

        $this->assertDatabaseCount('graph_positions', 2);
        $this->assertDatabaseHas('graph_positions', [
            'user_id' => $otherUser->id,
            'x' => 10.0,
        ]);
        $this->assertDatabaseHas('graph_positions', [
            'user_id' => $this->user->id,
            'x' => 500.0,
        ]);
    }

    public function test_belongs_to_user(): void
    {
        $position = GraphPosition::create([
            'user_id' => $this->user->id,
            'node_type' => 'prompt',
            'node_id' => 1,
            'x' => 100.0,
            'y' => 200.0,
        ]);

        $this->assertEquals($this->user->id, $position->user->id);
    }
}
