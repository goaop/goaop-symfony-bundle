<?php

declare(strict_types=1);

namespace Go\Symfony\GoAopBundle\Tests\Fixtures\App\Cycle;

/**
 * Dependency of the SafeAspect, whose constructor runs the advised Locksmith::cut()
 */
class Locksmith
{
    public function __construct()
    {
        $this->cut();
    }

    public function cut(): void {}
}
