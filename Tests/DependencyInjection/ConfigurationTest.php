<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2015, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Symfony\GoAopBundle\Tests\DependencyInjection;

use Go\Symfony\GoAopBundle\DependencyInjection\Configuration;
use Go\Symfony\GoAopBundle\DependencyInjection\GoAopExtension;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionConfigurationTestCase;

/**
 * Class ConfigurationTest
 */
class ConfigurationTest extends AbstractExtensionConfigurationTestCase
{
    /**
     * @test
     */
    public function itHasReasonableDefaults()
    {
        $expectedConfiguration = [
            'cache_warmer'     => true,
            'doctrine_support' => false,
            'options'          => [
                'features'      => 0,
                'app_dir'       => '%kernel.root_dir%/../src',
                'cache_dir'     => '%kernel.cache_dir%/aspect',
                'debug'         => '%kernel.debug%',
                'include_paths' => [],
                'exclude_paths' => [],
            ],
        ];

        $sources = [
            __DIR__ . '/../Fixtures/config/empty.xml',
        ];

        $this->assertProcessedConfigurationEquals($expectedConfiguration, $sources);
    }

    /**
     * @test
     */
    public function itCanBeFullyConfiguredViaYml()
    {
        $expectedConfiguration = [
            'cache_warmer'     => false,
            'doctrine_support' => true,
            'options'          => [
                'debug'           => false,
                'features'        => 7,
                'app_dir'         => '/my/app/dir',
                'cache_dir'       => '/my/cache/dir',
                'include_paths'   => [
                    '/path/to/include',
                    '/other/path/to/include',
                ],
                'exclude_paths'   => [
                    '/path/to/exclude',
                    '/other/path/to/exclude',
                ],
                'container_class' => 'Container\Class',
            ],
        ];

        $sources = [
            __DIR__ . '/../Fixtures/config/full.yml',
        ];

        $this->assertProcessedConfigurationEquals($expectedConfiguration, $sources);
    }

    /**
     * @test
     */
    public function itCanBeFullyConfiguredViaXml()
    {
        $expectedConfiguration = [
            'cache_warmer'     => false,
            'doctrine_support' => true,
            'options'          => [
                'debug'           => false,
                'features'        => 7,
                'app_dir'         => '/my/app/dir',
                'cache_dir'       => '/my/cache/dir',
                'include_paths'   => [
                    '/path/to/include',
                    '/other/path/to/include',
                ],
                'exclude_paths'   => [
                    '/path/to/exclude',
                    '/other/path/to/exclude',
                ],
                'container_class' => 'Container\Class',
            ],
        ];

        $sources = [
            __DIR__ . '/../Fixtures/config/full.xml',
        ];

        $this->assertProcessedConfigurationEquals($expectedConfiguration, $sources);
    }

    /**
     * {@inheritdoc}
     */
    protected function getContainerExtension()
    {
        return new GoAopExtension();
    }

    /**
     * {@inheritdoc}
     */
    protected function getConfiguration()
    {
        return new Configuration();
    }
}
