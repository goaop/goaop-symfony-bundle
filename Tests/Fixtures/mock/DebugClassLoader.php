<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2015, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Component\Debug;

/**
 * Class DebugClassLoader
 *
 * A mock class for testing initialization flow.
 */
class DebugClassLoader
{
    public static $enabled = false;
    public static $invocations = [];

    public static function enable()
    {
        self::$enabled = true;
        self::$invocations[] = 'enable';
    }

    public static function disable()
    {
        self::$enabled = false;
        self::$invocations[] = 'disable';
    }

    public static function reset()
    {
        self::$enabled = false;
        self::$invocations = [];
    }
}
