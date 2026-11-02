<?php

declare(strict_types=1);

namespace Go\Symfony\GoAopBundle\Tests\Fixtures\App\Cycle;

use Go\Aop\Aspect;
use Go\Lang\Attribute\Before;
use RuntimeException;

/**
 * Fails in its own constructor, which is no circular reference
 */
final class MisconfiguredAspect implements Aspect
{
    private readonly int $step;

    public function __construct(Counter $counter)
    {
        $this->step = $this->configure($counter);
    }

    #[Before('execution(public Go\Symfony\GoAopBundle\Tests\Fixtures\App\Cycle\Counter->increment(*))')]
    public function beforeIncrement(): void
    {
        $this->step;
    }

    private function configure(Counter $counter): int
    {
        throw new RuntimeException(sprintf('Misconfigured at %d', $counter->count));
    }
}
