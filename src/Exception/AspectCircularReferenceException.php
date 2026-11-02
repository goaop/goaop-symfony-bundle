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

namespace Go\Symfony\GoAopBundle\Exception;

use Go\Aop\Aspect;
use LogicException;
use Throwable;

/**
 * Thrown when creating an aspect runs one of the advices of that aspect.
 *
 * An aspect is created by the Symfony container when one of its advices runs for the first time, after its
 * dependencies. When creating a dependency (directly or through other services) runs an advice of the same
 * aspect, for example because the aspect advises the constructor of a service it depends on, that advice
 * would run on an aspect that does not exist yet.
 */
final class AspectCircularReferenceException extends LogicException
{
    /**
     * @param class-string<Aspect> $aspectClass Class of the aspect being created
     * @param list<string>         $path        Calls from the creation of the aspect to its advice, outermost first
     * @param Throwable            $previous    Exception of the framework that stopped the advice before it ran on the
     *                                          aspect being created
     */
    public function __construct(
        public readonly string $aspectClass,
        public readonly array $path,
        Throwable $previous,
    ) {
        parent::__construct(
            sprintf(
                'Circular reference detected for the aspect "%s", path: "%s". The aspect is created when one of its advices '
                . 'runs first, and creating its dependencies ran an advice of the same aspect, which can not run before the '
                . 'aspect exists. Inject the dependency lazily (#[Autowire(lazy: true)], #[AutowireServiceClosure] or a '
                . 'service locator), or do not advise the code that runs while the dependency is created.',
                $aspectClass,
                implode(' -> ', [$aspectClass, ...$path]),
            ),
            0,
            $previous,
        );
    }
}
