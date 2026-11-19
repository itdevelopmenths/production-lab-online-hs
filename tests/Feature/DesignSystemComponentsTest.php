<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class DesignSystemComponentsTest extends TestCase
{
    public function test_core_primitives_match_blueprint_markup_and_dark_mode_parity(): void
    {
        $btn = Blade::render('<x-button variant="primary" size="sm">Simpan</x-button>');
        $this->assertStringContainsString('from-[#1e1e1e] to-[#0a0a0a]', $btn);
        $this->assertStringContainsString('rounded-[6px]', $btn);
        $this->assertStringContainsString('active:scale-[0.98]', $btn);

        $tabs = Blade::render('<x-status-tabs :items="[[\'key\' => \'all\', \'label\' => \'Semua\', \'count\' => 12]]" active="all" />');
        $this->assertStringContainsString('bg-[#f3f4f6] dark:bg-gray-900', $tabs);
        $this->assertStringContainsString('bg-white dark:bg-gray-800', $tabs);

        $stat = Blade::render('<x-stat-box title="Total Nilai" value="12.500.000" prefix="Rp" iconColor="emerald" trend="+8.5%"><x-slot:icon><svg></svg></x-slot:icon></x-stat-box>');
        $this->assertStringContainsString('text-[28px]', $stat);
        $this->assertStringContainsString('tracking-[-0.308px]', $stat);
        $this->assertStringContainsString('bg-emerald-50 dark:bg-emerald-500/15', $stat);
        $this->assertStringContainsString('flex-1 min-w-0', $stat);
        $this->assertStringContainsString('break-words', $stat);

        $card = Blade::render("<x-card title=\"Data PO\" :count=\"5\"><x-slot:icon><svg></svg></x-slot:icon>\nIsi</x-card>");
        $this->assertStringContainsString('rounded-[12px]', $card);
        $this->assertStringContainsString('size-[42px]', $card);
        $this->assertStringContainsString('text-[16px]', $card);

        $pill = Blade::render('<x-badge variant="completed" :pill="true">Selesai</x-badge>');
        $this->assertStringContainsString('rounded-full', $pill);
        $this->assertStringContainsString('dark:bg-emerald-500/10', $pill);
    }
}
