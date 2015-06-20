<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2015, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Symfony\GoAopBundle\CacheWarmer;


use Go\Core\AspectKernel;
use Go\Instrument\ClassLoading\CachePathManager;
use Go\Instrument\ClassLoading\SourceTransformingLoader;
use Go\Instrument\FileSystem\Enumerator;
use Go\Instrument\Transformer\FilterInjectorTransformer;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmer;

/**
 * Warming the cache with injected advices
 *
 * NB: in some cases hierarchy analysis can trigger "Fatal Error: class XXX not found". This is means, that there is
 * some class with unresolved parent classes. To avoid this issue, just exclude bad classes from analysis via
 * 'excludePaths' configuration option.
 */
class AspectCacheWarmer extends CacheWarmer
{
    /**
     * Instance of aspect kernel
     *
     * @var AspectKernel
     */
    private $aspectKernel;

    /**
     * @var CachePathManager
     */
    private $cachePathManager;

    /**
     * @param AspectKernel $aspectKernel
     * @param CachePathManager $cachePathManager
     */
    public function __construct(AspectKernel $aspectKernel, CachePathManager $cachePathManager)
    {
        $this->aspectKernel     = $aspectKernel;
        $this->cachePathManager = $cachePathManager;
    }

    /**
     * Checks whether this warmer is optional or not.
     *
     * Optional warmers can be ignored on certain conditions.
     *
     * A warmer should return true if the cache can be
     * generated incrementally and on-demand.
     *
     * @return bool true if the warmer is optional, false otherwise
     */
    public function isOptional()
    {
        return false;
    }

    /**
     * Warms up the cache.
     *
     * @param string $cacheDir The cache directory
     */
    public function warmUp($cacheDir)
    {
        $options     = $this->aspectKernel->getOptions();
        $oldCacheDir = $this->cachePathManager->getCacheDir();

        $this->cachePathManager->setCacheDir($cacheDir.'/aspect');

        $enumerator = new Enumerator($options['appDir'], $options['includePaths'], $options['excludePaths']);
        $iterator   = $enumerator->enumerate();

        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            throw new \ErrorException($errstr, $errno, 0, $errfile, $errline);
        });

        $errors = array();
        foreach ($iterator as $file) {
            $realPath = $file->getRealPath();
            try {
                // This will trigger creation of cache
                file_get_contents(
                    FilterInjectorTransformer::PHP_FILTER_READ.
                    SourceTransformingLoader::FILTER_IDENTIFIER.
                    "/resource=" . $realPath
                );
            } catch (\Exception $e) {
                $errors[$realPath] = $e;
            }
        }

        restore_error_handler();
        $this->cachePathManager->flushCacheState();
        $this->cachePathManager->setCacheDir($oldCacheDir);
    }
}