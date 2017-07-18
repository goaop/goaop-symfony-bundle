<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2015, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Symfony\GoAopBundle\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Class DebugAspectCommandTest
 */
class DebugAspectCommandTest extends TestCase
{
    public function setUp()
    {
        $process = new Process(sprintf('php %s cache:warmup:aop', realpath(__DIR__.'/../Fixtures/project/bin/console')));
        $process->run();

        $this->assertTrue($process->isSuccessful(), 'Unable to execute "cache:warmup:aop" command.');
    }

    /**
     * @test
     * @runInSeparateProcess
     */
    public function itDisplaysAspectsDebugInfo()
    {
        $process = new Process(sprintf('php %s debug:aspect', realpath(__DIR__.'/../Fixtures/project/bin/console')));
        $process->run();

        $this->assertTrue($process->isSuccessful(), 'Unable to execute "debug:aspect" command.');

        $output = $process->getOutput();

        $expected = [
            'Go\Symfony\GoAopBundle\Kernel\AspectSymfonyKernel has following enabled aspects',
            'Go\Symfony\GoAopBundle\Tests\TestProject\Aspect\LoggingAspect',
            'Go\Symfony\GoAopBundle\Tests\TestProject\Aspect\LoggingAspect->beforeMethod'
        ];

        foreach ($expected as $string) {
            $this->assertContains($string, $output);
        }
    }
}
