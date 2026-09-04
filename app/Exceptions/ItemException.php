<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Throwable;

class ItemException extends Exception
{
    protected int $statusCode;
    protected array $extraData;

    public function __construct(
        string $message = "",
        int $statusCode = 422,
        array $extraData = [],
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $statusCode, $previous);
        $this->statusCode = $statusCode;
        $this->extraData = $extraData;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getExtraData(): array
    {
        return $this->extraData;
    }

    /**
     * Render the exception into an HTTP response.
     */
    public function render($request): JsonResponse
    {
        $payload = array_merge([
            'message' => $this->getMessage(),
        ], $this->extraData);

        return response()->json($payload, $this->statusCode);
    }
}
