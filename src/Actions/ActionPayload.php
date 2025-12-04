<?php

namespace App\Actions;
use JsonSerializable;

class ActionPayload implements JsonSerializable
{

    private int $statusCode;
    /**
     * @var array|object|null
     */
    private $data;
    private $description;

    private ?ActionError $error;
    /**
     * @inheritDoc
     */
    public function __construct(
        int $statusCode = 200,
            $data = null,
            $description = null,
        ?ActionError $error = null,

    ) {
        $this->statusCode = $statusCode;
        $this->data = $data;
        $this->description = $description;
        $this->error = $error;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array|null|object
     */
    public function getData()
    {
        return $this->data;
    }

    public function getError(): ?ActionError
    {
        return $this->error;
    }
    public function getDescription()
    {
        return $this->description;
    }

    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        $payload = [
            'type' => $this->statusCode == 200 ? 'success' : 'error',
            'description' => $this->getDescription()
        ];

        if ($this->data !== null) {
            $payload['data'] = $this->data;
        } elseif ($this->error !== null) {
            $payload['error'] = $this->error;
        }

        return $payload;
    }
}