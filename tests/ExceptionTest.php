<?php

namespace BootDesk\ChatSDK\Core\Tests;

use BootDesk\ChatSDK\Core\Exceptions\AdapterException;
use BootDesk\ChatSDK\Core\Exceptions\AuthenticationException;
use BootDesk\ChatSDK\Core\Exceptions\RateLimitException;
use BootDesk\ChatSDK\Core\Exceptions\ResourceNotFoundException;
use BootDesk\ChatSDK\Core\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

class ExceptionTest extends TestCase
{
    public function test_rate_limit_exception_with_retry_after(): void
    {
        $e = new RateLimitException('Too many requests', 429, null, 30);
        $this->assertSame('Too many requests', $e->getMessage());
        $this->assertSame(429, $e->getCode());
        $this->assertSame(30, $e->retryAfter);
    }

    public function test_adapter_exception(): void
    {
        $e = new AdapterException('Adapter error');
        $this->assertSame('Adapter error', $e->getMessage());
    }

    public function test_validation_exception(): void
    {
        $e = new ValidationException('Invalid input');
        $this->assertSame('Invalid input', $e->getMessage());
    }

    public function test_authentication_exception(): void
    {
        $e = new AuthenticationException('Auth failed');
        $this->assertSame('Auth failed', $e->getMessage());
    }

    public function test_resource_not_found_exception(): void
    {
        $e = new ResourceNotFoundException('Not found');
        $this->assertSame('Not found', $e->getMessage());
    }
}
