<?php

namespace Tests\Feature;

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class SecurityHeadersCspTest extends TestCase
{
    // ==========================================
    // 1. BLACK-BOX TESTING (HTTP Header Contract)
    // ==========================================

    public function test_blackbox_api_response_has_hardened_csp_without_unsafe_directives(): void
    {
        $response = $this->get('/api/health');

        $response->assertHeader('Content-Security-Policy');

        $csp = $response->headers->get('Content-Security-Policy');

        // Verify unsafe-eval and unsafe-inline in script-src are ELIMINATED
        $this->assertStringNotContainsString("'unsafe-eval'", $csp, 'CSP must not contain unsafe-eval');
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $csp, 'script-src must not allow un-nonced unsafe-inline');

        // Verify nonce is present in script-src
        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-[A-Za-z0-9+\\/=]+'/", $csp);

        // Verify essential security directives
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
    }

    public function test_blackbox_all_standard_security_headers_are_present(): void
    {
        $response = $this->get('/api/health');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
    }

    public function test_blackbox_hsts_is_not_sent_in_non_production_environment(): void
    {
        $response = $this->get('/api/health');

        $this->assertNull($response->headers->get('Strict-Transport-Security'));
    }

    // ==========================================
    // 2. WHITE-BOX TESTING (Middleware & Nonce Logic)
    // ==========================================

    public function test_whitebox_middleware_generates_unique_nonce_per_request(): void
    {
        $middleware = new SecurityHeaders();

        $req1 = Request::create('/test-1', 'GET');
        $resp1 = $middleware->handle($req1, fn ($r) => new Response('OK'));

        $req2 = Request::create('/test-2', 'GET');
        $resp2 = $middleware->handle($req2, fn ($r) => new Response('OK'));

        $nonce1 = $req1->attributes->get('csp_nonce');
        $nonce2 = $req2->attributes->get('csp_nonce');

        $this->assertNotEmpty($nonce1);
        $this->assertNotEmpty($nonce2);
        $this->assertNotEquals($nonce1, $nonce2, 'Nonces across separate requests must be unique');

        // Verify base64 string format (16 bytes = 24 base64 chars with padding)
        $this->assertEquals(24, strlen($nonce1));
    }

    public function test_whitebox_request_attribute_matches_header_nonce(): void
    {
        $middleware = new SecurityHeaders();
        $request = Request::create('/test-match', 'GET');

        $response = $middleware->handle($request, fn ($r) => new Response('OK'));

        $attrNonce = $request->attributes->get('csp_nonce');
        $cspHeader = $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("'nonce-{$attrNonce}'", $cspHeader);
    }

    // ==========================================
    // 3. GREY-BOX TESTING (Application & View Lifecycle)
    // ==========================================

    public function test_greybox_csp_header_is_applied_consistently_across_multiple_endpoints(): void
    {
        // 1. Public health check endpoint
        $res1 = $this->get('/api/health');
        $res1->assertHeader('Content-Security-Policy');
        $this->assertStringNotContainsString("'unsafe-eval'", $res1->headers->get('Content-Security-Policy'));

        // 2. Post auth endpoint (422 validation response)
        $res2 = $this->postJson('/api/auth/login', []);
        $res2->assertHeader('Content-Security-Policy');
        $this->assertStringNotContainsString("'unsafe-eval'", $res2->headers->get('Content-Security-Policy'));
    }

    public function test_greybox_view_receives_shared_csp_nonce_variable(): void
    {
        $this->get('/api/health');

        $this->assertTrue(view()->shared('cspNonce') !== null);
        $this->assertEquals(24, strlen(view()->shared('cspNonce')));
    }
}
