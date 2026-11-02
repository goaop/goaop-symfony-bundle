<?php

declare(strict_types=1);

namespace Go\Symfony\GoAopBundle\Tests\Fixtures\App\Service;

/**
 * Collects the events recorded by the aspects, injected into them by the container
 */
final class Recorder
{
    /** @var list<string> */
    public array $events = [];

    public function record(string $event): void
    {
        $this->events[] = $event;
    }
}
