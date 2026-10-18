<?php

namespace Tests\Feature\Support;

use App\Filament\Pages\SupportInbox;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SupportInboxRouteSmokeTest extends TestCase
{
    public function test_support_inbox_route_is_registered(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->assertTrue(
            class_exists(SupportInbox::class),
            'SupportInbox class missing'
        );

        // نوحّد فواصل المسارات حتى ينجح الاختبار على ويندوز (\\) ولينكس (/).
        $this->assertSame(
            str_replace('\\', '/', base_path('app/Filament/Pages/SupportInbox.php')),
            str_replace('\\', '/', (new \ReflectionClass(SupportInbox::class))->getFileName())
        );

        $pages = Filament::getPanel('admin')->getPages();
        $this->assertContains(SupportInbox::class, $pages);

        $this->assertTrue(
            Route::has('filament.admin.pages.support-inbox'),
            'Named route missing. Routes: '.implode(',', collect(Route::getRoutes())->map->getName()->filter(fn ($n) => is_string($n) && str_contains($n, 'support'))->all())
        );

        $this->assertStringContainsString('/admin/support-inbox', SupportInbox::getUrl(panel: 'admin'));
    }
}
