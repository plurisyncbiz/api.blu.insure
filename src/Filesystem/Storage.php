<?php

namespace App\Filesystem;
use League\Flysystem\FilesystemOperator;
final class Storage implements FileReaderInterface, FileWriterInterface
{
    private FilesystemOperator $filesystem;
    public function __construct(FilesystemOperator $filesystem)
    {
        $this->filesystem = $filesystem;
    }
    public function read(string $location): string
    {
        return $this->filesystem->read($location);
    }
    public function write(string $location, string $data): void
    {
        $this->filesystem->write($location, $data);
    }
}