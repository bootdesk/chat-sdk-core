<?php

namespace BootDesk\ChatSDK\Core\Tests;

use BootDesk\ChatSDK\Core\Cards\Card;
use BootDesk\ChatSDK\Core\PostableMessage;
use PHPUnit\Framework\TestCase;

class PostableMessageTest extends TestCase
{
    public function test_text_factory(): void
    {
        $msg = PostableMessage::text('Hello');
        $this->assertFalse($msg->isCard());
        $this->assertSame('Hello', $msg->getTextContent());
    }

    public function test_markdown_factory(): void
    {
        $msg = PostableMessage::markdown('# Title');
        $this->assertFalse($msg->isCard());
        $this->assertSame('# Title', $msg->getTextContent());
    }

    public function test_card_factory(): void
    {
        $card = Card::make()->header('Test');
        $msg = PostableMessage::card($card);
        $this->assertTrue($msg->isCard());
        $this->assertSame('Test', $msg->getTextContent());
    }

    public function test_with_metadata(): void
    {
        $msg = new PostableMessage(
            content: 'Hello',
            metadata: ['key' => 'value'],
        );
        $this->assertSame(['key' => 'value'], $msg->metadata);
    }

    public function test_with_reply_to(): void
    {
        $msg = new PostableMessage(
            content: 'Reply',
            replyToMessageId: 'msg_123',
        );
        $this->assertSame('msg_123', $msg->replyToMessageId);
    }
}
