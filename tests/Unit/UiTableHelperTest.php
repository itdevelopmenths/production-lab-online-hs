<?php

namespace Tests\Unit;

use App\Support\UiTableHelper;
use PHPUnit\Framework\TestCase;

class UiTableHelperTest extends TestCase
{
    public function test_action_buttons_render_blueprint_32px_squares_with_sr_only_and_dark_mode(): void
    {
        $edit = UiTableHelper::actionLink('/produk/1/edit', 'Edit', 'pencil', 'default');
        $del = UiTableHelper::actionButton("hapus('/produk/1')", 'Hapus', 'trash', 'danger');
        $ok = UiTableHelper::actionButton("approve(1)", 'Setujui', 'check', 'success');

        $this->assertStringContainsString('size-[32px]', $edit);
        $this->assertStringContainsString('rounded-[6px]', $edit);
        $this->assertStringContainsString('border-[#e8e8e8] dark:border-border', $edit);
        $this->assertStringContainsString('<span class="sr-only">Edit</span>', $edit);
        $this->assertStringContainsString('title="Edit"', $edit);

        $this->assertStringContainsString('border-[#ffc9c9] dark:border-red-500/30', $del);
        $this->assertStringContainsString('<span class="sr-only">Hapus</span>', $del);

        $this->assertStringContainsString('border-[#a4f4cf] dark:border-emerald-500/30', $ok);
        $this->assertStringContainsString('<span class="sr-only">Setujui</span>', $ok);

        $pill = UiTableHelper::statusPill('Selesai', 'completed');
        $this->assertStringContainsString('rounded-full', $pill);
        $this->assertStringContainsString('dark:bg-emerald-500/10', $pill);
    }
}
