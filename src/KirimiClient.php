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
     * @param string $phone Recipient phone number (with country code)
     * @param string $message Message content
     * @param string|null $mediaUrl Optional media URL
     * @return array Response data
     * @throws KirimiException When API request fails
     */
    public function sendMessage(string $deviceId, string $phone, string $message, ?string $mediaUrl = null): array
    {
        $data = [
            'user_code' => $this->userCode,
            'secret'    => $this->secret,
            'device_id' => $deviceId,
            'phone'     => $phone,
            'message'   => $message,
        ];

        if ($mediaUrl !== null) {
            $data['media_url'] = $mediaUrl;
        }

        return $this->postJson('/v1/send-message', $data, 'send message');
    }

    /**
     * Send WhatsApp message without typing effect (fast mode)
     *
     * @param string $deviceId Device ID
     * @param string $phone Recipient phone number
     * @param string $message Message content
     * @param string|null $mediaUrl Optional media URL
     * @return array Response data
     * @throws KirimiException When API request fails
     */
    public function sendMessageFast(string $deviceId, string $phone, string $message, ?string $mediaUrl = null): array
    {
        $data = [
            'user_code' => $this->userCode,
            'secret'    => $this->secret,
            'device_id' => $deviceId,
            'phone'     => $phone,
            'message'   => $message,
        ];

        if ($mediaUrl !== null) {
            $data['media_url'] = $mediaUrl;
        }

        return $this->postJson('/v1/send-message-fast', $data, 'send message fast');
    }

    /**
     * Send WhatsApp message with a file via multipart/form-data (max 50MB)
     *
     * @param string $deviceId Device ID
     * @param string $phone Recipient phone number
     * @param string $filePath Absolute path to the file
     * @param array $options Optional: message (string), fileName (string)
     * @return array Response data
     * @throws KirimiException When API request fails
     */
    public function sendMessageFile(string $deviceId, string $phone, string $filePath, array $options = []): array
    {
        $multipart = [
            ['name' => 'user_code', 'contents' => $this->userCode],
            ['name' => 'secret',    'contents' => $this->secret],
            ['name' => 'device_id', 'contents' => $deviceId],
            ['name' => 'phone',     'contents' => $phone],
            [
                'name'     => 'file',
                'contents' => fopen($filePath, 'r'),
                'filename' => $options['fileName'] ?? basename($filePath),
            ],
        ];

        if (!empty($options['message'])) {
            $multipart[] = ['name' => 'message', 'contents' => $options['message']];
        }

        if (!empty($options['fileName'])) {
            $multipart[] = ['name' => 'fileName', 'contents' => $options['fileName']];
        }

        try {
            $response = $this->httpClient->post('/v1/send-message-file', [
                'multipart' => $multipart,
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            if (!$body['success']) {
                throw new KirimiException($body['message'] ?? 'Failed to send message file');
            }

            return $body['data'] ?? [];

        } catch (RequestException $e) {
            throw new KirimiException('Send message file failed: ' . $this->parseErrorResponse($e));
        } catch (GuzzleException $e) {
            throw new KirimiException('HTTP request failed: ' . $e->getMessage());
        }
    }

    /**
     * Send message via WhatsApp Business API (WABA / Meta Cloud API)
     *
     * @param string $deviceId WABA device ID
     * @param string $phone Recipient phone number
     * @param string $message Message content
     * @return array Response data
     * @throws KirimiException When API request fails
     */
    public function sendWabaMessage(string $deviceId, string $phone, string $message): array
    {
        return $this->postJson('/v1/waba/send-message', [
            'user_code' => $this->userCode,
            'secret'    => $this->secret,
            'device_id' => $deviceId,
            'phone'     => $phone,
            'message'   => $message,
        ], 'send WABA message');
    }

    /**
     * List all registered devices
     *
     * @return array Response data
     * @throws KirimiException When API request fails
     */
    public function listDevices(): array
    {
        return $this->postJson('/v1/list-devices', [
            'user_code' => $this->userCode,
            'secret'    => $this->secret,
        ], 'list devices');
    }

    /**
     * Get device connection status
     *
     * @param string $deviceId Device ID
     * @return array Response data
     * @throws KirimiException When API request fails
     */
    public function deviceStatus(string $deviceId): array
    {
        return $this->postJson('/v1/device-status', [
            'user_code' => $this->userCode,
            'secret'    => $this->secret,
            'device_id' => $deviceId,
        ], 'device status');
    }

    /**
     * Get enhanced/detailed device status
     *
     * @param string $deviceId Device ID
     * @return array Response data
     * @throws KirimiException When API request fails
     */
    public function deviceStatusEnhanced(string $deviceId): array
    {
        return $this->postJson('/v1/device-status-enhanced', [
            'user_code' => $this->userCode,
            'secret'    => $this->secret,
            'device_id' => $deviceId,
        ], 'device status enhanced');
    }

    /**
     * Get current user account info
     *
     * @return array Response data
     * @throws KirimiException When API request fails
     */
    public function userInfo(): array
    {
        return $this->postJson('/v1/user-info', [
            'user_code' => $this->userCode,
            'secret'    => $this->secret,
        ], 'user info');
    }

    /**
     * Save a contact
     *
     * @param string $phone Phone number
     * @param array $options Optional: name (string), email (string)
     * @return array Response data
     * @throws KirimiException When API request fails
     */
    public function saveContact(string $phone, array $options = []): array
    {
        $data = [
            'user_code' => $this->userCode,
            'secret'    => $this->secret,
            'phone'     => $phone,
        ];

        if (!empty($options['name'])) {
            $data['name'] = $options['name'];
        }

        if (!empty($options['email'])) {
            $data['email'] = $options['email'];
        }

        return $this->postJson('/v1/save-contact', $data, 'save contact');
    }

    /**
     * Generate and send OTP to WhatsApp number
     *
     * @param string $deviceId Device ID
     * @param string $phone Phone number to send OTP
     * @param array $options Optional: otp_length (int), otp_type (numeric|alphabetic|alphanumeric), customOtpMessage (string)
     * @return array Response data
     * @throws KirimiException When API request fails
     */
    public function generateOTP(string $deviceId, string $phone, array $options = []): array
    {
        $data = [
            'user_code' => $this->userCode,
            'secret'    => $this->secret,
            'device_id' => $deviceId,
            'phone'     => $phone,
        ];

        if (!empty($options['otp_length'])) {
            $data['otp_length'] = $options['otp_length'];
        }

        if (!empty($options['otp_type'])) {
            $data['otp_type'] = $options['otp_type'];
        }

        if (!empty($options['customOtpMessage'])) {
            $data['customOtpMessage'] = $options['customOtpMessage'];
        }

        return $this->postJson('/v1/generate-otp', $data, 'generate OTP');
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
        return $this->postJson('/v1/validate-otp', [
            'user_code' => $this->userCode,
            'secret'    => $this->secret,
            'device_id' => $deviceId,
            'phone'     => $phone,
            'otp'       => $otp,
        ], 'validate OTP');
    }

    /**
     * Send OTP via WABA template or device (V2)
     *
     * @param string $phone Recipient phone number
     * @param string $deviceId Device ID
     * @param array $options Optional: method (device|waba), app_name, template_code, custom_message
     * @return array Response data
     * @throws KirimiException When API request fails
     */
    public function sendOtpV2(string $phone, string $deviceId, array $options = []): array
    {
        $data = [
            'user_code' => $this->userCode,
            'secret'    => $this->secret,
            'phone'     => $phone,
            'device_id' => $deviceId,
        ];

        foreach (['method', 'app_name', 'template_code', 'custom_message'] as $key) {
            if (!empty($options[$key])) {
                $data[$key] = $options[$key];
            }
        }

        return $this->postJson('/v2/otp/send', $data, 'send OTP v2');
    }

    /**
     * Verify OTP code (V2)
     *
     * @param string $phone Phone number
     * @param string $otpCode OTP code to verify
     * @return array Response data
     * @throws KirimiException When API request fails
     */
    public function verifyOtpV2(string $phone, string $otpCode): array
    {
        return $this->postJson('/v2/otp/verify', [
            'user_code' => $this->userCode,
            'secret'    => $this->secret,
            'phone'     => $phone,
            'otp_code'  => $otpCode,
        ], 'verify OTP v2');
    }

    /**
     * Broadcast message to multiple recipients
     *
     * @param string $deviceId Device ID
     * @param array|string $phones Array or comma-separated string of phone numbers
     * @param string $message Message content
     * @param array $options Optional: delay (int, seconds between messages)
     * @return array Response data
     * @throws KirimiException When API request fails
     */
    public function broadcastMessage(string $deviceId, $phones, string $message, array $options = []): array
    {
        if (is_array($phones)) {
            $phones = implode(',', $phones);
        }

        $data = [
            'user_code' => $this->userCode,
            'secret'    => $this->secret,
            'device_id' => $deviceId,
            'phones'    => $phones,
            'message'   => $message,
        ];

        if (isset($options['delay'])) {
            $data['delay'] = (int) $options['delay'];
        }

        return $this->postJson('/v1/broadcast-message', $data, 'broadcast message');
    }

    /**
     * List deposits
     *
     * @param string|null $status Filter by status: '', 'paid', 'unpaid', 'expired'
     * @return array Response data
     * @throws KirimiException When API request fails
     */
    public function listDeposits(?string $status = null): array
    {
        $data = [
            'user_code' => $this->userCode,
            'secret'    => $this->secret,
        ];

        if ($status !== null) {
            $data['status'] = $status;
        }

        return $this->postJson('/v1/list-deposits', $data, 'list deposits');
    }

    /**
     * List available packages
     *
     * @return array Response data
     * @throws KirimiException When API request fails
     */
    public function listPackages(): array
    {
        return $this->postJson('/v1/list-packages', [
            'user_code' => $this->userCode,
            'secret'    => $this->secret,
        ], 'list packages');
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
     * Internal helper: POST JSON and return data array
     *
     * @param string $path API path
     * @param array $data Request body
     * @param string $action Action label for error messages
     * @return array Response data
     * @throws KirimiException
     */
    private function postJson(string $path, array $data, string $action): array
    {
        try {
            $response = $this->httpClient->post($path, ['json' => $data]);
            $body = json_decode($response->getBody()->getContents(), true);

            if (!$body['success']) {
                throw new KirimiException($body['message'] ?? "Failed to {$action}");
            }

            return $body['data'] ?? [];

        } catch (RequestException $e) {
            throw new KirimiException(ucfirst($action) . ' failed: ' . $this->parseErrorResponse($e));
        } catch (GuzzleException $e) {
            throw new KirimiException('HTTP request failed: ' . $e->getMessage());
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
