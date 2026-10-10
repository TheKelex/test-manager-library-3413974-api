<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_health_endpoint_returns_the_contract_response(): void
    {
        $this->getJson('/health')
            ->assertOk()
            ->assertExactJson(['status' => 'ok']);
    }

    public function test_api_health_endpoint_is_not_exposed(): void
    {
        $this->getJson('/api/v1/health')->assertNotFound();
    }
}
