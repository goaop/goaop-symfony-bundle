<?php

declare(strict_types=1);

namespace Go\Symfony\GoAopBundle\Tests\Fixtures\App\Signature;

use Countable;

/**
 * Interface declaring a constructor, implemented by woven classes (issue #23)
 */
interface GatewayInterface extends Countable
{
    public function __construct(string $name, int|float $fee = 0, ?Currency $currency = null);

    public static function create(string $name): static;

    public function charge(int|float $amount, ?string $reference = null): static;

    /**
     * @return list<string>
     */
    public function references(): array;
}
