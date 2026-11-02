<?php

declare(strict_types=1);

namespace Go\Symfony\GoAopBundle\Tests\Fixtures\App\Cycle;

/**
 * Woven service advised by an aspect that depends on it: no cycle, the service exists before its first advice runs
 */
class Ledger
{
    /** @var list<string> */
    public array $entries = [];

    public function post(string $entry): void
    {
        $this->entries[] = $entry;
    }
}
