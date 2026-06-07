<?php

namespace BootDesk\ChatSDK\Core\Tests;

use BootDesk\ChatSDK\Core\Author;
use BootDesk\ChatSDK\Core\Message;
use BootDesk\ChatSDK\Core\SentMessage;
use BootDesk\ChatSDK\Core\Tests\Helpers\MemoryStateAdapter;
use BootDesk\ChatSDK\Core\Transcript\DefaultTranscriptsApi;
use PHPUnit\Framework\TestCase;

class TranscriptsApiTest extends TestCase
{
    private MemoryStateAdapter $state;

    private DefaultTranscriptsApi $transcripts;

    protected function setUp(): void
    {
        $this->state = new MemoryStateAdapter;
        $this->transcripts = new DefaultTranscriptsApi($this->state, [
            'max_messages' => 5,
            'ttl_ms' => 3600000,
        ]);
    }

    private function makeMessage(string $text): Message
    {
        return new Message(
            id: uniqid('msg_'),
            threadId: 'mock:C123:456',
            author: new Author(id: 'U1', name: 'Test'),
            text: $text,
        );
    }

    private function makeSentMessage(string $id, string $text): SentMessage
    {
        return new SentMessage(
            id: $id,
            threadId: 'mock:C123:456',
            timestamp: (string) time(),
        );
    }

    public function test_append_and_list(): void
    {
        $this->transcripts->append('user:U1', $this->makeMessage('Hello'));
        $this->transcripts->append('user:U1', $this->makeMessage('World'));

        $entries = $this->transcripts->list('user:U1');
        $this->assertCount(2, $entries);
        $this->assertSame('Hello', $entries[0]['text']);
        $this->assertSame('World', $entries[1]['text']);
    }

    public function test_append_direction(): void
    {
        $this->transcripts->append('user:U1', $this->makeMessage('Hello'));
        $msg = $this->makeSentMessage('sent_1', 'Hi there');
        $this->transcripts->appendOutgoing('user:U1', $msg, 'Hi there');

        $entries = $this->transcripts->list('user:U1');
        $this->assertCount(2, $entries);
        $this->assertSame('incoming', $entries[0]['direction']);
        $this->assertSame('outgoing', $entries[1]['direction']);
    }

    public function test_append_outgoing_uses_bot_author(): void
    {
        $msg = $this->makeSentMessage('sent_1', 'Hello world');
        $this->transcripts->appendOutgoing('user:U1', $msg, 'Hello world');

        $entries = $this->transcripts->list('user:U1');
        $this->assertSame('bot', $entries[0]['authorId']);
    }

    public function test_count(): void
    {
        $this->transcripts->append('user:U1', $this->makeMessage('A'));
        $this->transcripts->append('user:U1', $this->makeMessage('B'));
        $this->transcripts->append('user:U1', $this->makeMessage('C'));

        $this->assertSame(3, $this->transcripts->count('user:U1'));
    }

    public function test_count_returns_zero_for_unknown_user(): void
    {
        $this->assertSame(0, $this->transcripts->count('user:unknown'));
    }

    public function test_delete(): void
    {
        $this->transcripts->append('user:U1', $this->makeMessage('Hello'));
        $this->transcripts->delete('user:U1');

        $this->assertSame(0, $this->transcripts->count('user:U1'));
    }

    public function test_list_returns_empty_for_unknown_user(): void
    {
        $this->assertSame([], $this->transcripts->list('user:nobody'));
    }

    public function test_max_messages_enforced(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->transcripts->append('user:U1', $this->makeMessage("msg_{$i}"));
        }

        $entries = $this->transcripts->list('user:U1');
        $this->assertCount(5, $entries);
        $this->assertSame('msg_9', $entries[4]['text']);
    }
}
