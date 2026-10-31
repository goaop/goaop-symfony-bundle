<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2015, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Symfony\GoAopBundle\Kernel;

use Composer\Autoload\ClassLoader;
use Go\Aop\Aspect;
use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Instrument\ClassLoading\AopComposerLoader;
use Go\Instrument\FileSystem\Enumerator;
use LogicException;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use Symfony\Component\ErrorHandler\DebugClassLoader;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * Aspect kernel of a Symfony application.
 *
 * The kernel is created by the "goaop.aspect_kernel" service, from the options of the "go_aop"
 * configuration and the aspects collected from the service container (see {@see self::create()}),
 * and by the bootstrap file dumped into the cache directory ({@see self::bootstrap()}), which
 * initializes the very same kernel outside of the Symfony kernel: for `vendor/bin/aspect` and
 * for opcache preloading.
 *
 * Extend it and point the "go_aop.kernel_class" option at your class to register additional
 * source transformers or to change the transformation pipeline.
 *
 * @phpstan-import-type UserKernelOptions from AspectKernel
 */
class AspectSymfonyKernel extends AspectKernel
{
    /**
     * Classes that were declared before the kernel was initialized, recorded in debug mode only
     *
     * @var list<class-string>
     */
    private array $classesDeclaredBeforeInitialization = [];

    /**
     * Creates the kernel of the running Symfony application.
     *
     * The aspects are registered by their class, as deferred services whose factories take the
     * aspect instance from the application container when one of its advices runs for the first
     * time: weaving and cache warm-up only need the aspect classes and never create an aspect.
     *
     * @phpstan-param UserKernelOptions $options
     *
     * @param ServiceProviderInterface<Aspect> $aspects     Aspect services, keyed by their class
     * @param ContainerInterface               $application Container the aspect container delegates unknown ids to
     */
    public static function create(array $options, ServiceProviderInterface $aspects, ContainerInterface $application): static
    {
        $kernel = self::instance();
        $kernel->init($options);

        $aspectContainer = $kernel->getContainer();
        $aspectContainer->setDelegate($application);
        foreach (array_keys($aspects->getProvidedServices()) as $aspectClass) {
            /** @var class-string<Aspect> $aspectClass */
            $aspectContainer->addLazyService($aspectClass, static fn(): Aspect => $aspects->get($aspectClass));
        }

        return $kernel;
    }

    /**
     * Initializes the kernel outside of the Symfony application, from the dumped bootstrap file.
     *
     * Every aspect is registered by its class only: the weaver, the cache warmer and the debug commands
     * work with the aspect classes, and opcache preloading declares the woven classes without ever
     * calling an advice. An advice that runs anyway (an aspect instance is needed) fails with a clear
     * message instead of a half-configured aspect.
     *
     * @phpstan-param UserKernelOptions $options
     *
     * @param list<class-string<Aspect>> $aspectClasses
     */
    public static function bootstrap(array $options, array $aspectClasses): static
    {
        $kernel = self::instance();
        $kernel->init($options);

        $aspectContainer = $kernel->getContainer();
        foreach ($aspectClasses as $aspectClass) {
            $aspectContainer->addLazyService($aspectClass, static function () use ($aspectClass): never {
                throw new LogicException(sprintf(
                    'The aspect "%s" can not be created: this Go! AOP kernel was bootstrapped outside of the Symfony '
                    . 'application (from the dumped bootstrap file), where aspects are known by their class only.',
                    $aspectClass,
                ));
            });
        }

        return $kernel;
    }

    /**
     * Initializes the kernel.
     *
     * Symfony's DebugClassLoader wraps the Composer class loader in debug mode, which hides it from the
     * Go! AOP class loader. The wrapping is lifted for the time of the initialization and put back on top
     * of the Go! AOP loader afterwards, so that both keep working.
     */
    public function init(array $options = []): void
    {
        if ($this->wasInitialized) {
            return;
        }

        $debugClassLoaderEnabled = class_exists(DebugClassLoader::class, false) && self::hasRegisteredLoader(DebugClassLoader::class);
        if ($debugClassLoaderEnabled) {
            DebugClassLoader::disable();
        }
        try {
            parent::init($options);

            if ($this->options['debug']) {
                $this->classesDeclaredBeforeInitialization = get_declared_classes();
            }
            if (!self::hasRegisteredLoader(AopComposerLoader::class)) {
                throw new LogicException(sprintf(
                    'Go! AOP could not hook into the class loader: no "%s" instance is registered with spl_autoload_register(). '
                    . 'Make sure that the application is loaded through the Composer autoloader and that no other '
                    . 'autoloader wrapper hides it.',
                    ClassLoader::class,
                ));
            }
        } finally {
            if ($debugClassLoaderEnabled) {
                DebugClassLoader::enable();
            }
        }
    }

    /**
     * Classes below the include paths that were declared before the kernel was initialized, in debug mode.
     *
     * Such classes can not be woven in this process: they were loaded by code running before this bundle
     * booted (a bundle registered before it, or the container compiler in the process that built the
     * container). The list is empty in production mode, where it is not recorded.
     *
     * @return list<class-string>
     */
    public function getClassesDeclaredBeforeInitialization(): array
    {
        if ($this->classesDeclaredBeforeInitialization === []) {
            return [];
        }

        $isWeavable = new Enumerator($this->options['appDir'], $this->options['includePaths'], $this->options['excludePaths'])->getPathFilter();
        $classes    = [];
        foreach ($this->classesDeclaredBeforeInitialization as $class) {
            $file = new ReflectionClass($class)->getFileName();
            if (is_string($file) && $isWeavable($file)) {
                $classes[] = $class;
            }
        }

        return $classes;
    }

    protected function configureAop(AspectContainer $container): void
    {
        // Aspects are registered by create() and bootstrap(): the kernel itself has no static configuration
    }

    /**
     * Returns the kernel instance of the process, which must be an instance of this class
     */
    private static function instance(): static
    {
        $kernel = static::getInstance();
        if (!$kernel instanceof static) {
            throw new LogicException(sprintf(
                'The Go! AOP kernel of this process is an instance of "%s", while the Symfony application is '
                . 'configured with "%s": only one aspect kernel can exist per process.',
                $kernel::class,
                static::class,
            ));
        }

        return $kernel;
    }

    /**
     * Checks whether an instance of the given class is registered as an autoloader
     *
     * @param class-string $loaderClass
     */
    private static function hasRegisteredLoader(string $loaderClass): bool
    {
        foreach (spl_autoload_functions() as $autoloader) {
            if (is_array($autoloader) && $autoloader[0] instanceof $loaderClass) {
                return true;
            }
        }

        return false;
    }
}
