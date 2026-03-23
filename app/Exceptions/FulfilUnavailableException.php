<?php

namespace App\Exceptions;

use Exception;

/**
 * Exception thrown when the Fulfil API is unreachable or times out.
 *
 * This indicates a connectivity issue (DNS failure, server down, timeout with
 * 0 bytes received) rather than an application-level API error. Controllers
 * should catch this to show a user-friendly "service unavailable" message
 * instead of raw cURL errors.
 */
class FulfilUnavailableException extends Exception
{
    protected string $endpoint;

    protected string $method;

    protected float $timeoutSeconds;

    public function __construct(
        string $message,
        string $endpoint,
        string $method,
        float $timeoutSeconds,
        ?Exception $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->endpoint = $endpoint;
        $this->method = $method;
        $this->timeoutSeconds = $timeoutSeconds;
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getTimeoutSeconds(): float
    {
        return $this->timeoutSeconds;
    }

    /**
     * User-facing message suitable for display in the UI.
     */
    public function getUserMessage(): string
    {
        return 'Unable to reach our data provider (Fulfil). Please try again later or contact support if the issue persists.';
    }
}
