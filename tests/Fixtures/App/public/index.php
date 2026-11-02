<?php

declare(strict_types=1);

/*
 * Front controller of the fixture application: handles one request for /greet/{name} in debug mode, like a
 * web server would, and prints the response with the classes that were loaded before weaving was possible.
 *
 * Usage: php public/index.php [name] [environment]
 */

use Go\Core\AspectKernel;
use Go\Symfony\GoAopBundle\Kernel\AspectSymfonyKernel;
use Go\Symfony\GoAopBundle\Tests\Fixtures\App\Kernel;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\HttpFoundation\Request;

require dirname(__DIR__, 4) . '/vendor/autoload.php';

$name        = $argv[1] ?? 'web';
$environment = $argv[2] ?? 'test';

// Enables the DebugClassLoader, like the Symfony runtime does in debug mode
Debug::enable();

$kernel   = new Kernel($environment, true);
$request  = Request::create('/greet/' . $name);
$response = $kernel->handle($request);

$aspectKernel = $kernel->getContainer()->get(AspectKernel::class);
assert($aspectKernel instanceof AspectSymfonyKernel);

echo json_encode([
    'status'              => $response->getStatusCode(),
    'response'            => json_decode((string) $response->getContent(), true),
    'loadedBeforeWeaving' => $aspectKernel->getClassesDeclaredBeforeInitialization(),
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), PHP_EOL;

$kernel->terminate($request, $response);
