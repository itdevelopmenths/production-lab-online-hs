<?php

namespace Tests\Unit;

use App\Support\Dashboard\Rupiah;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RupiahTest extends TestCase
{
    public static function ringkasProvider(): array
    {
        return [
            'nol' => [0, 'Rp 0'],
            'ratusan' => [750, 'Rp 750'],
            'ribu' => [950000, 'Rp 950 rb'],
            'juta' => [850000000, 'Rp 850 jt'],
            'miliar' => [1234567890, 'Rp 1,2 M'],
            'miliar bulat' => [2000000000, 'Rp 2 M'],
            'triliun' => [2500000000000, 'Rp 2,5 T'],
        ];
    }

    #[DataProvider('ringkasProvider')]
    public function test_rupiah_ringkas_formats(float|int $nilai, string $expected): void
    {
        $this->assertSame($expected, Rupiah::ringkas((float) $nilai));
    }

    public function test_rupiah_penuh(): void
    {
        $this->assertSame('Rp 1.234.567.890', Rupiah::penuh(1234567890));
    }
}
