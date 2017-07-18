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

class DebugAdvisorCommandTest extends TestCase
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
    public function itDisplaysAdvisorsDebugInfo()
    {
        $process = new Process(sprintf('php %s debug:advisor', realpath(__DIR__.'/../Fixtures/project/bin/console')));
        $process->run();

        $this->assertTrue($process->isSuccessful(), 'Unable to execute "debug:advisor" command.');

        $output = $process->getOutput();

        $expected = [
            'List of registered advisors in the container',
            'Go\Symfony\GoAopBundle\Tests\TestProject\Aspect\LoggingAspect->beforeMethod',
            '@execution(Go\Symfony\GoAopBundle\Tests\TestProject\Annotation\Loggable)',
        ];

        foreach ($expected as $string) {
            $this->assertContains($string, $output);
        }
    }

    /**
     * @test
     * @runInSeparateProcess
     */
    public function itDisplaysStatedAdvisorDebugInfo()
    {
        $process = new Process(sprintf('php %s debug:advisor --advisor="Go\Symfony\GoAopBundle\Tests\TestProject\Aspect\LoggingAspect->beforeMethod"', realpath(__DIR__.'/../Fixtures/project/bin/console')));
        $process->run();

        $this->assertTrue($process->isSuccessful(), 'Unable to execute "debug:advisor" command.');

        $output = $process->getOutput();

        $expected = [
            'Total 3 files to analyze.',
            '-> matching method Go\Symfony\GoAopBundle\Tests\TestProject\Application\Main->doSomething',
        ];

        foreach ($expected as $string) {
            $this->assertContains($string, $output);
        }
    }
}
