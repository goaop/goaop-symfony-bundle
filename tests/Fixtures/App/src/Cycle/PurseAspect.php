<?php

declare(strict_types=1);

namespace Go\Symfony\GoAopBundle\Tests\Fixtures\App\Cycle;

use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Attribute\After;
use Go\Lang\Attribute\Before;
use Go\Symfony\GoAopBundle\Tests\Fixtures\App\Service\Recorder;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Advises the constructor of the service it depends on, which is injected lazily: no cycle
 */
final class PurseAspect implements Aspect
{
    public function __construct(
        #[Autowire(lazy: true)]
        private readonly Purse $purse,
        private readonly Recorder $recorder,
    ) {}

    #[Before('execution(public Go\Symfony\GoAopBundle\Tests\Fixtures\App\Cycle\Purse->__construct(*))')]
    public function beforeConstruct(): void
    {
        $this->recorder->record('purse:constructing');
    }

    #[After('execution(public Go\Symfony\GoAopBundle\Tests\Fixtures\App\Cycle\Purse->add(*))')]
    public function afterAdd(MethodInvocation $invocation): void
    {
        $this->recorder->record('purse:balance:' . $this->purse->balance);
    }
}
