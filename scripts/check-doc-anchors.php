#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Verifies that `file:line` anchors in the docs still point at the right place.
 *
 * Rules:
 *   A. [`Symbol::method()`](path) `:NN`  — line NN of path must declare
 *      `function method(`.
 *   B. [`Class`](path) `:NN`             — path must have at least NN lines.
 *   C. `config/x.php:NN`                 — config file must have at least NN lines.
 *   D. code-fence `'key' => ..., // :NN` — the config file linked just before the
 *      fence must have `'key'` on line NN.
 *   E. bare `` `method()` `` `:NN`       — inferred from the most recent linked
 *      file; checked only when that file actually declares the method.
 *
 * Usage: php scripts/check-doc-anchors.php [path]
 * Exit code 1 on any drift.
 */
$root = dirname(__DIR__);
$target = $argv[1] ?? $root.'/docs';

$target = str_replace('\\', '/', $target);

$failures = [];
$checked = 0;

function doc_files(string $target): array
{
    if (is_file($target)) {
        return [$target];
    }

    $out = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if ($file->isFile() && str_ends_with($file->getFilename(), '.md')) {
            $out[] = str_replace('\\', '/', $file->getPathname());
        }
    }
    sort($out);

    return $out;
}

function resolve_target(string $docPath, string $link): ?string
{
    $link = explode('#', $link)[0];
    if ($link === '' || preg_match('#^[a-z]+://#i', $link)) {
        return null;
    }

    $base = dirname($docPath);
    $real = realpath($base.'/'.$link);

    return $real === false ? null : str_replace('\\', '/', $real);
}

function lines_of(string $path): array
{
    static $cache = [];
    if (! isset($cache[$path])) {
        $cache[$path] = is_file($path) ? file($path, FILE_IGNORE_NEW_LINES) : [];
    }

    return $cache[$path];
}

foreach (doc_files($target) as $doc) {
    $lines = lines_of($doc);
    $lastLinked = null;
    $lastConfig = null;
    $seenLinks = [];
    $inFence = false;

    foreach ($lines as $i => $line) {
        $n = $i + 1;

        if (preg_match('/^\s*```/', $line)) {
            $inFence = ! $inFence;

            continue;
        }

        if (preg_match('#\((\.\./)+[^)\s]*config/[a-z_]+\.php\)#', $line) || preg_match('#config/[a-z_]+\.php#', $line)) {
            $lastConfig = preg_match('#(config/[a-z_]+\.php)#', $line, $cm) ? $cm[1] : $lastConfig;
        }

        // D. code-fence config key anchor
        if ($inFence && $lastConfig !== null
            && preg_match("/^\s*'([a-z_]+)'\s*=>.*?\/\/\s*:(\d+)\s*$/", $line, $m)) {
            $checked++;
            $cfg = resolve_target($doc, $root.'/'.$lastConfig) ?? $root.'/'.$lastConfig;
            $cfgLines = lines_of($cfg);
            $at = (int) $m[2];
            if (! isset($cfgLines[$at - 1]) || ! str_contains($cfgLines[$at - 1], "'{$m[1]}'")) {
                $failures[] = sprintf('%s:%d  config key %s not at %s:%d', $doc, $n, $m[1], $lastConfig, $at);
            }
        }

        // C. config path anchor in prose
        if (preg_match('#`?config/[a-z_]+\.php:(\d+)`?#', $line, $m)) {
            $checked++;
            preg_match('#(config/[a-z_]+\.php):(\d+)#', $line, $mm);
            $cfg = $root.'/'.$mm[1];
            if (count(lines_of($cfg)) < (int) $mm[2]) {
                $failures[] = sprintf('%s:%d  %s:%d beyond EOF (%d lines)', $doc, $n, $mm[1], $mm[2], count(lines_of($cfg)));
            }
        }

        // A/B. linked anchor
        if (preg_match('#\[`([^`]+)`\]\(([^)\s]+)\)[\s`]*`?:(\d+)`?#', $line, $m)) {
            $label = $m[1];
            $path = resolve_target($doc, $m[2]);
            $at = (int) $m[3];
            $checked++;

            if ($path === null) {
                $failures[] = sprintf('%s:%d  unresolvable link %s', $doc, $n, $m[2]);

                continue;
            }

            if (preg_match('/\b([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $label, $fm)) {
                $fn = $fm[1];
                $target = lines_of($path);
                if (! isset($target[$at - 1]) || ! preg_match('/function\s+'.preg_quote($fn, '/').'\b/', $target[$at - 1])) {
                    $found = null;
                    foreach ($target as $k => $l) {
                        if (preg_match('/function\s+'.preg_quote($fn, '/').'\b/', $l)) {
                            $found = $k + 1;
                            break;
                        }
                    }
                    $failures[] = sprintf(
                        '%s:%d  %s() should be %s (doc says :%d)',
                        $doc, $n, $fn, $found === null ? 'not found' : $path.':'.$found, $at
                    );
                }
            } elseif (count(lines_of($path)) < $at) {
                $failures[] = sprintf('%s:%d  %s:%d beyond EOF (%d lines)', $doc, $n, $m[2], $at, count(lines_of($path)));
            }

            $lastLinked = $path;
            $seenLinks[] = $path;

            continue;
        }

        // E2. bare `Class::method()` :NN — resolve the class by link basename
        if (preg_match('#`([A-Za-z_][A-Za-z0-9_]*)::([A-Za-z_][A-Za-z0-9_]*)\(\)`\s*`?:(\d+)`?#', $line, $m)) {
            $cand = null;
            foreach ($seenLinks as $p) {
                if (basename($p) === $m[1].'.php') {
                    $cand = $p;
                }
            }
            if ($cand !== null && preg_match('/function\s+'.preg_quote($m[2], '/').'\b/', implode("\n", lines_of($cand)))) {
                $checked++;
                $at = (int) $m[3];
                if (! preg_match('/function\s+'.preg_quote($m[2], '/').'\b/', lines_of($cand)[$at - 1] ?? '')) {
                    $failures[] = sprintf('%s:%d  %s::%s() (inferred %s) doc says :%d', $doc, $n, $m[1], $m[2], $cand, $at);
                }
            }

            continue;
        }

        // E. bare `method()` :NN using the most recent linked file
        if ($lastLinked !== null && preg_match('#`([A-Za-z_][A-Za-z0-9_]*)\(\)`\s*`?:(\d+)`?#', $line, $m)) {
            $target = lines_of($lastLinked);
            $fn = $m[1];
            $at = (int) $m[2];
            $declares = false;
            foreach ($target as $l) {
                if (preg_match('/function\s+'.preg_quote($fn, '/').'\b/', $l)) {
                    $declares = true;
                    break;
                }
            }
            if ($declares) {
                $checked++;
                if (! isset($target[$at - 1]) || ! preg_match('/function\s+'.preg_quote($fn, '/').'\b/', $target[$at - 1])) {
                    $failures[] = sprintf('%s:%d  %s() (inferred %s) doc says :%d', $doc, $n, $fn, $lastLinked, $at);
                }
            }
        }
    }
}

if ($failures) {
    fwrite(STDERR, "Doc anchors are stale:\n");
    foreach ($failures as $f) {
        fwrite(STDERR, '  '.$f."\n");
    }
    fwrite(STDERR, "\n".count($failures)." stale anchor(s); {$checked} checked.\n");
    exit(1);
}

echo "OK — {$checked} doc anchors verified.\n";
