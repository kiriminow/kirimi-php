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
     * @param array|null $httpOptions Extra Guzzle client options (e.g. handler for tests)
     */
    public function __construct(
        string $userCode,
        string $secret,
        string $endpoint = 'https://api.kirimi.id',
        ?array $httpOptions = null
    ) {
        $this->userCode = $userCode;
        $this->secret = $secret;
        $this->endpoint = rtrim($endpoint, '/');

        $this->httpClient = new Client(array_merge([
            'base_uri' => $this->endpoint,
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]
        ], $httpOptions ?? []));
    }

    // ─── Internal helpers ───────────────────────────────────────────────────

    /**
     * Internal helper: auth fields present in every request body
     *
     * @return array
     */
    private function authData(): array
    {
        return [
            'user_code' => $this->userCode,
            'secret'    => $this->secret,
        ];
    }

    /**
     * Internal helper: drop null values so optional fields never reach the wire
     *
     * @param array $data Request body
     * @return array
     */
    private function compact(array $data): array
    {
        return array_filter($data, static function ($value) {
            return $value !== null;
        });
    }

    // ─── WhatsApp Unofficial ────────────────────────────────────────────────

    /**
     * Send WhatsApp message with optional media
     *
     * @param string $deviceId Device ID
     * @param string $receiver Recipient phone number (with country code)
     * @param string $message Message content
     * @param string|null $mediaUrl Optional media URL
     * @param array $options Optional: fileName (string), enableTypingEffect (bool),
     *                       typingSpeedMs (int, 100-800), quotedMessageId (string)
     * @return array Response data
     * @throws KirimiException When API request fails
     */
    public function sendMessage(
        string $deviceId,
        string $receiver,
        string $message,
        ?string $mediaUrl = null,
        array $options = []
    ): array {
        $data = [
            'device_id' => $deviceId,
            'receiver'  => $receiver,
            'message'   => $message,
            'media_url' => $mediaUrl,
        ];

        foreach (['fileName', 'enableTypingEffect', 'typingSpeedMs', 'quotedMessageId'] as $key) {
            if (isset($options[$key])) {
                $data[$key] = $options[$key];
            }
        }

        return $this->postJson('/v1/send-message', $data, 'send message');
    }

    /**
     * Send WhatsApp message without typing effect (fast mode)
     *
     * @param string $deviceId Device ID
     * @param string $receiver Recipient phone number
     * @param string $message Message content
     * @param string|null $mediaUrl Optional media URL
     * @param array $options Optional: fileName (string), quotedMessageId (string)
     * @return array Response data
     * @throws KirimiException When API request fails
     */
    public function sendMessageFast(
        string $deviceId,
        string $receiver,
        string $message,
        ?string $mediaUrl = null,
        array $options = []
    ): array {
        $data = [
            'device_id' => $deviceId,
            'receiver'  => $receiver,
            'message'   => $message,
            'media_url' => $mediaUrl,
        ];

        foreach (['fileName', 'quotedMessageId'] as $key) {
            if (isset($options[$key])) {
                $data[$key] = $options[$key];
            }
        }

        return $this->postJson('/v1/send-message-fast', $data, 'send message fast');
    }

    /**
     * Send WhatsApp message with a file via multipart/form-data (max 50MB)
     *
     * @param string $deviceId Device ID
     * @param string $receiver Recipient phone number
     * @param string $filePath Absolute path to the file
     * @param array $options Optional: message (string), caption (string),
     *                       fileName (string), quotedMessageId (string)
     * @return array Response data
     * @throws KirimiException When API request fails
     */
    public function sendMessageFile(
        string $deviceId,
        string $receiver,
        string $filePath,
        array $options = []
    ): array {
        $fileName = $options['fileName'] ?? basename($filePath);

        $multipart = [
            ['name' => 'user_code', 'contents' => $this->userCode],
            ['name' => 'secret',    'contents' => $this->secret],
            ['name' => 'device_id', 'contents' => $deviceId],
            ['name' => 'receiver',  'contents' => $receiver],
            [
                'name'     => 'file',
                'contents' => fopen($filePath, 'r'),
                'filename' => $fileName,
            ],
            ['name' => 'fileName', 'contents' => $fileName],
        ];

        foreach (['message', 'caption', 'quotedMessageId'] as $key) {
            if (!empty($options[$key])) {
                $multipart[] = ['name' => $key, 'contents' => $options[$key]];
            }
        }

        try {
            $response = $this->httpClient->post('/v1/send-message-file', [
                'multipart' => $multipart,
            ]);

            return $this->unwrap($response);

        } catch (RequestException $e) {
            throw $this->fromRequestException($e, 'Send message file');
        } catch (GuzzleException $e) {
            throw new KirimiException('HTTP request failed: ' . $e->getMessage());
        }
    }

    /**
     * Broadcast message to multiple recipients
     *
     * @param string $deviceId Device ID
     * @param string $label Broadcast label for identification (max 100 chars)
     * @param array $numbers Array of recipient phone numbers (max 1000)
     * @param string $message Message content
     * @param array $options Optional: delay (int), delayMin (int), delayMax (int),
     *                       media_url (string), fileName (string), started_at (string),
     *                       enableTypingEffect (bool), typingSpeedMs (int)
     * @return array Response data
     * @throws KirimiException When API request fails
     */
    public function broadcastMessage(
        string $deviceId,
        string $label,
        array $numbers,
        string $message,
        array $options = []
    ): array {
        $data = [
            'device_id' => $deviceId,
            'label'     => $label,
            'numbers'   => array_values($numbers),
            'message'   => $message,
        ];

        foreach ([
            'delay', 'delayMin', 'delayMax', 'media_url', 'fileName',
            'started_at', 'enableTypingEffect', 'typingSpeedMs',
        ] as $key) {
            if (isset($options[$key])) {
                $data[$key] = $options[$key];
            }
        }

        return $this->postJson('/v1/broadcast-message', $data, 'broadcast message');
    }

    // ─── WABA (Meta Cloud API) ──────────────────────────────────────────────

    /**
     * Send a Meta-approved template via WhatsApp Business API (WABA / Meta Cloud API)
     *
     * @param string $wabaId WhatsApp Business Account ID (not the device ID)
     * @param string $to Recipient phone number
     * @param string $templateName Meta-approved template name
     * @param array $options Optional: variables (string[]), header (array), buttons (array)
     * @return array Response data
     * @throws KirimiException When API request fails
     */
    public function sendWabaMessage(
        string $wabaId,
        string $to,
        string $templateName,
        array $options = []
    ): array {
        $data = [
            'waba_id'       => $wabaId,
            'to'            => $to,
            'template_name' => $templateName,
        ];

        foreach (['variables', 'header', 'buttons'] as $key) {
            if (isset($options[$key])) {
                $data[$key] = $options[$key];
            }
        }

        return $this->postJson('/v1/waba/send-message', $data, 'send WABA message');
    }

    /**
     * Send a free-form reply. Only allowed within 24h of the customer's last message.
     *
     * @param string $wabaId WhatsApp Business Account ID
     * @param string $to Recipient phone number
     * @param array $message Message object, e.g. ['type' => 'text', 'text' => 'hi']
     * @return array Response data
     * @throws KirimiException When API request fails
     */
    public function wabaReply(string $wabaId, string $to, array $message): array
    {
        return $this->postJson('/v1/waba/messages/reply', [
            'waba_id' => $wabaId,
            'to'      => $to,
            'message' => $message,
        ], 'send WABA reply');
    }

    /**
     * List WABA conversations still inside the 24h customer service window
     *
     * @param int|null $limit Page size, 1-200 (default 50)
     * @param int|null $page Page number, 1-based (default 1)
     * @return array Response data
     * @throws KirimiException When API request fails
     */
    public function wabaConversations(?int $limit = null, ?int $page = null): array
    {
        return $this->postJson('/v1/waba/conversations', [
            'limit' => $limit,
            'page'  => $page,
        ], 'list WABA conversations');
    }

    /**
     * Refresh template status from Meta for one WABA
     *
     * @param string $wabaId WhatsApp Business Account ID
     * @return array Response data
     * @throws KirimiException When API request fails
     */
    public function wabaTemplatesSync(string $wabaId): array
    {
        return $this->postJson('/v1/waba/templates/sync', [
            'waba_id' => $wabaId,
        ], 'sync WABA templates');
    }

    /**
     * Send an OTP through your own WABA + AUTHENTICATION template
     *
     * @param string $wabaId WhatsApp Business Account ID
     * @param string $to Recipient phone number
     * @param string $templateName AUTHENTICATION category template, APPROVED status
     * @return array Response data
     * @throws KirimiException When API request fails
     */
    public function wabaSendOtp(string $wabaId, string $to, string $templateName): array
    {
        return $this->postJson('/v1/waba/send-otp', [
            'waba_id'       => $wabaId,
            'to'            => $to,
            'template_name' => $templateName,
        ], 'send WABA OTP');
    }

    /**
     * Verify an OTP previously sent through wabaSendOtp
     *
     * @param string $wabaId WhatsApp Business Account ID
     * @param string $to Recipient phone number
     * @param string $otpCode OTP code to verify (4-8 digits)
     * @return array Response data
     * @throws KirimiException When API request fails
     */
    public function wabaVerifyOtp(string $wabaId, string $to, string $otpCode): array
    {
        return $this->postJson('/v1/waba/verify-otp', [
            'waba_id'  => $wabaId,
            'to'       => $to,
            'otp_code' => $otpCode,
        ], 'verify WABA OTP');
    }

    // ─── Devices ────────────────────────────────────────────────────────────

    /**
     * Create a new device
     *
     * @param int|string $packageId Package ID for the new device
     * @param string|null $voucherCode Optional discount voucher code
     * @return array Response data
     * @throws KirimiException When API request fails
     */
    public function createDevice($packageId, ?string $voucherCode = null): array
    {
        return $this->postJson('/v1/create-device', [
            'package_id'   => $packageId,
            'voucher_code' => $voucherCode,
        ], 'create device');
    }

    /**
     * Connect a device and obtain its QR/session state
     *
     * @param string $deviceId Device ID
     * @return array Response data
     * @throws KirimiException When API request fails
     */
    public function connectDevice(string $deviceId): array
    {
        return $this->postJson('/v1/connect-device', [
            'device_id' => $deviceId,
        ], 'connect device');
    }

    /**
     * Renew a device subscription
     *
     * @param string $deviceId Device ID
     * @param int|string $packageId Package ID for the renewal
     * @param string|null $voucherCode Optional discount voucher code
     * @return array Response data
     * @throws KirimiException When API request fails
     */
    public function renewDevice(string $deviceId, $packageId, ?string $voucherCode = null): array
    {
        return $this->postJson('/v1/renew-device', [
            'device_id'    => $deviceId,
            'package_id'   => $packageId,
            'voucher_code' => $voucherCode,
        ], 'renew device');
    }

    /**
     * List all registered devices
     *
     * @param int|null $page Page number, 1-based (default 1)
     * @param int|null $limit Page size (default 10)
     * @return array Response data
     * @throws KirimiException When API request fails
     */
    public function listDevices(?int $page = null, ?int $limit = null): array
    {
        return $this->postJson('/v1/list-devices', [
            'page'  => $page,
            'limit' => $limit,
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
        return $this->postJson('/v1/user-info', [], 'user info');
    }

    // ─── Contacts ───────────────────────────────────────────────────────────

    /**
     * Save a contact. Existing numbers are skipped, not overwritten.
     *
     * @param string $nama Contact name
     * @param string $nomor Contact phone number
     * @param string|null $deviceId Optional device ID
     * @return array Response data
     * @throws KirimiException When API request fails
     */
    public function saveContact(string $nama, string $nomor, ?string $deviceId = null): array
    {
        return $this->postJson('/v1/save-contact', [
            'nama'      => $nama,
            'nomor'     => $nomor,
            'device_id' => $deviceId,
        ], 'save contact');
    }

    /**
     * Save up to 1000 contacts in one request
     *
     * @param array $contacts Array of ['nama' => string, 'nomor' => string]
     * @param string|null $deviceId Optional device ID
     * @return array Response data
     * @throws KirimiException When API request fails
     */
    public function saveContactsBulk(array $contacts, ?string $deviceId = null): array
    {
        return $this->postJson('/v1/save-contacts-bulk', [
            'contacts'  => array_values($contacts),
            'device_id' => $deviceId,
        ], 'save contacts bulk');
    }

    // ─── OTP v1 (legacy) ────────────────────────────────────────────────────

    /**
     * Generate and send OTP to WhatsApp number
     *
     * @param string $deviceId Device ID
     * @param string $phone Phone number to send OTP
     * @param array $options Optional: otp_length (int, 4-20), otp_type (numeric|alphabetic|alphanumeric),
     *                       customOtpText (string), customOtpMessage (string),
     *                       enableTypingEffect (bool), typingSpeedMs (int)
     * @return array Response data
     * @throws KirimiException When API request fails
     */
    public function generateOTP(string $deviceId, string $phone, array $options = []): array
    {
        $data = [
            'device_id' => $deviceId,
            'phone'     => $phone,
        ];

        foreach ([
            'otp_length', 'otp_type', 'customOtpText', 'customOtpMessage',
            'enableTypingEffect', 'typingSpeedMs',
        ] as $key) {
            if (isset($options[$key])) {
                $data[$key] = $options[$key];
            }
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
            'device_id' => $deviceId,
            'phone'     => $phone,
            'otp'       => $otp,
        ], 'validate OTP');
    }

    // ─── OTP v2 ─────────────────────────────────────────────────────────────

    /**
     * Send OTP via the Kirimi provider, your own device, or your own WABA (V2)
     *
     * @param string $phone Recipient phone number
     * @param array $options Optional: method (whatsapp|waba|device|waba_user), app_name (string),
     *                       device_id (string), waba_id (string), template_name (string),
     *                       custom_message (string)
     * @return array Response data
     * @throws KirimiException When API request fails
     */
    public function sendOtpV2(string $phone, array $options = []): array
    {
        $data = [
            'phone' => $phone,
        ];

        foreach (['method', 'app_name', 'device_id', 'waba_id', 'template_name', 'custom_message'] as $key) {
            if (isset($options[$key])) {
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
            'phone'    => $phone,
            'otp_code' => $otpCode,
        ], 'verify OTP v2');
    }

    // ─── OTP Reverse ────────────────────────────────────────────────────────

    /**
     * Create a reverse OTP token and the message the customer must send back
     *
     * @param string $phone Customer phone number to verify
     * @param string $deviceId Device that acts as the bot
     * @param array $options Optional: app_name (string), callback_url (string),
     *                       custom_message (string), success_message (string), failure_message (string)
     * @return array Response data
     * @throws KirimiException When API request fails
     */
    public function otpReverseCreate(string $phone, string $deviceId, array $options = []): array
    {
        $data = [
            'phone'     => $phone,
            'device_id' => $deviceId,
        ];

        foreach (['app_name', 'callback_url', 'custom_message', 'success_message', 'failure_message'] as $key) {
            if (isset($options[$key])) {
                $data[$key] = $options[$key];
            }
        }

        return $this->postJson('/v2/otp-reverse/create', $data, 'create reverse OTP');
    }

    /**
     * Check the status of a reverse OTP token
     *
     * @param string $token ULID token returned by otpReverseCreate
     * @return array Response data
     * @throws KirimiException When API request fails
     */
    public function otpReverseStatus(string $token): array
    {
        return $this->postJson('/v2/otp-reverse/status', [
            'token' => $token,
        ], 'reverse OTP status');
    }

    // ─── Packages & Deposits ────────────────────────────────────────────────

    /**
     * List available packages
     *
     * @return array Response data
     * @throws KirimiException When API request fails
     */
    public function listPackages(): array
    {
        return $this->postJson('/v1/list-packages', [], 'list packages');
    }

    /**
     * Create a deposit payment link (nominal minimum 100)
     *
     * @param int $nominal Deposit amount in IDR
     * @return array Response data
     * @throws KirimiException When API request fails
     */
    public function createDeposit(int $nominal): array
    {
        return $this->postJson('/v1/create-deposit', [
            'nominal' => $nominal,
        ], 'create deposit');
    }

    /**
     * Check a deposit's status by reference
     *
     * @param string $ref Deposit reference ID
     * @return array Response data
     * @throws KirimiException When API request fails
     */
    public function depositStatus(string $ref): array
    {
        return $this->postJson('/v1/deposit-status', [
            'ref' => $ref,
        ], 'deposit status');
    }

    /**
     * Cancel an unpaid deposit
     *
     * @param string $ref Deposit reference ID
     * @return array Response data
     * @throws KirimiException When API request fails
     */
    public function cancelDeposit(string $ref): array
    {
        return $this->postJson('/v1/cancel-deposit', [
            'ref' => $ref,
        ], 'cancel deposit');
    }

    /**
     * List deposits for the account
     *
     * @param array $options Optional: page (int), limit (int),
     *                       status ('unpaid'|'paid'|'expired'|'cancelled')
     * @return array Response data
     * @throws KirimiException When API request fails
     */
    public function listDeposits(array $options = []): array
    {
        $data = [];

        foreach (['page', 'limit', 'status'] as $key) {
            if (isset($options[$key])) {
                $data[$key] = $options[$key];
            }
        }

        return $this->postJson('/v1/list-deposits', $data, 'list deposits');
    }

    // ─── Misc ───────────────────────────────────────────────────────────────

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

            if (!is_array($body)) {
                throw new KirimiException('Invalid JSON response from health check');
            }

            return $body;

        } catch (RequestException $e) {
            throw $this->fromRequestException($e, 'Health check');
        } catch (GuzzleException $e) {
            throw new KirimiException("HTTP request failed: {$e->getMessage()}");
        }
    }

    /**
     * Internal helper: POST JSON and return data array
     *
     * @param string $path API path
     * @param array $data Request body (auth fields are added automatically)
     * @param string $action Action label for error messages
     * @return array Response data
     * @throws KirimiException
     */
    private function postJson(string $path, array $data, string $action): array
    {
        $body = array_merge($data, $this->authData());
        $body = $this->compact($body);

        try {
            $response = $this->httpClient->post($path, ['json' => $body]);

            return $this->unwrap($response);

        } catch (RequestException $e) {
            throw $this->fromRequestException($e, ucfirst($action));
        } catch (GuzzleException $e) {
            throw new KirimiException('HTTP request failed: ' . $e->getMessage());
        }
    }

    /**
     * Internal helper: decode the envelope and return its data payload
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     * @return array
     * @throws KirimiException
     */
    private function unwrap($response): array
    {
        $raw = $response->getBody()->getContents();
        $body = json_decode($raw, true);

        if (!is_array($body)) {
            throw new KirimiException(
                'Invalid JSON response from API',
                0,
                null,
                $response->getStatusCode()
            );
        }

        if (!array_key_exists('success', $body)) {
            throw new KirimiException(
                'Malformed API response: missing "success" field',
                0,
                null,
                $response->getStatusCode()
            );
        }

        if (!$body['success']) {
            throw new KirimiException(
                $body['message'] ?? 'Request failed',
                0,
                null,
                $response->getStatusCode()
            );
        }

        return $body['data'] ?? [];
    }

    /**
     * Internal helper: build a KirimiException from a failed HTTP response,
     * preserving the HTTP status code for the caller.
     *
     * @param RequestException $exception
     * @param string $prefix Error message prefix, e.g. "Send message"
     * @return KirimiException
     */
    private function fromRequestException(RequestException $exception, string $prefix): KirimiException
    {
        $statusCode = $exception->hasResponse()
            ? $exception->getResponse()->getStatusCode()
            : null;

        return new KirimiException(
            $prefix . ' failed: ' . $this->parseErrorResponse($exception),
            0,
            null,
            $statusCode
        );
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

            if (is_array($body) && isset($body['message'])) {
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
