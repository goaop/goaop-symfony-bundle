<?php

declare(strict_types=1);

namespace Go\Symfony\GoAopBundle\Tests\Fixtures\App\Aspect;

use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Attribute\Before;
use Go\Symfony\GoAopBundle\Tests\Fixtures\App\Service\Recorder;

/**
 * Advises a Doctrine entity, which is discovered by the ORM through the woven class
 */
final class EntityAspect implements Aspect
{
    public function __construct(
        private readonly Recorder $recorder,
    ) {}

    #[Before('execution(public Go\Symfony\GoAopBundle\Tests\Fixtures\App\Entity\Order->total(*))')]
    public function beforeTotal(MethodInvocation $invocation): void
    {
        $this->recorder->record('before:' . $invocation->getMethod()->getName());
    }
}
