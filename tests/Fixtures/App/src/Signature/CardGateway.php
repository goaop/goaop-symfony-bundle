<?php

declare(strict_types=1);

namespace Go\Symfony\GoAopBundle\Tests\Fixtures\App\Signature;

use DateTimeImmutable;
use Stringable;

/**
 * Woven class extending a woven parent, with the typed constructor and method signatures of issue #23
 */
class CardGateway extends AbstractGateway implements Stringable
{
    public function __construct(
        string $name,
        int|float $fee = 0.5,
        ?Currency $currency = Currency::Euro,
        private readonly DateTimeImmutable $openedAt = new DateTimeImmutable('2000-01-01'),
        private readonly string|Stringable|null $label = null,
    ) {
        parent::__construct($name, $fee, $currency);
    }

    public function charge(int|float $amount, ?string $reference = null): static
    {
        return parent::charge($amount + $this->fee, $reference);
    }

    public function describe(): string
    {
        return sprintf('%s (%s%s since %s)', $this->label ?? $this->name, $this->currency?->symbol() ?? '', $this->fee, $this->openedAt->format('Y'));
    }

    public function total(int|float ...$amounts): float
    {
        return (float) array_sum($amounts) + $this->fee * count($amounts);
    }

    public function __toString(): string
    {
        return $this->describe();
    }
}
