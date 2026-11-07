<?php

declare(strict_types=1);

namespace Go\Symfony\GoAopBundle\Tests\DependencyInjection;

use Go\Aop\Features;
use Go\Symfony\GoAopBundle\DependencyInjection\KernelOptionsResolver;
use Go\Symfony\GoAopBundle\Kernel\AspectSymfonyKernel;
use PHPUnit\Framework\TestCase;

class KernelOptionsResolverTest extends TestCase
{
    public function testOptionsAreTranslatedToKernelOptionNames(): void
    {
        $options = KernelOptionsResolver::fromConfig([
            'debug'              => true,
            'app_dir'            => '/app',
            'cache_dir'          => '/app/var/cache/aop',
            'cache_file_mode'    => '0750',
            'include_paths'      => ['one' => '/app/src', 'two' => '/app/lib'],
            'exclude_paths'      => ['/app/src/Legacy'],
            'parsed_files_limit' => 250,
            'features'           => ['PREBUILT_CACHE', 'INTERCEPT_INCLUDES'],
            'kernel_class'       => AspectSymfonyKernel::class,
            'cache_warmer'       => true,
            'doctrine'           => null,
        ]);

        $this->assertSame([
            'debug'            => true,
            'appDir'           => '/app',
            'cacheDir'         => '/app/var/cache/aop',
            'includePaths'     => ['/app/src', '/app/lib'],
            'excludePaths'     => ['/app/src/Legacy'],
            'parsedFilesLimit' => 250,
            'features'         => Features::PREBUILT_CACHE | Features::INTERCEPT_INCLUDES,
            'cacheFileMode'    => 0o750,
        ], $options);
    }

    public function testCacheFileModeIsOmittedWhenNotConfigured(): void
    {
        $options = KernelOptionsResolver::fromConfig([
            'debug'              => '%kernel.debug%',
            'app_dir'            => '/app',
            'cache_dir'          => '/app/var/cache/aop',
            'cache_file_mode'    => null,
            'include_paths'      => [],
            'exclude_paths'      => [],
            'parsed_files_limit' => 100,
            'features'           => [],
            'kernel_class'       => AspectSymfonyKernel::class,
            'cache_warmer'       => true,
            'doctrine'           => null,
        ]);

        $this->assertArrayNotHasKey('cacheFileMode', $options);
        $this->assertSame(0, $options['features']);
    }

    public function testCacheFileModeNormalization(): void
    {
        $this->assertNull(KernelOptionsResolver::cacheFileMode(null));
        $this->assertSame(0o770, KernelOptionsResolver::cacheFileMode(0o770));
        $this->assertSame(0o770, KernelOptionsResolver::cacheFileMode('0770'));
        $this->assertSame(0o770, KernelOptionsResolver::cacheFileMode('770'));
        $this->assertSame(0o644, KernelOptionsResolver::cacheFileMode('0o644'));
        $this->assertNull(KernelOptionsResolver::cacheFileMode('0444'), 'owner must be able to read and write');
        $this->assertNull(KernelOptionsResolver::cacheFileMode(0o1777), 'out of range');
        $this->assertNull(KernelOptionsResolver::cacheFileMode('rwx'));
        $this->assertNull(KernelOptionsResolver::cacheFileMode(1.5));
    }

    public function testCacheDirAnchor(): void
    {
        $this->assertSame(['anchor' => 'cache', 'suffix' => '/aop'], KernelOptionsResolver::cacheDirAnchor('%kernel.cache_dir%/aop'));
        $this->assertSame(['anchor' => 'build', 'suffix' => '/go-aop'], KernelOptionsResolver::cacheDirAnchor('%kernel.build_dir%/go-aop'));
        $this->assertSame(['anchor' => null, 'suffix' => ''], KernelOptionsResolver::cacheDirAnchor('%kernel.project_dir%/var/aop'));
        $this->assertSame(['anchor' => null, 'suffix' => ''], KernelOptionsResolver::cacheDirAnchor('/srv/aop'));
    }

    public function testFeatureNamesComeFromTheFrameworkConstants(): void
    {
        $this->assertSame(['INTERCEPT_INCLUDES', 'PREBUILT_CACHE'], KernelOptionsResolver::featureNames());
    }
}
