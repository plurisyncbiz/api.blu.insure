<?php

namespace App\Domain\Services;

use App\Domain\Interfaces\SftpInterface;

class SftpService
{
    public function __construct(private SftpInterface $sftp) {}

    public function processUpload(string $filename, string $content): array
    {
        $this->sftp->connect();

        // Business logic: e.g., define remote path, validate content, etc.
        $remotePath = '/outgoing/' . $filename;
        $success = $this->sftp->upload($remotePath, $content);

        $files = $this->sftp->listFiles('/outgoing');

        $this->sftp->disconnect();

        return [
            'success' => $success,
            'uploaded_file' => $filename,
            'current_files' => $files
        ];
    }
}