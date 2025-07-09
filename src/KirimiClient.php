<?php

namespace Kirimi;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;

/**
 * Kirimi WhatsApp API Client for PHP
 * 
 * Official PHP client library for the Kirimi WhatsApp API.
 * Provides methods to send messages, handle OTP verification, and manage WhatsApp communication.
 * 
 * @package Kirimi
 * @author Ari Padrian <yolkmonday@gmail.com>
 * @license MIT
 * @version 1.0.0
 */
class KirimiClient
{
    /**
     * @var string User code for authentication
     */
    private string $userCode;

    /**
     * @var string Secret key for authentication
     */
    private string $secret;

    /**
     * @var string API endpoint base URL
     */
    private string $endpoint;

    /**
     * @var Client Guzzle HTTP client instance
     */
    private Client $httpClient;

    /**
     * Constructor
     * 
     * @param string $userCode User code from Kirimi Dashboard
     * @param string $secret Secret key from Kirimi Dashboard
     * @param string $endpoint API endpoint base URL (default: https://api.kirimi.id)
     */
    public function __construct(string $userCode, string $secret, string $endpoint = 'https://api.kirimi.id')
    {
        $this->userCode = $userCode;
        $this->secret = $secret;
        $this->endpoint = rtrim($endpoint, '/');
        
        $this->httpClient = new Client([
            'base_uri' => $this->endpoint,
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]
        ]);
    }

    /**
     * Send WhatsApp message with optional media
     * 
     * @param string $deviceId Device ID
     * @param string $receiver Receiver phone number (with country code)
     * @param string $message Message content (max 1200 characters)
     * @param string|null $mediaUrl Optional media URL
     * @return array Response data
     * @throws KirimiException When API request fails
     */
    public function sendMessage(string $deviceId, string $receiver, string $message, ?string $mediaUrl = null): array
    {
        $data = [
            'user_code' => $this->userCode,
            'device_id' => $deviceId,
            'receiver' => $receiver,
            'message' => $message,
            'secret' => $this->secret
        ];

        if ($mediaUrl !== null) {
            $data['media_url'] = $mediaUrl;
        }

        try {
            $response = $this->httpClient->post('/v1/send-message', [
                'json' => $data
            ]);

            $body = json_decode($response->getBody()->getContents(), true);
            
            if (!$body['success']) {
                throw new KirimiException($body['message'] ?? 'Failed to send message');
            }

            return $body['data'] ?? [];

        } catch (RequestException $e) {
            $errorMessage = $this->parseErrorResponse($e);
            throw new KirimiException("Send message failed: {$errorMessage}");
        } catch (GuzzleException $e) {
            throw new KirimiException("HTTP request failed: {$e->getMessage()}");
        }
    }

    /**
     * Generate and send OTP to WhatsApp number
     * 
     * @param string $deviceId Device ID
     * @param string $phone Phone number to send OTP
     * @return array Response data
     * @throws KirimiException When API request fails
     */
    public function generateOTP(string $deviceId, string $phone): array
    {
        $data = [
            'user_code' => $this->userCode,
            'device_id' => $deviceId,
            'phone' => $phone,
            'secret' => $this->secret
        ];

        try {
            $response = $this->httpClient->post('/v1/generate-otp', [
                'json' => $data
            ]);

            $body = json_decode($response->getBody()->getContents(), true);
            
            if (!$body['success']) {
                throw new KirimiException($body['message'] ?? 'Failed to generate OTP');
            }

            return $body['data'] ?? [];

        } catch (RequestException $e) {
            $errorMessage = $this->parseErrorResponse($e);
            throw new KirimiException("Generate OTP failed: {$errorMessage}");
        } catch (GuzzleException $e) {
            throw new KirimiException("HTTP request failed: {$e->getMessage()}");
        }
    }

    /**
     * Validate OTP code
     * 
     * @param string $deviceId Device ID
     * @param string $phone Phone number that received OTP
     * @param string $otp OTP code to validate
     * @return array Response data
     * @throws KirimiException When API request fails
     */
    public function validateOTP(string $deviceId, string $phone, string $otp): array
    {
        $data = [
            'user_code' => $this->userCode,
            'device_id' => $deviceId,
            'phone' => $phone,
            'otp' => $otp,
            'secret' => $this->secret
        ];

        try {
            $response = $this->httpClient->post('/v1/validate-otp', [
                'json' => $data
            ]);

            $body = json_decode($response->getBody()->getContents(), true);
            
            if (!$body['success']) {
                throw new KirimiException($body['message'] ?? 'Failed to validate OTP');
            }

            return $body['data'] ?? [];

        } catch (RequestException $e) {
            $errorMessage = $this->parseErrorResponse($e);
            throw new KirimiException("Validate OTP failed: {$errorMessage}");
        } catch (GuzzleException $e) {
            throw new KirimiException("HTTP request failed: {$e->getMessage()}");
        }
    }

    /**
     * Check API health status
     * 
     * @return array Health status response
     * @throws KirimiException When API request fails
     */
    public function healthCheck(): array
    {
        try {
            $response = $this->httpClient->get('/');
            $body = json_decode($response->getBody()->getContents(), true);
            
            return $body ?? [];

        } catch (RequestException $e) {
            $errorMessage = $this->parseErrorResponse($e);
            throw new KirimiException("Health check failed: {$errorMessage}");
        } catch (GuzzleException $e) {
            throw new KirimiException("HTTP request failed: {$e->getMessage()}");
        }
    }

    /**
     * Parse error response from API
     * 
     * @param RequestException $exception
     * @return string Error message
     */
    private function parseErrorResponse(RequestException $exception): string
    {
        if ($exception->hasResponse()) {
            $response = $exception->getResponse();
            $body = json_decode($response->getBody()->getContents(), true);
            
            if (isset($body['message'])) {
                return $body['message'];
            }
        }

        return $exception->getMessage();
    }

    /**
     * Get user code
     * 
     * @return string
     */
    public function getUserCode(): string
    {
        return $this->userCode;
    }

    /**
     * Get API endpoint
     * 
     * @return string
     */
    public function getEndpoint(): string
    {
        return $this->endpoint;
    }
} 