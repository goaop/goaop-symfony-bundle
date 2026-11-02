<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2015, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Symfony\GoAopBundle\DependencyInjection\Compiler;

use Go\Aop\Aspect;
use Go\Core\AspectKernel;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Collects the services tagged as aspects into a service locator keyed by the aspect class.
 *
 * The locator is the second argument of the aspect kernel service: the kernel registers every class of
 * the locator as a deferred aspect, since the weaver only needs the classes, and the Symfony container
 * creates the aspect when one of its advices runs first.
 */
final class AspectCollectorPass implements CompilerPassInterface
{
    /**
     * Tag of the aspect services, applied automatically to autoconfigured services implementing Go\Aop\Aspect
     */
    public const string ASPECT_TAG = 'goaop.aspect';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(AspectKernel::class)) {
            return;
        }

        $aspects = [];
        foreach (array_keys($container->findTaggedServiceIds(self::ASPECT_TAG, true)) as $id) {
            $class = $this->resolveClass($container, $id);
            if (!is_subclass_of($class, Aspect::class)) {
                throw new InvalidArgumentException(sprintf(
                    'The service "%s" is tagged "%s", but its class "%s" does not implement "%s".',
                    $id,
                    self::ASPECT_TAG,
                    $class,
                    Aspect::class,
                ));
            }
            if (isset($aspects[$class]) && (string) $aspects[$class] !== $id) {
                throw new InvalidArgumentException(sprintf(
                    'The aspect class "%s" is registered twice, as the services "%s" and "%s": an aspect is a singleton, '
                    . 'the generated proxies refer to it by its class.',
                    $class,
                    (string) $aspects[$class],
                    $id,
                ));
            }
            $aspects[$class] = new Reference($id);
        }

        $container->getDefinition(AspectKernel::class)
            ->replaceArgument(1, ServiceLocatorTagPass::register($container, $aspects));
    }

    /**
     * Resolves the class of a (possibly inherited) service definition
     *
     * @return class-string
     */
    private function resolveClass(ContainerBuilder $container, string $id): string
    {
        $definition = $container->findDefinition($id);
        $class      = $definition->getClass();
        $parent     = $definition;
        while ($class === null && $parent instanceof ChildDefinition) {
            $parent = $container->findDefinition($parent->getParent());
            $class  = $parent->getClass();
        }
        $class = $class ?? $id;
        $class = $container->getParameterBag()->resolveValue($class);

        if (!is_string($class) || $container->getReflectionClass($class, false) === null) {
            throw new InvalidArgumentException(sprintf(
                'The class of the aspect service "%s" can not be resolved: define the "class" of the service explicitly.',
                $id,
            ));
        }

        /** @var class-string $class */
        return $class;
    }
}
