# Kirimi PHP Client

[![Packagist Version](https://img.shields.io/packagist/v/kirimi/kirimi-php)](https://packagist.org/packages/kirimi/kirimi-php)
[![Packagist Downloads](https://img.shields.io/packagist/dm/kirimi/kirimi-php)](https://packagist.org/packages/kirimi/kirimi-php)
[![PHP Version](https://img.shields.io/packagist/php-v/kirimi/kirimi-php)](https://packagist.org/packages/kirimi/kirimi-php)
[![License](https://img.shields.io/packagist/l/kirimi/kirimi-php)](https://github.com/yolkmonday/kirimi-php/blob/main/LICENSE)

Official PHP client library for the Kirimi WhatsApp API. This library provides a simple and efficient way to send WhatsApp messages, handle OTP generation and validation, and manage WhatsApp communication from your PHP applications.

## 🚀 Features

- ✅ Send WhatsApp messages (text and media)
- ✅ Broadcast to up to 1000 recipients
- ✅ WhatsApp Business API (WABA) templates, replies, conversations and OTP
- ✅ Generate and validate OTP codes (v1 and v2)
- ✅ Reverse OTP verification
- ✅ Devices, contacts, packages and deposits
- ✅ Support for multiple package types (Free, Lite, Basic, Pro)
- ✅ PSR-4 autoloading support
- ✅ Comprehensive error handling with HTTP status codes
- ✅ Type hints and modern PHP features
- ✅ Service classes for common use cases

## 📦 Installation

Install via Composer:

```bash
composer require kirimi/kirimi-php
```

## 🔧 Requirements

- PHP 8.0 or higher
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

Send WhatsApp messages with optional media support. The recipient is passed as `$receiver`
and sent to the API as `receiver` (country code, no `+`).

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

// With advanced options
$result = $client->sendMessage('device_id', '628123456789', 'Hello!', null, [
    'enableTypingEffect' => true,
    'typingSpeedMs'      => 350,       // 100-800
    'quotedMessageId'    => 'MSG_ID',
]);
```

**Parameters:**
- `$deviceId` (string): Your device ID
- `$receiver` (string): Recipient's phone number (with country code)
- `$message` (string): Message content
- `$mediaUrl` (string|null): URL of media file to send (optional)
- `$options` (array): Optional `fileName`, `enableTypingEffect`, `typingSpeedMs`, `quotedMessageId`

### Send Message Fast

Send a message without the typing effect simulation.

```php
$result = $client->sendMessageFast('device_id', '628123456789', 'Hello!');
```

### Send Message File

Send a file/document via multipart upload (max 50MB). The filename is sent both as the
multipart filename and as the `fileName` field.

```php
$result = $client->sendMessageFile(
    'device_id',
    '628123456789',
    '/path/to/document.pdf',
    ['message' => 'Here is your invoice', 'fileName' => 'invoice.pdf']
);
```

### Broadcast Message

Send a message to up to 1000 recipients. `$numbers` is always sent as a JSON array
and `$label` is required.

```php
$result = $client->broadcastMessage(
    'device_id',
    'promo-juli',                                 // label, max 100 chars
    ['628111111111', '628222222222'],
    'Promo hari ini!',
    ['delay' => 30]                               // seconds, clamped 30-3600
);
```

### WABA — Send Template Message

Send a Meta-approved template via WhatsApp Business API. WABA endpoints use `waba_id`,
never `device_id`.

```php
$result = $client->sendWabaMessage('waba_id', '628123456789', 'order_update', [
    'variables' => ['Budi', 'ORD-001'],
    'header'    => ['type' => 'text', 'text' => 'Order update'],
]);
```

### WABA — Reply, Conversations & Templates

```php
// Free-form reply (within the 24h customer service window)
$result = $client->wabaReply('waba_id', '628123456789', [
    'type' => 'text',
    'text' => 'Halo, ada yang bisa dibantu?',
]);

// List conversations
$conversations = $client->wabaConversations(50, 1);   // limit, page

// Refresh template status from Meta
$templates = $client->wabaTemplatesSync('waba_id');
```

### WABA — OTP

```php
$result = $client->wabaSendOtp('waba_id', '628123456789', 'otp_auth');
$verify = $client->wabaVerifyOtp('waba_id', '628123456789', '123456');
```

### Devices

```php
$device  = $client->createDevice(3, 'VOUCHER10');       // package_id, voucher_code
$connect = $client->connectDevice('device_id');          // returns QR/session state
$renew   = $client->renewDevice('device_id', 4, 'VOUCHER10');

$devices  = $client->listDevices(1, 10);                 // page, limit
$status   = $client->deviceStatus('device_id');
$detailed = $client->deviceStatusEnhanced('device_id');
```

### User Info

```php
$info = $client->userInfo();
```

### Contacts

Existing numbers are skipped, not overwritten.

```php
$result = $client->saveContact('John Doe', '628123456789', 'device_id');

$bulk = $client->saveContactsBulk([
    ['nama' => 'John Doe', 'nomor' => '628123456789'],
    ['nama' => 'Jane Doe', 'nomor' => '628987654321'],
], 'device_id');   // max 1000 contacts
```

### Generate OTP

Generate and send OTP via device WhatsApp.

```php
// Basic
$result = $client->generateOTP('device_id', '628123456789');

// With options
$result = $client->generateOTP('device_id', '628123456789', [
    'otp_length'       => 6,           // 4-20, default 8
    'otp_type'         => 'numeric',   // numeric | alphabetic | alphanumeric
    'customOtpText'    => 'Your code',
    'customOtpMessage' => 'Your OTP is {otp}. Valid for 5 minutes.',
]);
```

### Validate OTP

```php
$result = $client->validateOTP('device_id', '628123456789', '123456');
```

### Send OTP V2

Send an OTP through the Kirimi provider, your own device, or your own WABA.

```php
// Kirimi provider (Rp 595 per delivered OTP)
$result = $client->sendOtpV2('628123456789', [
    'method'   => 'whatsapp',
    'app_name' => 'MyApp',
]);

// Your own connected device (free)
$result = $client->sendOtpV2('628123456789', [
    'method'         => 'device',
    'device_id'      => 'device_id',
    'custom_message' => 'Your OTP is {{otp}}',
]);

// Your own WABA + AUTHENTICATION template (free)
$result = $client->sendOtpV2('628123456789', [
    'method'        => 'waba_user',
    'waba_id'       => 'waba_id',
    'template_name' => 'otp_auth',
]);
```

### Verify OTP V2

```php
$result = $client->verifyOtpV2('628123456789', '123456');
```

### Reverse OTP

The customer sends a token back to your device, which verifies automatically.

```php
$create = $client->otpReverseCreate('628123456789', 'device_id', [
    'app_name'        => 'MyApp',
    'callback_url'    => 'https://example.com/callback',
    'custom_message'  => 'Send {{token}} from {{phone}} to verify.',
    'success_message' => 'Verified!',
    'failure_message' => 'Verification failed.',
]);

$status = $client->otpReverseStatus($create['token']);   // pending|verified|phone_mismatch|expired
```

### Deposits & Packages

```php
$packages = $client->listPackages();

$deposit = $client->createDeposit(50000);        // min 100
$status  = $client->depositStatus($ref);
$cancel  = $client->cancelDeposit($ref);         // must be unpaid

$all  = $client->listDeposits();
$paid = $client->listDeposits(['status' => 'paid', 'page' => 1, 'limit' => 10]);
```

**Package Support:**
- **Free**: Text only (with watermark)
- **Lite/Basic/Pro**: Text + Media support

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

The library provides comprehensive error handling using `KirimiException`. Use
`getStatusCode()` to distinguish failures; it returns the HTTP status code from the
response (`null` for network errors).

| Code | Meaning |
|------|---------|
| `400` | Invalid params |
| `401` | Wrong secret |
| `402` | Insufficient balance (`/v2/otp/send` whatsapp) |
| `403` | Feature not in package / subscription inactive |
| `404` | Not found |
| `429` | Rate limited |
| `500` | Server error |
| `502` | Number undeliverable |
| `503` | Provider outage |

```php
use Kirimi\KirimiException;

try {
    $client->sendMessage('device_id', '628123456789', 'Hello');
} catch (KirimiException $e) {
    switch ($e->getStatusCode()) {
        case 401:
            echo 'Invalid credentials';
            break;
        case 402:
            echo 'Insufficient balance';
            break;
        case 429:
            echo 'Rate limited, retry later';
            break;
        default:
            echo 'Request failed: ' . $e->getMessage();
    }
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



