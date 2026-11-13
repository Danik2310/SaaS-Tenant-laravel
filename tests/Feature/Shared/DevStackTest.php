<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use App\Console\DevStack;
use Tests\TestCase;

/**
 * Covers the decision logic only. Nothing here may spawn a process: tests that
 * started real dev servers would leak them into the suite and bind ports.
 */
class DevStackTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app()['env'] = 'testing';
        config([
            'dev.stack' => false,
            'dev.services.vite.command' => ['pnpm', 'run', 'dev'],
            'dev.services.reverb.command' => ['artisan', 'reverb:start'],
        ]);
    }

    public function test_it_stays_disabled_outside_a_local_environment(): void
    {
        app()['env'] = 'production';
        config(['dev.stack' => true]);

        $this->assertFalse((new DevStack)->shouldBoot());
    }

    public function test_it_stays_disabled_when_the_flag_is_off(): void
    {
        app()['env'] = 'local';
        config(['dev.stack' => false]);

        $this->assertFalse((new DevStack)->shouldBoot());
    }

    public function test_it_boots_when_local_and_enabled(): void
    {
        app()['env'] = 'local';
        config(['dev.stack' => true]);

        $this->assertTrue((new DevStack)->shouldBoot());
    }

    public function test_it_only_starts_services_whose_ports_are_free(): void
    {
        $stack = new DevStack;

        $this->assertSame(
            ['vite', 'reverb'],
            $stack->servicesToStart(viteListening: false, reverbListening: false),
        );
        $this->assertSame(
            ['reverb'],
            $stack->servicesToStart(viteListening: true, reverbListening: false),
        );
        $this->assertSame(
            ['vite'],
            $stack->servicesToStart(viteListening: false, reverbListening: true),
        );
        $this->assertSame(
            [],
            $stack->servicesToStart(viteListening: true, reverbListening: true),
        );
    }

    public function test_it_builds_the_configured_commands(): void
    {
        $stack = new DevStack;

        $vite = $stack->commandFor('vite');
        $this->assertContains('pnpm', $vite);
        $this->assertContains('run', $vite);
        $this->assertContains('dev', $vite);

        $reverb = $stack->commandFor('reverb');
        $this->assertContains(PHP_BINARY, $reverb);
        $this->assertContains(base_path('artisan'), $reverb);
        $this->assertContains('reverb:start', $reverb);
    }

    public function test_it_returns_no_command_for_an_unknown_service(): void
    {
        $this->assertSame([], (new DevStack)->commandFor('queue'));
    }
}
