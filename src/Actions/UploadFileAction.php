<?php

namespace App\Actions;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Domain\Services\SftpService;
use App\Actions\Action;
#[\AllowDynamicProperties]
class UploadFileAction extends Action
{
    public function __construct(
        private SftpService $sftpService
    ) {}

    public function action(): Response
    {
        $data = $this->resolveParsedBody();
        $filename = $data['filename'] ?? 'test.txt';
        $content = $data['content'] ?? 'Hello World';

        // Execute Domain Logic
        $result = $this->sftpService->processUpload($filename, $content);

        // Return Response
        return $this->respondWithData('', 200, $result);

    }
}