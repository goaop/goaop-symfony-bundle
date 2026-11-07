<?php

declare(strict_types=1);

namespace Go\Symfony\GoAopBundle\Tests\DependencyInjection;

use Go\Core\AspectKernel;
use Go\Symfony\GoAopBundle\GoAopBundle;
use Go\Symfony\GoAopBundle\Kernel\AspectSymfonyKernel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ConfigurationTest extends TestCase
{
    public function testDefaults(): void
    {
        $config = $this->process([]);

        $this->assertSame([
            'debug'              => '%kernel.debug%',
            'app_dir'            => '%kernel.project_dir%',
            'cache_dir'          => '%kernel.cache_dir%/aop',
            'cache_file_mode'    => null,
            'include_paths'      => ['%kernel.project_dir%/src'],
            'exclude_paths'      => [],
            'parsed_files_limit' => AspectKernel::DEFAULT_PARSED_FILES_LIMIT,
            'features'           => [],
            'kernel_class'       => AspectSymfonyKernel::class,
            'cache_warmer'       => true,
            'doctrine'           => null,
        ], $config);
    }

    public function testFeaturesAreNormalizedToConstantNames(): void
    {
        $config = $this->process(['features' => ['prebuilt_cache', 'PREBUILT_CACHE', 'Intercept_Includes']]);

        $this->assertSame(['PREBUILT_CACHE', 'INTERCEPT_INCLUDES'], $config['features']);
    }

    public function testSingleFeatureCanBeGivenAsString(): void
    {
        $this->assertSame(['PREBUILT_CACHE'], $this->process(['features' => 'prebuilt_cache'])['features']);
    }

    public function testUnknownFeatureIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Unknown Go! AOP feature "INTERCEPT_FUNCTIONS", known features are: INTERCEPT_INCLUDES, PREBUILT_CACHE.');

        $this->process(['features' => ['INTERCEPT_FUNCTIONS']]);
    }

    public function testXmlStyleSingularKeysAreAccepted(): void
    {
        $config = $this->process(['include_path' => ['src', 'lib'], 'exclude_path' => 'src/Legacy', 'feature' => 'PREBUILT_CACHE']);

        $this->assertSame(['src', 'lib'], $config['include_paths']);
        $this->assertSame(['src/Legacy'], $config['exclude_paths']);
        $this->assertSame(['PREBUILT_CACHE'], $config['features']);
    }

    /**
     * @return iterable<string, array{int|string}>
     */
    public static function validCacheFileModes(): iterable
    {
        yield 'octal literal' => [0o770];
        yield 'octal string' => ['0770'];
        yield 'short octal string' => ['770'];
        yield 'explicit octal prefix' => ['0o644'];
    }

    #[DataProvider('validCacheFileModes')]
    public function testCacheFileModeAcceptsOctalNotations(int|string $mode): void
    {
        $this->assertSame($mode, $this->process(['cache_file_mode' => $mode])['cache_file_mode']);
    }

    public function testCacheFileModeMustGrantOwnerAccess(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The cache file mode "0444" is not a valid octal file mode between 0600 and 0777.');

        $this->process(['cache_file_mode' => '0444']);
    }

    public function testParsedFilesLimitMustBePositive(): void
    {
        $this->assertSame(500, $this->process(['parsed_files_limit' => 500])['parsed_files_limit']);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The value 0 is too small for path "go_aop.parsed_files_limit". Should be greater than or equal to 1');

        $this->process(['parsed_files_limit' => 0]);
    }

    public function testKernelClassMustExtendTheBundleKernel(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('must extend "Go\Symfony\GoAopBundle\Kernel\AspectSymfonyKernel"');

        $this->process(['kernel_class' => \stdClass::class]);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function process(array $config): array
    {
        $extension     = new GoAopBundle()->getContainerExtension();
        $this->assertNotNull($extension);
        $configuration = $extension->getConfiguration([], new ContainerBuilder());
        $this->assertNotNull($configuration);

        return new Processor()->processConfiguration($configuration, ['go_aop' => $config]);
    }
}
