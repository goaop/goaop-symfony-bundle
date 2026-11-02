<?php

declare(strict_types=1);

namespace Go\Symfony\GoAopBundle\Tests\Fixtures\App\Cycle;

/**
 * Woven service called by the constructor of the Scheduler
 */
class Clock
{
    public function now(): int
    {
        return 42;
    }
}
