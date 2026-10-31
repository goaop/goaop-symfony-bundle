<?php

declare(strict_types=1);

namespace Go\Symfony\GoAopBundle\Tests\Fixtures\App\Aspect;

use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Attribute\After;
use Go\Lang\Attribute\AfterThrowing;
use Go\Lang\Attribute\Around;
use Go\Lang\Attribute\Before;
use Go\Symfony\GoAopBundle\Tests\Fixtures\App\Service\Recorder;
use Throwable;

/**
 * Autoconfigured aspect (implements Go\Aop\Aspect) with a dependency injected by the container
 */
final class LoggingAspect implements Aspect
{
    public function __construct(
        private readonly Recorder $recorder,
    ) {}

    #[Before('execution(public Go\Symfony\GoAopBundle\Tests\Fixtures\App\Service\GreetingService->greet(*))')]
    public function beforeGreet(MethodInvocation $invocation): void
    {
        $this->recorder->record('before:' . $invocation->getMethod()->getName());
    }

    #[After('execution(public Go\Symfony\GoAopBundle\Tests\Fixtures\App\Service\GreetingService->greet(*))')]
    public function afterGreet(MethodInvocation $invocation): void
    {
        $this->recorder->record('after:' . $invocation->getMethod()->getName());
    }

    #[Around('execution(public Go\Symfony\GoAopBundle\Tests\Fixtures\App\Service\GreetingService->shout(*))')]
    public function aroundShout(MethodInvocation $invocation): mixed
    {
        $result = $invocation->proceed();

        return is_string($result) ? strtoupper($result) : $result;
    }

    #[AfterThrowing('execution(public Go\Symfony\GoAopBundle\Tests\Fixtures\App\Service\GreetingService->explode(*))')]
    public function afterExplode(MethodInvocation $invocation, Throwable $exception): void
    {
        $this->recorder->record('afterThrowing:' . $invocation->getMethod()->getName() . ':' . $exception->getMessage());
    }
}
