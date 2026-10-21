<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Tests\TestCase;

class DeveloperToolsTest extends TestCase
{
    public function test_error_code_index_requires_the_configured_username_and_password(): void
    {
        $this->get('/docs/error-codes')->assertUnauthorized();

        $this->withHeaders([
            'Authorization' => 'Basic '.base64_encode('docs-user:docs-pass'),
        ])->get('/docs/error-codes')
            ->assertOk()
            ->assertSee('PAY-PRC-002')
            ->assertSee('ORD-DEL-001');
    }

    public function test_health_uses_the_envelope_and_replaces_unsafe_trace_ids(): void
    {
        $response = $this->withHeader('X-Trace-Id', "bad\x01<script>")
            ->get('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('trace_code', 'SYS-HLT-200')
            ->assertJsonPath('code', '200')
            ->assertJsonPath('data.status', 'ok');

        $traceId = $response->headers->get('X-Trace-Id');
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', (string) $traceId);
        $response->assertJsonPath('trace_id', $traceId);

        $this->withHeader('X-Trace-Id', 'client-trace-1234')
            ->get('/api/v1/health')
            ->assertHeader('X-Trace-Id', 'client-trace-1234');
    }

    public function test_unknown_api_routes_and_methods_return_indexed_codes(): void
    {
        $this->get('/api/v1/nope')->assertNotFound()
            ->assertJsonPath('trace_code', 'SYS-GEN-002')
            ->assertJsonPath('code', '404');
        $this->delete('/api/v1/health')->assertStatus(405)
            ->assertJsonPath('trace_code', 'SYS-GEN-003')
            ->assertJsonPath('code', '405');
    }

    public function test_api_docs_require_credentials(): void
    {
        $this->get('/docs')->assertUnauthorized();

        $this->withHeaders([
            'Authorization' => 'Basic '.base64_encode('docs-user:docs-pass'),
        ])->get('/docs')->assertOk();
    }
}
