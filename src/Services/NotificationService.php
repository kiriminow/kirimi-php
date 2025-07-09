<?php

namespace Kirimi\Services;

use Kirimi\KirimiClient;
use Kirimi\KirimiException;

/**
 * Notification Service
 * 
 * Provides convenient methods for sending various types of WhatsApp notifications
 * 
 * @package Kirimi\Services
 * @author Ari Padrian <yolkmonday@gmail.com>
 */
class NotificationService
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
     * Send welcome message to new user
     * 
     * @param string $phoneNumber Phone number to send message
     * @param string $userName User name
     * @return array Result with success status and data
     */
    public function sendWelcomeMessage(string $phoneNumber, string $userName): array
    {
        $message = "Welcome {$userName}! 🎉\n\nThank you for joining our service. We're excited to have you!";
        
        try {
            $result = $this->client->sendMessage($this->deviceId, $phoneNumber, $message);
            
            return [
                'success' => true,
                'data' => $result,
                'message' => 'Welcome message sent successfully'
            ];

        } catch (KirimiException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Failed to send welcome message'
            ];
        }
    }

    /**
     * Send order confirmation message
     * 
     * @param string $phoneNumber Phone number to send message
     * @param string $orderId Order ID
     * @param array $items Array of order items
     * @return array Result with success status and data
     */
    public function sendOrderConfirmation(string $phoneNumber, string $orderId, array $items): array
    {
        $itemsList = implode("\n", array_map(function($item) {
            return "• {$item}";
        }, $items));
        
        $message = "Order Confirmation #{$orderId} ✅\n\nItems:\n{$itemsList}\n\nThank you for your order!";
        
        try {
            $result = $this->client->sendMessage($this->deviceId, $phoneNumber, $message);
            
            return [
                'success' => true,
                'data' => $result,
                'message' => 'Order confirmation sent successfully'
            ];

        } catch (KirimiException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Failed to send order confirmation'
            ];
        }
    }

    /**
     * Send invoice with document attachment
     * 
     * @param string $phoneNumber Phone number to send message
     * @param string $invoiceNumber Invoice number
     * @param string $documentUrl URL of the invoice document
     * @return array Result with success status and data
     */
    public function sendInvoiceWithDocument(string $phoneNumber, string $invoiceNumber, string $documentUrl): array
    {
        $message = "Invoice #{$invoiceNumber} 📄\n\nPlease find your invoice document attached.";
        
        try {
            $result = $this->client->sendMessage($this->deviceId, $phoneNumber, $message, $documentUrl);
            
            return [
                'success' => true,
                'data' => $result,
                'message' => 'Invoice sent successfully'
            ];

        } catch (KirimiException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Failed to send invoice'
            ];
        }
    }

    /**
     * Send custom notification message
     * 
     * @param string $phoneNumber Phone number to send message
     * @param string $message Message content
     * @param string|null $mediaUrl Optional media URL
     * @return array Result with success status and data
     */
    public function sendCustomNotification(string $phoneNumber, string $message, ?string $mediaUrl = null): array
    {
        try {
            $result = $this->client->sendMessage($this->deviceId, $phoneNumber, $message, $mediaUrl);
            
            return [
                'success' => true,
                'data' => $result,
                'message' => 'Notification sent successfully'
            ];

        } catch (KirimiException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Failed to send notification'
            ];
        }
    }

    /**
     * Send appointment reminder
     * 
     * @param string $phoneNumber Phone number to send message
     * @param string $appointmentDate Appointment date
     * @param string $appointmentTime Appointment time
     * @param string $location Location of appointment
     * @return array Result with success status and data
     */
    public function sendAppointmentReminder(string $phoneNumber, string $appointmentDate, string $appointmentTime, string $location): array
    {
        $message = "🗓️ Appointment Reminder\n\n";
        $message .= "Date: {$appointmentDate}\n";
        $message .= "Time: {$appointmentTime}\n";
        $message .= "Location: {$location}\n\n";
        $message .= "Please arrive 10 minutes early. Thank you!";
        
        try {
            $result = $this->client->sendMessage($this->deviceId, $phoneNumber, $message);
            
            return [
                'success' => true,
                'data' => $result,
                'message' => 'Appointment reminder sent successfully'
            ];

        } catch (KirimiException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Failed to send appointment reminder'
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