<?php
declare(strict_types=1);

namespace Tinect\Flysystem\Garbage\Tests;

use League\Flysystem\Config;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use League\Flysystem\UnableToGeneratePublicUrl;
use League\Flysystem\UnableToGenerateTemporaryUrl;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UrlGeneration\PublicUrlGenerator;
use League\Flysystem\UrlGeneration\TemporaryUrlGenerator;

class UrlGeneratorAdapter extends InMemoryFilesystemAdapter implements TemporaryUrlGenerator, PublicUrlGenerator
{
    private $fp;

    public function publicUrl(string $path, Config $config): string
    {
        try {
            return $this->getUrl($path);
        } catch (UnableToReadFile $e) {
            throw new UnableToGeneratePublicUrl($e->getMessage(), $path);
        }
    }

    public function temporaryUrl(string $path, \DateTimeInterface $expiresAt, Config $config): string
    {
        try {
            return $this->getUrl($path);
        } catch (UnableToReadFile $e) {
            throw new UnableToGenerateTemporaryUrl($e->getMessage(), $path);
        }
    }

    private function getUrl(string $path)
    {
        $this->fp = tmpfile();

        stream_copy_to_stream($this->readStream($path), $this->fp);

        rewind($this->fp);

        return stream_get_meta_data($this->fp)['uri'];
    }
}
