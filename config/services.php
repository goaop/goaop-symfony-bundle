<?php

declare(strict_types=1);

use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Symfony\GoAopBundle\CacheWarmer\AspectCacheWarmer;
use Go\Symfony\GoAopBundle\CacheWarmer\BootstrapDumper;
use Go\Symfony\GoAopBundle\Command\CacheWarmupCommand;
use Go\Symfony\GoAopBundle\Command\DebugAdvisorCommand;
use Go\Symfony\GoAopBundle\Command\DebugAspectCommand;
use Go\Symfony\GoAopBundle\Command\DebugWeavingCommand;
use Go\Symfony\GoAopBundle\DependencyInjection\Compiler\AspectCollectorPass;
use Go\Symfony\GoAopBundle\Kernel\AspectSymfonyKernel;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    // The kernel of the process: created once, initialized with the configured options when the bundle boots
    $services->set('goaop.aspect_kernel', AspectSymfonyKernel::class)
        ->factory([AspectSymfonyKernel::class, 'create'])
        ->args([
            param('goaop.options'),
            service(AspectCollectorPass::LOCATOR_ID),
            service('service_container'),
        ])
        ->public();
    $services->alias(AspectKernel::class, 'goaop.aspect_kernel');

    $services->set('goaop.aspect_container', AspectContainer::class)
        ->factory([service('goaop.aspect_kernel'), 'getContainer']);
    $services->alias(AspectContainer::class, 'goaop.aspect_container');

    // Writes the bootstrap file that initializes the same kernel outside of Symfony (vendor/bin/aspect, preloading)
    $services->set('goaop.bootstrap_dumper', BootstrapDumper::class)
        ->args([
            param('goaop.options'),
            param('goaop.kernel_class'),
            service(AspectCollectorPass::LOCATOR_ID),
        ]);

    $services->set('goaop.cache_warmer', AspectCacheWarmer::class)
        ->args([
            service('goaop.aspect_kernel'),
            service('goaop.bootstrap_dumper'),
            param('goaop.options'),
            param('goaop.cache_dir_anchor'),
        ])
        ->tag('kernel.cache_warmer');

    foreach ([
        'goaop.command.cache_warmup'  => CacheWarmupCommand::class,
        'goaop.command.debug_aspect'  => DebugAspectCommand::class,
        'goaop.command.debug_advisor' => DebugAdvisorCommand::class,
        'goaop.command.debug_weaving' => DebugWeavingCommand::class,
    ] as $id => $class) {
        $services->set($id, $class)
            ->args([service('goaop.aspect_kernel')])
            ->tag('console.command');
    }
};
