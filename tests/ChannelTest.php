<?php

namespace BootDesk\ChatSDK\Core\Tests;

use BootDesk\ChatSDK\Core\Channel;
use BootDesk\ChatSDK\Core\PostableMessage;
use BootDesk\ChatSDK\Core\Tests\Helpers\MockAdapter;
use PHPUnit\Framework\TestCase;

class ChannelTest extends TestCase
{
    private MockAdapter $adapter;

    private Channel $channel;

    protected function setUp(): void
    {
        $this->adapter = new MockAdapter;
        $this->channel = new Channel('mock:C123', $this->adapter);
    }

    public function test_post_string(): void
    {
        $sent = $this->channel->post('Hello channel!');
        $this->assertCount(1, $this->adapter->sentMessages);
        $this->assertSame('mock:C123', $sent->threadId);
    }

    public function test_post_postable(): void
    {
        $sent = $this->channel->post(PostableMessage::text('Hello!'));
        $this->assertCount(1, $this->adapter->sentMessages);
    }

    public function test_fetch_metadata(): void
    {
        $info = $this->channel->fetchMetadata();
        $this->assertNotNull($info);
        $this->assertSame('mock:C123', $info->id);
        $this->assertSame('test-channel', $info->name);
    }
}
