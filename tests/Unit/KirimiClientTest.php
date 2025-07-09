<?php

namespace Kirimi\Tests\Unit;

use Kirimi\KirimiClient;
use Kirimi\KirimiException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for KirimiClient
 */
class KirimiClientTest extends TestCase
{
    private KirimiClient $client;
    private string $testUserCode = 'test_user_code';
    private string $testSecret = 'test_secret_key';

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new KirimiClient($this->testUserCode, $this->testSecret);
    }

    public function testConstructor(): void
    {
        $this->assertInstanceOf(KirimiClient::class, $this->client);
        $this->assertEquals($this->testUserCode, $this->client->getUserCode());
        $this->assertEquals('https://api.kirimi.id', $this->client->getEndpoint());
    }

    public function testConstructorWithCustomEndpoint(): void
    {
        $customEndpoint = 'https://custom.api.endpoint.com';
        $client = new KirimiClient($this->testUserCode, $this->testSecret, $customEndpoint);
        
        $this->assertEquals($customEndpoint, $client->getEndpoint());
    }

    public function testConstructorRemovesTrailingSlashFromEndpoint(): void
    {
        $endpointWithSlash = 'https://api.kirimi.id/';
        $client = new KirimiClient($this->testUserCode, $this->testSecret, $endpointWithSlash);
        
        $this->assertEquals('https://api.kirimi.id', $client->getEndpoint());
    }

    public function testGetUserCode(): void
    {
        $this->assertEquals($this->testUserCode, $this->client->getUserCode());
    }

    public function testGetEndpoint(): void
    {
        $this->assertEquals('https://api.kirimi.id', $this->client->getEndpoint());
    }

    /**
     * Note: The following tests would require mocking HTTP responses
     * In a real test suite, you would mock the Guzzle HTTP client
     * to test the API method behaviors without making actual HTTP calls
     */

    public function testSendMessageParameterValidation(): void
    {
        // This test would require mocking HTTP client
        // For now, we just test that the method exists and has the right signature
        $this->assertTrue(method_exists($this->client, 'sendMessage'));
        
        $reflection = new \ReflectionMethod($this->client, 'sendMessage');
        $parameters = $reflection->getParameters();
        
        $this->assertCount(4, $parameters);
        $this->assertEquals('deviceId', $parameters[0]->getName());
        $this->assertEquals('receiver', $parameters[1]->getName());
        $this->assertEquals('message', $parameters[2]->getName());
        $this->assertEquals('mediaUrl', $parameters[3]->getName());
        $this->assertTrue($parameters[3]->allowsNull());
    }

    public function testGenerateOTPMethodExists(): void
    {
        $this->assertTrue(method_exists($this->client, 'generateOTP'));
        
        $reflection = new \ReflectionMethod($this->client, 'generateOTP');
        $parameters = $reflection->getParameters();
        
        $this->assertCount(2, $parameters);
        $this->assertEquals('deviceId', $parameters[0]->getName());
        $this->assertEquals('phone', $parameters[1]->getName());
    }

    public function testValidateOTPMethodExists(): void
    {
        $this->assertTrue(method_exists($this->client, 'validateOTP'));
        
        $reflection = new \ReflectionMethod($this->client, 'validateOTP');
        $parameters = $reflection->getParameters();
        
        $this->assertCount(3, $parameters);
        $this->assertEquals('deviceId', $parameters[0]->getName());
        $this->assertEquals('phone', $parameters[1]->getName());
        $this->assertEquals('otp', $parameters[2]->getName());
    }

    public function testHealthCheckMethodExists(): void
    {
        $this->assertTrue(method_exists($this->client, 'healthCheck'));
        
        $reflection = new \ReflectionMethod($this->client, 'healthCheck');
        $parameters = $reflection->getParameters();
        
        $this->assertCount(0, $parameters);
    }
} 