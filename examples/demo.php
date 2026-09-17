<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Kirimi\KirimiClient;
use Kirimi\KirimiException;
use Kirimi\Services\OTPService;
use Kirimi\Services\NotificationService;

// Configuration - use environment variables or replace with your credentials
$userCode = $_ENV['KIRIMI_USER_CODE'] ?? 'your_user_code';
$secret = $_ENV['KIRIMI_SECRET_KEY'] ?? 'your_secret_key';
$deviceId = $_ENV['KIRIMI_DEVICE_ID'] ?? 'your_device_id';
$testPhone = $_ENV['TEST_PHONE'] ?? '628123456789';

/**
 * Demonstrate all Kirimi PHP Client features
 */
function demonstrateKirimiFeatures(string $userCode, string $secret, string $deviceId, string $testPhone): void
{
    echo "🚀 Kirimi PHP Client Demo" . PHP_EOL . PHP_EOL;

    try {
        // Initialize client
        $client = new KirimiClient($userCode, $secret);

        // 1. Health Check
        echo "1. Checking API health..." . PHP_EOL;
        $health = $client->healthCheck();
        echo "✅ API Status: " . json_encode($health) . PHP_EOL . PHP_EOL;

        // 2. Send Text Message
        echo "2. Sending text message..." . PHP_EOL;
        $textResult = $client->sendMessage(
            $deviceId,
            $testPhone,
            'Hello from Kirimi PHP Client! 🎉'
        );
        echo "✅ Text message sent: " . json_encode($textResult) . PHP_EOL . PHP_EOL;

        // 3. Send Media Message
        echo "3. Sending media message..." . PHP_EOL;
        $mediaResult = $client->sendMessage(
            $deviceId,
            $testPhone,
            'Here is a sample image! 📸',
            'https://picsum.photos/300/200'
        );
        echo "✅ Media message sent: " . json_encode($mediaResult) . PHP_EOL . PHP_EOL;

        // 4. Send Message (fast mode)
        echo "4. Sending fast message..." . PHP_EOL;
        $fastResult = $client->sendMessageFast($deviceId, $testPhone, 'Fast hello! ⚡');
        echo "✅ Fast message sent: " . json_encode($fastResult) . PHP_EOL . PHP_EOL;

        // 5. Generate OTP (requires Basic or Pro package)
        echo "5. Generating OTP..." . PHP_EOL;
        try {
            $otpResult = $client->generateOTP($deviceId, $testPhone, [
                'otp_length' => 6,
                'otp_type'   => 'numeric',
            ]);
            echo "✅ OTP generated: " . json_encode($otpResult) . PHP_EOL;

            // 6. Validate OTP (demo with dummy code)
            echo "6. Validating OTP (demo with code '123456')..." . PHP_EOL;
            try {
                $validateResult = $client->validateOTP($deviceId, $testPhone, '123456');
                echo "✅ OTP validated: " . json_encode($validateResult) . PHP_EOL;
            } catch (KirimiException $e) {
                echo "ℹ️ OTP validation failed (expected for demo): " . $e->getMessage() . PHP_EOL;
            }
        } catch (KirimiException $e) {
            echo "ℹ️ OTP generation failed (requires Basic/Pro package): " . $e->getMessage() . PHP_EOL;
        }
        echo PHP_EOL;

        echo "🎉 Demo completed successfully!" . PHP_EOL;
        
    } catch (KirimiException $e) {
        echo "❌ Demo failed: " . $e->getMessage() . PHP_EOL;
        echo PHP_EOL . "Please make sure you have:" . PHP_EOL;
        echo "- Valid user code and secret key" . PHP_EOL;
        echo "- A connected device" . PHP_EOL;
        echo "- Sufficient quota" . PHP_EOL;
        echo "- Correct environment variables or update the credentials in this file" . PHP_EOL;
    }
}

/**
 * Demonstrate OTP Service
 */
function demonstrateOTPService(string $userCode, string $secret, string $deviceId, string $testPhone): void
{
    echo PHP_EOL . "📱 OTP Service Demo" . PHP_EOL . PHP_EOL;

    $otpService = new OTPService($userCode, $secret, $deviceId);

    // Send OTP
    echo "Sending verification code..." . PHP_EOL;
    $sendResult = $otpService->sendVerificationCode($testPhone);
    
    if ($sendResult['success']) {
        echo "✅ " . $sendResult['message'] . PHP_EOL;
        echo "Data: " . json_encode($sendResult['data']) . PHP_EOL;
    } else {
        echo "❌ " . $sendResult['message'] . ": " . $sendResult['error'] . PHP_EOL;
    }

    // Verify OTP (demo with dummy code)
    echo PHP_EOL . "Verifying OTP (demo with code '123456')..." . PHP_EOL;
    $verifyResult = $otpService->verifyCode($testPhone, '123456');
    
    if ($verifyResult['success'] && $verifyResult['verified']) {
        echo "✅ OTP verified successfully!" . PHP_EOL;
    } else {
        echo "ℹ️ OTP verification failed (expected for demo)" . PHP_EOL;
        if (isset($verifyResult['error'])) {
            echo "Error: " . $verifyResult['error'] . PHP_EOL;
        }
    }
}

/**
 * Demonstrate Notification Service
 */
function demonstrateNotificationService(string $userCode, string $secret, string $deviceId, string $testPhone): void
{
    echo PHP_EOL . "🔔 Notification Service Demo" . PHP_EOL . PHP_EOL;

    $notificationService = new NotificationService($userCode, $secret, $deviceId);

    // Welcome message
    echo "1. Sending welcome message..." . PHP_EOL;
    $welcomeResult = $notificationService->sendWelcomeMessage($testPhone, 'John Doe');
    displayResult($welcomeResult);

    // Order confirmation
    echo "2. Sending order confirmation..." . PHP_EOL;
    $orderResult = $notificationService->sendOrderConfirmation(
        $testPhone,
        'ORD-12345',
        ['Product A - Qty: 2', 'Product B - Qty: 1', 'Product C - Qty: 3']
    );
    displayResult($orderResult);

    // Invoice with document
    echo "3. Sending invoice with document..." . PHP_EOL;
    $invoiceResult = $notificationService->sendInvoiceWithDocument(
        $testPhone,
        'INV-67890',
        'https://example.com/sample-invoice.pdf'
    );
    displayResult($invoiceResult);

    // Appointment reminder
    echo "4. Sending appointment reminder..." . PHP_EOL;
    $appointmentResult = $notificationService->sendAppointmentReminder(
        $testPhone,
        '2024-01-15',
        '10:00 AM',
        'Main Office, Jakarta'
    );
    displayResult($appointmentResult);

    // Custom notification
    echo "5. Sending custom notification..." . PHP_EOL;
    $customResult = $notificationService->sendCustomNotification(
        $testPhone,
        'This is a custom notification with media! 🎊',
        'https://picsum.photos/400/300'
    );
    displayResult($customResult);
}

/**
 * Demonstrate the WABA, device, contact and deposit endpoints
 */
function demonstrateAdvancedFeatures(string $userCode, string $secret, string $deviceId, string $testPhone): void
{
    echo PHP_EOL . "🏢 WABA, Devices, Contacts & Deposits Demo" . PHP_EOL . PHP_EOL;

    $client = new KirimiClient($userCode, $secret);
    $wabaId = $_ENV['KIRIMI_WABA_ID'] ?? 'your_waba_id';

    // Account info and packages
    echo "1. Fetching account info and packages..." . PHP_EOL;
    try {
        echo "   User: " . json_encode($client->userInfo()) . PHP_EOL;
        echo "   Packages: " . json_encode($client->listPackages()) . PHP_EOL;
    } catch (KirimiException $e) {
        echo "   ℹ️ " . $e->getMessage() . PHP_EOL;
    }
    echo PHP_EOL;

    // Devices
    echo "2. Listing devices..." . PHP_EOL;
    try {
        echo "   " . json_encode($client->listDevices(1, 10)) . PHP_EOL;
    } catch (KirimiException $e) {
        echo "   ℹ️ " . $e->getMessage() . PHP_EOL;
    }
    echo PHP_EOL;

    // Contacts
    echo "3. Saving a contact (nama + nomor)..." . PHP_EOL;
    try {
        $contact = $client->saveContact('Demo Contact', $testPhone, $deviceId);
        echo "   ✅ " . json_encode($contact) . PHP_EOL;
    } catch (KirimiException $e) {
        echo "   ℹ️ " . $e->getMessage() . PHP_EOL;
    }
    echo PHP_EOL;

    // WABA template message
    echo "4. Sending WABA template message..." . PHP_EOL;
    try {
        $waba = $client->sendWabaMessage($wabaId, $testPhone, 'hello_world', [
            'variables' => ['Demo'],
        ]);
        echo "   ✅ " . json_encode($waba) . PHP_EOL;
    } catch (KirimiException $e) {
        echo "   ℹ️ " . $e->getMessage() . PHP_EOL;
    }
    echo PHP_EOL;

    // WABA conversations
    echo "5. Listing WABA conversations..." . PHP_EOL;
    try {
        echo "   " . json_encode($client->wabaConversations(50, 1)) . PHP_EOL;
    } catch (KirimiException $e) {
        echo "   ℹ️ " . $e->getMessage() . PHP_EOL;
    }
    echo PHP_EOL;

    // Deposits
    echo "6. Listing deposits..." . PHP_EOL;
    try {
        echo "   " . json_encode($client->listDeposits(['limit' => 10])) . PHP_EOL;
    } catch (KirimiException $e) {
        echo "   ℹ️ " . $e->getMessage() . PHP_EOL;
    }
    echo PHP_EOL;
}

/**
 * Demonstrate HTTP status code aware error handling
 */
function demonstrateStatusCodeHandling(): void
{
    echo "🔍 HTTP Status Code Handling" . PHP_EOL . PHP_EOL;

    try {
        $client = new KirimiClient('invalid_user', 'invalid_secret');
        $client->userInfo();
    } catch (KirimiException $e) {
        echo "Status: " . var_export($e->getStatusCode(), true) . PHP_EOL;
        echo "Message: " . $e->getMessage() . PHP_EOL;
    }

    echo PHP_EOL;
}

/**
 * Display result helper function
 */
function displayResult(array $result): void
{
    if ($result['success']) {
        echo "✅ " . $result['message'] . PHP_EOL;
    } else {
        echo "❌ " . $result['message'] . ": " . $result['error'] . PHP_EOL;
    }
    echo PHP_EOL;
}

/**
 * Error handling demonstration
 */
function demonstrateErrorHandling(): void
{
    echo "⚠️ Error Handling Demo" . PHP_EOL . PHP_EOL;

    // Invalid credentials
    try {
        $invalidClient = new KirimiClient('invalid_user', 'invalid_secret');
        $invalidClient->sendMessage('invalid_device', '628123456789', 'Test message');
    } catch (KirimiException $e) {
        echo "Expected error caught: " . $e->getMessage() . PHP_EOL;
    }

    // Invalid phone number format
    try {
        $client = new KirimiClient('test_user', 'test_secret');
        $client->sendMessage('test_device', 'invalid_phone', 'Test message');
    } catch (KirimiException $e) {
        echo "Expected error caught: " . $e->getMessage() . PHP_EOL;
    }

    echo PHP_EOL;
}

/**
 * Usage examples for different frameworks
 */
function showFrameworkExamples(): void
{
    echo "🔧 Framework Integration Examples" . PHP_EOL . PHP_EOL;

    echo "Laravel Service Example:" . PHP_EOL;
    echo "```php" . PHP_EOL;
    echo "// In app/Services/WhatsAppService.php" . PHP_EOL;
    echo "use Kirimi\KirimiClient;" . PHP_EOL;
    echo "" . PHP_EOL;
    echo "class WhatsAppService {" . PHP_EOL;
    echo "    public function sendNotification(\$phone, \$message) {" . PHP_EOL;
    echo "        \$client = new KirimiClient(" . PHP_EOL;
    echo "            config('services.kirimi.user_code')," . PHP_EOL;
    echo "            config('services.kirimi.secret')" . PHP_EOL;
    echo "        );" . PHP_EOL;
    echo "        return \$client->sendMessage(" . PHP_EOL;
    echo "            config('services.kirimi.device_id')," . PHP_EOL;
    echo "            \$phone," . PHP_EOL;
    echo "            \$message" . PHP_EOL;
    echo "        );" . PHP_EOL;
    echo "    }" . PHP_EOL;
    echo "}" . PHP_EOL;
    echo "```" . PHP_EOL . PHP_EOL;

    echo "Symfony Service Example:" . PHP_EOL;
    echo "```yaml" . PHP_EOL;
    echo "# config/services.yaml" . PHP_EOL;
    echo "parameters:" . PHP_EOL;
    echo "    kirimi.user_code: '%env(KIRIMI_USER_CODE)%'" . PHP_EOL;
    echo "    kirimi.secret: '%env(KIRIMI_SECRET_KEY)%'" . PHP_EOL;
    echo "" . PHP_EOL;
    echo "services:" . PHP_EOL;
    echo "    App\\Service\\WhatsAppService:" . PHP_EOL;
    echo "        arguments:" . PHP_EOL;
    echo "            - '%kirimi.user_code%'" . PHP_EOL;
    echo "            - '%kirimi.secret%'" . PHP_EOL;
    echo "```" . PHP_EOL . PHP_EOL;
}

// Main execution
if (php_sapi_name() === 'cli') {
    echo "=================================" . PHP_EOL;
    echo "   KIRIMI PHP CLIENT DEMO" . PHP_EOL;
    echo "=================================" . PHP_EOL . PHP_EOL;

    // Basic demonstration
    demonstrateKirimiFeatures($userCode, $secret, $deviceId, $testPhone);

    // OTP Service demonstration
    demonstrateOTPService($userCode, $secret, $deviceId, $testPhone);

    // Notification Service demonstration
    demonstrateNotificationService($userCode, $secret, $deviceId, $testPhone);

    // WABA, devices, contacts and deposits
    demonstrateAdvancedFeatures($userCode, $secret, $deviceId, $testPhone);

    // Error handling demonstration
    demonstrateErrorHandling();
    demonstrateStatusCodeHandling();

    // Framework examples
    showFrameworkExamples();

    echo "=================================" . PHP_EOL;
    echo "         DEMO COMPLETED" . PHP_EOL;
    echo "=================================" . PHP_EOL;
    echo PHP_EOL . "For more information, visit:" . PHP_EOL;
    echo "- Documentation: https://dash.kirimi.id/docs" . PHP_EOL;
    echo "- GitHub: https://github.com/yolkmonday/kirimi-php" . PHP_EOL;
    echo "- Support: support@kirimi.id" . PHP_EOL;
} 