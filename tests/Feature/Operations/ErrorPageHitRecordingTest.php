<?php

namespace Tests\Feature\Operations;

use App\Models\ErrorPageHit;
use App\Services\Operations\ErrorPageHitRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErrorPageHitRecordingTest extends TestCase
{
    use RefreshDatabase;

    public function test_html_404_increments_daily_counter(): void
    {
        $this->get('/this-route-definitely-does-not-exist-'.uniqid())
            ->assertNotFound();

        $row = ErrorPageHit::query()->where('status', 404)->first();

        $this->assertNotNull($row);
        $this->assertSame(1, (int) $row->hits);
        $this->assertTrue($row->day->isToday());
    }

    public function test_json_404_is_not_counted(): void
    {
        $this->getJson('/api-missing-'.uniqid())
            ->assertNotFound();

        $this->assertSame(0, ErrorPageHit::query()->count());
    }

    public function test_summarize_groups_gateway_and_server_statuses(): void
    {
        $recorder = app(ErrorPageHitRecorder::class);
        $recorder->record(404);
        $recorder->record(500);
        $recorder->record(505);
        $recorder->record(502);
        $recorder->record(503);

        $summary = $recorder->summarize();

        $this->assertSame(1, $summary['not_found']);
        $this->assertSame(2, $summary['server_error']);
        $this->assertSame(2, $summary['gateway']);
    }

    public function test_static_gateway_unavailable_page_exists_for_edge_proxy_use(): void
    {
        $path = public_path('gateway-unavailable.html');

        $this->assertFileExists($path);
        $this->assertStringContainsString('الخدمة غير جاهزة حالياً', (string) file_get_contents($path));
    }
}
