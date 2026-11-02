<?php

declare(strict_types=1);

namespace Go\Symfony\GoAopBundle\Tests\DependencyInjection\Compiler;

use Go\Symfony\GoAopBundle\Config\ClassSignatureResource;
use Go\Symfony\GoAopBundle\DependencyInjection\Compiler\ClassSignatureResourcePass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Config\Resource\ReflectionClassResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;

class ClassSignatureResourcePassTest extends TestCase
{
    private string $appDir;

    protected function setUp(): void
    {
        $this->appDir = sys_get_temp_dir() . '/goaop-signature-pass-' . bin2hex(random_bytes(4));
        mkdir($this->appDir . '/src', recursive: true);
        mkdir($this->appDir . '/lib');
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->appDir);
    }

    public function testReflectionResourcesOfWeavableClassesAreReplaced(): void
    {
        $parent  = $this->declareClass('lib');
        $woven   = $this->declareClass('src', $parent);
        $child   = $this->declareClass('lib', $woven);
        $outside = $this->declareClass('lib');
        $file    = new FileResource(__FILE__);

        $container = $this->createContainer();
        foreach ([$woven, $child, $outside] as $class) {
            $container->addResource(new ReflectionClassResource(new ReflectionClass($class)));
        }
        $container->addResource($file);

        new ClassSignatureResourcePass()->process($container);

        $resources = $container->getResources();
        $this->assertCount(4, $resources);
        [$wovenResource, $childResource, $outsideResource, $fileResource] = $resources;

        $this->assertInstanceOf(ClassSignatureResource::class, $wovenResource);
        $this->assertSame('goaop.class_signature.' . $woven, (string) $wovenResource);
        $this->assertSame([$this->fileOf($woven), $this->fileOf($parent)], array_keys($wovenResource->getFiles()));

        $this->assertInstanceOf(ClassSignatureResource::class, $childResource, 'A class with a weavable parent loads the parent');
        $this->assertSame([$this->fileOf($child), $this->fileOf($woven), $this->fileOf($parent)], array_keys($childResource->getFiles()));

        $this->assertInstanceOf(ReflectionClassResource::class, $outsideResource, 'Classes outside of the include paths keep their resource');
        $this->assertSame($file, $fileResource);
        $this->assertTrue($wovenResource->isFresh(time()));
    }

    public function testWovenFilesOfTheCacheDirectoryBelongToWeavableClasses(): void
    {
        // A class woven through the class map of the cache is reported by reflection with its woven file
        mkdir($this->appDir . '/var/cache/aop/src', recursive: true);
        $class     = $this->declareClass('var/cache/aop/src');
        $container = $this->createContainer();
        $container->addResource(new ReflectionClassResource(new ReflectionClass($class)));

        new ClassSignatureResourcePass()->process($container);

        $this->assertInstanceOf(ClassSignatureResource::class, $container->getResources()[0]);
    }

    public function testExcludedPathsAreNotWeavable(): void
    {
        $class     = $this->declareClass('src');
        $container = $this->createContainer(['excludePaths' => [$this->appDir . '/src']]);
        $container->addResource(new ReflectionClassResource(new ReflectionClass($class)));

        new ClassSignatureResourcePass()->process($container);

        $this->assertInstanceOf(ReflectionClassResource::class, $container->getResources()[0]);
    }

    public function testResourcesAreKeptInProductionMode(): void
    {
        $class     = $this->declareClass('src');
        $container = $this->createContainer();
        $container->setParameter('kernel.debug', false);
        $container->addResource(new ReflectionClassResource(new ReflectionClass($class)));

        new ClassSignatureResourcePass()->process($container);

        $this->assertInstanceOf(ReflectionClassResource::class, $container->getResources()[0]);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function createContainer(array $options = []): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', true);
        $container->setParameter('kernel.project_dir', $this->appDir);
        $container->setParameter('goaop.options', $options + [
            'appDir'       => '%kernel.project_dir%',
            'includePaths' => ['%kernel.project_dir%/src'],
            'excludePaths' => [],
            'cacheDir'     => '%kernel.project_dir%/var/cache/aop',
        ]);

        return $container;
    }

    /**
     * Declares a class with a file of its own, in a subdirectory of the application
     *
     * @return class-string
     */
    private function declareClass(string $directory, ?string $parent = null): string
    {
        $class = 'GoAopSignaturePassFixture' . bin2hex(random_bytes(4));
        $file  = $this->appDir . '/' . $directory . '/' . $class . '.php';
        file_put_contents($file, sprintf("<?php\n\nclass %s%s\n{\n}\n", $class, $parent !== null ? ' extends ' . $parent : ''));
        require $file;

        /** @var class-string $class */
        return $class;
    }

    /**
     * @param class-string $class
     */
    private function fileOf(string $class): string
    {
        return (string) new ReflectionClass($class)->getFileName();
    }
}
