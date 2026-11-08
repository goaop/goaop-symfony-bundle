<?php

declare(strict_types=1);

namespace Go\Symfony\GoAopBundle\Tests\Functional;

use Composer\Autoload\ClassLoader;
use Go\Instrument\ClassLoading\AopComposerLoader;
use Go\Symfony\GoAopBundle\Kernel\ComposerLoaderExposer;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The aspect kernel replaces Composer's loader; in a build process the bundle registers it again behind the Go! AOP
 * loader, where Symfony's container dumper finds it (#46)
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class ComposerLoaderExposerTest extends FunctionalTestCase
{
    public function testComposerLoaderIsRegisteredOnceBehindTheAopLoader(): void
    {
        $this->bootKernel();
        $this->assertSame([], self::composerLoaders(), 'The aspect kernel replaced the loader of Composer');

        ComposerLoaderExposer::exposeBehindAopLoaders();
        ComposerLoaderExposer::exposeBehindAopLoaders();

        $loaders = self::composerLoaders();
        $this->assertCount(1, $loaders);
        $aopLoader = AopComposerLoader::ofProcess();
        $this->assertSame($aopLoader->getOriginalLoader(), $loaders[0]);
        $positions = array_keys(array_filter(
            spl_autoload_functions(),
            static fn (mixed $autoloader): bool => is_array($autoloader) && ($autoloader[0] === $aopLoader || $autoloader[0] === $loaders[0]),
        ));
        $this->assertSame([$aopLoader, $loaders[0]], array_map(static fn (int $position): object => spl_autoload_functions()[$position][0], $positions), 'Behind the Go! AOP loader');
    }

    public function testBuildProcessExposesTheComposerLoaderWhenTheBundleBoots(): void
    {
        $_SERVER['argv'] = ['bin/console', 'cache:clear'];

        $this->bootKernel();

        $this->assertCount(1, self::composerLoaders());
    }

    public function testOtherProcessesKeepOnlyTheAopLoader(): void
    {
        $_SERVER['argv'] = ['bin/console', 'about'];

        $this->bootKernel();

        $this->assertSame([], self::composerLoaders());
    }

    /**
     * @return list<ClassLoader>
     */
    private static function composerLoaders(): array
    {
        $loaders = [];
        foreach (spl_autoload_functions() as $autoloader) {
            if (is_array($autoloader) && $autoloader[0] instanceof ClassLoader) {
                $loaders[] = $autoloader[0];
            }
        }

        return $loaders;
    }
}
