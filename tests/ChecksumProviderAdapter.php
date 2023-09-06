<?php
declare(strict_types=1);

namespace Tinect\Flysystem\Garbage\Tests;

use League\Flysystem\CalculateChecksumFromStream;
use League\Flysystem\ChecksumProvider;
use League\Flysystem\Config;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use League\Flysystem\UnableToProvideChecksum;
use League\Flysystem\UnableToReadFile;

class ChecksumProviderAdapter extends InMemoryFilesystemAdapter implements ChecksumProvider
{
    use CalculateChecksumFromStream;

    public function checksum(string $path, Config $config): string
    {
        try {
            return $this->calculateChecksumFromStream($path, $config);
        } catch (UnableToReadFile $e) {
            throw new UnableToProvideChecksum($e->getMessage(), $path);
        }
    }
}
