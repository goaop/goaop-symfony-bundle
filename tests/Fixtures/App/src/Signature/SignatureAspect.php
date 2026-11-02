<?php

declare(strict_types=1);

namespace Go\Symfony\GoAopBundle\Tests\Fixtures\App\Signature;

use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Attribute\Around;
use Go\Lang\Attribute\Before;
use Go\Symfony\GoAopBundle\Tests\Fixtures\App\Service\Recorder;

/**
 * Advises the methods of the classes of this namespace; registered in the "signatures" environment only
 */
final class SignatureAspect implements Aspect
{
    public function __construct(
        private readonly Recorder $recorder,
    ) {}

    #[Around('execution(public Go\Symfony\GoAopBundle\Tests\Fixtures\App\Signature\*->*(*))')]
    public function aroundMethod(MethodInvocation $invocation): mixed
    {
        $this->recorder->record('around:' . $invocation->getMethod()->getName());

        return $invocation->proceed();
    }

    #[Before('execution(public Go\Symfony\GoAopBundle\Tests\Fixtures\App\Signature\*::*(*))')]
    public function beforeStaticMethod(MethodInvocation $invocation): void
    {
        $this->recorder->record('before:' . $invocation->getMethod()->getName());
    }
}
