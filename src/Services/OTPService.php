<?php

namespace Kirimi\Services;

use Kirimi\KirimiClient;
use Kirimi\KirimiException;

/**
 * OTP Service
 * 
 * Provides convenient methods for OTP generation and validation
 * 
 * @package Kirimi\Services
 * @author Ari Padrian <yolkmonday@gmail.com>
 */
class OTPService
{
    /**
     * @var KirimiClient
     */
    private KirimiClient $client;

    /**
     * @var string
     */
    private string $deviceId;

    /**
     * Constructor
     * 
     * @param string $userCode User code from Kirimi Dashboard
     * @param string $secret Secret key from Kirimi Dashboard
     * @param string $deviceId Device ID
     */
    public function __construct(string $userCode, string $secret, string $deviceId)
    {
        $this->client = new KirimiClient($userCode, $secret);
        $this->deviceId = $deviceId;
    }

    /**
     * Send verification code to phone number
     * 
     * @param string $phoneNumber Phone number to send OTP
     * @return array Result with success status and data
     */
    public function sendVerificationCode(string $phoneNumber): array
    {
        try {
            $result = $this->client->generateOTP($this->deviceId, $phoneNumber);
            
            return [
                'success' => true,
                'data' => $result,
                'message' => "OTP sent to {$phoneNumber}"
            ];

        } catch (KirimiException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Failed to send OTP'
            ];
        }
    }

    /**
     * Verify OTP code
     * 
     * @param string $phoneNumber Phone number that received OTP
     * @param string $code OTP code to verify
     * @return array Result with success status and verification result
     */
    public function verifyCode(string $phoneNumber, string $code): array
    {
        try {
            $result = $this->client->validateOTP($this->deviceId, $phoneNumber, $code);
            
            return [
                'success' => true,
                'verified' => $result['verified'] ?? false,
                'data' => $result,
                'message' => 'OTP verified successfully'
            ];

        } catch (KirimiException $e) {
            return [
                'success' => false,
                'verified' => false,
                'error' => $e->getMessage(),
                'message' => 'Failed to verify OTP'
            ];
        }
    }

    /**
     * Get the underlying Kirimi client
     * 
     * @return KirimiClient
     */
    public function getClient(): KirimiClient
    {
        return $this->client;
    }

    /**
     * Get device ID
     * 
     * @return string
     */
    public function getDeviceId(): string
    {
        return $this->deviceId;
    }
} 