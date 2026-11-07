<?php

declare(strict_types=1);

namespace Go\Symfony\GoAopBundle\Tests\Fixtures\App;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Go\Symfony\GoAopBundle\GoAopBundle;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Bundle\WebProfilerBundle\WebProfilerBundle;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

/**
 * Minimal Symfony application used by the test-suite.
 *
 * Environments:
 *  - "test": debug mode, weaving on demand
 *  - "prod": production mode with a prebuilt cache (the cache must be warmed up before booting) and a parsed files
 *    limit of its own
 *  - "doctrine": debug mode with DoctrineBundle and an attribute-mapped entity
 *  - "profiler": debug mode with the web profiler and a /greet route
 *  - "misplaced": GoAopBundle registered after FrameworkBundle, to test the ordering check
 *  - "signatures": debug mode with the woven classes of src/Signature and their aspect (DebugClassLoader checks)
 *  - "generated": debug mode, the Admin directory is woven too; its class extends a class generated into the cache
 *    directory that Composer can not locate (see BaseClassGenerator)
 *  - "generated_excluded": the "generated" environment with the Admin directory in exclude_paths
 *  - "generated_partial": the "generated" environment with the PARTIAL_REFLECTION feature
 *  - "cycle": debug mode with the aspects of src/Cycle, which depend on the services they advise
 *
 * The cache directory is taken from the APP_CACHE_DIR environment variable so that every test
 * process works in its own directory.
 */
final class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        if ($this->environment === 'misplaced') {
            yield new FrameworkBundle();
            yield new GoAopBundle();

            return;
        }

        yield new GoAopBundle();
        yield new FrameworkBundle();
        if ($this->environment === 'doctrine') {
            yield new DoctrineBundle();
        }
        if ($this->environment === 'profiler') {
            yield new TwigBundle();
            yield new WebProfilerBundle();
        }
    }

    public function boot(): void
    {
        if (str_starts_with($this->environment, 'generated')) {
            BaseClassGenerator::register($this->getCacheDir() . '/generated');
        }
        parent::boot();
    }

    public function greet(string $name): Response
    {
        $greeting = $this->container->get(Service\GreetingService::class);
        assert($greeting instanceof Service\GreetingService);
        $recorder = $this->container->get(Service\Recorder::class);
        assert($recorder instanceof Service\Recorder);

        return new JsonResponse(['greeting' => $greeting->greet($name), 'events' => $recorder->events]);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('greet', '/greet/{name}')->controller([$this, 'greet']);
        if ($this->environment === 'profiler') {
            $routes->import('@WebProfilerBundle/Resources/config/routing/profiler.php')->prefix('/_profiler');
        }
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        $cacheDir = $_SERVER['APP_CACHE_DIR'] ?? $_ENV['APP_CACHE_DIR'] ?? null;
        if (is_string($cacheDir) && $cacheDir !== '') {
            return $cacheDir . '/' . $this->environment;
        }

        return parent::getCacheDir();
    }

    public function getLogDir(): string
    {
        return dirname($this->getCacheDir(), 2) . '/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $framework = [
            'secret'                => 'go-aop',
            'test'                  => true,
            'http_method_override'  => false,
            'handle_all_throwables' => true,
            'php_errors'            => ['log' => true],
            'router'                => ['utf8' => true],
        ];
        if ($this->environment === 'profiler') {
            $framework['profiler'] = ['enabled' => true, 'collect' => true];
            $container->extension('twig', ['strict_variables' => true]);
            $container->extension('web_profiler', ['toolbar' => false, 'intercept_redirects' => false]);
        }
        $container->extension('framework', $framework);

        $goAop = [
            'include_paths' => ['%kernel.project_dir%/src'],
        ];
        if ($this->environment === 'prod') {
            $goAop['features'] = ['PREBUILT_CACHE'];
            // Other than the default, to check that it reaches the kernel and the cache warmer
            $goAop['parsed_files_limit'] = 50;
        }
        if (str_starts_with($this->environment, 'generated')) {
            $goAop['include_paths'][] = '%kernel.project_dir%/Admin';
        }
        if ($this->environment === 'generated_excluded') {
            $goAop['exclude_paths'] = ['%kernel.project_dir%/Admin'];
        }
        if ($this->environment === 'generated_partial') {
            $goAop['features'] = ['PARTIAL_REFLECTION'];
        }
        $container->extension('go_aop', $goAop);

        if ($this->environment === 'doctrine') {
            $container->extension('doctrine', [
                'dbal' => ['driver' => 'pdo_sqlite', 'memory' => true],
                'orm'  => [
                    'enable_native_lazy_objects' => true,
                    'mappings' => [
                        'App' => [
                            'type'      => 'attribute',
                            'dir'       => '%kernel.project_dir%/src/Entity',
                            'prefix'    => 'Go\\Symfony\\GoAopBundle\\Tests\\Fixtures\\App\\Entity',
                            'is_bundle' => false,
                        ],
                    ],
                ],
            ]);
            $container->services()->alias('test.attribute_driver', 'doctrine.orm.default_attribute_metadata_driver')->public();
        }

        // Keeps the test output free of the request logs of the framework
        $container->services()->set('logger', NullLogger::class);

        $services = $container->services()->defaults()->autowire()->autoconfigure();
        $services->load('Go\\Symfony\\GoAopBundle\\Tests\\Fixtures\\App\\', __DIR__ . '/src/')
            ->exclude([__DIR__ . '/src/Entity', __DIR__ . '/src/Signature', __DIR__ . '/src/Cycle']);
        $services->get(Service\GreetingService::class)->public();
        $services->get(Service\Recorder::class)->public();

        if ($this->environment === 'signatures') {
            $services->set(Signature\SignatureAspect::class);
            $services->set(Signature\CardGateway::class)->arg('$name', 'card')->public();
        }
        if (str_starts_with($this->environment, 'generated')) {
            $services->set(Admin\ReportAspect::class);
            $services->set(Admin\ReportController::class)->public();
        }
        if ($this->environment === 'cycle') {
            $services->load('Go\\Symfony\\GoAopBundle\\Tests\\Fixtures\\App\\Cycle\\', __DIR__ . '/src/Cycle/')->public();
        }
    }
}
