# Kirimi PHP Client

[![Packagist Version](https://img.shields.io/packagist/v/kirimi/kirimi-php)](https://packagist.org/packages/kirimi/kirimi-php)
[![Packagist Downloads](https://img.shields.io/packagist/dm/kirimi/kirimi-php)](https://packagist.org/packages/kirimi/kirimi-php)
[![PHP Version](https://img.shields.io/packagist/php-v/kirimi/kirimi-php)](https://packagist.org/packages/kirimi/kirimi-php)
[![License](https://img.shields.io/packagist/l/kirimi/kirimi-php)](https://github.com/yolkmonday/kirimi-php/blob/main/LICENSE)

Official PHP client library for the Kirimi WhatsApp API. This library provides a simple and efficient way to send WhatsApp messages, handle OTP generation and validation, and manage WhatsApp communication from your PHP applications.

## 🚀 Features

- ✅ Send WhatsApp messages (text and media)
- ✅ Generate and validate OTP codes
- ✅ Support for multiple package types (Free, Lite, Basic, Pro)
- ✅ PSR-4 autoloading support
- ✅ Comprehensive error handling
- ✅ Type hints and modern PHP features
- ✅ Health check monitoring
- ✅ Service classes for common use cases

## 📦 Installation

Install via Composer:

```bash
composer require kirimi/kirimi-php
```

## 🔧 Requirements

- PHP 7.4 or higher
- Guzzle HTTP client (installed automatically)
- ext-json (usually included in PHP)

## 🔧 Setup

Get your User Code and Secret Key from the [Kirimi Dashboard](https://dash.kirimi.id/docs).

```php
<?php

require_once 'vendor/autoload.php';

use Kirimi\KirimiClient;

$client = new KirimiClient('YOUR_USER_CODE', 'YOUR_SECRET_KEY');
```

## 📖 API Reference

### Constructor

```php
$client = new KirimiClient($userCode, $secret, $endpoint = 'https://api.kirimi.id');
```

**Parameters:**
- `$userCode` (string): Your unique user code from Kirimi Dashboard
- `$secret` (string): Your secret key for authentication
- `$endpoint` (string): API endpoint URL (optional)

### Send Message

Send WhatsApp messages with optional media support.

```php
// Text message only
$result = $client->sendMessage('device_id', '628123456789', 'Hello World!');

// Message with media
$result = $client->sendMessage(
    'device_id',
    '628123456789',
    'Check out this image!',
    'https://example.com/image.jpg'
);
```

**Parameters:**
- `$deviceId` (string): Your device ID
- `$phone` (string): Recipient's phone number (with country code)
- `$message` (string): Message content
- `$mediaUrl` (string|null): URL of media file to send (optional)

### Send Message Fast

Send message without typing effect simulation.

```php
$result = $client->sendMessageFast('device_id', '628123456789', 'Hello!');
```

### Send Message File

Send a file/document via multipart upload (max 50MB).

```php
$result = $client->sendMessageFile(
    'device_id',
    '628123456789',
    '/path/to/document.pdf',
    ['message' => 'Here is your invoice', 'fileName' => 'invoice.pdf']
);
```

### Send WABA Message

Send message explicitly via WhatsApp Business API (Meta Cloud API).

```php
$result = $client->sendWabaMessage('waba_device_id', '628123456789', 'Hello from WABA!');
```

### List Devices

```php
$devices = $client->listDevices();
```

### Device Status

```php
$status = $client->deviceStatus('device_id');
$detailed = $client->deviceStatusEnhanced('device_id');
```

### User Info

```php
$info = $client->userInfo();
```

### Save Contact

```php
$result = $client->saveContact('628123456789', ['name' => 'John Doe', 'email' => 'john@example.com']);
```

### Broadcast Message

Send to multiple recipients. `$phones` accepts array or comma-separated string.

```php
$result = $client->broadcastMessage(
    'device_id',
    ['628111111111', '628222222222', '628333333333'],
    'Promo hari ini!',
    ['delay' => 3]  // 3 seconds between messages
);
```

### List Deposits & Packages

```php
$all = $client->listDeposits();
$paid = $client->listDeposits('paid');   // '', 'paid', 'unpaid', 'expired'
$packages = $client->listPackages();
```

**Package Support:**
- **Free**: Text only (with watermark)
- **Lite/Basic/Pro**: Text + Media support

### Generate OTP

Generate and send OTP via device WhatsApp.

```php
// Basic
$result = $client->generateOTP('device_id', '628123456789');

// With options
$result = $client->generateOTP('device_id', '628123456789', [
    'otp_length'       => 6,
    'otp_type'         => 'numeric',   // numeric | alphabetic | alphanumeric
    'customOtpMessage' => 'Your OTP is {otp}. Valid for 5 minutes.',
]);
```

### Validate OTP

```php
$result = $client->validateOTP('device_id', '628123456789', '123456');
```

### Send OTP V2

Send OTP via WABA template or device (V2 endpoint).

```php
$result = $client->sendOtpV2('628123456789', 'device_id', [
    'method'          => 'device',   // device | waba
    'app_name'        => 'MyApp',
    'custom_message'  => 'Your code is {otp}',
    // 'template_code' => 'my_template' // for waba method
]);
```

### Verify OTP V2

```php
$result = $client->verifyOtpV2('628123456789', '123456');
```

### Health Check

Check the API service status.

```php
$status = $client->healthCheck();
print_r($status);
```

## 🎯 Quick Start

Check out the `examples/demo.php` file for a complete demonstration of all features:

```bash
# Set your credentials as environment variables
export KIRIMI_USER_CODE="your_user_code"
export KIRIMI_SECRET_KEY="your_secret_key"
export KIRIMI_DEVICE_ID="your_device_id"
export TEST_PHONE="628123456789"

# Run the example
composer run example
# or
php examples/demo.php
```

## 💡 Usage Examples

### Basic WhatsApp Messaging

```php
<?php

require_once 'vendor/autoload.php';

use Kirimi\KirimiClient;
use Kirimi\KirimiException;

$client = new KirimiClient('your_user_code', 'your_secret');

try {
    $result = $client->sendMessage(
        'your_device_id',
        '628123456789',
        'Welcome to our service! 🎉'
    );
    echo "Message sent successfully: " . json_encode($result) . PHP_EOL;
} catch (KirimiException $e) {
    echo "Failed to send message: " . $e->getMessage() . PHP_EOL;
}
```

### OTP Verification Flow

```php
<?php

require_once 'vendor/autoload.php';

use Kirimi\Services\OTPService;

$otpService = new OTPService('your_user_code', 'your_secret', 'your_device_id');

// Send OTP
$result = $otpService->sendVerificationCode('628123456789');
if ($result['success']) {
    echo "OTP sent successfully!" . PHP_EOL;
} else {
    echo "Failed to send OTP: " . $result['error'] . PHP_EOL;
}

// Verify OTP (user provides the code)
$verifyResult = $otpService->verifyCode('628123456789', '123456');
if ($verifyResult['success'] && $verifyResult['verified']) {
    echo "OTP verified successfully!" . PHP_EOL;
} else {
    echo "OTP verification failed!" . PHP_EOL;
}
```

### Notification Service

```php
<?php

require_once 'vendor/autoload.php';

use Kirimi\Services\NotificationService;

$notificationService = new NotificationService('your_user_code', 'your_secret', 'your_device_id');

// Send welcome message
$result = $notificationService->sendWelcomeMessage('628123456789', 'John Doe');

// Send order confirmation
$result = $notificationService->sendOrderConfirmation(
    '628123456789',
    'ORD-001',
    ['Product A', 'Product B', 'Product C']
);

// Send invoice with document
$result = $notificationService->sendInvoiceWithDocument(
    '628123456789',
    'INV-001',
    'https://example.com/invoice.pdf'
);

// Send appointment reminder
$result = $notificationService->sendAppointmentReminder(
    '628123456789',
    '2024-01-15',
    '10:00 AM',
    'Main Office'
);
```

### Laravel Integration

```php
<?php

// In your Laravel service provider or controller
use Kirimi\KirimiClient;

class WhatsAppService
{
    private KirimiClient $kirimi;

    public function __construct()
    {
        $this->kirimi = new KirimiClient(
            config('services.kirimi.user_code'),
            config('services.kirimi.secret')
        );
    }

    public function sendNotification(string $phone, string $message): bool
    {
        try {
            $this->kirimi->sendMessage(
                config('services.kirimi.device_id'),
                $phone,
                $message
            );
            return true;
        } catch (KirimiException $e) {
            Log::error('WhatsApp notification failed: ' . $e->getMessage());
            return false;
        }
    }
}

// In config/services.php
return [
    'kirimi' => [
        'user_code' => env('KIRIMI_USER_CODE'),
        'secret' => env('KIRIMI_SECRET_KEY'),
        'device_id' => env('KIRIMI_DEVICE_ID'),
    ],
];
```

## 📋 Package Types & Features

| Package | ID | Features | OTP Support |
|---------|----|---------:|:-----------:|
| Free | 1 | Text only (with watermark) | ❌ |
| Lite | 2, 6, 9 | Text + Media | ❌ |
| Basic | 3, 7, 10 | Text + Media + OTP | ✅ |
| Pro | 4, 8, 11 | Text + Media + OTP | ✅ |

## ⚠️ Error Handling

The library provides comprehensive error handling using `KirimiException`:

```php
use Kirimi\KirimiException;

try {
    $client->sendMessage('device_id', 'invalid_number', 'Hello');
} catch (KirimiException $e) {
    $errorMessage = $e->getMessage();
    
    if (strpos($errorMessage, 'Parameter tidak lengkap') !== false) {
        echo 'Missing required parameters';
    } elseif (strpos($errorMessage, 'device tidak terhubung') !== false) {
        echo 'Device is not connected';
    } elseif (strpos($errorMessage, 'kuota habis') !== false) {
        echo 'Quota exceeded';
    }
    // Handle other specific errors...
}
```

## 🔒 Security Notes

- Always keep your secret key secure and never expose it in client-side code
- Use environment variables to store credentials
- Validate phone numbers before sending messages
- Implement rate limiting in your application

```php
// Good practice: use environment variables
$client = new KirimiClient(
    $_ENV['KIRIMI_USER_CODE'],
    $_ENV['KIRIMI_SECRET_KEY']
);
```

## 🚦 Rate Limits & Quotas

- Each message sent reduces your device quota (unless unlimited)
- OTP codes expire after 5 minutes
- Device must be in 'connected' status to send messages
- Check your dashboard for current quota and usage statistics

## 🧪 Testing

Run the test suite:

```bash
composer test
```

Run tests with coverage:

```bash
composer test-coverage
```

Check code style:

```bash
composer cs-check
```

Fix code style:

```bash
composer cs-fix
```

## 🤝 Contributing

Contributions are welcome! Please follow these steps:

1. Fork the repository
2. Create a feature branch
3. Follow PSR-12 coding standards
4. Add tests for new features
5. Submit a pull request

## 📄 License

[MIT](https://github.com/yolkmonday/kirimi-php/blob/main/LICENSE)

## 👨‍💻 Author

**Ari Padrian** - [yolkmonday@gmail.com](mailto:yolkmonday@gmail.com)

## 📚 Additional Resources

- [Kirimi Dashboard](https://dash.kirimi.id)
- [API Documentation](https://dash.kirimi.id/docs)
- [Support](mailto:support@kirimi.id)
- [GitHub Repository](https://github.com/yolkmonday/kirimi-php)

---

Made with ❤️ for the PHP and WhatsApp automation community



