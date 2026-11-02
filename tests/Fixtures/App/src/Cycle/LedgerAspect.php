<?php

declare(strict_types=1);

namespace Go\Symfony\GoAopBundle\Tests\Fixtures\App\Cycle;

use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Attribute\Before;
use Go\Symfony\GoAopBundle\Tests\Fixtures\App\Service\Recorder;

/**
 * Depends on the service it advises
 */
final class LedgerAspect implements Aspect
{
    public function __construct(
        private readonly Ledger $ledger,
        private readonly Recorder $recorder,
    ) {}

    #[Before('execution(public Go\Symfony\GoAopBundle\Tests\Fixtures\App\Cycle\Ledger->post(*))')]
    public function beforePost(MethodInvocation $invocation): void
    {
        $this->recorder->record(sprintf(
            'post:%d:%s',
            count($this->ledger->entries),
            $invocation->getThis() === $this->ledger ? 'same' : 'other',
        ));
    }
}
