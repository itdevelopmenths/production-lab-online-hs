<?php

namespace Tests\Feature;

use Tests\TestCase;

class DesignSystemFoundationTest extends TestCase
{
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
}
