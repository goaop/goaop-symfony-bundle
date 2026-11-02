<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Symfony\GoAopBundle\DependencyInjection\Compiler;

use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\Persistence\Mapping\Driver\ClassLocator;
use Go\Bridge\Doctrine\WovenEntityClassLocator;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Makes Doctrine ORM discover entities through the woven classes.
 *
 * Doctrine's attribute driver locates the entity classes of a directory by including every source file,
 * which bypasses the class loader: an entity included that way is declared from its original source and
 * loses its aspects for the rest of the request. The driver accepts a class locator since ORM 3.6, so the
 * configured directories of every attribute driver are wrapped into the WovenEntityClassLocator of the
 * framework, which reads the class names from the sources and loads them through the class loader.
 */
final class DoctrineIntegrationPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('.goaop.doctrine')
            || !class_exists(AttributeDriver::class)
            || !interface_exists(ClassLocator::class)
            || !class_exists(WovenEntityClassLocator::class)
        ) {
            return;
        }

        foreach ($container->getDefinitions() as $definition) {
            if ($definition->getClass() !== AttributeDriver::class) {
                continue;
            }
            $arguments = $definition->getArguments();
            $paths     = $arguments[0] ?? $arguments['$paths'] ?? null;
            if (!is_array($paths)) {
                continue;
            }
            $locator = new Definition(WovenEntityClassLocator::class, [array_values($paths)]);
            $locator->setFactory([WovenEntityClassLocator::class, 'createFromDirectories']);

            $definition->replaceArgument(isset($arguments[0]) ? 0 : '$paths', $locator);
        }
    }
}
