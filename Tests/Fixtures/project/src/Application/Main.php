<?php

namespace Go\Symfony\GoAopBundle\Tests\TestProject\Application;

use Go\Symfony\GoAopBundle\Tests\TestProject\Annotation as Aop;

class Main
{
    /**
     * @Aop\Loggable()
     */
    public function doSomething()
    {

    }
}
