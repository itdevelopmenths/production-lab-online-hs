<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DesignSystemFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $this->seed();
        Model::preventLazyLoading(true);
    }
    public function test_css_foundation_contains_blueprint_tokens_dark_variant_and_fluid_scaling(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $vite = file_get_contents(base_path('vite.config.js'));
        $js = file_get_contents(resource_path('js/app.js'));

        $this->assertIsString($css);
        $this->assertIsString($vite);
        $this->assertIsString($js);

        // Font weights 300-800 lokal & bebas Bunny CDN Instrument Sans
        $this->assertStringContainsString("@import '@fontsource/inter/latin-300.css';", $css);
        $this->assertStringContainsString("@import '@fontsource/inter/latin-800.css';", $css);
        $this->assertStringNotContainsString('Instrument Sans', $vite);

        // Tailwind v4 dark variant & token inti Blueprint
        $this->assertStringContainsString('@custom-variant dark (&:is(.dark *));', $css);
        foreach ([
            '--bg: #FFFFFF;',
            '--bg-2: #FBFBFB;',
            '--bg-3: #F5F5F5;',
            '--border: #E8E8E8;',
            '--color-bg: var(--bg);',
            '--color-brand-500: #000000;',
            '--color-gray-150:',
            '--shadow-theme-xs:',
            '@utility glass',
            '@utility custom-scrollbar',
            '.sidebar-active-tab',
            '.swal2-popup',
            'html.hs-app-shell',
            'clamp(0.8125rem, calc(0.5rem + 0.4167vw), 1rem)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $css);
        }

        // Theme controller di app.js
        $this->assertStringContainsString('window.hsTheme', $js);
        $this->assertStringContainsString('hs.theme', $js);
    }

    public function test_app_shell_renders_blueprint_sidebar_glass_header_and_theme_toggle(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $this->actingAs($manager);

        $dashboard = $this->get(route('dashboard'));
        $dashboard->assertOk();
        $dashboard->assertSee('hs.theme', false);
        $dashboard->assertSee('bg-gray-50 dark:bg-gray-950', false);
        $dashboard->assertSee('lg:w-[264px]', false);
        $dashboard->assertSee('sidebar-active-tab', false);
        $dashboard->assertSee('h-16', false);
        $dashboard->assertSee('backdrop-blur-md', false);
        $dashboard->assertSee('hsTheme.toggle()', false);
        $dashboard->assertSee('Logo HS - black.png', false);

        $produk = $this->get(route('produk.index'));
        $produk->assertOk();
        $produk->assertSee('#627EF2', false);
    }
}
