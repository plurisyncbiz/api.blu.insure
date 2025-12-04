<?php

namespace App\Filesystem;

interface FileReaderInterface
{
    public function read(string $location): string;
}