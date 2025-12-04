<?php
namespace App\Domain\Interfaces;

interface SftpInterface
{
    public function connect(): void;
    public function upload(string $remotePath, string $localContent): bool;
    public function listFiles(string $path): array;
    public function disconnect(): void;
}