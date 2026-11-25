<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TunnelPreviewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance('env', 'local');

        Route::get('/_test/tunnel-preview', fn () => response("<p>Caf\u{00E9}</p>"));
    }

    public function test_local_tunnel_html_declares_the_utf8_byte_length(): void
    {
        $this->get('https://preview-8080.asse.devtunnels.ms/_test/tunnel-preview')
            ->assertOk()
            ->assertHeader('Content-Length', '12')
            ->assertSeeText("Caf\u{00E9}");
    }

    public function test_localhost_responses_keep_their_existing_headers(): void
    {
        $this->get('http://localhost/_test/tunnel-preview')
            ->assertOk()
            ->assertHeaderMissing('Content-Length');
    }

    public function test_production_responses_keep_their_existing_headers(): void
    {
        $this->app->instance('env', 'production');

        $this->get('https://preview-8080.asse.devtunnels.ms/_test/tunnel-preview')
            ->assertOk()
            ->assertHeaderMissing('Content-Length');
    }

    public function test_streamed_html_keeps_streaming_without_a_fixed_length(): void
    {
        Route::get('/_test/tunnel-stream', fn () => response()->stream(
            static function (): void {
                echo '<p>Stream</p>';
            },
            200,
            ['Content-Type' => 'text/html'],
        ));

        $this->get('https://preview-8080.asse.devtunnels.ms/_test/tunnel-stream')
            ->assertOk()
            ->assertStreamed()
            ->assertHeaderMissing('Content-Length');
    }

    public function test_head_requests_do_not_get_a_zero_length_for_the_get_response(): void
    {
        $this->head('https://preview-8080.asse.devtunnels.ms/_test/tunnel-preview')
            ->assertOk()
            ->assertHeaderMissing('Content-Length');
    }
}
