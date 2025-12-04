<?php

namespace Filesystem;

interface FileReaderInterface
{
    public function read(string $location): string;
}