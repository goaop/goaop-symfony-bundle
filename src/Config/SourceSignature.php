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

use PhpToken;

/**
 * Signature of a PHP source file that ignores the bodies of the non-static methods, computed from the
 * tokens of the file, without loading anything.
 *
 * The signature changes with every change of the file that can change the reflection of its classes:
 * declarations, attributes, doc comments, constants, properties with their defaults and hooks, method
 * signatures, and the bodies of static methods (the container calls static methods like
 * getSubscribedServices() or getSubscribedEvents() at compile time). It does not change with the bodies
 * of non-static methods, plain comments and whitespace, which no compiled container depends on.
 *
 * @internal
 */
final class SourceSignature
{
    /**
     * Placeholder that replaces the body of a non-static method in the signature
     */
    private const string SKIPPED_BODY = '{}';

    /**
     * Static helper, never instantiated
     *
     * @codeCoverageIgnore
     */
    private function __construct() {}

    /**
     * Signature of a file, or null when the file can not be read
     */
    public static function ofFile(string $file): ?string
    {
        $code = is_file($file) ? @file_get_contents($file) : false;

        return $code === false ? null : self::ofCode($code);
    }

    /**
     * Signature of PHP source code
     */
    public static function ofCode(string $code): string
    {
        $tokens = PhpToken::tokenize($code);
        $count  = count($tokens);
        $hash   = hash_init('xxh128');

        // One entry per open curly brace: whether it opens the body of a class-like declaration, and the depth of
        // parentheses and square brackets around it, which is restored when the brace closes
        $braces = [];
        // Depth of parentheses, square brackets and attributes inside the innermost curly brace
        $nesting = 0;
        // A class, interface, trait or enum keyword was seen, its body is the next curly brace
        $classBodyFollows = false;
        // A "static" modifier was seen in the current member declaration of a class body
        $isStatic = false;
        // The current member declaration is a non-static method, its body is the next curly brace
        $methodBodyFollows = false;
        $previous = null;

        for ($index = 0; $index < $count; ++$index) {
            $token = $tokens[$index];
            if ($token->isIgnorable() && !$token->is(T_DOC_COMMENT)) {
                continue;
            }
            $inClassBody = $braces !== [] && $braces[array_key_last($braces)][0];

            if ($token->is([T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM]) && !$previous?->is(T_DOUBLE_COLON)) {
                $classBodyFollows = true;
            } elseif ($inClassBody && $nesting === 0 && $token->is(T_STATIC)) {
                $isStatic = true;
            } elseif ($inClassBody && $nesting === 0 && $token->is(T_FUNCTION)) {
                $methodBodyFollows = !$isStatic;
            } elseif ($token->is(['(', '[', T_ATTRIBUTE])) {
                ++$nesting;
            } elseif ($token->is([')', ']'])) {
                --$nesting;
            } elseif ($nesting === 0 && $token->is(';')) {
                $isStatic = $methodBodyFollows = false;
            } elseif ($token->is(['{', T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES])) {
                if ($methodBodyFollows && $nesting === 0) {
                    hash_update($hash, self::SKIPPED_BODY . "\0");
                    $index    = self::skipBlock($tokens, $index);
                    $previous = $token;
                    $isStatic = $methodBodyFollows = false;
                    continue;
                }
                $braces[] = [$classBodyFollows && $nesting === 0 && $token->is('{'), $nesting];
                $nesting  = 0;
                $isStatic = $methodBodyFollows = $classBodyFollows = false;
            } elseif ($token->is('}')) {
                $nesting  = array_pop($braces)[1] ?? 0;
                $isStatic = $methodBodyFollows = false;
            }

            hash_update($hash, $token->text . "\0");
            $previous = $token;
        }

        return hash_final($hash);
    }

    /**
     * Returns the index of the curly brace that closes the block opened at the given index
     *
     * @param array<PhpToken> $tokens Tokens as PhpToken::tokenize() returns them
     */
    private static function skipBlock(array $tokens, int $index): int
    {
        $depth = 0;
        $count = count($tokens);
        for (; $index < $count; ++$index) {
            $token = $tokens[$index];
            if ($token->is(['{', T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES])) {
                ++$depth;
            } elseif ($token->is('}') && --$depth === 0) {
                return $index;
            }
        }

        return $count;
    }
}
