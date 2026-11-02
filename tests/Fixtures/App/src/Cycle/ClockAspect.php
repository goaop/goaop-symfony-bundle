<?php

declare(strict_types=1);

namespace Go\Symfony\GoAopBundle\Tests\Fixtures\App\Cycle;

use Go\Aop\Aspect;
use Go\Lang\Attribute\Before;
use Go\Symfony\GoAopBundle\Tests\Fixtures\App\Service\Recorder;

/**
 * Depends on the Scheduler, whose constructor runs the advised Clock::now(): a cycle through another service
 */
final class ClockAspect implements Aspect
{
    public function __construct(
        private readonly Scheduler $scheduler,
        private readonly Recorder $recorder,
    ) {}

    #[Before('execution(public Go\Symfony\GoAopBundle\Tests\Fixtures\App\Cycle\Clock->now(*))')]
    public function beforeNow(): void
    {
        $this->recorder->record('clock:advised:' . $this->scheduler::class);
    }
}
