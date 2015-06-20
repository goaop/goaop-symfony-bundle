<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2015, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Symfony\GoAopBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Collects all aspects into the one single parameter
 */
class AspectCollectorPass implements CompilerPassInterface
{

    /**
     * You can modify the container here before it is dumped to PHP code.
     *
     * @param ContainerBuilder $container
     *
     * @api
     */
    public function process(ContainerBuilder $container)
    {
        $aspectIds       = $container->findTaggedServiceIds('goaop.aspect');
        $aspectContainer = $container->getDefinition('goaop.aspect.container');
        foreach ($aspectIds as $aspectId => $aspectTags) {
            $aspectContainer->addMethodCall('registerAspect', array(new Reference($aspectId)));
        }
    }
}