<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Symfony\GoAopBundle\DataCollector;

use Go\Aop\Advisor;
use Go\Aop\Aspect;
use Go\Aop\Features;
use Go\Aop\Framework\AbstractInterceptor;
use Go\Aop\Pointcut;
use Go\Aop\Proxy;
use Go\Core\AspectKernel;
use Go\Core\AspectLoader;
use Go\Instrument\ClassLoading\CachePathManager;
use Go\Symfony\GoAopBundle\Kernel\AspectSymfonyKernel;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\VarDumper\Cloner\Data;
use Throwable;

/**
 * Collects the state of the aspect kernel for the web profiler: the registered aspects with their advisors,
 * the woven classes used by the request and the classes that were loaded before weaving was possible.
 *
 * @phpstan-type AdvisorInfo array{id: string, type: string, pointcut: string}
 * @phpstan-type AspectInfo array{class: class-string, file: string|null, advisors: list<AdvisorInfo>, pointcuts: list<string>}
 * @phpstan-type CollectedData array{
 *   kernel_class?: class-string<AspectKernel>,
 *   debug?: bool,
 *   app_dir?: string,
 *   cache_dir?: string,
 *   include_paths?: list<string>,
 *   exclude_paths?: list<string>,
 *   features?: list<string>,
 *   aspects?: list<AspectInfo>,
 *   woven_classes?: array<class-string, string>,
 *   cached_classes?: int,
 *   classes_before_init?: list<class-string>
 * }
 */
final class AspectDataCollector extends DataCollector
{
    public const string NAME = 'go_aop';

    /**
     * @phpstan-var CollectedData
     */
    protected array|Data $data = [];

    public function __construct(
        private readonly AspectKernel $aspectKernel,
    ) {}

    public function collect(Request $request, Response $response, ?Throwable $exception = null): void
    {
        $options   = $this->aspectKernel->getOptions();
        $container = $this->aspectKernel->getContainer();

        $features = [];
        foreach (new ReflectionClass(Features::class)->getConstants() as $name => $value) {
            if ($name !== 'ALL' && is_int($value) && ($options['features'] & $value) !== 0) {
                $features[] = $name;
            }
        }

        $this->data = [
            'kernel_class'          => $this->aspectKernel::class,
            'debug'                 => $options['debug'],
            'app_dir'               => $options['appDir'],
            'cache_dir'             => (string) $options['cacheDir'],
            'include_paths'         => array_values($options['includePaths']),
            'exclude_paths'         => array_values($options['excludePaths']),
            'features'              => $features,
            'aspects'               => $this->collectAspects(),
            'woven_classes'         => $this->collectWovenClasses(),
            'cached_classes'        => count($container->getService(CachePathManager::class)->queryClassMap()),
            'classes_before_init'   => $this->aspectKernel instanceof AspectSymfonyKernel
                ? $this->aspectKernel->getClassesDeclaredBeforeInitialization()
                : [],
        ];
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function reset(): void
    {
        $this->data = [];
    }

    /**
     * @return list<AspectInfo>
     */
    public function getAspects(): array
    {
        return $this->collected()['aspects'] ?? [];
    }

    public function getAdvisorCount(): int
    {
        return array_sum(array_map(static fn(array $aspect): int => count($aspect['advisors']), $this->getAspects()));
    }

    /**
     * Woven classes declared during the request, with their proxy files
     *
     * @return array<class-string, string>
     */
    public function getWovenClasses(): array
    {
        return $this->collected()['woven_classes'] ?? [];
    }

    /**
     * Number of woven classes known to the cache
     */
    public function getCachedClassCount(): int
    {
        return $this->collected()['cached_classes'] ?? 0;
    }

    /**
     * Classes below the include paths that were declared before the aspect kernel was initialized
     *
     * @return list<class-string>
     */
    public function getClassesDeclaredBeforeInitialization(): array
    {
        return $this->collected()['classes_before_init'] ?? [];
    }

    public function getKernelClass(): string
    {
        return $this->collected()['kernel_class'] ?? AspectSymfonyKernel::class;
    }

    public function isDebug(): bool
    {
        return $this->collected()['debug'] ?? false;
    }

    public function getAppDir(): string
    {
        return $this->collected()['app_dir'] ?? '';
    }

    public function getCacheDir(): string
    {
        return $this->collected()['cache_dir'] ?? '';
    }

    /**
     * @return list<string>
     */
    public function getIncludePaths(): array
    {
        return $this->collected()['include_paths'] ?? [];
    }

    /**
     * @return list<string>
     */
    public function getExcludePaths(): array
    {
        return $this->collected()['exclude_paths'] ?? [];
    }

    /**
     * @return list<string>
     */
    public function getFeatures(): array
    {
        return $this->collected()['features'] ?? [];
    }

    /**
     * The collected data as an array: after unserialization from the profiler storage it is an array as well
     *
     * @phpstan-return CollectedData
     */
    private function collected(): array
    {
        return is_array($this->data) ? $this->data : [];
    }

    /**
     * @return list<AspectInfo>
     */
    private function collectAspects(): array
    {
        $container = $this->aspectKernel->getContainer();
        $loader    = $container->getService(AspectLoader::class);

        $aspects = [];
        foreach ($container->getServiceIdsByInterface(Aspect::class) as $aspectClass) {
            if (!is_subclass_of($aspectClass, Aspect::class)) {
                continue;
            }
            $reflection = new ReflectionClass($aspectClass);
            $advisors   = [];
            $pointcuts  = [];
            foreach ($loader->load($reflection) as $id => $item) {
                if ($item instanceof Advisor) {
                    $advice     = $item->getAdvice();
                    $advisors[] = [
                        'id'       => $id,
                        'type'     => $advice->getType()->name,
                        'pointcut' => $advice instanceof AbstractInterceptor ? $advice->pointcutExpression : '',
                    ];
                } elseif ($item instanceof Pointcut) {
                    $pointcuts[] = $id;
                }
            }
            $file      = $reflection->getFileName();
            $aspects[] = [
                'class'     => $reflection->getName(),
                'file'      => is_string($file) ? $file : null,
                'advisors'  => $advisors,
                'pointcuts' => $pointcuts,
            ];
        }

        return $aspects;
    }

    /**
     * @return array<class-string, string>
     */
    private function collectWovenClasses(): array
    {
        $woven = [];
        foreach (get_declared_classes() as $class) {
            if (!is_subclass_of($class, Proxy::class)) {
                continue;
            }
            $file = new ReflectionClass($class)->getFileName();
            if (is_string($file)) {
                $woven[$class] = $file;
            }
        }
        ksort($woven);

        return $woven;
    }
}
