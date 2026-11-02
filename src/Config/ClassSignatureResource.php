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

namespace Go\Symfony\GoAopBundle\Config;

use Symfony\Component\Config\Resource\ReflectionClassResource;
use Symfony\Component\Config\Resource\SelfCheckingResourceInterface;

/**
 * Tracks the signature of a weavable class for the freshness check of the compiled container, without
 * loading the class.
 *
 * In debug mode the kernel checks the freshness of the compiled container before the bundles boot, thus
 * before the aspect kernel is initialized. Symfony's {@see ReflectionClassResource} computes the signature
 * of a service class by reflection when one of its files changed, so it loads the class through the plain
 * Composer loader: the class is declared from its original source and stays unwoven for the rest of the
 * request. As an edit of a method body leaves the signature unchanged, the container is not rebuilt and
 * the same happens on every following request until the cache is cleared.
 *
 * This resource replaces it for the classes of the include paths: it compares the source signatures of
 * the files of the class hierarchy (see {@see SourceSignature}) with the ones recorded when the container
 * was compiled, so an edited method body keeps the container fresh and the class is loaded, and woven,
 * when the application uses it. Every other change of these files makes the container stale, a superset
 * of the changes the reflection based check detects.
 *
 * The resource is unserialized from the meta file of the container before the bundles boot, so it must
 * never be loaded through the weaver itself: it belongs to the bundle, which is never woven.
 */
final class ClassSignatureResource implements SelfCheckingResourceInterface
{
    /**
     * Signatures of the files already computed in this process, keyed by the file and its modification time
     *
     * @var array<string, string|null>
     */
    private static array $signatures = [];

    /**
     * @var array<string, string> Source signatures, keyed by the files of the class hierarchy
     */
    private readonly array $files;

    /**
     * @param string        $className Name of the tracked class
     * @param list<string>  $files     Files of the class hierarchy: the class, its parents, interfaces and traits
     */
    public function __construct(
        private readonly string $className,
        array $files,
    ) {
        $signatures = [];
        foreach ($files as $file) {
            $signatures[$file] = SourceSignature::ofFile($file) ?? '';
        }
        $this->files = $signatures;
    }

    public function __toString(): string
    {
        return 'goaop.class_signature.' . $this->className;
    }

    /**
     * @return array<string, string> Source signatures, keyed by the files of the class hierarchy
     */
    public function getFiles(): array
    {
        return $this->files;
    }

    public function isFresh(int $timestamp): bool
    {
        foreach ($this->files as $file => $signature) {
            $modificationTime = @filemtime($file);
            if ($modificationTime === false) {
                return false;
            }
            if ($modificationTime > $timestamp && self::signatureOf($file, $modificationTime) !== $signature) {
                return false;
            }
        }

        return true;
    }

    private static function signatureOf(string $file, int $modificationTime): ?string
    {
        $key = $file . "\0" . $modificationTime;
        if (!array_key_exists($key, self::$signatures)) {
            self::$signatures[$key] = SourceSignature::ofFile($file);
        }

        return self::$signatures[$key];
    }
}
