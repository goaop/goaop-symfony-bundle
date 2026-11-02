<?php

declare(strict_types=1);

namespace Go\Symfony\GoAopBundle\Tests\Fixtures\App\Cycle;

/**
 * Service calling an advised method of another service from its constructor
 */
class Scheduler
{
    public readonly int $startedAt;

    public function __construct(Clock $clock)
    {
        $this->startedAt = $clock->now();
    }
}
