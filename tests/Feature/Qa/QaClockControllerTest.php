<?php

namespace Tests\Feature\Qa;

use App\Models\User;
use App\Services\Qa\QaClock;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class QaClockControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Env 'local' mengaktifkan QA tools; CSRF hanya di-skip otomatis saat env 'testing'.
        $this->app['env'] = 'local';
        $this->withoutMiddleware(PreventRequestForgery::class);

        $this->seed();

        config([
            'qa.lead_time_test' => true,
            'qa.clock_path' => storage_path('framework/testing/qa-clock-'.uniqid().'.json'),
        ]);

        $this->actingAs(User::where('email', 'manager@heavenscent.id')->firstOrFail());
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

    public function test_clock_page_404_when_disabled(): void
    {
        $this->app['env'] = 'testing';

        // Handler 404 global (bootstrap/app.php) mengalihkan request HTML dengan flash error, JSON tetap 404.
        $this->getJson(route('qa.clock'))->assertNotFound();
        $this->get(route('qa.clock'))->assertSessionHas('error');
    }

    public function test_actions_404_when_disabled(): void
    {
        $this->app['env'] = 'testing';

        $this->postJson(route('qa.clock.advance'), ['hari' => 1, 'jam' => 0])->assertNotFound();
        $this->postJson(route('qa.clock.set'), ['waktu' => $this->clock()->realNow()->addDay()->format('Y-m-d\TH:i')])->assertNotFound();
        $this->postJson(route('qa.clock.reset'))->assertNotFound();
        $this->assertSame(0, $this->clock()->offsetMinutes());
    }

    public function test_clock_page_renders_when_enabled(): void
    {
        $this->get(route('qa.clock'))
            ->assertOk()
            ->assertSee('QA Clock')
            ->assertSee('Worker queue (<code>queue:work</code>) mengikuti QA Clock pada setiap polling.', false);
    }

    public function test_advance_adds_offset(): void
    {
        $this->post(route('qa.clock.advance'), ['hari' => 2, 'jam' => 3])
            ->assertRedirect(route('qa.clock'))
            ->assertSessionHas('success');

        $this->assertSame(2 * 1440 + 180, $this->clock()->offsetMinutes());
    }

    public function test_advance_rejects_zero_total(): void
    {
        $this->post(route('qa.clock.advance'), ['hari' => 0, 'jam' => 0])
            ->assertSessionHasErrors('hari');

        $this->assertSame(0, $this->clock()->offsetMinutes());
    }

    public function test_advance_rejects_negative(): void
    {
        $this->post(route('qa.clock.advance'), ['hari' => -1])
            ->assertSessionHasErrors('hari');

        $this->assertSame(0, $this->clock()->offsetMinutes());
    }

    public function test_set_rejects_past_time(): void
    {
        $this->post(route('qa.clock.set'), [
            'waktu' => $this->clock()->realNow()->subHour()->format('Y-m-d\TH:i'),
        ])->assertSessionHasErrors('waktu');

        $this->assertSame(0, $this->clock()->offsetMinutes());
    }

    public function test_set_rejects_time_beyond_five_years(): void
    {
        $this->post(route('qa.clock.set'), [
            'waktu' => $this->clock()->realNow()->addYears(6)->format('Y-m-d\TH:i'),
        ])->assertSessionHasErrors('waktu');

        $this->assertSame(0, $this->clock()->offsetMinutes());
    }

    public function test_set_future_time_sets_offset(): void
    {
        $this->post(route('qa.clock.set'), [
            'waktu' => $this->clock()->realNow()->addDays(5)->format('Y-m-d\TH:i'),
        ])->assertRedirect(route('qa.clock'));

        $offset = $this->clock()->offsetMinutes();
        $this->assertGreaterThanOrEqual(5 * 1440 - 2, $offset);
        $this->assertLessThanOrEqual(5 * 1440, $offset);
    }

    public function test_reset_sets_offset_zero(): void
    {
        $this->clock()->setOffsetMinutes(1440);

        $this->post(route('qa.clock.reset'))->assertRedirect(route('qa.clock'));

        $this->assertSame(0, $this->clock()->offsetMinutes());
    }

    public function test_banner_shows_fake_date_when_offset(): void
    {
        $this->clock()->setOffsetMinutes(3 * 1440);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('QA CLOCK')
            ->assertSee($this->clock()->realNow()->addDays(3)->format('d M Y'))
            ->assertSee('(+3 hari 0 jam)', false);
    }

    public function test_banner_neutral_when_offset_zero(): void
    {
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Mode QA aktif')
            ->assertDontSee('QA CLOCK:');
    }

    public function test_banner_hidden_when_disabled(): void
    {
        $this->app['env'] = 'testing';

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('QA CLOCK')
            ->assertDontSee('Mode QA aktif');
    }

    public function test_any_logged_in_role_can_open_page(): void
    {
        $this->actingAs(User::where('email', 'gudang@heavenscent.id')->firstOrFail());

        $this->get(route('qa.clock'))->assertOk();
    }
}
