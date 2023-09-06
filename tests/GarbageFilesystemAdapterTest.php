<?php
declare(strict_types=1);

namespace Tinect\Flysystem\Garbage\Tests;

use League\Flysystem\AdapterTestUtilities\FilesystemAdapterTestCase;
use League\Flysystem\ChecksumProvider;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use League\Flysystem\UnableToGeneratePublicUrl;
use League\Flysystem\UnableToGenerateTemporaryUrl;
use League\Flysystem\UnableToProvideChecksum;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UrlGeneration\PublicUrlGenerator;
use League\Flysystem\UrlGeneration\TemporaryUrlGenerator;
use League\Flysystem\Visibility;
use PHPUnit\Framework\MockObject\Generator\MockClass;
use Tinect\Flysystem\Garbage\GarbageFilesystemAdapter;

class GarbageFilesystemAdapterTest extends FilesystemAdapterTestCase
{
    protected static function createFilesystemAdapter(): FilesystemAdapter
    {
        return new GarbageFilesystemAdapter(new InMemoryFilesystemAdapter());
    }

    // we want the default tests to be succeeded, so we make sure the garbage-folder is removed after each test
    protected function tearDown(): void
    {
        $this->clearStorage();
    }

    public function test_empty_garbage_path_throws_error(): void
    {
        static::expectException(\InvalidArgumentException::class);
        static::expectExceptionMessage('Garbage path must not be empty.');

        new GarbageFilesystemAdapter(new InMemoryFilesystemAdapter(), '');
    }

    public function test_garbage_path_is_trimmed(): void
    {
        $adapter = new GarbageFilesystemAdapter(new InMemoryFilesystemAdapter(), '/garbage/');

        static::assertEquals('garbage', $adapter->getGarbagePath());
    }

    public function test_overwriting_file_produces_garbage_entry(): void
    {
        $adapter = $this->adapter();

        $adapter->write('file.txt', 'contents', new Config());
        $adapter->write('file.txt', 'contents1', new Config());
        self::assertSame('contents1', $adapter->read('file.txt'));

        $garbagePath = 'garbage/' . date('Ymd') . '/file.txt';

        self::assertTrue($adapter->fileExists($garbagePath));
        self::assertSame('contents', $adapter->read($garbagePath));
    }

    public function test_overwriting_with_streamed_file_produces_garbage_entry(): void
    {
        $adapter = $this->adapter();

        $writeStream = stream_with_contents('contents');
        $adapter->writeStream('file.txt', $writeStream, new Config());
        if (is_resource($writeStream)) {
            fclose($writeStream);
        }

        $writeStream = stream_with_contents('contents1');
        $adapter->writeStream('file.txt', $writeStream, new Config());
        if (is_resource($writeStream)) {
            fclose($writeStream);
        }

        self::assertSame('contents1', $adapter->read('file.txt'));

        $garbagePath = 'garbage/' . date('Ymd') . '/file.txt';

        self::assertTrue($adapter->fileExists($garbagePath));
        self::assertSame('contents', $adapter->read($garbagePath));
    }

    public function test_deleting_file_produces_garbage_entry(): void
    {
        $adapter = $this->adapter();

        $adapter->write('file.txt', 'contents', new Config());
        $adapter->delete('file.txt');

        $garbagePath = 'garbage/' . date('Ymd') . '/file.txt';

        self::assertTrue($adapter->fileExists($garbagePath));
        self::assertSame('contents', $adapter->read($garbagePath));
    }

    public function test_deleting_directory_with_multiple_files_produces_garbage_entries(): void
    {
        $adapter = $this->adapter();

        $adapter->write('dir/file1.txt', 'contents', new Config());
        $adapter->write('dir/file2.txt', 'contents', new Config());
        $adapter->deleteDirectory('dir');

        $garbagePath = 'garbage/' . date('Ymd') . '/dir/file1.txt';
        $garbagePath2 = 'garbage/' . date('Ymd') . '/dir/file2.txt';

        self::assertTrue($adapter->fileExists($garbagePath));
        self::assertSame('contents', $adapter->read($garbagePath));
        self::assertTrue($adapter->fileExists($garbagePath2));
        self::assertSame('contents', $adapter->read($garbagePath2));
    }

    public function test_moving_file_produces_garbage_entry(): void
    {
        $adapter = $this->adapter();

        $adapter->write('file.txt', 'contents', new Config());
        $adapter->move('file.txt', 'file1.txt', new Config());

        $garbagePath = 'garbage/' . date('Ymd') . '/file.txt';

        self::assertTrue($adapter->fileExists($garbagePath));
        self::assertSame('contents', $adapter->read($garbagePath));
    }

    public function test_copyfileIntoGarbage_with_invalid_source_visibility_throws_error(): void
    {
        $sourceAdapter = new VisibilityUnsupportedAdapter();
        $adapter = new GarbageFilesystemAdapter($sourceAdapter);

        $adapter->write('file.txt', 'contents', new Config([Config::OPTION_VISIBILITY => Visibility::PUBLIC]));
        $adapter->delete('file.txt');

        $garbagePath = 'garbage/' . date('Ymd') . '/file.txt';

        self::assertTrue($adapter->fileExists($garbagePath));

        $this->expectException(UnableToRetrieveMetadata::class);
        $adapter->visibility($garbagePath)->visibility();
    }

    public function test_file_in_garbage_has_same_visibility_as_source_file(): void
    {
        $sourceAdapter = new VisibilityFromConfigWhenCopyAdapter();
        $adapter = new GarbageFilesystemAdapter($sourceAdapter);

        $adapter->write('filea.txt', 'contents', new Config([Config::OPTION_VISIBILITY => 'my-visibility']));
        $adapter->delete('filea.txt');

        $garbagePath = 'garbage/' . date('Ymd') . '/filea.txt';

        self::assertSame('my-visibility', $adapter->visibility($garbagePath)->visibility());
    }

    public function test_directory_delete_results_in_listContents_with_deep(): void
    {
        $sourceAdapter = $this->createMock(FilesystemAdapter::class);
        $sourceAdapter->expects(self::once())->method('listContents')->with('dir', true)
            ->willReturn([]);

        $adapter = new GarbageFilesystemAdapter($sourceAdapter);

        $adapter->createDirectory('dir', new Config());
        $adapter->deleteDirectory('dir');
    }

    public function test_directory_delete_results_in_copyFileIntoGarbage_with_suppressed_fileExist(): void
    {
        $sourceAdapter = $this->createMock(FilesystemAdapter::class);

        $sourceAdapter->expects(self::exactly(1))->method('listContents')->with('dir', true)
            ->willReturn([
                new FileAttributes('dir/file.txt'),
            ]);

        $sourceAdapter->expects(self::exactly(3))->method('fileExists')->willReturn(true);

        $adapter = new GarbageFilesystemAdapter($sourceAdapter);

        $adapter->createDirectory('dir', new Config());
        $adapter->write('dir/file.txt', 'asdf', new Config());
        $adapter->deleteDirectory('dir');
    }

    /**
     * @test
     */
    public function generating_a_public_url(): void
    {
        if (!is_callable('parent::generating_a_public_url')) {
            $this->markTestSkipped();
        }

        static::$adapter = new GarbageFilesystemAdapter(new UrlGeneratorAdapter());
        parent::generating_a_public_url();
    }

    public function test_public_url_with_adapter_without_support_throws_error(): void
    {
        $sourceAdapter = new InMemoryFilesystemAdapter();

        static::$adapter = new GarbageFilesystemAdapter($sourceAdapter);
        self::assertNotInstanceOf(PublicUrlGenerator::class, $sourceAdapter);

        static::expectException(UnableToGeneratePublicUrl::class);
        parent::generating_a_public_url();
    }

    /**
     * @test
     */
    public function generating_a_temporary_url(): void
    {
        if (!is_callable('parent::generating_a_temporary_url')) {
            $this->markTestSkipped();
        }

        static::$adapter = new GarbageFilesystemAdapter(new UrlGeneratorAdapter());
        parent::generating_a_temporary_url();
    }

    public function test_temporary_url_with_adapter_without_support_throws_error(): void
    {
        $sourceAdapter = new InMemoryFilesystemAdapter();

        static::$adapter = new GarbageFilesystemAdapter($sourceAdapter);
        self::assertNotInstanceOf(TemporaryUrlGenerator::class, $sourceAdapter);

        static::expectException(UnableToGenerateTemporaryUrl::class);
        parent::generating_a_temporary_url();
    }

    /**
     * @test
     */
    public function get_checksum(): void
    {
        if (!is_callable('parent::get_checksum')) {
            $this->markTestSkipped();
        }

        static::$adapter = new GarbageFilesystemAdapter(new ChecksumProviderAdapter());
        parent::get_checksum();
    }

    public function test_checksum_with_adapter_without_support_throws_error(): void
    {
        $sourceAdapter = new InMemoryFilesystemAdapter();

        static::$adapter = new GarbageFilesystemAdapter($sourceAdapter);
        self::assertNotInstanceOf(ChecksumProvider::class, $sourceAdapter);

        static::expectException(UnableToProvideChecksum::class);
        parent::get_checksum();
    }

    /**
     * @test
     */
    public function cannot_get_checksum_for_non_existent_file(): void
    {
        if (!is_callable('parent::cannot_get_checksum_for_non_existent_file')) {
            $this->markTestSkipped();
        }

        static::$adapter = new GarbageFilesystemAdapter(new ChecksumProviderAdapter());
        parent::cannot_get_checksum_for_non_existent_file();
    }

    /**
     * @test
     */
    public function cannot_get_checksum_for_directory(): void
    {
        if (!is_callable('parent::cannot_get_checksum_for_directory')) {
            $this->markTestSkipped();
        }

        static::$adapter = new GarbageFilesystemAdapter(new ChecksumProviderAdapter());
        parent::cannot_get_checksum_for_directory();
    }
}