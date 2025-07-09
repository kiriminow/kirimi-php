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
     * Constructor
     * 
     * @param string $message Error message
     * @param int $code Error code (default: 0)
     * @param Exception|null $previous Previous exception
     */
    public function __construct(string $message = "", int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
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