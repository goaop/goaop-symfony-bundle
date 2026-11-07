<?php

declare(strict_types=1);

namespace Go\Symfony\GoAopBundle\Tests\Console;

use Go\Symfony\GoAopBundle\Console\BuildCommandDetector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BuildCommandDetectorTest extends TestCase
{
    /**
     * @return iterable<string, array{list<string>, bool}>
     */
    public static function commandLines(): iterable
    {
        yield 'cache:clear' => [['bin/console', 'cache:clear'], true];
        yield 'cache:warmup' => [['bin/console', 'cache:warmup', '--no-optional-warmers'], true];
        yield 'cache:warmup:aop' => [['bin/console', 'cache:warmup:aop', '--preload'], true];
        yield 'debug:weaving' => [['bin/console', 'debug:weaving'], true];
        yield 'options before the name' => [['bin/console', '--no-debug', '-v', '--env=prod', 'cache:clear'], true];
        yield 'environment as the next token' => [['bin/console', '--env', 'prod', 'cache:clear'], true];
        yield 'short environment option' => [['bin/console', '-e', 'prod', 'cache:warmup'], true];
        yield 'end of the options' => [['bin/console', '--', 'cache:clear'], true];
        yield 'abbreviated name' => [['bin/console', 'cache:cl'], true];
        yield 'abbreviated namespace and name' => [['bin/console', 'c:w:a'], true];
        yield 'other case' => [['bin/console', 'Cache:Clear'], true];
        yield 'another command' => [['bin/console', 'messenger:consume', 'async'], false];
        yield 'another cache command' => [['bin/console', 'cache:pool:clear', 'cache.app'], false];
        yield 'a build command as an argument' => [['bin/console', 'help', 'cache:clear'], false];
        yield 'environment named like a command' => [['bin/console', '-e', 'cache:clear', 'about'], false];
        yield 'no command' => [['bin/console'], false];
        yield 'only options' => [['bin/console', '--version'], false];
    }

    /**
     * @param list<string> $argv
     */
    #[DataProvider('commandLines')]
    public function testBuildCommandsAreRecognizedOnTheCommandLine(array $argv, bool $isBuildCommand): void
    {
        $this->assertSame($isBuildCommand, BuildCommandDetector::isBuildCommand($argv));
    }
}
