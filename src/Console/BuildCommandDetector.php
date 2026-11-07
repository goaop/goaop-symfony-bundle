<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Symfony\GoAopBundle\Console;

/**
 * Tells whether the process runs a console command that builds the AOP cache.
 *
 * With Features::PREBUILT_CACHE a class below the include paths that the cache misses fails to load (#808 of
 * goaop/framework), except in a process marked as one that builds the cache, where it is loaded unwoven. The commands
 * that build the cache boot the application first, and with it the aspect kernel, so the classes they load before
 * the build starts must not fail: `cache:clear` compiles the new container (which loads the service classes) before
 * its cache warmers run, the other cache warmers of `cache:warmup` run before the AOP one, and the commands of the
 * framework mark the process only once they run. The command is read from the command line when the bundle boots,
 * before any of them loads a class, the way the console application finds it: options before the name are skipped,
 * and an abbreviated name (`cache:cl`, `c:c`) counts when it can stand for one of these commands.
 *
 * @internal
 */
final class BuildCommandDetector
{
    /**
     * Console commands that build the AOP cache
     */
    public const array BUILD_COMMANDS = ['cache:clear', 'cache:warmup', 'cache:warmup:aop', 'debug:weaving'];

    /**
     * Static helper, never instantiated
     *
     * @codeCoverageIgnore
     */
    private function __construct() {}

    /**
     * Tells whether this process is a console command that builds the AOP cache
     */
    public static function isBuildProcess(): bool
    {
        $argv = $_SERVER['argv'] ?? null;
        if (!in_array(PHP_SAPI, ['cli', 'phpdbg'], true) || !is_array($argv)) {
            return false;
        }

        return self::isBuildCommand(array_values(array_filter($argv, is_string(...))));
    }

    /**
     * Tells whether the command line runs a console command that builds the AOP cache
     *
     * @param list<string> $argv Command line, the script first
     */
    public static function isBuildCommand(array $argv): bool
    {
        $name = self::commandName($argv);
        if ($name === null || $name === '') {
            return false;
        }
        // Each part of the name may be abbreviated, as the console application accepts it
        $parts      = array_map(static fn(string $part): string => preg_quote($part, '/'), explode(':', $name));
        $expression = '/^' . implode('[^:]*:', $parts) . '[^:]*$/i';

        return preg_grep($expression, self::BUILD_COMMANDS) !== [];
    }

    /**
     * Returns the first argument of the command line, which the console application takes as the command name
     *
     * @param list<string> $argv
     */
    private static function commandName(array $argv): ?string
    {
        $tokens = array_slice($argv, 1);
        foreach ($tokens as $index => $token) {
            if ($token === '--') {
                return $tokens[$index + 1] ?? null;
            }
            if (str_starts_with($token, '-')) {
                continue;
            }
            // The value of the only global option that takes one, given as the next token
            if ($index > 0 && in_array($tokens[$index - 1], ['-e', '--env'], true)) {
                continue;
            }

            return $token;
        }

        return null;
    }
}
