<?php

declare(strict_types=1);

namespace Go\Symfony\GoAopBundle\Tests\Fixtures\App\Service;

use RuntimeException;

/**
 * Weaving target: advised by the LoggingAspect
 */
class GreetingService
{
    public function greet(string $name): string
    {
        return sprintf('Hello, %s!', $name);
    }

    public function shout(string $phrase): string
    {
        return $phrase . '!';
    }

    public function explode(): never
    {
        throw new RuntimeException('boom');
    }
}
