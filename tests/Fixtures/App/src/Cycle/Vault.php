<?php

declare(strict_types=1);

namespace Go\Symfony\GoAopBundle\Tests\Fixtures\App\Cycle;

/**
 * Woven service calling an advised method from its constructor, advised by an aspect that depends on it
 */
class Vault
{
    public bool $locked = false;

    public function __construct()
    {
        $this->lock();
    }

    public function lock(): void
    {
        $this->locked = true;
    }
}
