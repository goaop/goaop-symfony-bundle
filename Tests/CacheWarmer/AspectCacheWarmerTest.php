<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2015, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Symfony\GoAopBundle\Tests\CacheWarmer;

use Go\Aop\Proxy;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Go\Symfony\GoAopBundle\Tests\TestProject\Application\Main;

/**
 * Class AspectCacheWarmerTest
 */
class AspectCacheWarmerTest extends WebTestCase
{
    protected $cacheDir = __DIR__.'/../Fixtures/project/var/cache/test';

    public function setUp()
    {
        $filesystem = new Filesystem();

        if ($filesystem->exists($this->cacheDir)) {
            $filesystem->remove($this->cacheDir);
        }
    }

    /**
     * @test
     * @runInSeparateProcess
     */
    public function itWarmsUpCache()
    {
        $this->assertFalse(file_exists($this->cacheDir));

        self::bootKernel();

        $this->assertTrue(file_exists($this->cacheDir.'/aspect/_proxies/Application/Main.php'));
        $this->assertTrue(file_exists($this->cacheDir.'/aspect/Application/Main.php'));

        $reflection = new \ReflectionClass(Main::class);
        $this->assertTrue($reflection->implementsInterface(Proxy::class));
    }
}
