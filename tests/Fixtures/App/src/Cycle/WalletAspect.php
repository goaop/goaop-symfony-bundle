<?php

declare(strict_types=1);

namespace Go\Symfony\GoAopBundle\Tests\Fixtures\App\Cycle;

use Go\Aop\Aspect;
use Go\Lang\Attribute\Before;
use Go\Symfony\GoAopBundle\Tests\Fixtures\App\Service\Recorder;

/**
 * Advises the constructor of the service it depends on: a cycle
 */
final class WalletAspect implements Aspect
{
    public function __construct(
        private readonly Wallet $wallet,
        private readonly Recorder $recorder,
    ) {}

    #[Before('execution(public Go\Symfony\GoAopBundle\Tests\Fixtures\App\Cycle\Wallet->__construct(*))')]
    public function beforeConstruct(): void
    {
        $this->recorder->record('wallet:advised:' . $this->wallet::class);
    }
}
