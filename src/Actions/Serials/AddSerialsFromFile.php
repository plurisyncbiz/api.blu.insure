<?php

namespace App\Actions\Serials;

use App\Actions\Action;
use App\Repositories\SerialsRepository;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface as Response;
use App\Filesystem\Storage;
use League\Csv\Reader;

#[\AllowDynamicProperties]
final class AddSerialsFromFile extends Action
{
    protected Logger $logger;

    protected SerialsRepository $serials;
    private Storage $storage;
    public function __construct(Logger $logger, SerialsRepository $serials, Storage $storage)
    {
        $this->logger = $logger;
        $this->serials = $serials;
        $this->storage = $storage;
    }
    protected function action(): Response
    {
        // TODO: Implement action() method.
        $filename = $this->resolveArg('filename');

        //process rows into an array
        $file = file(getcwd() . '/../storage/' . $filename);
        array_shift($file);
        $csv = array_map('str_getcsv', $file);

        $result = $this->serials->addSerials($csv);
        //put in Action
        return $this->respondWithData($result);
    }
}