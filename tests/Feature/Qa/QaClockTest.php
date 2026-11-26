<?php

namespace Tests\Feature\Qa;

use App\Models\User;
use App\Services\Qa\QaClock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Tests\TestCase;

class QaClockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'qa.time_tools' => true,
            'qa.clock_path' => storage_path('framework/testing/qa-clock-'.uniqid().'.json'),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        @unlink(config('qa.clock_path'));

        parent::tearDown();
    }

    private function clock(): QaClock
    {
        return app(QaClock::class);
    }

    public function test_disabled_when_flag_false(): void
    {
        config(['qa.time_tools' => false]);

        $this->assertFalse($this->clock()->enabled());
    }

    public function test_disabled_in_production_even_if_flag_true(): void
    {
        $this->app['env'] = 'production';

        try {
            $this->assertFalse($this->clock()->enabled());
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    public function test_offset_defaults_to_zero_without_file(): void
    {
        $this->assertSame(0, $this->clock()->offsetMinutes());
    }

    public function test_offset_zero_when_file_corrupt(): void
    {
        file_put_contents(config('qa.clock_path'), '{nope');

        $this->assertSame(0, $this->clock()->offsetMinutes());
    }

    public function test_offset_zero_when_file_negative(): void
    {
        file_put_contents(config('qa.clock_path'), '{"offset_minutes":-30}');

        $this->assertSame(0, $this->clock()->offsetMinutes());
    }

    public function test_set_and_advance_persist_to_file(): void
    {
        $this->clock()->setOffsetMinutes(60, 'qa@x');
        $this->clock()->advance(30);

        $this->assertSame(90, $this->clock()->offsetMinutes());

        $data = json_decode(file_get_contents(config('qa.clock_path')), true);
        $this->assertSame(90, $data['offset_minutes']);
        $this->assertSame('qa@x', $data['updated_by'] ?? null);
    }

    public function test_set_negative_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->clock()->setOffsetMinutes(-1);
    }

    public function test_apply_shifts_now_by_offset(): void
    {
        $this->clock()->setOffsetMinutes(3 * 1440);
        $this->clock()->apply();

        $this->assertLessThanOrEqual(
            1,
            abs(now()->diffInMinutes($this->clock()->realNow()->addDays(3)))
        );
    }

    public function test_apply_after_reset_clears_test_now(): void
    {
        $this->clock()->setOffsetMinutes(60);
        $this->clock()->apply();
        $this->assertTrue(Carbon::hasTestNow());

        $this->clock()->reset();
        $this->clock()->apply();

        $this->assertFalse(Carbon::hasTestNow());
    }

    public function test_apply_noop_when_disabled(): void
    {
        file_put_contents(config('qa.clock_path'), '{"offset_minutes":60}');
        config(['qa.time_tools' => false]);

        $this->clock()->apply();

        $this->assertFalse(Carbon::hasTestNow());
    }

    public function test_web_request_runs_under_fake_clock(): void
    {
        $this->seed();
        $this->clock()->setOffsetMinutes(3 * 1440);

        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $this->actingAs($manager)->get(route('dashboard'));

        $this->assertSame(
            $this->clock()->realNow()->addDays(3)->toDateString(),
            now()->toDateString()
        );
    }
}
