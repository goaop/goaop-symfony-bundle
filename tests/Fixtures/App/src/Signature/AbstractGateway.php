<?php

declare(strict_types=1);

namespace Go\Symfony\GoAopBundle\Tests\Fixtures\App\Signature;

/**
 * Woven parent class of a woven class, implementing an interface that declares the constructor
 */
abstract class AbstractGateway implements GatewayInterface
{
    /** @var list<string> */
    protected array $references = [];

    public function __construct(
        public readonly string $name,
        protected int|float $fee = 0,
        protected ?Currency $currency = null,
    ) {}

    public static function create(string $name): static
    {
        return new static($name);
    }

    public function charge(int|float $amount, ?string $reference = null): static
    {
        $this->references[] = $reference ?? sprintf('%s-%d', $this->name, count($this->references) + 1);

        return $this;
    }

    public function references(): array
    {
        return $this->references;
    }

    /**
     * Tentative return type of an internal interface, kept for compatibility
     *
     * @return int
     */
    #[\ReturnTypeWillChange]
    public function count()
    {
        return count($this->references);
    }

    abstract public function describe(): string;
}
