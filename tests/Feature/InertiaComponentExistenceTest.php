<?php

declare(strict_types=1);

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

/**
 * Guards that every Inertia component rendered by the backend has a matching
 * page component on disk.
 *
 * `resources/views/app.blade.php` renders the root view for every Inertia
 * response, and the front-end resolves the page from
 * `resources/js/Pages/{component}.jsx` (`resources/js/app.jsx`). A controller
 * that renders a component with no corresponding file therefore 500s at render
 * time — which is invisible in tests that only assert the Inertia component
 * name (e.g. `->component('X', false)`).
 *
 * Flow: scan every `inertia(...)` call under app/, extract the component name,
 * and assert a matching `.jsx` file exists under resources/js/Pages/.
 */
class InertiaComponentExistenceTest extends TestCase
{
    public function test_every_inertia_component_has_a_page_component(): void
    {
        $missing = [];

        foreach ($this->inertiaComponents() as $component) {
            $path = resource_path('js/Pages/'.$component.'.jsx');

            if (! is_file($path)) {
                $missing[$component] = $component;
            }
        }

        $this->assertSame(
            [],
            array_values($missing),
            'Inertia components with no page component (would 500 at render): '.implode(', ', $missing),
        );
    }

    public function test_at_least_one_inertia_component_was_found(): void
    {
        $this->assertNotEmpty(
            $this->inertiaComponents(),
            'The scanner found no inertia() calls — the detection logic is broken.',
        );
    }

    /**
     * @return array<int, string>
     */
    private function inertiaComponents(): array
    {
        $components = [];

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(app_path(), RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());

            if (preg_match_all('/inertia\(\s*[\'"]([^\'"]+)[\'"]/', $contents, $matches)) {
                foreach ($matches[1] as $component) {
                    $components[$component] = $component;
                }
            }
        }

        return array_values($components);
    }
}
