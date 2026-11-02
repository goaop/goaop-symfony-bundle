<?php

declare(strict_types=1);

namespace Go\Symfony\GoAopBundle\Tests\Config;

use Go\Symfony\GoAopBundle\Config\SourceSignature;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SourceSignatureTest extends TestCase
{
    private const string SOURCE = <<<'PHP'
        <?php

        namespace App\Service;

        use App\Attribute\Logged;

        /**
         * A service
         */
        #[Logged(level: 'info')]
        final class Greeter implements GreeterInterface
        {
            use GreeterTrait {
                hello as protected;
            }

            public const string PREFIX = 'Hello';

            private array $names = ['world'];

            public string $last { get => $this->names[0]; }

            public function __construct(private readonly Clock $clock = new Clock()) {}

            public function greet(string $name): string
            {
                $closure = function () use ($name) { return "{$name}!"; };

                return sprintf('%s, %s', self::PREFIX, $closure());
            }

            #[Required]
            public function setNames(array $names = ['a' => [1, 2]]): static
            {
                $this->names = match (count($names)) { 0 => ['world'], default => $names };

                return $this;
            }

            abstract protected function format(string $text): string;

            public static function getSubscribedServices(): array
            {
                return ['clock' => Clock::class];
            }
        }
        PHP;

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function equivalentSources(): iterable
    {
        yield 'method body' => ["return sprintf('%s, %s', self::PREFIX, \$closure());", "return self::PREFIX . ', ' . \$name;"];
        yield 'closure in a method body' => ['return "{$name}!";', 'return "{$name}?";'];
        yield 'match in a method body' => ["default => \$names }", "1 => \$names, default => array_values(\$names) }"];
        yield 'empty method body' => ['public function __construct(private readonly Clock $clock = new Clock()) {}', 'public function __construct(private readonly Clock $clock = new Clock()) { echo 1; }'];
        yield 'whitespace' => ['public function greet(string $name): string', "public function greet(\n    string \$name\n): string"];
        yield 'plain comment' => ['$closure = function', "// Builds the greeting\n        \$closure = function"];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function differentSources(): iterable
    {
        yield 'method signature' => ['public function greet(string $name): string', 'public function greet(?string $name): string'];
        yield 'method visibility' => ['public function greet(', 'protected function greet('];
        yield 'new method' => ['#[Required]', "public function bye(): void {}\n\n    #[Required]"];
        yield 'method attribute' => ['#[Required]', '#[Required, Deprecated]'];
        yield 'parameter default' => ["['a' => [1, 2]]", "['a' => [1, 3]]"];
        yield 'promoted property' => ['private readonly Clock $clock', 'protected readonly Clock $clock'];
        yield 'class attribute' => ["#[Logged(level: 'info')]", "#[Logged(level: 'debug')]"];
        yield 'doc comment' => ['* A service', '* A greeting service'];
        yield 'interface' => ['implements GreeterInterface', 'implements GreeterInterface, \Countable'];
        yield 'constant' => ["PREFIX = 'Hello'", "PREFIX = 'Hi'"];
        yield 'property default' => ["['world']", "['all']"];
        yield 'property hook' => ['get => $this->names[0];', 'get => $this->names[1];'];
        yield 'trait adaptation' => ['hello as protected;', 'hello as private;'];
        yield 'imports' => ['use App\Attribute\Logged;', 'use Other\Logged;'];
        yield 'abstract method' => ['abstract protected function format(string $text): string;', 'abstract protected function format(string $text): ?string;'];
        yield 'static method body' => ["return ['clock' => Clock::class];", "return ['clock' => Clock::class, 'logger' => Logger::class];"];
    }

    #[DataProvider('equivalentSources')]
    public function testSignatureIgnoresChangesThatNoContainerDependsOn(string $search, string $replace): void
    {
        $this->assertSame(SourceSignature::ofCode(self::SOURCE), SourceSignature::ofCode(self::edit($search, $replace)));
    }

    #[DataProvider('differentSources')]
    public function testSignatureChangesWithTheDeclarations(string $search, string $replace): void
    {
        $this->assertNotSame(SourceSignature::ofCode(self::SOURCE), SourceSignature::ofCode(self::edit($search, $replace)));
    }

    public function testBodiesOfMethodsOfAnInterfaceAndOfFunctionsOutsideOfClassesAreKept(): void
    {
        $code = <<<'PHP'
            <?php
            function helper(): int { return 1; }
            enum Status: string
            {
                case On = 'on';
                public function label(): string { return 'On'; }
            }
            PHP;

        $this->assertNotSame(SourceSignature::ofCode($code), SourceSignature::ofCode(str_replace('return 1;', 'return 2;', $code)), 'A function outside of a class is part of the signature');
        $this->assertSame(SourceSignature::ofCode($code), SourceSignature::ofCode(str_replace("return 'On';", "return 'Enabled';", $code)), 'The body of an enum method is not');
    }

    public function testClassConstantFetchDoesNotOpenAClassBody(): void
    {
        $code = <<<'PHP'
            <?php
            $class = Foo::class;
            if ($class) { function inner(): int { return 1; } }
            PHP;

        $this->assertNotSame(SourceSignature::ofCode($code), SourceSignature::ofCode(str_replace('return 1;', 'return 2;', $code)));
    }

    public function testSignatureOfAFile(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'goaop-signature');
        $this->assertIsString($file);
        try {
            file_put_contents($file, self::SOURCE);
            $this->assertSame(SourceSignature::ofCode(self::SOURCE), SourceSignature::ofFile($file));
        } finally {
            unlink($file);
        }
        $this->assertNull(SourceSignature::ofFile($file));
    }

    private static function edit(string $search, string $replace): string
    {
        self::assertStringContainsString($search, self::SOURCE);

        return str_replace($search, $replace, self::SOURCE);
    }
}
