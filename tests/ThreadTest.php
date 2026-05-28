<?php

namespace BootDesk\ChatSDK\Core\Tests;

use BootDesk\ChatSDK\Core\Cards\Card;
use BootDesk\ChatSDK\Core\Chat;
use BootDesk\ChatSDK\Core\Contracts\Adapter;
use BootDesk\ChatSDK\Core\Contracts\SentMiddleware;
use BootDesk\ChatSDK\Core\FetchOptions;
use BootDesk\ChatSDK\Core\PostableMessage;
use BootDesk\ChatSDK\Core\SentMessage;
use BootDesk\ChatSDK\Core\Tests\Helpers\MemoryStateAdapter;
use BootDesk\ChatSDK\Core\Tests\Helpers\MockAdapter;
use BootDesk\ChatSDK\Core\Tests\Helpers\TestSentMiddleware;
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

    public function test_sent_middleware_called_on_post(): void
    {
        $middleware = new TestSentMiddleware;
        $this->chat->addSentMiddleware($middleware);

        $this->thread->post('Hello');

        $this->assertTrue($middleware->called);
        $this->assertNotNull($middleware->lastResult);
        $this->assertSame('mock:C123:1234', $middleware->lastResult->threadId);
        $this->assertSame('post', $middleware->lastOperation);
    }

    public function test_sent_middleware_called_on_edit(): void
    {
        $middleware = new TestSentMiddleware;
        $this->chat->addSentMiddleware($middleware);

        $sent = $this->thread->post('Original');
        $middleware->called = false;

        $this->thread->edit($sent->id, PostableMessage::text('Edited'));

        $this->assertTrue($middleware->called);
        $this->assertNotNull($middleware->lastMessage);
        $this->assertSame('Edited', $middleware->lastMessage->getTextContent());
        $this->assertSame('edit', $middleware->lastOperation);
    }

    public function test_sent_middleware_called_on_post_ephemeral(): void
    {
        $middleware = new TestSentMiddleware;
        $this->chat->addSentMiddleware($middleware);

        $this->thread->postEphemeral('U1', 'Ephemeral');

        $this->assertTrue($middleware->called);
        $this->assertSame('postEphemeral', $middleware->lastOperation);
    }

    public function test_sent_middleware_can_modify_result(): void
    {
        $middleware = new class implements SentMiddleware
        {
            public function handle(string $threadId, PostableMessage $message, SentMessage $result, Adapter $adapter, string $operation, callable $next): SentMessage
            {
                return new SentMessage(
                    id: 'modified-'.$result->id,
                    threadId: $result->threadId,
                    timestamp: $result->timestamp,
                    additionalMessages: $result->additionalMessages,
                    raw: $result->raw,
                );
            }
        };
        $this->chat->addSentMiddleware($middleware);

        $sent = $this->thread->post('Hello');

        $this->assertStringStartsWith('modified-', $sent->id);
    }
}
