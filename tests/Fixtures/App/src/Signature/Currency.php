<?php

declare(strict_types=1);

namespace Go\Symfony\GoAopBundle\Tests\Fixtures\App\Signature;

/**
 * Woven enum, used as a typed parameter with a default value
 */
enum Currency: string
{
    case Euro   = 'EUR';
    case Dollar = 'USD';

    public function symbol(): string
    {
        return match ($this) {
            self::Euro   => '€',
            self::Dollar => '$',
        };
    }
}
