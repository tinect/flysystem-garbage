<?php
declare(strict_types=1);

namespace Tinect\Flysystem\Garbage\Tests;

use League\Flysystem\Config;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use League\Flysystem\UnableToCopyFile;

/**
 * InMemoryFilesystemAdapter does just copy visibility from source, so we need to set it here.
 * Other adapters, like AsyncAwsS3Adapter, support it out of the box.
 *
 * Issue opened: https://github.com/thephpleague/flysystem/issues/1697
 */
class VisibilityFromConfigWhenCopyAdapter extends InMemoryFilesystemAdapter
{
    public function copy(string $source, string $destination, Config $config): void
    {
        $visibility = $config->get(Config::OPTION_VISIBILITY);
        if ($visibility === null) {
            throw UnableToCopyFile::fromLocationTo($source, $destination);
        }

        parent::copy($source, $destination, $config);
        $this->setVisibility($destination, $visibility);
    }
}
