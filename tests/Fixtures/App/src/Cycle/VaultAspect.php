<?php

declare(strict_types=1);

namespace Go\Symfony\GoAopBundle\Tests\Fixtures\App\Cycle;

use Go\Aop\Aspect;
use Go\Lang\Attribute\Before;
use Go\Symfony\GoAopBundle\Tests\Fixtures\App\Service\Recorder;

/**
 * Advises a method that the constructor of the service it depends on calls: a cycle
 */
final class VaultAspect implements Aspect
{
    public function __construct(
        private readonly Vault $vault,
        private readonly Recorder $recorder,
    ) {}

    #[Before('execution(public Go\Symfony\GoAopBundle\Tests\Fixtures\App\Cycle\Vault->lock(*))')]
    public function beforeLock(): void
    {
        $this->recorder->record('vault:advised:' . ($this->vault->locked ? 'locked' : 'open'));
    }
}
