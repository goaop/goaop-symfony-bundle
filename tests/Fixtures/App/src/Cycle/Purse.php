<?php

declare(strict_types=1);

namespace Go\Symfony\GoAopBundle\Tests\Fixtures\App\Cycle;

use Go\Symfony\GoAopBundle\Tests\Fixtures\App\Service\Recorder;

/**
 * Woven service whose constructor is advised by an aspect that depends on it lazily
 */
class Purse
{
    public int $balance = 0;

    public function __construct(Recorder $recorder)
    {
        $recorder->record('purse:constructed');
    }

    public function add(int $amount): void
    {
        $this->balance += $amount;
    }
}
