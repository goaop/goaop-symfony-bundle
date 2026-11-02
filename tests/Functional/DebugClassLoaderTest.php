<?php

declare(strict_types=1);

namespace Go\Symfony\GoAopBundle\Tests\Functional;

use Go\Aop\Proxy;
use Go\Symfony\GoAopBundle\Tests\Fixtures\App\Service\Recorder;
use Go\Symfony\GoAopBundle\Tests\Fixtures\App\Signature\CardGateway;
use Go\Symfony\GoAopBundle\Tests\Fixtures\App\Signature\Currency;
use Go\Symfony\GoAopBundle\Tests\Fixtures\App\Signature\GatewayInterface;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\ErrorHandler\DebugClassLoader;

/**
 * Woven classes are checked by Symfony's DebugClassLoader in debug mode (issue #23).
 *
 * The aspect kernel puts the DebugClassLoader back on top of the Go! AOP class loader, so the DebugClassLoader checks
 * the woven classes: the proxy class, with the original code turned into a trait. The fixtures of the "signatures"
 * environment (tests/Fixtures/App/src/Signature) are a woven class implementing an interface that declares the
 * constructor, extending a woven parent, with typed signatures (promoted properties, default values, nullable, union
 * and static types, variadics, a tentative return type) and a woven enum.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class DebugClassLoaderTest extends FunctionalTestCase
{
    private const string ENVIRONMENT = 'signatures';

    public function testWovenClassesPassTheDebugClassLoaderChecksAfterAnAspectChange(): void
    {
        // The flow of the issue: a warmed up debug cache, a change of an aspect, then cache:clear and cache:warmup
        self::runConsole(['cache:warmup'], self::ENVIRONMENT);

        $aspectFile  = self::projectDir() . '/src/Signature/SignatureAspect.php';
        $aspectMtime = filemtime($aspectFile);
        $this->assertIsInt($aspectMtime);
        try {
            // The woven classes become stale: the aspect is newer than the cache
            sleep(1);
            $this->assertTrue(touch($aspectFile));

            // cache:clear boots the application first, so the aspect kernel is initialized when the new container is
            // compiled: the compiler loads the woven classes, through the DebugClassLoader of the console
            self::runConsole(['cache:clear'], self::ENVIRONMENT);
            $metaFiles = glob(self::cacheRootDir() . '/' . self::ENVIRONMENT . '/*Container.php.meta') ?: [];
            $this->assertCount(1, $metaFiles);
            $this->assertStringContainsString(
                '/aop/src/Signature/CardGateway.php',
                (string) file_get_contents($metaFiles[0]),
                'The container of cache:clear must be compiled from the woven classes',
            );
            $this->assertSame([], self::fixtureMessages(self::loggedDeprecations()), 'Deprecations of the container compilation');

            self::runConsole(['cache:warmup'], self::ENVIRONMENT);

            $messages = [];
            set_error_handler(static function (int $type, string $message, string $file = '', int $line = 0) use (&$messages): bool {
                $messages[] = ['message' => $message, 'file' => $file];

                return true;
            });
            try {
                DebugClassLoader::enable();
                $container = $this->bootKernel(self::ENVIRONMENT)->getContainer();

                $autoloaders = spl_autoload_functions();
                $this->assertIsArray($autoloaders[0]);
                $this->assertInstanceOf(DebugClassLoader::class, $autoloaders[0][0]);

                $gateway  = $container->get(CardGateway::class);
                $recorder = $container->get(Recorder::class);
                $this->assertInstanceOf(CardGateway::class, $gateway);
                $this->assertInstanceOf(Recorder::class, $recorder);
                $this->assertInstanceOf(Proxy::class, $gateway, 'The class must be woven');
                $this->assertStringStartsWith(
                    self::cacheRootDir() . '/' . self::ENVIRONMENT . '/aop/',
                    (string) new \ReflectionClass($gateway)->getFileName(),
                    'The woven class must be loaded from the cache, behind the DebugClassLoader',
                );

                $gateway->charge(10)->charge(5, 'invoice');
                $this->assertSame(['card-1', 'invoice'], $gateway->references());
                $this->assertCount(2, $gateway);
                $this->assertSame(4.0, $gateway->total(1, 2));
                $this->assertSame('card (€0.5 since 2000)', (string) $gateway);

                $created = CardGateway::create('static');
                $this->assertInstanceOf(GatewayInterface::class, $created);
                $this->assertInstanceOf(Proxy::class, Currency::Dollar);
                $this->assertSame('$', Currency::Dollar->symbol());

                foreach (['around:__construct', 'around:charge', 'around:count', 'around:total', 'around:symbol', 'before:create'] as $event) {
                    $this->assertContains($event, $recorder->events);
                }
            } finally {
                restore_error_handler();
            }

            $this->assertSame([], self::fixtureMessages($messages), 'Errors and deprecations of the woven classes at runtime');
        } finally {
            touch($aspectFile, $aspectMtime);
        }
    }

    /**
     * Deprecations collected by Symfony while the container was compiled
     *
     * @return list<array{message: string, file: string}>
     */
    private static function loggedDeprecations(): array
    {
        $logFiles = glob(self::cacheRootDir() . '/' . self::ENVIRONMENT . '/*Deprecations.log') ?: [];
        if ($logFiles === []) {
            return [];
        }
        $logs = unserialize((string) file_get_contents($logFiles[0]));
        assert(is_array($logs));

        $deprecations = [];
        foreach ($logs as $log) {
            assert(is_array($log) && is_string($log['message']) && is_string($log['file']));
            $deprecations[] = ['message' => $log['message'], 'file' => $log['file']];
        }

        return $deprecations;
    }

    /**
     * Messages about the classes of the fixture application, woven or not
     *
     * Other messages (from the dependencies) are not about weaving and are left out.
     *
     * @param list<array{message: string, file: string}> $messages
     *
     * @return list<string>
     */
    private static function fixtureMessages(array $messages): array
    {
        $fixtureMessages = [];
        foreach ($messages as ['message' => $message, 'file' => $file]) {
            if (str_contains($message, 'Tests\\Fixtures\\App\\')
                || str_starts_with($file, self::cacheRootDir())
                || str_starts_with($file, self::projectDir())
            ) {
                $fixtureMessages[] = $message . ' (' . $file . ')';
            }
        }

        return $fixtureMessages;
    }
}
