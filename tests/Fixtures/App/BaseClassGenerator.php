<?php

declare(strict_types=1);

namespace Go\Symfony\GoAopBundle\Tests\Fixtures\App;

/**
 * Generates a base class into the cache directory and loads it with an autoloader of its own, the way code
 * generators (admin generators, ORM proxies, ...) do: Composer does not know the class
 */
final class BaseClassGenerator
{
    public const string CLASS_NAME = 'GeneratedCode\\Admin\\BaseReportController';

    private static bool $registered = false;

    /**
     * Writes the generated class unless it exists, returns its file
     */
    public static function generate(string $directory): string
    {
        $file = $directory . '/BaseReportController.php';
        if (!is_file($file)) {
            if (!is_dir($directory)) {
                mkdir($directory, 0o777, true);
            }
            file_put_contents($file, <<<'PHP'
                <?php

                namespace GeneratedCode\Admin;

                abstract class BaseReportController
                {
                    public function title(): string
                    {
                        return 'generated base';
                    }
                }
                PHP);
        }

        return $file;
    }

    /**
     * Registers the autoloader that generates and loads the class on demand
     */
    public static function register(string $directory): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;
        spl_autoload_register(static function (string $class) use ($directory): void {
            if ($class === self::CLASS_NAME) {
                require self::generate($directory);
            }
        });
    }
}
