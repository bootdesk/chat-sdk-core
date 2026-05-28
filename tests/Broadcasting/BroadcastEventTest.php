<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Tests\Broadcasting;

use BootDesk\ChatSDK\Core\Broadcasting\DirectMessageRequestedEvent;
use BootDesk\ChatSDK\Core\Broadcasting\MessageDeletedEvent;
use BootDesk\ChatSDK\Core\Broadcasting\MessageEditedEvent;
use BootDesk\ChatSDK\Core\Broadcasting\MessagePostedEvent;
use BootDesk\ChatSDK\Core\Broadcasting\ReactionAddedEvent;
use BootDesk\ChatSDK\Core\Broadcasting\ReactionRemovedEvent;
use BootDesk\ChatSDK\Core\Broadcasting\StreamingChunkEvent;
use BootDesk\ChatSDK\Core\Broadcasting\TypingStartedEvent;
use PHPUnit\Framework\TestCase;

class BroadcastEventTest extends TestCase
{
    public function test_message_posted_event(): void
    {
        $event = new MessagePostedEvent(
            threadId: 'web:u1:c1',
            messageId: 'msg-1',
            text: 'Hello',
            author: ['id' => 'bot', 'name' => 'Bot'],
        );

        $this->assertSame('message.posted', $event->type);
        $this->assertSame('web:u1:c1', $event->threadId);
        $this->assertSame('msg-1', $event->messageId);
        $this->assertSame('Hello', $event->text);
        $this->assertSame(['id' => 'bot', 'name' => 'Bot'], $event->author);

        $array = $event->toArray();
        $this->assertSame('message.posted', $array['type']);
        $this->assertSame('web:u1:c1', $array['threadId']);
        $this->assertSame('msg-1', $array['data']['messageId']);
        $this->assertSame('Hello', $array['data']['text']);
        $this->assertArrayHasKey('timestamp', $array);
    }

    public function test_message_edited_event(): void
    {
        $event = new MessageEditedEvent(
            threadId: 'web:u1:c1',
            messageId: 'msg-1',
            newText: 'Edited text',
        );

        $this->assertSame('message.edited', $event->type);
        $this->assertSame('msg-1', $event->messageId);
        $this->assertSame('Edited text', $event->newText);

        $array = $event->toArray();
        $this->assertSame('message.edited', $array['type']);
        $this->assertSame('msg-1', $array['data']['messageId']);
        $this->assertSame('Edited text', $array['data']['newText']);
    }

    public function test_message_deleted_event(): void
    {
        $event = new MessageDeletedEvent(
            threadId: 'web:u1:c1',
            messageId: 'msg-1',
        );

        $this->assertSame('message.deleted', $event->type);
        $this->assertSame('msg-1', $event->messageId);

        $array = $event->toArray();
        $this->assertSame('message.deleted', $array['type']);
        $this->assertSame('msg-1', $array['data']['messageId']);
    }

    public function test_reaction_added_event(): void
    {
        $event = new ReactionAddedEvent(
            threadId: 'web:u1:c1',
            messageId: 'msg-1',
            emoji: '👍',
            user: ['id' => 'u1'],
        );

        $this->assertSame('reaction.added', $event->type);
        $this->assertSame('msg-1', $event->messageId);
        $this->assertSame('👍', $event->emoji);
        $this->assertSame(['id' => 'u1'], $event->user);

        $array = $event->toArray();
        $this->assertSame('reaction.added', $array['type']);
        $this->assertSame('msg-1', $array['data']['messageId']);
        $this->assertSame('👍', $array['data']['emoji']);
    }

    public function test_reaction_removed_event(): void
    {
        $event = new ReactionRemovedEvent(
            threadId: 'web:u1:c1',
            messageId: 'msg-1',
            emoji: '👍',
            user: ['id' => 'u1'],
        );

        $this->assertSame('reaction.removed', $event->type);
        $this->assertSame('msg-1', $event->messageId);
        $this->assertSame('👍', $event->emoji);

        $array = $event->toArray();
        $this->assertSame('reaction.removed', $array['type']);
        $this->assertSame('msg-1', $array['data']['messageId']);
    }

    public function test_typing_started_event(): void
    {
        $event = new TypingStartedEvent(
            threadId: 'web:u1:c1',
            userId: 'u1',
        );

        $this->assertSame('typing.started', $event->type);
        $this->assertSame('u1', $event->userId);

        $array = $event->toArray();
        $this->assertSame('typing.started', $array['type']);
        $this->assertSame('u1', $array['data']['userId']);
    }

    public function test_streaming_chunk_event(): void
    {
        $event = new StreamingChunkEvent(
            threadId: 'web:u1:c1',
            messageId: 'msg-1',
            chunk: 'Hello',
            isFinal: false,
        );

        $this->assertSame('streaming.chunk', $event->type);
        $this->assertSame('msg-1', $event->messageId);
        $this->assertSame('Hello', $event->chunk);
        $this->assertFalse($event->isFinal);

        $array = $event->toArray();
        $this->assertSame('streaming.chunk', $array['type']);
        $this->assertSame('msg-1', $array['data']['messageId']);
        $this->assertSame('Hello', $array['data']['chunk']);
        $this->assertFalse($array['data']['isFinal']);
    }

    public function test_streaming_chunk_event_final(): void
    {
        $event = new StreamingChunkEvent(
            threadId: 'web:u1:c1',
            messageId: 'msg-1',
            chunk: '',
            isFinal: true,
        );

        $this->assertSame('streaming.chunk', $event->type);
        $this->assertTrue($event->isFinal);

        $array = $event->toArray();
        $this->assertTrue($array['data']['isFinal']);
    }

    public function test_event_timestamp_can_be_set(): void
    {
        $timestamp = 1234567890;
        $event = new MessagePostedEvent(
            threadId: 'web:u1:c1',
            messageId: 'msg-1',
            text: 'Hello',
            author: ['id' => 'bot'],
            timestamp: $timestamp,
        );

        $this->assertSame($timestamp, $event->timestamp);
        $this->assertSame($timestamp, $event->toArray()['timestamp']);
    }

    public function test_dm_requested_event(): void
    {
        $event = new DirectMessageRequestedEvent(
            threadId: 'web:u1:conv1',
            userId: 'u1',
        );

        $this->assertSame('dm.requested', $event->type);
        $this->assertSame('web:u1:conv1', $event->threadId);
        $this->assertSame('u1', $event->userId);

        $array = $event->toArray();
        $this->assertSame('dm.requested', $array['type']);
        $this->assertSame('u1', $array['data']['userId']);
    }
}
