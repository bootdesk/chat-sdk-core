<?php

namespace BootDesk\ChatSDK\Core\Tests;

use BootDesk\ChatSDK\Core\Cards\Card;
use BootDesk\ChatSDK\Core\Chat;
use BootDesk\ChatSDK\Core\FetchOptions;
use BootDesk\ChatSDK\Core\PostableMessage;
use BootDesk\ChatSDK\Core\Tests\Helpers\MemoryStateAdapter;
use BootDesk\ChatSDK\Core\Tests\Helpers\MockAdapter;
use BootDesk\ChatSDK\Core\Thread;
use PHPUnit\Framework\TestCase;

class ThreadTest extends TestCase
{
    private MemoryStateAdapter $state;

    private MockAdapter $adapter;

    private Chat $chat;

    private Thread $thread;

    protected function setUp(): void
    {
        $this->state = new MemoryStateAdapter;
        $this->adapter = new MockAdapter;
        $this->chat = new Chat(
            state: $this->state,
            adapters: ['mock' => $this->adapter],
        );
        $this->thread = new Thread('mock:C123:1234', $this->chat, $this->adapter, $this->state);
    }

    public function test_post_string(): void
    {
        $sent = $this->thread->post('Hello!');
        $this->assertCount(1, $this->adapter->sentMessages);
        $this->assertSame('mock:C123:1234', $sent->threadId);
    }

    public function test_post_postable_message(): void
    {
        $sent = $this->thread->post(PostableMessage::text('Hello!'));
        $this->assertCount(1, $this->adapter->sentMessages);
    }

    public function test_post_card(): void
    {
        $card = Card::make()
            ->header('Test')
            ->section(fn ($s) => $s->text('Body'));

        $sent = $this->thread->post($card);
        $this->assertCount(1, $this->adapter->sentMessages);
    }

    public function test_subscribe(): void
    {
        $this->thread->subscribe();
        $this->assertTrue($this->thread->isSubscribed());
    }

    public function test_unsubscribe(): void
    {
        $this->thread->subscribe();
        $this->thread->unsubscribe();
        $this->assertFalse($this->thread->isSubscribed());
    }

    public function test_state_management(): void
    {
        $this->thread->setState(['key' => 'value']);
        $state = $this->thread->getState();
        $this->assertSame(['key' => 'value'], $state);
    }

    public function test_state_overwrite(): void
    {
        $this->thread->setState(['key1' => 'val1']);
        $this->thread->setState(['key2' => 'val2']);
        $state = $this->thread->getState();
        $this->assertSame(['key2' => 'val2'], $state);
    }

    public function test_edit_message(): void
    {
        $sent = $this->thread->post('Original');
        $edited = $this->thread->edit($sent->id, PostableMessage::text('Edited'));
        $this->assertSame($sent->id, $edited->id);
    }

    public function test_delete_message(): void
    {
        $sent = $this->thread->post('To delete');
        $this->thread->delete($sent->id);
        $this->assertCount(1, $this->adapter->sentMessages);
    }

    public function test_start_typing(): void
    {
        $this->thread->startTyping();
        $this->assertTrue(true);
    }

    public function test_post_ephemeral(): void
    {
        $this->thread->postEphemeral('U1', 'Hello');
        $this->assertTrue(true);
    }

    public function test_fetch_messages(): void
    {
        $result = $this->thread->fetchMessages();
        $this->assertEmpty($result->messages);
    }

    public function test_fetch_messages_with_options(): void
    {
        $options = new FetchOptions(limit: 10);
        $result = $this->thread->fetchMessages($options);
        $this->assertEmpty($result->messages);
    }
}
