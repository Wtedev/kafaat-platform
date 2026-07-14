<?php

namespace Tests\Feature\Operations;

use App\Filament\Pages\ErrorPageStatsPage;
use App\Models\ErrorPageVisit;
use App\Models\User;
use App\Services\Operations\ErrorPageVisitRecorder;
use Filament\Facades\Filament;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class ErrorPageVisitsSystemTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbacRoles();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_arabic_404_page_is_shown(): void
    {
        $response = $this->get('/missing-route-'.uniqid());

        $response->assertNotFound();
        $response->assertSee('الصفحة غير موجودة', false);
        $response->assertDontSee('Stack trace', false);
        $response->assertDontSee('NotFoundHttpException', false);
    }

    public function test_arabic_403_page_is_shown(): void
    {
        try {
            abort(403);
        } catch (HttpException $e) {
            $rendered = $this->app[ExceptionHandler::class]
                ->render(Request::create('/forbidden-test', 'GET'), $e);

            $this->assertSame(403, $rendered->getStatusCode());
            $this->assertStringContainsString('غير مصرح', $rendered->getContent());
            $this->assertStringNotContainsString('HttpException', $rendered->getContent());
        }
    }

    public function test_arabic_419_page_content(): void
    {
        $html = $this->app['view']->make('errors.419')->render();

        $this->assertStringContainsString('انتهت الجلسة', $html);
        $this->assertStringContainsString('إعادة تحميل', $html);
    }

    public function test_arabic_429_page_is_shown_via_abort(): void
    {
        try {
            abort(429);
        } catch (HttpException $e) {
            $rendered = $this->app[ExceptionHandler::class]
                ->render(Request::create('/too-many', 'GET'), $e);

            $this->assertSame(429, $rendered->getStatusCode());
            $this->assertStringContainsString('طلبات كثيرة', $rendered->getContent());
        }
    }

    public function test_html_404_records_a_visit_with_correct_status(): void
    {
        $path = '/this-route-definitely-does-not-exist-'.uniqid();

        $this->get($path)->assertNotFound();

        $row = ErrorPageVisit::query()->where('status_code', 404)->first();

        $this->assertNotNull($row);
        $this->assertSame(404, (int) $row->status_code);
        $this->assertStringContainsString('this-route-definitely-does-not-exist', (string) $row->requested_url);
        $this->assertSame('GET', $row->request_method);
    }

    public function test_same_request_is_not_double_logged(): void
    {
        $recorder = app(ErrorPageVisitRecorder::class);
        $request = Request::create('/once', 'GET');
        $response = new Response('err', 404, ['Content-Type' => 'text/html']);

        $recorder->recordFromResponse($request, $response);
        $recorder->recordFromResponse($request, $response);

        $this->assertSame(1, ErrorPageVisit::query()->count());
    }

    public function test_sensitive_query_params_are_redacted(): void
    {
        $recorder = app(ErrorPageVisitRecorder::class);
        $request = Request::create('/login?password=secret&token=abc&email=a@b.com', 'GET');

        $sanitized = $recorder->sanitizeUrl($request);

        $this->assertTrue(
            str_contains($sanitized, '[redacted]') || str_contains($sanitized, '%5Bredacted%5D'),
            "Expected redacted marker in: {$sanitized}"
        );
        $this->assertStringNotContainsString('secret', $sanitized);
        $this->assertStringNotContainsString('abc', $sanitized);
        $this->assertStringContainsString('email=', $sanitized);
    }

    public function test_authorization_header_is_never_stored(): void
    {
        $recorder = app(ErrorPageVisitRecorder::class);
        $request = Request::create('/secret-path', 'GET');
        $request->headers->set('Authorization', 'Bearer super-secret-token');
        $request->headers->set('Cookie', 'session=xyz');

        $recorder->recordFromResponse($request, new Response('', 404, ['Content-Type' => 'text/html']));

        $row = ErrorPageVisit::query()->first();
        $this->assertNotNull($row);
        $payload = json_encode($row->toArray());
        $this->assertStringNotContainsString('super-secret-token', (string) $payload);
        $this->assertStringNotContainsString('Bearer', (string) $payload);
        $this->assertStringNotContainsString('session=xyz', (string) $payload);
    }

    public function test_error_page_still_renders_when_database_insert_fails(): void
    {
        Schema::drop('error_page_visits');

        $response = $this->get('/missing-when-db-down-'.uniqid());
        $response->assertNotFound();
        $response->assertSee('الصفحة غير موجودة', false);

        $this->artisan('migrate', [
            '--path' => 'database/migrations/2026_07_14_090000_create_error_page_visits_table.php',
            '--force' => true,
        ])->assertSuccessful();
    }

    public function test_beneficiary_cannot_access_error_stats_page(): void
    {
        $user = User::factory()->create([
            'role_type' => 'beneficiary',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(ErrorPageStatsPage::getUrl())
            ->assertForbidden();
    }

    public function test_admin_can_access_error_stats_page(): void
    {
        $admin = User::factory()->create([
            'role_type' => 'admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('admin');

        Livewire::actingAs($admin)
            ->test(ErrorPageStatsPage::class)
            ->assertSuccessful()
            ->assertSee('إحصاءات صفحات الأخطاء');
    }

    public function test_health_up_returns_200_without_db_and_is_not_counted(): void
    {
        $before = ErrorPageVisit::query()->count();

        $this->get('/up')->assertOk();

        $this->assertSame($before, ErrorPageVisit::query()->count());
    }

    public function test_json_404_is_not_counted(): void
    {
        $this->getJson('/api-missing-'.uniqid())->assertNotFound();

        $this->assertSame(0, ErrorPageVisit::query()->count());
    }

    public function test_filters_and_pagination_on_stats_page(): void
    {
        $admin = User::factory()->create([
            'role_type' => 'admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('admin');

        foreach (range(1, 20) as $i) {
            ErrorPageVisit::query()->create([
                'status_code' => $i % 2 === 0 ? 404 : 500,
                'requested_url' => '/filter-demo/page-'.$i,
                'route_name' => null,
                'request_method' => 'GET',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'UA-'.$i,
                'referer' => null,
                'user_id' => null,
                'exception_class' => null,
            ]);
        }

        Livewire::actingAs($admin)
            ->test(ErrorPageStatsPage::class)
            ->set('filterStatus', '404')
            ->set('filterUrl', 'filter-demo')
            ->call('applyFilters')
            ->assertSet('stats.total', 10)
            ->assertSee('/filter-demo/page-');
    }

    public function test_prune_command_deletes_old_rows(): void
    {
        $old = ErrorPageVisit::query()->create([
            'status_code' => 404,
            'requested_url' => '/old',
            'route_name' => null,
            'request_method' => 'GET',
            'ip_address' => null,
            'user_agent' => null,
            'referer' => null,
            'user_id' => null,
            'exception_class' => null,
        ]);
        $old->forceFill(['created_at' => now()->subDays(120), 'updated_at' => now()->subDays(120)])->save();

        ErrorPageVisit::query()->create([
            'status_code' => 404,
            'requested_url' => '/new',
            'route_name' => null,
            'request_method' => 'GET',
            'ip_address' => null,
            'user_agent' => null,
            'referer' => null,
            'user_id' => null,
            'exception_class' => null,
        ]);

        $this->artisan('error-pages:prune', ['--days' => 90])
            ->assertSuccessful();

        $this->assertSame(1, ErrorPageVisit::query()->count());
        $this->assertSame('/new', ErrorPageVisit::query()->value('requested_url'));
    }

    public function test_beneficiary_cannot_access_stats_can_access_gate(): void
    {
        $user = User::factory()->create([
            'role_type' => 'beneficiary',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user);
        $this->assertFalse(ErrorPageStatsPage::canAccess());
    }

    public function test_admin_prune_action_removes_old_records(): void
    {
        $admin = User::factory()->create([
            'role_type' => 'admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('admin');

        $old = ErrorPageVisit::query()->create([
            'status_code' => 500,
            'requested_url' => '/ancient',
            'route_name' => null,
            'request_method' => 'GET',
            'ip_address' => null,
            'user_agent' => null,
            'referer' => null,
            'user_id' => null,
            'exception_class' => null,
        ]);
        $old->forceFill(['created_at' => now()->subDays(100), 'updated_at' => now()->subDays(100)])->save();

        Livewire::actingAs($admin)
            ->test(ErrorPageStatsPage::class)
            ->callAction('prune')
            ->assertHasNoActionErrors();

        $this->assertSame(0, ErrorPageVisit::query()->count());
    }

    public function test_emergency_fallback_assets_exist(): void
    {
        $this->assertFileExists(base_path('emergency-fallback/index.html'));
        $this->assertFileExists(base_path('emergency-fallback/styles.css'));
        $html = (string) file_get_contents(base_path('emergency-fallback/index.html'));
        $this->assertStringContainsString('الخدمة غير متاحة مؤقتًا', $html);
        $this->assertStringContainsString('Cloudflare', $html);
    }

    public function test_favicon_noise_is_not_recorded(): void
    {
        $this->get('/favicon.ico');

        $this->assertSame(0, ErrorPageVisit::query()->count());
    }

    public function test_static_gateway_unavailable_page_still_exists(): void
    {
        $path = public_path('gateway-unavailable.html');

        $this->assertFileExists($path);
        $this->assertStringContainsString('الخدمة غير جاهزة حالياً', (string) file_get_contents($path));
    }
}
