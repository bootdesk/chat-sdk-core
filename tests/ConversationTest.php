<?php

namespace BootDesk\ChatSDK\Core\Tests;

use BootDesk\ChatSDK\Core\Chat;
use BootDesk\ChatSDK\Core\Conversations\AskResponse;
use BootDesk\ChatSDK\Core\Conversations\Conversation;
use BootDesk\ChatSDK\Core\Conversations\ConversationState;
use BootDesk\ChatSDK\Core\Message;
use BootDesk\ChatSDK\Core\MessageContext;
use BootDesk\ChatSDK\Core\Tests\Helpers\MemoryStateAdapter;
use BootDesk\ChatSDK\Core\Tests\Helpers\MockAdapter;
use BootDesk\ChatSDK\Core\Thread;
use PHPUnit\Framework\TestCase;

class TestConversation extends Conversation
{
    public array $log = [];

    public function start(Thread $thread, Message $message): void
    {
        $this->log[] = 'start';
        $this->ask(
            thread: $thread,
            question: 'What is your name?',
            next: 'askEmail',
        );
    }

    public function askEmail(Thread $thread, Message $message): void
    {
        $this->log[] = "name:{$message->text}";
        $this->ask(
            thread: $thread,
            question: "Hi {$message->text}! What is your email?",
            next: 'confirm',
            data: ['name' => $message->text],
        );
    }

    public function confirm(Thread $thread, Message $message): void
    {
        $state = ConversationState::get($thread);
        $this->log[] = "email:{$message->text}";
        $this->log[] = 'data:'.json_encode($state['data']);
        $this->say($thread, 'Done!');
        $this->end($thread);
    }
}

class RepeatableConversation extends Conversation
{
    public static bool $maxReachedCalled = false;

    public function start(Thread $thread, Message $message): void {}

    public function onMaxReached(Thread $thread, Message $message): void
    {
        self::$maxReachedCalled = true;
    }
}

class ConversationTest extends TestCase
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
        $this->thread = new Thread('mock:C123:conv', $this->chat, $this->adapter, $this->state);
    }

    public function test_conversation_start(): void
    {
        $conv = new TestConversation;
        $msg = \BootDesk\ChatSDK\Core\Tests\Helpers\createTestMessage(threadId: 'mock:C123:conv');

        $this->chat->conversationManager->start(TestConversation::class, $this->thread, $msg);

        $state = ConversationState::get($this->thread);
        $this->assertSame(TestConversation::class, $state['class']);
        $this->assertSame('askEmail', $state['step']);
        $this->assertCount(1, $this->adapter->sentMessages);
    }

    public function test_conversation_intercept_continues(): void
    {
        $msg = \BootDesk\ChatSDK\Core\Tests\Helpers\createTestMessage(threadId: 'mock:C123:conv');

        $this->chat->conversationManager->start(TestConversation::class, $this->thread, $msg);

        $reply = \BootDesk\ChatSDK\Core\Tests\Helpers\createTestMessage(
            text: 'John',
            threadId: 'mock:C123:conv',
        );

        $consumed = $this->chat->conversationManager->intercept($this->thread, $reply);
        $this->assertTrue($consumed);

        $state = ConversationState::get($this->thread);
        $this->assertSame('confirm', $state['step']);
        $this->assertSame(['name' => 'John'], $state['data']);
    }

    public function test_conversation_end(): void
    {
        $msg = \BootDesk\ChatSDK\Core\Tests\Helpers\createTestMessage(threadId: 'mock:C123:conv');
        $this->chat->conversationManager->start(TestConversation::class, $this->thread, $msg);

        $reply1 = \BootDesk\ChatSDK\Core\Tests\Helpers\createTestMessage(text: 'John', threadId: 'mock:C123:conv');
        $this->chat->conversationManager->intercept($this->thread, $reply1);

        $reply2 = \BootDesk\ChatSDK\Core\Tests\Helpers\createTestMessage(text: 'john@test.com', threadId: 'mock:C123:conv');
        $this->chat->conversationManager->intercept($this->thread, $reply2);

        $state = ConversationState::get($this->thread);
        $this->assertEmpty($state);
    }

    public function test_no_intercept_without_active_conversation(): void
    {
        $msg = \BootDesk\ChatSDK\Core\Tests\Helpers\createTestMessage(threadId: 'mock:C123:conv');
        $consumed = $this->chat->conversationManager->intercept($this->thread, $msg);

        $this->assertFalse($consumed);
    }

    public function test_conversation_blocks_normal_handlers(): void
    {
        $handlerCalled = false;
        $this->chat->onNewMessage('/.*/', function (MessageContext $ctx) use (&$handlerCalled) {
            $handlerCalled = true;
        });

        $msg = \BootDesk\ChatSDK\Core\Tests\Helpers\createTestMessage(threadId: 'mock:C123:conv');
        $this->chat->conversationManager->start(TestConversation::class, $this->thread, $msg);

        $reply = \BootDesk\ChatSDK\Core\Tests\Helpers\createTestMessage(text: 'John', threadId: 'mock:C123:conv');
        $this->chat->processMessage($this->adapter, 'mock:C123:conv', $reply);

        $this->assertFalse($handlerCalled, 'Normal handler should not be called during active conversation');
    }

    public function test_conversation_manager_clear(): void
    {
        $conv = new TestConversation;
        $msg = \BootDesk\ChatSDK\Core\Tests\Helpers\createTestMessage(threadId: 'mock:C123:conv');
        $this->chat->conversationManager->start(TestConversation::class, $this->thread, $msg);

        $this->chat->conversationManager->clear($this->thread);
        $state = ConversationState::get($this->thread);
        $this->assertEmpty($state);
    }

    public function test_conversation_manager_start_invalid_class(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $msg = \BootDesk\ChatSDK\Core\Tests\Helpers\createTestMessage();
        $this->chat->conversationManager->start(\stdClass::class, $this->thread, $msg);
    }

    public function test_intercept_with_timeout(): void
    {
        ConversationState::save($this->thread, [
            'class' => TestConversation::class,
            'step' => 'start',
            'timeoutAt' => time() - 1,
        ]);

        $msg = \BootDesk\ChatSDK\Core\Tests\Helpers\createTestMessage(threadId: 'mock:C123:conv');
        $consumed = $this->chat->conversationManager->intercept($this->thread, $msg);
        $this->assertFalse($consumed);
    }

    public function test_intercept_with_invalid_class_clears_state(): void
    {
        ConversationState::save($this->thread, [
            'class' => 'NonExistentClass',
            'step' => 'start',
        ]);

        $msg = \BootDesk\ChatSDK\Core\Tests\Helpers\createTestMessage(threadId: 'mock:C123:conv');
        $consumed = $this->chat->conversationManager->intercept($this->thread, $msg);
        $this->assertFalse($consumed);

        $state = ConversationState::get($this->thread);
        $this->assertEmpty($state);
    }

    public function test_ask_response_repeat(): void
    {
        $response = new AskResponse($this->thread);
        $result = $response->repeat('Try again', 5, 'start');
        $this->assertSame($response, $result);

        $state = ConversationState::get($this->thread);
        $this->assertSame('Try again', $state['_repeat']['message']);
        $this->assertSame(5, $state['_repeat']['maxAttempts']);
        $this->assertSame('start', $state['_repeat']['onMaxReached']);
    }

    public function test_ask_response_timeout(): void
    {
        $response = new AskResponse($this->thread);
        $result = $response->timeout(60);
        $this->assertSame($response, $result);

        $state = ConversationState::get($this->thread);
        $this->assertArrayHasKey('timeoutAt', $state);
    }

    public function test_conversation_pause_and_end_with_stack(): void
    {
        $parent = new TestConversation;
        $child = new TestConversation;
        $msg = \BootDesk\ChatSDK\Core\Tests\Helpers\createTestMessage(threadId: 'mock:C123:conv');

        // Simulate parent conversation state
        ConversationState::save($this->thread, [
            'class' => TestConversation::class,
            'step' => 'pausePoint',
            'data' => [],
        ]);

        // Pause to child conversation (via reflection since pause is protected)
        $ref = new \ReflectionMethod(Conversation::class, 'pause');
        $ref->invokeArgs($parent, [TestConversation::class, $this->thread, $msg]);

        $state = ConversationState::get($this->thread);
        $this->assertSame(TestConversation::class, $state['class']);
        $this->assertSame('start', $state['step']);
        $this->assertCount(1, $state['data']['_stack']);

        // End child conversation, should restore parent
        $refEnd = new \ReflectionMethod(Conversation::class, 'end');
        $refEnd->invokeArgs($child, [$this->thread]);

        $state = ConversationState::get($this->thread);
        $this->assertSame(TestConversation::class, $state['class']);
        $this->assertSame('pausePoint', $state['step']);
    }

    public function test_conversation_say(): void
    {
        $conv = new TestConversation;
        $ref = new \ReflectionMethod(Conversation::class, 'say');
        $ref->invokeArgs($conv, [$this->thread, 'Hello from say']);

        $this->assertCount(1, $this->adapter->sentMessages);
    }

    public function test_intercept_with_repeat_max_reached(): void
    {
        ConversationState::save($this->thread, [
            'class' => TestConversation::class,
            'step' => 'start',
            '_repeat' => [
                'message' => 'Try again',
                'maxAttempts' => 1,
                'attempts' => 1,
                'onMaxReached' => null,
            ],
        ]);

        $msg = \BootDesk\ChatSDK\Core\Tests\Helpers\createTestMessage(threadId: 'mock:C123:conv');
        $consumed = $this->chat->conversationManager->intercept($this->thread, $msg);
        $this->assertTrue($consumed);

        $state = ConversationState::get($this->thread);
        $this->assertEmpty($state);
    }

    public function test_intercept_with_repeat_resends_message(): void
    {
        ConversationState::save($this->thread, [
            'class' => TestConversation::class,
            'step' => 'start',
            '_repeat' => [
                'message' => 'Try again',
                'maxAttempts' => 3,
                'attempts' => 0,
                'onMaxReached' => null,
            ],
        ]);

        $msg = \BootDesk\ChatSDK\Core\Tests\Helpers\createTestMessage(threadId: 'mock:C123:conv');
        $consumed = $this->chat->conversationManager->intercept($this->thread, $msg);

        $this->assertTrue($consumed);
        $this->assertCount(1, $this->adapter->sentMessages);
    }

    public function test_intercept_with_repeat_max_reached_calls_handler(): void
    {
        RepeatableConversation::$maxReachedCalled = false;

        ConversationState::save($this->thread, [
            'class' => RepeatableConversation::class,
            'step' => 'start',
            '_repeat' => [
                'message' => 'Try again',
                'maxAttempts' => 1,
                'attempts' => 1,
                'onMaxReached' => 'onMaxReached',
            ],
        ]);

        $msg = \BootDesk\ChatSDK\Core\Tests\Helpers\createTestMessage(threadId: 'mock:C123:conv');
        $consumed = $this->chat->conversationManager->intercept($this->thread, $msg);
        $this->assertTrue($consumed);
        $this->assertTrue(RepeatableConversation::$maxReachedCalled);
    }
}
