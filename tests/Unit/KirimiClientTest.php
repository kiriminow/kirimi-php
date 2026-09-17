<?php

namespace Kirimi\Tests\Unit;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Kirimi\KirimiClient;
use Kirimi\KirimiException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for KirimiClient
 */
class KirimiClientTest extends TestCase
{
    private string $testUserCode = 'test_user_code';
    private string $testSecret = 'test_secret_key';

    /** @var array<int, array> Recorded request history (Guzzle Middleware) */
    private array $history = [];

    /** @var MockHandler */
    private MockHandler $mock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->history = [];
        $this->mock = new MockHandler();
    }

    /**
     * Build a client wired to a Guzzle MockHandler.
     *
     * @param array $responses Queue of Response objects (or exceptions)
     */
    private function makeClient(array $responses = []): KirimiClient
    {
        $this->mock = new MockHandler($responses);

        $stack = HandlerStack::create($this->mock);
        $stack->push(Middleware::history($this->history));

        return new KirimiClient(
            $this->testUserCode,
            $this->testSecret,
            'https://api.kirimi.id',
            ['handler' => $stack]
        );
    }

    /**
     * Decode and return the JSON body of the last recorded request.
     */
    private function lastRequestBody(): array
    {
        $this->assertNotEmpty($this->history, 'No request was recorded');

        $request = $this->history[count($this->history) - 1]['request'];

        return json_decode((string) $request->getBody(), true) ?? [];
    }

    /**
     * Return the parsed multipart fields of the last recorded request.
     *
     * @return array<string, string>
     */
    private function lastMultipartFields(): array
    {
        $this->assertNotEmpty($this->history, 'No request was recorded');

        $request = $this->history[count($this->history) - 1]['request'];
        $contentType = $request->getHeaderLine('Content-Type');
        $raw = (string) $request->getBody();

        preg_match('/boundary="?([^";]+)"?/', $contentType, $m);
        $this->assertNotEmpty($m, 'Multipart boundary not found');

        $fields = [];
        $parts = explode('--' . $m[1], $raw);

        foreach ($parts as $part) {
            if (!preg_match('/name="([^"]+)"/', $part, $nameMatch)) {
                continue;
            }

            $value = preg_split("/\r\n\r\n/", $part, 2);
            $value = $value[1] ?? '';
            $fields[$nameMatch[1]] = rtrim($value, "\r\n-");
        }

        return $fields;
    }

    private function successResponse(array $data = ['ok' => true]): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'success' => true,
            'data'    => $data,
            'message' => 'OK',
        ]));
    }

    private function errorResponse(int $status, string $message = 'Something went wrong'): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode([
            'success' => false,
            'data'    => null,
            'message' => $message,
        ]));
    }

    // ─── Constructor ────────────────────────────────────────────────────────

    public function testConstructor(): void
    {
        $client = new KirimiClient($this->testUserCode, $this->testSecret);
        $this->assertInstanceOf(KirimiClient::class, $client);
        $this->assertEquals($this->testUserCode, $client->getUserCode());
        $this->assertEquals('https://api.kirimi.id', $client->getEndpoint());
    }

    public function testConstructorWithCustomEndpoint(): void
    {
        $client = new KirimiClient($this->testUserCode, $this->testSecret, 'https://custom.api.endpoint.com');
        $this->assertEquals('https://custom.api.endpoint.com', $client->getEndpoint());
    }

    public function testConstructorRemovesTrailingSlashFromEndpoint(): void
    {
        $client = new KirimiClient($this->testUserCode, $this->testSecret, 'https://api.kirimi.id/');
        $this->assertEquals('https://api.kirimi.id', $client->getEndpoint());
    }

    public function testGetUserCode(): void
    {
        $client = new KirimiClient($this->testUserCode, $this->testSecret);
        $this->assertEquals($this->testUserCode, $client->getUserCode());
    }

    public function testGetEndpoint(): void
    {
        $client = new KirimiClient($this->testUserCode, $this->testSecret);
        $this->assertEquals('https://api.kirimi.id', $client->getEndpoint());
    }

    // ─── WhatsApp Unofficial ────────────────────────────────────────────────

    public function testSendMessageSendsReceiverAndNotPhone(): void
    {
        $client = $this->makeClient([$this->successResponse()]);
        $result = $client->sendMessage('dev-1', '628123456789', 'halo');

        $body = $this->lastRequestBody();
        $this->assertSame('628123456789', $body['receiver']);
        $this->assertArrayNotHasKey('phone', $body);
        $this->assertSame('dev-1', $body['device_id']);
        $this->assertSame('halo', $body['message']);
        $this->assertSame($this->testUserCode, $body['user_code']);
        $this->assertSame($this->testSecret, $body['secret']);
        $this->assertSame(['ok' => true], $result);
    }

    public function testSendMessageIncludesOptionalFields(): void
    {
        $client = $this->makeClient([$this->successResponse()]);
        $client->sendMessage('dev-1', '628123456789', 'halo', 'https://x/y.jpg', [
            'fileName'           => 'y.jpg',
            'enableTypingEffect' => true,
            'typingSpeedMs'      => 350,
            'quotedMessageId'    => 'MSG1',
        ]);

        $body = $this->lastRequestBody();
        $this->assertSame('https://x/y.jpg', $body['media_url']);
        $this->assertSame('y.jpg', $body['fileName']);
        $this->assertTrue($body['enableTypingEffect']);
        $this->assertSame(350, $body['typingSpeedMs']);
        $this->assertSame('MSG1', $body['quotedMessageId']);
        $this->assertArrayNotHasKey('phone', $body);
    }

    public function testSendMessageOmitsNullMediaUrl(): void
    {
        $client = $this->makeClient([$this->successResponse()]);
        $client->sendMessage('dev-1', '628123456789', 'halo');

        $this->assertArrayNotHasKey('media_url', $this->lastRequestBody());
    }

    public function testSendMessageFastSendsReceiver(): void
    {
        $client = $this->makeClient([$this->successResponse()]);
        $client->sendMessageFast('dev-1', '628123456789', 'halo', 'https://x/y.jpg');

        $body = $this->lastRequestBody();
        $this->assertSame('628123456789', $body['receiver']);
        $this->assertArrayNotHasKey('phone', $body);
        $this->assertSame('https://x/y.jpg', $body['media_url']);
        $this->assertArrayNotHasKey('enableTypingEffect', $body);
    }

    public function testSendMessageFileSendsReceiverAndFileNameOnce(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'kirimi');
        file_put_contents($tmp, 'hello');

        try {
            $client = $this->makeClient([$this->successResponse()]);
            $client->sendMessageFile('dev-1', '628123456789', $tmp, [
                'message' => 'here',
                'fileName' => 'invoice.pdf',
            ]);

            $fields = $this->lastMultipartFields();

            $this->assertSame('628123456789', $fields['receiver']);
            $this->assertArrayNotHasKey('phone', $fields);
            $this->assertSame('invoice.pdf', $fields['fileName']);
            $this->assertSame('here', $fields['message']);
            $this->assertSame($this->testUserCode, $fields['user_code']);

            $request = $this->history[0]['request'];
            $raw = (string) $request->getBody();
            $this->assertSame(
                1,
                substr_count($raw, 'name="fileName"'),
                'fileName must appear exactly once as a field'
            );
            $this->assertStringContainsString('filename="invoice.pdf"', $raw);
        } finally {
            @unlink($tmp);
        }
    }

    public function testBroadcastMessageSendsNumbersArrayAndLabel(): void
    {
        $client = $this->makeClient([$this->successResponse()]);
        $client->broadcastMessage('dev-1', 'promo-juli', ['628111111111', '628222222222'], 'Promo!', [
            'delay' => 30,
        ]);

        $body = $this->lastRequestBody();
        $this->assertSame('promo-juli', $body['label']);
        $this->assertIsArray($body['numbers']);
        $this->assertSame(['628111111111', '628222222222'], $body['numbers']);
        $this->assertArrayNotHasKey('phones', $body);
        $this->assertSame(30, $body['delay']);
    }

    public function testBroadcastMessageReindexesNumbers(): void
    {
        $client = $this->makeClient([$this->successResponse()]);
        $client->broadcastMessage('dev-1', 'promo', [5 => '628111111111', 9 => '628222222222'], 'Promo!');

        $numbers = $this->lastRequestBody()['numbers'];
        $this->assertSame([0, 1], array_keys($numbers));
    }

    // ─── WABA ───────────────────────────────────────────────────────────────

    public function testSendWabaMessageSendsWabaIdToTemplateName(): void
    {
        $client = $this->makeClient([$this->successResponse()]);
        $client->sendWabaMessage('waba-1', '628123456789', 'order_update', [
            'variables' => ['A', 'B'],
            'header'    => ['type' => 'text', 'text' => 'Hi'],
            'buttons'   => [['type' => 'url', 'url' => 'https://x']],
        ]);

        $body = $this->lastRequestBody();
        $this->assertSame('waba-1', $body['waba_id']);
        $this->assertSame('628123456789', $body['to']);
        $this->assertSame('order_update', $body['template_name']);
        $this->assertSame(['A', 'B'], $body['variables']);
        $this->assertSame(['type' => 'text', 'text' => 'Hi'], $body['header']);
        $this->assertCount(1, $body['buttons']);
        $this->assertArrayNotHasKey('device_id', $body);
        $this->assertArrayNotHasKey('phone', $body);
        $this->assertArrayNotHasKey('message', $body);
    }

    public function testWabaReplySendsMessageObject(): void
    {
        $client = $this->makeClient([$this->successResponse()]);
        $client->wabaReply('waba-1', '628123456789', ['type' => 'text', 'text' => 'hai']);

        $body = $this->lastRequestBody();
        $this->assertSame('waba-1', $body['waba_id']);
        $this->assertSame('628123456789', $body['to']);
        $this->assertSame(['type' => 'text', 'text' => 'hai'], $body['message']);
    }

    public function testWabaConversations(): void
    {
        $client = $this->makeClient([$this->successResponse()]);
        $client->wabaConversations(25, 2);

        $body = $this->lastRequestBody();
        $this->assertSame(25, $body['limit']);
        $this->assertSame(2, $body['page']);
        $this->assertSame($this->testUserCode, $body['user_code']);
    }

    public function testWabaConversationsOmitsNullPagination(): void
    {
        $client = $this->makeClient([$this->successResponse()]);
        $client->wabaConversations();

        $body = $this->lastRequestBody();
        $this->assertArrayNotHasKey('limit', $body);
        $this->assertArrayNotHasKey('page', $body);
    }

    public function testWabaTemplatesSync(): void
    {
        $client = $this->makeClient([$this->successResponse()]);
        $client->wabaTemplatesSync('waba-1');

        $body = $this->lastRequestBody();
        $this->assertSame('waba-1', $body['waba_id']);
        $this->assertSame($this->testSecret, $body['secret']);
    }

    public function testWabaSendOtp(): void
    {
        $client = $this->makeClient([$this->successResponse()]);
        $client->wabaSendOtp('waba-1', '628123456789', 'otp_auth');

        $body = $this->lastRequestBody();
        $this->assertSame('waba-1', $body['waba_id']);
        $this->assertSame('628123456789', $body['to']);
        $this->assertSame('otp_auth', $body['template_name']);
    }

    public function testWabaVerifyOtp(): void
    {
        $client = $this->makeClient([$this->successResponse()]);
        $client->wabaVerifyOtp('waba-1', '628123456789', '123456');

        $body = $this->lastRequestBody();
        $this->assertSame('waba-1', $body['waba_id']);
        $this->assertSame('628123456789', $body['to']);
        $this->assertSame('123456', $body['otp_code']);
    }

    // ─── Devices ────────────────────────────────────────────────────────────

    public function testCreateDevice(): void
    {
        $client = $this->makeClient([$this->successResponse()]);
        $client->createDevice(3);

        $body = $this->lastRequestBody();
        $this->assertSame(3, $body['package_id']);
        $this->assertArrayNotHasKey('voucher_code', $body);
        $this->assertSame($this->testUserCode, $body['user_code']);
    }

    public function testCreateDeviceWithVoucher(): void
    {
        $client = $this->makeClient([$this->successResponse()]);
        $client->createDevice('3', 'VOUCHER10');

        $body = $this->lastRequestBody();
        $this->assertSame('3', $body['package_id']);
        $this->assertSame('VOUCHER10', $body['voucher_code']);
    }

    public function testConnectDevice(): void
    {
        $client = $this->makeClient([$this->successResponse()]);
        $client->connectDevice('dev-1');

        $body = $this->lastRequestBody();
        $this->assertSame('dev-1', $body['device_id']);
        $this->assertSame($this->testSecret, $body['secret']);
    }

    public function testRenewDevice(): void
    {
        $client = $this->makeClient([$this->successResponse()]);
        $client->renewDevice('dev-1', 4, 'VOUCHER10');

        $body = $this->lastRequestBody();
        $this->assertSame('dev-1', $body['device_id']);
        $this->assertSame(4, $body['package_id']);
        $this->assertSame('VOUCHER10', $body['voucher_code']);
    }

    public function testListDevicesWithPagination(): void
    {
        $client = $this->makeClient([$this->successResponse()]);
        $client->listDevices(2, 10);

        $body = $this->lastRequestBody();
        $this->assertSame(2, $body['page']);
        $this->assertSame(10, $body['limit']);
        $this->assertSame($this->testUserCode, $body['user_code']);
    }

    public function testListDevicesWithoutPagination(): void
    {
        $client = $this->makeClient([$this->successResponse()]);
        $client->listDevices();

        $body = $this->lastRequestBody();
        $this->assertArrayNotHasKey('page', $body);
        $this->assertArrayNotHasKey('limit', $body);
    }

    public function testDeviceStatus(): void
    {
        $client = $this->makeClient([$this->successResponse()]);
        $client->deviceStatus('dev-1');

        $this->assertSame('dev-1', $this->lastRequestBody()['device_id']);
    }

    public function testDeviceStatusEnhanced(): void
    {
        $client = $this->makeClient([$this->successResponse()]);
        $client->deviceStatusEnhanced('dev-1');

        $this->assertSame('dev-1', $this->lastRequestBody()['device_id']);
    }

    public function testUserInfo(): void
    {
        $client = $this->makeClient([$this->successResponse()]);
        $client->userInfo();

        $body = $this->lastRequestBody();
        $this->assertSame($this->testUserCode, $body['user_code']);
        $this->assertSame($this->testSecret, $body['secret']);
    }

    // ─── Contacts ───────────────────────────────────────────────────────────

    public function testSaveContactSendsNamaNomor(): void
    {
        $client = $this->makeClient([$this->successResponse()]);
        $client->saveContact('Budi', '628123456789');

        $body = $this->lastRequestBody();
        $this->assertSame('Budi', $body['nama']);
        $this->assertSame('628123456789', $body['nomor']);
        $this->assertArrayNotHasKey('name', $body);
        $this->assertArrayNotHasKey('phone', $body);
        $this->assertSame($this->testUserCode, $body['user_code']);
    }

    public function testSaveContactWithDeviceId(): void
    {
        $client = $this->makeClient([$this->successResponse()]);
        $client->saveContact('Budi', '628123456789', 'dev-1');

        $body = $this->lastRequestBody();
        $this->assertSame('dev-1', $body['device_id']);
    }

    public function testSaveContactsBulk(): void
    {
        $client = $this->makeClient([$this->successResponse()]);
        $client->saveContactsBulk([
            ['nama' => 'Budi', 'nomor' => '628111111111'],
            ['nama' => 'Ani',  'nomor' => '628222222222'],
        ], 'dev-1');

        $body = $this->lastRequestBody();
        $this->assertSame([
            ['nama' => 'Budi', 'nomor' => '628111111111'],
            ['nama' => 'Ani',  'nomor' => '628222222222'],
        ], $body['contacts']);
        $this->assertSame('dev-1', $body['device_id']);
    }

    // ─── OTP v1 ─────────────────────────────────────────────────────────────

    public function testGenerateOTPSendsSnakeCaseFields(): void
    {
        $client = $this->makeClient([$this->successResponse()]);
        $client->generateOTP('dev-1', '628123456789', [
            'otp_length'       => 6,
            'otp_type'         => 'numeric',
            'customOtpText'    => 'Kode Anda',
            'customOtpMessage' => 'Your OTP is {otp}',
            'typingSpeedMs'    => 300,
        ]);

        $body = $this->lastRequestBody();
        $this->assertSame(6, $body['otp_length']);
        $this->assertSame('numeric', $body['otp_type']);
        $this->assertSame('Kode Anda', $body['customOtpText']);
        $this->assertSame('Your OTP is {otp}', $body['customOtpMessage']);
        $this->assertSame(300, $body['typingSpeedMs']);
        $this->assertSame('dev-1', $body['device_id']);
        $this->assertArrayNotHasKey('otpLength', $body);
        $this->assertArrayNotHasKey('otpType', $body);
    }

    public function testValidateOTP(): void
    {
        $client = $this->makeClient([$this->successResponse()]);
        $client->validateOTP('dev-1', '628123456789', '123456');

        $body = $this->lastRequestBody();
        $this->assertSame('dev-1', $body['device_id']);
        $this->assertSame('628123456789', $body['phone']);
        $this->assertSame('123456', $body['otp']);
    }

    // ─── OTP v2 ─────────────────────────────────────────────────────────────

    public function testSendOtpV2WhatsappMethod(): void
    {
        $client = $this->makeClient([$this->successResponse()]);
        $client->sendOtpV2('628123456789', ['method' => 'whatsapp', 'app_name' => 'MyApp']);

        $body = $this->lastRequestBody();
        $this->assertSame('628123456789', $body['phone']);
        $this->assertSame('whatsapp', $body['method']);
        $this->assertSame('MyApp', $body['app_name']);
        $this->assertArrayNotHasKey('device_id', $body);
        $this->assertArrayNotHasKey('waba_id', $body);
    }

    public function testSendOtpV2DeviceMethod(): void
    {
        $client = $this->makeClient([$this->successResponse()]);
        $client->sendOtpV2('628123456789', [
            'method'         => 'device',
            'device_id'      => 'dev-1',
            'custom_message' => 'Your OTP is {{otp}}',
        ]);

        $body = $this->lastRequestBody();
        $this->assertSame('device', $body['method']);
        $this->assertSame('dev-1', $body['device_id']);
        $this->assertSame('Your OTP is {{otp}}', $body['custom_message']);
    }

    public function testSendOtpV2WabaUserMethod(): void
    {
        $client = $this->makeClient([$this->successResponse()]);
        $client->sendOtpV2('628123456789', [
            'method'        => 'waba_user',
            'waba_id'       => 'waba-1',
            'template_name' => 'otp_auth',
        ]);

        $body = $this->lastRequestBody();
        $this->assertSame('waba_user', $body['method']);
        $this->assertSame('waba-1', $body['waba_id']);
        $this->assertSame('otp_auth', $body['template_name']);
        $this->assertArrayNotHasKey('device_id', $body);
    }

    public function testVerifyOtpV2(): void
    {
        $client = $this->makeClient([$this->successResponse()]);
        $client->verifyOtpV2('628123456789', '123456');

        $body = $this->lastRequestBody();
        $this->assertSame('628123456789', $body['phone']);
        $this->assertSame('123456', $body['otp_code']);
    }

    // ─── OTP Reverse ────────────────────────────────────────────────────────

    public function testOtpReverseCreate(): void
    {
        $client = $this->makeClient([$this->successResponse()]);
        $client->otpReverseCreate('628123456789', 'dev-1', [
            'app_name'        => 'MyApp',
            'callback_url'    => 'https://example.com/cb',
            'custom_message'  => 'Send {{token}} from {{phone}}',
            'success_message' => 'Verified!',
            'failure_message' => 'Failed',
        ]);

        $body = $this->lastRequestBody();
        $this->assertSame('628123456789', $body['phone']);
        $this->assertSame('dev-1', $body['device_id']);
        $this->assertSame('MyApp', $body['app_name']);
        $this->assertSame('https://example.com/cb', $body['callback_url']);
        $this->assertSame('Send {{token}} from {{phone}}', $body['custom_message']);
        $this->assertSame('Verified!', $body['success_message']);
        $this->assertSame('Failed', $body['failure_message']);
    }

    public function testOtpReverseCreateWithDefaultsOnly(): void
    {
        $client = $this->makeClient([$this->successResponse()]);
        $client->otpReverseCreate('628123456789', 'dev-1');

        $body = $this->lastRequestBody();
        $this->assertSame('628123456789', $body['phone']);
        $this->assertSame('dev-1', $body['device_id']);
        $this->assertArrayNotHasKey('app_name', $body);
    }

    public function testOtpReverseStatus(): void
    {
        $client = $this->makeClient([$this->successResponse()]);
        $client->otpReverseStatus('TOKEN123');

        $body = $this->lastRequestBody();
        $this->assertSame('TOKEN123', $body['token']);
        $this->assertSame($this->testSecret, $body['secret']);
    }

    // ─── Packages & Deposits ────────────────────────────────────────────────

    public function testListPackages(): void
    {
        $client = $this->makeClient([$this->successResponse()]);
        $client->listPackages();

        $body = $this->lastRequestBody();
        $this->assertSame($this->testUserCode, $body['user_code']);
        $this->assertSame($this->testSecret, $body['secret']);
    }

    public function testCreateDeposit(): void
    {
        $client = $this->makeClient([$this->successResponse()]);
        $client->createDeposit(50000);

        $body = $this->lastRequestBody();
        $this->assertSame(50000, $body['nominal']);
        $this->assertSame($this->testUserCode, $body['user_code']);
    }

    public function testDepositStatus(): void
    {
        $client = $this->makeClient([$this->successResponse()]);
        $client->depositStatus('REF-1');

        $this->assertSame('REF-1', $this->lastRequestBody()['ref']);
    }

    public function testCancelDeposit(): void
    {
        $client = $this->makeClient([$this->successResponse()]);
        $client->cancelDeposit('REF-1');

        $this->assertSame('REF-1', $this->lastRequestBody()['ref']);
    }

    public function testListDepositsWithOptions(): void
    {
        $client = $this->makeClient([$this->successResponse()]);
        $client->listDeposits(['page' => 1, 'limit' => 10, 'status' => 'paid']);

        $body = $this->lastRequestBody();
        $this->assertSame(1, $body['page']);
        $this->assertSame(10, $body['limit']);
        $this->assertSame('paid', $body['status']);
    }

    public function testListDepositsWithoutOptions(): void
    {
        $client = $this->makeClient([$this->successResponse()]);
        $client->listDeposits();

        $body = $this->lastRequestBody();
        $this->assertArrayNotHasKey('status', $body);
        $this->assertArrayNotHasKey('page', $body);
    }

    // ─── Errors ─────────────────────────────────────────────────────────────

    public function testUnauthorizedResponseThrowsWithStatusCode(): void
    {
        $client = $this->makeClient([$this->errorResponse(401, 'Secret tidak valid')]);

        try {
            $client->sendMessage('dev-1', '628123456789', 'halo');
            $this->fail('Expected KirimiException was not thrown');
        } catch (KirimiException $e) {
            $this->assertSame(401, $e->getStatusCode());
            $this->assertStringContainsString('Secret tidak valid', $e->getMessage());
        }
    }

    public function testInsufficientBalanceExposes402(): void
    {
        $client = $this->makeClient([$this->errorResponse(402, 'Saldo tidak cukup')]);

        try {
            $client->sendOtpV2('628123456789', ['method' => 'whatsapp']);
            $this->fail('Expected KirimiException was not thrown');
        } catch (KirimiException $e) {
            $this->assertSame(402, $e->getStatusCode());
        }
    }

    public function testRateLimitedExposes429(): void
    {
        $client = $this->makeClient([$this->errorResponse(429, 'Too many requests')]);

        try {
            $client->userInfo();
            $this->fail('Expected KirimiException was not thrown');
        } catch (KirimiException $e) {
            $this->assertSame(429, $e->getStatusCode());
        }
    }

    public function testSuccessFalseOn200ThrowsWithStatusCode(): void
    {
        $client = $this->makeClient([$this->errorResponse(200, 'Parameter tidak lengkap')]);

        try {
            $client->userInfo();
            $this->fail('Expected KirimiException was not thrown');
        } catch (KirimiException $e) {
            $this->assertSame(200, $e->getStatusCode());
            $this->assertStringContainsString('Parameter tidak lengkap', $e->getMessage());
        }
    }

    public function testMalformedJsonResponseThrows(): void
    {
        $client = $this->makeClient([
            new Response(200, ['Content-Type' => 'application/json'], 'not-json{{'),
        ]);

        $this->expectException(KirimiException::class);
        $this->expectExceptionMessage('Invalid JSON response from API');

        $client->userInfo();
    }

    public function testMissingSuccessFieldThrows(): void
    {
        $client = $this->makeClient([
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['data' => []])),
        ]);

        $this->expectException(KirimiException::class);
        $this->expectExceptionMessage('missing "success" field');

        $client->userInfo();
    }

    public function testExceptionHasNullStatusCodeWhenNotHttp(): void
    {
        $exception = new KirimiException('boom');
        $this->assertNull($exception->getStatusCode());
    }

    // ─── Misc ───────────────────────────────────────────────────────────────

    public function testHealthCheckMethodExists(): void
    {
        $this->assertTrue(method_exists(new KirimiClient('u', 's'), 'healthCheck'));

        $reflection = new \ReflectionMethod(KirimiClient::class, 'healthCheck');
        $this->assertCount(0, $reflection->getParameters());
    }

    public function testExistingMethodNamesArePreserved(): void
    {
        foreach ([
            'sendMessage', 'sendMessageFast', 'sendMessageFile', 'broadcastMessage',
            'sendWabaMessage', 'listDevices', 'deviceStatus', 'deviceStatusEnhanced',
            'userInfo', 'saveContact', 'generateOTP', 'validateOTP', 'sendOtpV2',
            'verifyOtpV2', 'listDeposits', 'listPackages',
        ] as $method) {
            $this->assertTrue(method_exists(KirimiClient::class, $method), "Missing method {$method}");
        }
    }

    public function testSendMessageSignatureKeepsReceiverName(): void
    {
        $reflection = new \ReflectionMethod(KirimiClient::class, 'sendMessage');
        $parameters = $reflection->getParameters();

        $this->assertSame('deviceId', $parameters[0]->getName());
        $this->assertSame('receiver', $parameters[1]->getName());
        $this->assertSame('message', $parameters[2]->getName());
        $this->assertSame('mediaUrl', $parameters[3]->getName());
        $this->assertTrue($parameters[3]->allowsNull());
    }
}
