<?php
declare(strict_types=1);

namespace Tinect\Flysystem\Garbage\Tests;

use League\Flysystem\FileAttributes;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use League\Flysystem\UnableToRetrieveMetadata;

class VisibilityUnsupportedAdapter extends InMemoryFilesystemAdapter
{
    public function visibility(string $path): FileAttributes
    {
        throw new UnableToRetrieveMetadata('Visibility is not supported.');
    }
}
