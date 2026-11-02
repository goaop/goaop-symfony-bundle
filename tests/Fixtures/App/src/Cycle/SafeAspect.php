<?php

declare(strict_types=1);

namespace Go\Symfony\GoAopBundle\Tests\Fixtures\App\Cycle;

use Go\Aop\Aspect;
use Go\Lang\Attribute\Before;

/**
 * Depends on the Locksmith, whose constructor runs an advice of this aspect that reads no property of the aspect:
 * without the check of the framework, that advice would run on the aspect being created without any error
 */
final class SafeAspect implements Aspect
{
    /**
     * Number of times beforeCut() ran
     */
    public static int $cuts = 0;

    public function __construct(
        private readonly Locksmith $locksmith,
    ) {}

    #[Before('execution(public Go\Symfony\GoAopBundle\Tests\Fixtures\App\Cycle\Safe->open(*))')]
    public function beforeOpen(): void
    {
        // Reads a property: the first call creates the aspect
        $this->locksmith->cut();
    }

    #[Before('execution(public Go\Symfony\GoAopBundle\Tests\Fixtures\App\Cycle\Locksmith->cut(*))')]
    public function beforeCut(): void
    {
        self::$cuts++;
    }
}
