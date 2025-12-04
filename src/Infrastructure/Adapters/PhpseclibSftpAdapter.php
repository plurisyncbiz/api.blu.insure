<?php

namespace App\Infrastructure\Adapters;

use App\Domain\Interfaces\SftpInterface;
use phpseclib3\Net\SFTP;
use RuntimeException;
class PhpseclibSftpAdapter implements SftpInterface
{

    private SFTP $sftp;

    public function __construct(
        private string $host,
        private string $username,
        private string $password,
        private int $port = 22
    ) {}

    public function connect(): void
    {
        $this->sftp = new SFTP($this->host, $this->port);
        if (!$this->sftp->login($this->username, $this->password)) {
            throw new RuntimeException("SFTP Login Failed");
        }
    }

    public function upload(string $remotePath, string $content): bool
    {
        return $this->sftp->put($remotePath, $content);
    }

    public function listFiles(string $path): array
    {
        return $this->sftp->nlist($path);
    }

    public function disconnect(): void
    {
        $this->sftp->disconnect();
    }
}