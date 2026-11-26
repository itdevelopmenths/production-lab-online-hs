<?php

namespace App\Services\Qa;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

/**
 * Jam global yang bisa digeser maju (offset menit) untuk pengujian QA.
 * Offset disimpan di file JSON supaya dibagi antar proses (web, queue, console).
 */
class QaClock
{
    /** Menandai bahwa apply() pernah memasang test-now. */
    private static bool $applied = false;

    public function enabled(): bool
    {
        return (bool) config('qa.time_tools') && ! app()->environment('production');
    }

    public function offsetMinutes(): int
    {
        $data = $this->readFile();

        if (! isset($data['offset_minutes']) || ! is_numeric($data['offset_minutes'])) {
            return 0;
        }

        $offset = (int) $data['offset_minutes'];

        return $offset > 0 ? $offset : 0;
    }

    public function setOffsetMinutes(int $minutes, ?string $actor = null): void
    {
        if ($minutes < 0) {
            throw new InvalidArgumentException('Offset menit tidak boleh negatif.');
        }

        $path = $this->path();
        File::ensureDirectoryExists(dirname($path));

        File::put($path, json_encode([
            'offset_minutes' => $minutes,
            'updated_at' => $this->realNow()->toIso8601String(),
            'updated_by' => $actor,
        ], JSON_PRETTY_PRINT));
    }

    public function advance(int $minutes, ?string $actor = null): void
    {
        // Tanpa actor baru, pertahankan actor terakhir yang tercatat.
        $actor ??= $this->readFile()['updated_by'] ?? null;

        $this->setOffsetMinutes($this->offsetMinutes() + $minutes, $actor);
    }

    public function reset(?string $actor = null): void
    {
        $this->setOffsetMinutes(0, $actor);
    }

    /** Jam asli, tidak terpengaruh test-now. */
    public function realNow(): Carbon
    {
        return Carbon::instance(new \DateTimeImmutable('now', new \DateTimeZone(config('app.timezone'))));
    }

    public function fakeNow(): Carbon
    {
        return $this->realNow()->addMinutes($this->offsetMinutes());
    }

    public function apply(): void
    {
        if (! $this->enabled()) {
            return;
        }

        if ($this->offsetMinutes() > 0) {
            Carbon::setTestNow($this->fakeNow());
            self::$applied = true;

            return;
        }

        if (self::$applied) {
            Carbon::setTestNow();
            self::$applied = false;
        }
    }

    /** Isi file clock sebagai array; kosong bila file tidak ada atau rusak. */
    private function readFile(): array
    {
        $path = $this->path();

        if (! is_file($path)) {
            return [];
        }

        $data = json_decode((string) @file_get_contents($path), true);

        return is_array($data) ? $data : [];
    }

    private function path(): string
    {
        return (string) config('qa.clock_path');
    }
}
