<?php

declare(strict_types=1);

namespace Go\Symfony\GoAopBundle\Tests\Fixtures\App\Cycle;

/**
 * Woven service whose advice creates the SafeAspect
 */
class Safe
{
    public function open(): string
    {
        return 'open';
    }
}
