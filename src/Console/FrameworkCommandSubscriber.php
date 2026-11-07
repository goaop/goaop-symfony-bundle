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

namespace Go\Symfony\GoAopBundle\Console;

use Go\Console\Command\BaseAspectCommand;
use Go\Console\Command\OpcacheWarmupCommand;
use Go\Symfony\GoAopBundle\Kernel\DebugClassLoaderGuard;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\ErrorHandler\DebugClassLoader;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Runs the commands of the framework in the console of the application as they run in their own tool.
 *
 * - Symfony's DebugClassLoader wraps every autoloader in debug mode, which hides the Go! AOP class loader from the
 *   build tooling of the framework that reads the Composer autoloader through it (the opcache.preload script of
 *   `cache:warmup:aop --preload`, the file cache primer of `cache:warmup:opcache`): the commands fail with "No
 *   Composer class loader is registered in this process". The DebugClassLoader checks the classes of the application,
 *   not a build tool, so it is lifted while a command of the framework runs.
 * - `cache:warmup:opcache` compiles the files the process included already (Composer's autoloaded files) again, and
 *   counts them as compiled when that fails because they declare functions. PHP keeps the fatal compile error as the
 *   last error, which Symfony's error handler reports at shutdown as a fatal error of the command: it is cleared when
 *   the command terminates.
 *
 * @internal
 */
final class FrameworkCommandSubscriber implements EventSubscriberInterface
{
    private bool $debugClassLoaderLifted = false;

    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND   => 'onCommand',
            // After every other listener: the last error must be cleared right before the process ends
            ConsoleEvents::TERMINATE => ['onTerminate', -2048],
        ];
    }

    public function onCommand(ConsoleCommandEvent $event): void
    {
        if (!$event->getCommand() instanceof BaseAspectCommand || !DebugClassLoaderGuard::isEnabled()) {
            return;
        }
        DebugClassLoader::disable();
        $this->debugClassLoaderLifted = true;
    }

    public function onTerminate(ConsoleTerminateEvent $event): void
    {
        if ($this->debugClassLoaderLifted) {
            $this->debugClassLoaderLifted = false;
            DebugClassLoader::enable();
        }
        if ($event->getCommand() instanceof OpcacheWarmupCommand) {
            error_clear_last();
        }
    }
}
