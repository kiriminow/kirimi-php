<?php

namespace Kirimi;

use Exception;

/**
 * Kirimi API Exception
 * 
 * Custom exception class for handling Kirimi API-specific errors
 * 
 * @package Kirimi
 * @author Ari Padrian <yolkmonday@gmail.com>
 */
class KirimiException extends Exception
{
    /**
     * @var int|null HTTP status code from the API response, if any
     */
    private ?int $statusCode = null;

    /**
     * Constructor
     * 
     * @param string $message Error message
     * @param int $code Error code (default: 0)
     * @param Exception|null $previous Previous exception
     * @param int|null $statusCode HTTP status code from the API response
     */
    public function __construct(string $message = "", int $code = 0, ?Exception $previous = null, ?int $statusCode = null)
    {
        parent::__construct($message, $code, $previous);
        $this->statusCode = $statusCode;
    }

    /**
     * Get the HTTP status code that caused this error.
     *
     * Returns null when the failure did not come from an HTTP response
     * (e.g. network error or a malformed payload).
     *
     * @return int|null
     */
    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    /**
     * Convert exception to string
     * 
     * @return string
     */
    public function __toString(): string
    {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }
} 