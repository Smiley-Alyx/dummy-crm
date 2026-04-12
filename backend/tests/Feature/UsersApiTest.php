<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsersApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_index_returns_paginated_list(): void
    {
        User::factory()->create([
            'name' => 'Dev One',
            'email' => 'dev1@example.test',
        ]);

        $res = $this->getJson('/api/users?per_page=10');

        $res->assertOk();
        $res->assertJsonStructure([
            'data',
            'current_page',
            'per_page',
        ]);

        $this->assertCount(1, $res->json('data'));
        $this->assertSame('Dev One', $res->json('data.0.name'));
    }
}
