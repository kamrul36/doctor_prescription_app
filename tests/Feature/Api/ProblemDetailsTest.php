<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class ProblemDetailsTest extends TestCase
{
    public function test_unknown_api_route_returns_problem_json(): void
    {
        $this->getJson('/api/v1/nope')
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJson(['status' => 404, 'title' => 'Not found', 'type' => 'about:blank']);
    }

    public function test_unauthenticated_request_returns_problem_json(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJson(['status' => 401, 'title' => 'Unauthenticated']);
    }

    public function test_validation_errors_are_listed(): void
    {
        $this->postJson('/api/v1/auth/login', [])
            ->assertStatus(422)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonStructure(['type', 'title', 'status', 'detail', 'instance', 'errors' => ['email']]);
    }

    public function test_web_routes_still_render_html(): void
    {
        $this->get('/login')->assertOk()->assertSee(config('app.name'));
    }
}
