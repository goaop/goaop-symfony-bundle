<?php

declare(strict_types=1);

namespace Go\Symfony\GoAopBundle\Tests\Fixtures\App\Admin;

use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Attribute\Around;

/**
 * Aspect of the class extending generated code, registered in the "generated" environments only
 */
final class ReportAspect implements Aspect
{
    #[Around('execution(public Go\Symfony\GoAopBundle\Tests\Fixtures\App\Admin\ReportController->render(*))')]
    public function aroundRender(MethodInvocation $invocation): mixed
    {
        return '[' . $invocation->proceed() . ']';
    }
}
