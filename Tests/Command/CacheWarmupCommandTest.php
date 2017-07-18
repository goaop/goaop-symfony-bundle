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
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * Class CacheWarmupCommandTest
 */
class CacheWarmupCommandTest extends TestCase
{
    protected $aspectCacheDir = __DIR__.'/../Fixtures/project/var/cache/test/aspect';

    public function setUp()
    {
        $filesystem = new Filesystem();

        if ($filesystem->exists($this->aspectCacheDir)) {
            $filesystem->remove($this->aspectCacheDir);
        }
    }

    /**
     * @test
     * @runInSeparateProcess
     */
    public function itWarmsUpCache()
    {
        $this->assertFalse(file_exists($this->aspectCacheDir));

        $process = new Process(sprintf('php %s cache:warmup:aop', realpath(__DIR__.'/../Fixtures/project/bin/console')));
        $process->run();

        $this->assertTrue($process->isSuccessful(), 'Unable to execute "cache:warmup:aop" command.');

        $this->assertTrue(file_exists($this->aspectCacheDir.'/_proxies/Application/Main.php'));
        $this->assertTrue(file_exists($this->aspectCacheDir.'/Application/Main.php'));
    }
}
