<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

// Every test process works in its own cache directory: the aspect kernel is a process-wide singleton
$_SERVER['APP_CACHE_DIR'] = $_ENV['APP_CACHE_DIR'] = sys_get_temp_dir() . '/goaop-symfony-bundle/' . getmypid() . '-' . bin2hex(random_bytes(3));
putenv('APP_CACHE_DIR=' . $_SERVER['APP_CACHE_DIR']);

// The directory is removed when the process ends; tests booting a kernel clean up after themselves anyway
register_shutdown_function(static function (): void {
    $directory = $_SERVER['APP_CACHE_DIR'];
    if (!is_dir($directory)) {
        return;
    }
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($files as $file) {
        $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
    }
    @rmdir($directory);
});
