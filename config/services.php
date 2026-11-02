<?php

declare(strict_types=1);

use Go\Console\Command\CacheWarmupCommand;
use Go\Console\Command\DebugAdvisorCommand;
use Go\Console\Command\DebugAspectCommand;
use Go\Console\Command\DebugWeavingCommand;
use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Symfony\GoAopBundle\CacheWarmer\AspectCacheWarmer;
use Go\Symfony\GoAopBundle\Kernel\AspectSymfonyKernel;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\abstract_arg;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    // The kernel of the process: created once, initialized with the configured options when the bundle boots.
    // The class and the factory follow the "kernel_class" option, the aspect locator is set by AspectCollectorPass
    $services->set(AspectKernel::class, AspectSymfonyKernel::class)
        ->factory([AspectSymfonyKernel::class, 'create'])
        ->args([
            param('goaop.options'),
            abstract_arg('service locator of the aspects, keyed by class'),
            service('service_container'),
        ])
        ->public();

    $services->set(AspectContainer::class)
        ->factory([service(AspectKernel::class), 'getContainer']);

    $services->set(AspectCacheWarmer::class)
        ->args([
            service(AspectKernel::class),
            param('goaop.options'),
            param('goaop.cache_dir_anchor'),
        ])
        ->tag('kernel.cache_warmer');

    // The commands of the framework work on the kernel of the process when it is initialized, as it is here
    foreach ([CacheWarmupCommand::class, DebugAspectCommand::class, DebugAdvisorCommand::class, DebugWeavingCommand::class] as $command) {
        $services->set($command)->tag('console.command');
    }
};
