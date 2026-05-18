<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Tests;

use BootDesk\ChatSDK\Core\PostableMessage;
use BootDesk\ChatSDK\Core\Support\AdapterRegistry;
use BootDesk\ChatSDK\Core\Support\AttachmentUtils;
use PHPUnit\Framework\TestCase;

class SupportTest extends TestCase
{
    public function test_adapter_registry_register_and_get(): void
    {
        AdapterRegistry::register('support_test_reg', \stdClass::class);
        $this->assertSame(\stdClass::class, AdapterRegistry::get('support_test_reg'));
    }

    public function test_adapter_registry_returns_null_for_unknown(): void
    {
        $this->assertNull(AdapterRegistry::get('support_test_nonexistent'));
    }

    public function test_adapter_registry_overwrite(): void
    {
        AdapterRegistry::register('support_test_a', \stdClass::class);
        AdapterRegistry::register('support_test_a', \Exception::class);
        $this->assertSame(\Exception::class, AdapterRegistry::get('support_test_a'));
    }

    public function test_attachment_utils_has_attachments(): void
    {
        $msg = PostableMessage::text('test');
        $this->assertFalse(AttachmentUtils::hasAttachments($msg));
    }

    public function test_attachment_utils_extract_files(): void
    {
        $msg = PostableMessage::text('test');
        $this->assertEmpty(AttachmentUtils::extractFiles($msg));
    }

    public function test_attachment_utils_extract_attachments(): void
    {
        $msg = PostableMessage::text('test');
        $this->assertEmpty(AttachmentUtils::extractAttachments($msg));
    }
}
