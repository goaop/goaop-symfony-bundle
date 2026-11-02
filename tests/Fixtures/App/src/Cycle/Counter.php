<?php

declare(strict_types=1);

namespace Go\Symfony\GoAopBundle\Tests\Fixtures\App\Cycle;

/**
 * Woven service advised by an aspect that fails to construct
 */
class Counter
{
    public int $count = 0;

    public function increment(): void
    {
        $this->count++;
    }
}
