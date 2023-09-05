<?php

declare(strict_types=1);

namespace Tinect\Flysystem\Garbage;

use League\Flysystem\ChecksumProvider;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToGeneratePublicUrl;
use League\Flysystem\UnableToGenerateTemporaryUrl;
use League\Flysystem\UnableToProvideChecksum;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UrlGeneration\PublicUrlGenerator;
use League\Flysystem\UrlGeneration\TemporaryUrlGenerator;

class GarbageFilesystemAdapter implements FilesystemAdapter, PublicUrlGenerator, ChecksumProvider, TemporaryUrlGenerator
{
    public function __construct(
        private readonly FilesystemAdapter $adapter
    ) {
    }

    public function fileExists(string $path): bool
    {
        return $this->adapter->fileExists($path);
    }

    public function directoryExists(string $path): bool
    {
        return $this->adapter->directoryExists($path);
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $this->copyFileIntoGarbage($path);

        $this->adapter->write($path, $contents, $config);
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->copyFileIntoGarbage($path);

        $this->adapter->writeStream($path, $contents, $config);
    }

    public function read(string $path): string
    {
        return $this->adapter->read($path);
    }

    public function readStream(string $path)
    {
        return $this->adapter->readStream($path);
    }

    public function delete(string $path): void
    {
        $this->copyFileIntoGarbage($path);

        $this->adapter->delete($path);
    }

    public function deleteDirectory(string $path): void
    {
        $this->copyDirectoryIntoGarbage($path);

        $this->adapter->deleteDirectory($path);
    }

    public function createDirectory(string $path, Config $config): void
    {
        $this->adapter->createDirectory($path, $config);
    }

    public function setVisibility(string $path, string $visibility): void
    {
        $this->adapter->setVisibility($path, $visibility);
    }

    public function visibility(string $path): FileAttributes
    {
        return $this->adapter->visibility($path);
    }

    public function mimeType(string $path): FileAttributes
    {
        return $this->adapter->mimeType($path);
    }

    public function lastModified(string $path): FileAttributes
    {
        return $this->adapter->lastModified($path);
    }

    public function fileSize(string $path): FileAttributes
    {
        return $this->adapter->fileSize($path);
    }

    public function listContents(string $path, bool $deep): iterable
    {
        return $this->adapter->listContents($path, $deep);
    }

    public function move(string $source, string $destination, Config $config): void
    {
        $this->copyFileIntoGarbage($source);

        $this->adapter->move($source, $destination, $config);
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        $this->adapter->copy($source, $destination, $config);
    }

    public function publicUrl(string $path, Config $config): string
    {
        if (!$this->adapter instanceof PublicUrlGenerator) {
            throw new UnableToGeneratePublicUrl(sprintf('Given adapter must implement "%s" to use publicUrl.', PublicUrlGenerator::class), $path);
        }

        return $this->adapter->publicUrl($path, $config);
    }

    public function checksum(string $path, Config $config): string
    {
        if (!$this->adapter instanceof ChecksumProvider) {
            throw new UnableToProvideChecksum(sprintf('Given adapter must implement "%s" to use checksum.', ChecksumProvider::class), $path);
        }

        return $this->adapter->checksum($path, $config);
    }

    public function temporaryUrl(string $path, \DateTimeInterface $expiresAt, Config $config): string
    {
        if (!$this->adapter instanceof TemporaryUrlGenerator) {
            throw new UnableToGenerateTemporaryUrl(sprintf('Given adapter must implement "%s" to use temporaryUrl.', TemporaryUrlGenerator::class), $path);
        }

        return $this->adapter->temporaryUrl($path, $expiresAt, $config);
    }

    private function getFiles(string $directoryPath): iterable
    {
        $contents = $this->listContents($directoryPath, true);

        /** @var StorageAttributes $entry */
        foreach ($contents as $entry) {
            if ($entry->isFile() === false) {
                continue;
            }

            yield $entry->path();
        }
    }

    private function copyDirectoryIntoGarbage(string $path): void
    {
        foreach ($this->getFiles($path) as $file) {
            $this->copyFileIntoGarbage($file, true);
        }
    }

    private function copyFileIntoGarbage(string $path, bool $suppressFileExistCheck = false): void
    {
        if (!$suppressFileExistCheck && !$this->fileExists($path)) {
            return;
        }

        $garbagePath = 'garbage/' . date('Ymd') . '/' . $path;

        /* There could be a file on this day */
        if ($this->fileExists($garbagePath)) {
            $garbagePath .= str_replace('.', '', (string) microtime(true));
        }

        $config = new Config();

        try {
            $visibility = $this->adapter->visibility($path)->visibility();

            if (!empty($visibility)) {
                $config->extend([Config::OPTION_VISIBILITY => $visibility]);
            }
        } catch (UnableToRetrieveMetadata) {
        }

        $this->copy($path, $garbagePath, $config);
    }
}
