<?php

declare(strict_types=1);

namespace App\Console;

use Symfony\Component\Process\Process;

/**
 * Companion processes for `php artisan serve` in local development.
 *
 * Flow:
 *  1. `serve` only starts PHP's built-in server, so booting the rest of the
 *     stack is opt-in (`DEV_STACK=true`) and local-only.
 *  2. Probe the Vite and Reverb ports first; a service that is already running
 *     is left alone instead of crashing on a bound port.
 *  3. Spawn the missing services with inherited STDIN/STDOUT/STDERR so their
 *     output stays in the same terminal as the server.
 *  4. On exit, kill the whole process tree (`taskkill /T` on Windows, because
 *     pnpm spawns node and a plain terminate would orphan it) and remove
 *     `public/hot` so the next `php artisan test` does not hit a stale Vite
 *     dev server.
 *  5. Windows has no pcntl signal trap, so a console control handler runs the
 *     same cleanup on Ctrl+C before the process is allowed to terminate.
 */
final class DevStack
{
    /** @var array<string, resource> */
    private array $children = [];

    private bool $startedVite = false;

    private bool $shutdownRegistered = false;

    public function shouldBoot(): bool
    {
        return app()->environment('local') && (bool) config('dev.stack', false);
    }

    /**
     * Services that still need starting, given which ports are already in use.
     *
     * @return list<string>
     */
    public function servicesToStart(bool $viteListening, bool $reverbListening): array
    {
        $services = [];

        if (! $viteListening) {
            $services[] = 'vite';
        }

        if (! $reverbListening) {
            $services[] = 'reverb';
        }

        return $services;
    }

    /**
     * Build the argv for a configured service.
     *
     * @return list<string>
     */
    public function commandFor(string $service): array
    {
        $command = (array) config("dev.services.{$service}.command", []);

        if ($command === []) {
            return [];
        }

        if ($command[0] === 'artisan') {
            $command[0] = PHP_BINARY;
            array_splice($command, 1, 0, [base_path('artisan')]);
        }

        // proc_open's array form talks to CreateProcess directly on Windows,
        // which cannot execute the pnpm.cmd shim. Route it through cmd.exe.
        if (PHP_OS_FAMILY === 'Windows' && ! $this->isExecutableImage($command[0])) {
            array_unshift($command, 'cmd', '/c');
        }

        return array_values($command);
    }

    public function boot(): void
    {
        $start = $this->servicesToStart(
            $this->portIsListening((int) config('dev.services.vite.port', 5174)),
            $this->portIsListening((int) config('dev.services.reverb.port', 8080)),
        );

        if ($start === []) {
            $this->report('Vite and Reverb are already running; nothing to start.');

            return;
        }

        foreach ($start as $service) {
            $this->start($service);
        }

        $this->registerShutdown();
    }

    public function shutdown(): void
    {
        foreach (array_reverse($this->children, true) as $service => $process) {
            $this->terminate($process);
            unset($this->children[$service]);
        }

        if ($this->startedVite) {
            @unlink(public_path('hot'));
            $this->startedVite = false;
        }

        $this->shutdownRegistered = false;
    }

    private function registerShutdown(): void
    {
        if ($this->shutdownRegistered) {
            return;
        }

        register_shutdown_function($this->shutdown(...));

        // Windows never registers ServeCommand's pcntl trap, so Ctrl+C would
        // otherwise terminate PHP without running the shutdown function.
        if (PHP_OS_FAMILY === 'Windows' && function_exists('sapi_windows_set_ctrl_handler')) {
            sapi_windows_set_ctrl_handler(function (int $event): bool {
                if (in_array($event, [PHP_WINDOWS_EVENT_CTRL_C, PHP_WINDOWS_EVENT_CTRL_BREAK], true)) {
                    $this->shutdown();
                }

                return false;
            });
        }

        $this->shutdownRegistered = true;
    }

    private function start(string $service): void
    {
        $command = $this->commandFor($service);

        if ($command === []) {
            return;
        }

        // A leftover hot file from a hard-killed session points at a dead Vite
        // server; Vite rewrites it as soon as it is ready.
        if ($service === 'vite') {
            @unlink(public_path('hot'));
        }

        // Inherited descriptors stream child output to this terminal instead of
        // buffering it in memory for the lifetime of the dev session.
        $descriptors = [
            0 => STDIN,
            1 => STDOUT,
            2 => STDERR,
        ];

        $process = proc_open($command, $descriptors, $pipes, base_path());

        if (! is_resource($process)) {
            $this->report("Could not start the {$service} dev process.");

            return;
        }

        $this->children[$service] = $process;
        $this->startedVite = $this->startedVite || $service === 'vite';

        $this->report("Started {$service}: ".implode(' ', $command));
    }

    private function portIsListening(int $port): bool
    {
        if ($port <= 0 || $port > 65535) {
            return false;
        }

        $socket = @fsockopen('127.0.0.1', $port, $errorCode, $errorMessage, 0.2);

        if (! is_resource($socket)) {
            return false;
        }

        fclose($socket);

        return true;
    }

    /**
     * @param  resource  $process
     */
    private function terminate($process): void
    {
        $status = @proc_get_status($process);

        if (is_array($status) && $status['running'] === true && (int) $status['pid'] > 0) {
            $this->killTree($process, (int) $status['pid']);
        }

        @proc_close($process);
    }

    /**
     * @param  resource  $process
     */
    private function killTree($process, int $pid): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            @proc_terminate($process);

            return;
        }

        // pnpm spawns node underneath cmd.exe; killing only the direct child
        // would leave Vite listening on 5174.
        Process::fromShellCommandline('taskkill /F /T /PID '.$pid)->run();
    }

    private function isExecutableImage(string $binary): bool
    {
        return (bool) preg_match('/\.(exe|com)$/i', $binary);
    }

    private function report(string $message): void
    {
        if (defined('STDOUT')) {
            fwrite(STDOUT, '[dev-stack] '.$message.PHP_EOL);
        }
    }
}
