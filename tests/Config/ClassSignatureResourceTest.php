<?php

declare(strict_types=1);

namespace Go\Symfony\GoAopBundle\Tests\Config;

use Go\Symfony\GoAopBundle\Config\ClassSignatureResource;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class ClassSignatureResourceTest extends TestCase
{
    private string $directory;

    private string $className;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/goaop-class-signature-' . bin2hex(random_bytes(4));
        $this->className = 'GoAopSignatureFixture' . bin2hex(random_bytes(4));
        mkdir($this->directory);
        $this->writeClass('return 1;');
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->directory);
    }

    public function testUnchangedFileIsFresh(): void
    {
        $resource = new ClassSignatureResource($this->className, [$this->file()]);

        $this->assertTrue($resource->isFresh(time()));
        $this->assertSame('goaop.class_signature.' . $this->className, (string) $resource);
        $this->assertSame([$this->file()], array_keys($resource->getFiles()));
    }

    public function testEditedMethodBodyKeepsTheResourceFreshWithoutLoadingTheClass(): void
    {
        $resource = new ClassSignatureResource($this->className, [$this->file()]);
        $compiledAt = (int) filemtime($this->file());

        $this->writeClass('return 2;', $compiledAt + 10);

        $this->assertTrue($this->unserialized($resource)->isFresh($compiledAt));
        $this->assertFalse(class_exists($this->className, false), 'The freshness check must never load the class');
    }

    public function testChangedSignatureMakesTheResourceStale(): void
    {
        $resource = new ClassSignatureResource($this->className, [$this->file()]);
        $compiledAt = (int) filemtime($this->file());

        $this->writeClass('return 1;', $compiledAt + 10, 'int $factor = 1');

        $this->assertFalse($this->unserialized($resource)->isFresh($compiledAt));
        $this->assertTrue($this->unserialized($resource)->isFresh($compiledAt + 10), 'Files older than the container are not checked');
        $this->assertFalse(class_exists($this->className, false));
    }

    public function testRemovedFileMakesTheResourceStale(): void
    {
        $resource = new ClassSignatureResource($this->className, [$this->file()]);
        unlink($this->file());

        $this->assertFalse($this->unserialized($resource)->isFresh(time()));
    }

    private function file(): string
    {
        return $this->directory . '/' . $this->className . '.php';
    }

    private function writeClass(string $body, ?int $modificationTime = null, string $parameters = ''): void
    {
        file_put_contents($this->file(), sprintf(
            "<?php\n\nfinal class %s\n{\n    public function value(%s): int\n    {\n        %s\n    }\n}\n",
            $this->className,
            $parameters,
            $body,
        ));
        if ($modificationTime !== null) {
            touch($this->file(), $modificationTime);
        }
        clearstatcache();
    }

    /**
     * The resource as the kernel reads it from the meta file of the container
     */
    private function unserialized(ClassSignatureResource $resource): ClassSignatureResource
    {
        $copy = unserialize(serialize($resource));
        $this->assertInstanceOf(ClassSignatureResource::class, $copy);

        return $copy;
    }
}
