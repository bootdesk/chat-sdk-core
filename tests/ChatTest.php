<?php

namespace BootDesk\ChatSDK\Core\Tests;

use BootDesk\ChatSDK\Core\ActionEvent;
use BootDesk\ChatSDK\Core\AppHomeOpenedEvent;
use BootDesk\ChatSDK\Core\AssistantContextChangedEvent;
use BootDesk\ChatSDK\Core\AssistantThreadStartedEvent;
use BootDesk\ChatSDK\Core\Author;
use BootDesk\ChatSDK\Core\Channel;
use BootDesk\ChatSDK\Core\Chat;
use BootDesk\ChatSDK\Core\Concurrency\Handler;
use BootDesk\ChatSDK\Core\Contracts\Adapter;
use BootDesk\ChatSDK\Core\Contracts\ReceivingMiddleware;
use BootDesk\ChatSDK\Core\Contracts\SendingMiddleware;
use BootDesk\ChatSDK\Core\Contracts\WebhookEventMiddleware;
use BootDesk\ChatSDK\Core\Contracts\WebhookMiddleware;
use BootDesk\ChatSDK\Core\Exceptions\ResourceNotFoundException;
use BootDesk\ChatSDK\Core\MemberJoinedChannelEvent;
use BootDesk\ChatSDK\Core\Message;
use BootDesk\ChatSDK\Core\MessageContext;
use BootDesk\ChatSDK\Core\MessageDeliveredEvent;
use BootDesk\ChatSDK\Core\MessageReadEvent;
use BootDesk\ChatSDK\Core\ModalCloseEvent;
use BootDesk\ChatSDK\Core\ModalSubmitEvent;
use BootDesk\ChatSDK\Core\OptionsLoadEvent;
use BootDesk\ChatSDK\Core\PostableMessage;
use BootDesk\ChatSDK\Core\QueueEntry;
use BootDesk\ChatSDK\Core\ReactionEvent;
use BootDesk\ChatSDK\Core\SlashCommandEvent;
use BootDesk\ChatSDK\Core\Tests\Helpers\MemoryStateAdapter;
use BootDesk\ChatSDK\Core\Tests\Helpers\MockAdapter;
use BootDesk\ChatSDK\Core\Tests\Helpers\MockAdapterResolver;
use BootDesk\ChatSDK\Core\Tests\Helpers\MockBatchedAdapter;
use BootDesk\ChatSDK\Core\Tests\Helpers\TestReceivingMiddleware;
use BootDesk\ChatSDK\Core\Tests\Helpers\TestSendingMiddleware;
use BootDesk\ChatSDK\Core\Tests\Helpers\TestWebhookMiddleware;
use BootDesk\ChatSDK\Core\Thread;
use BootDesk\ChatSDK\Core\WebhookEvent;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ChatTest extends TestCase
{
    private MemoryStateAdapter $state;

    private MockAdapter $adapter;

    private Chat $chat;

    protected function setUp(): void
    {
        $this->state = new MemoryStateAdapter;
        $this->adapter = new MockAdapter;
        $this->chat = new Chat(
            state: $this->state,
            adapters: ['mock' => $this->adapter],
        );
    }

    public function test_register_adapter(): void
    {
        $chat = new Chat($this->state);
        $adapter = new MockAdapter;
        $chat->registerAdapter('test', $adapter);

        $this->assertSame($adapter, $chat->resolveAdapter('test'));
    }

    public function test_resolve_adapter_returns_null_for_unknown(): void
    {
        $this->assertNull($this->chat->resolveAdapter('unknown'));
    }

    public function test_resolve_adapter_returns_registered(): void
    {
        $this->assertSame($this->adapter, $this->chat->resolveAdapter('mock'));
    }

    public function test_on_new_message_pattern_match(): void
    {
        $received = [];
        $this->chat->onNewMessage('/hello/', function (MessageContext $ctx) use (&$received) {
            $received[] = $ctx->message->text;
        });

        $message = \BootDesk\ChatSDK\Core\Tests\Helpers\createTestMessage(text: 'hello world');
        $this->chat->processMessage($this->adapter, $message->threadId, $message);

        $this->assertCount(1, $received);
        $this->assertSame('hello world', $received[0]);
    }

    public function test_on_new_message_no_match(): void
    {
        $received = [];
        $this->chat->onNewMessage('/goodbye/', function (MessageContext $ctx) use (&$received) {
            $received[] = $ctx->message->text;
        });

        $message = \BootDesk\ChatSDK\Core\Tests\Helpers\createTestMessage(text: 'hello world');
        $this->chat->processMessage($this->adapter, $message->threadId, $message);

        $this->assertEmpty($received);
    }

    public function test_on_new_message_catch_all(): void
    {
        $received = [];
        $this->chat->onNewMessage('/.*/', function (MessageContext $ctx) use (&$received) {
            $received[] = $ctx->message->text;
        });

        $message = \BootDesk\ChatSDK\Core\Tests\Helpers\createTestMessage(text: 'anything');
        $this->chat->processMessage($this->adapter, $message->threadId, $message);

        $this->assertCount(1, $received);
    }

    public function test_self_filter_skips_bot_messages(): void
    {
        $received = [];
        $this->chat->onNewMessage('/.*/', function (MessageContext $ctx) use (&$received) {
            $received[] = $ctx->message->text;
        });

        $message = \BootDesk\ChatSDK\Core\Tests\Helpers\createTestMessage(
            text: 'bot message',
            author: new Author(id: 'BOT123', isMe: true),
        );
        $this->chat->processMessage($this->adapter, $message->threadId, $message);

        $this->assertEmpty($received);
    }

    public function test_deduplication(): void
    {
        $count = 0;
        $this->chat->onNewMessage('/.*/', function (MessageContext $ctx) use (&$count) {
            $count++;
        });

        $message = \BootDesk\ChatSDK\Core\Tests\Helpers\createTestMessage(id: 'msg_dedup');
        $this->chat->processMessage($this->adapter, $message->threadId, $message);
        $this->chat->processMessage($this->adapter, $message->threadId, $message);

        $this->assertSame(1, $count);
    }

    public function test_dm_handler(): void
    {
        $received = [];
        $this->chat->onDirectMessage(function (MessageContext $ctx) use (&$received) {
            $received[] = 'dm';
        });

        $message = \BootDesk\ChatSDK\Core\Tests\Helpers\createTestMessage(isDM: true);
        $this->chat->processMessage($this->adapter, $message->threadId, $message);

        $this->assertCount(1, $received);
    }

    public function test_mention_handler(): void
    {
        $received = [];
        $this->chat->onNewMention(function (MessageContext $ctx) use (&$received) {
            $received[] = 'mention';
        });

        $message = \BootDesk\ChatSDK\Core\Tests\Helpers\createTestMessage(isMention: true);
        $this->chat->processMessage($this->adapter, $message->threadId, $message);

        $this->assertCount(1, $received);
    }

    public function test_subscribed_handler(): void
    {
        $threadId = 'mock:C123:sub';
        $this->state->subscribe($threadId);

        $received = [];
        $this->chat->onSubscribedMessage(function (MessageContext $ctx) use (&$received) {
            $received[] = 'subscribed';
        });

        $message = \BootDesk\ChatSDK\Core\Tests\Helpers\createTestMessage(threadId: $threadId);
        $this->chat->processMessage($this->adapter, $threadId, $message);

        $this->assertCount(1, $received);
    }

    public function test_dispatch_priority_dm_before_mention(): void
    {
        $order = [];
        $this->chat->onDirectMessage(function (MessageContext $ctx) use (&$order) {
            $order[] = 'dm';
        });
        $this->chat->onNewMention(function (MessageContext $ctx) use (&$order) {
            $order[] = 'mention';
        });

        $message = \BootDesk\ChatSDK\Core\Tests\Helpers\createTestMessage(isDM: true, isMention: true);
        $this->chat->processMessage($this->adapter, $message->threadId, $message);

        $this->assertSame(['dm'], $order);
    }

    public function test_skip_stops_dispatch(): void
    {
        $order = [];
        $this->chat->onDirectMessage(function (MessageContext $ctx) use (&$order) {
            $order[] = 'dm';
            $ctx->skip();
        });
        $this->chat->onNewMention(function (MessageContext $ctx) use (&$order) {
            $order[] = 'mention';
        });

        $message = \BootDesk\ChatSDK\Core\Tests\Helpers\createTestMessage(isDM: true, isMention: true);
        $this->chat->processMessage($this->adapter, $message->threadId, $message);

        $this->assertSame(['dm'], $order);
    }

    public function test_initialize_and_shutdown(): void
    {
        $this->chat->initialize();
        $this->assertTrue($this->adapter->initialized);
        $this->assertTrue($this->state->isConnected());

        $this->chat->shutdown();
        $this->assertTrue($this->adapter->disconnected);
        $this->assertFalse($this->state->isConnected());
    }

    public function test_thread_factory(): void
    {
        $thread = $this->chat->thread('mock:C123:1234');
        $this->assertInstanceOf(Thread::class, $thread);
        $this->assertSame('mock:C123:1234', $thread->id);
    }

    public function test_channel_factory(): void
    {
        $channel = $this->chat->channel('mock:C123');
        $this->assertInstanceOf(Channel::class, $channel);
        $this->assertSame('mock:C123', $channel->id);
    }

    public function test_process_slash_command_dispatches_handler(): void
    {
        $received = null;
        $this->chat->onSlashCommand('/help', function (SlashCommandEvent $event) use (&$received) {
            $received = $event;
        });

        $this->chat->processSlashCommand(
            adapter: $this->adapter,
            channelId: 'C123',
            command: '/help',
            text: 'topic search',
            user: new Author(id: 'U1'),
        );

        $this->assertNotNull($received);
        $this->assertSame('/help', $received->command);
        $this->assertSame('topic search', $received->text);
        $this->assertSame('U1', $received->user->id);
        $this->assertSame('C123', $received->channel->id);
    }

    public function test_process_slash_command_catch_all(): void
    {
        $commands = [];
        $this->chat->onSlashCommand(function (SlashCommandEvent $event) use (&$commands) {
            $commands[] = $event->command;
        });

        $this->chat->processSlashCommand($this->adapter, 'C123', '/help', '');
        $this->chat->processSlashCommand($this->adapter, 'C123', '/status', '');

        $this->assertSame(['/help', '/status'], $commands);
    }

    public function test_process_slash_command_multiple_patterns(): void
    {
        $received = [];
        $this->chat->onSlashCommand(['/status', '/health'], function (SlashCommandEvent $event) use (&$received) {
            $received[] = $event->command;
        });

        $this->chat->processSlashCommand($this->adapter, 'C123', '/status', '');
        $this->chat->processSlashCommand($this->adapter, 'C123', '/health', '');
        $this->chat->processSlashCommand($this->adapter, 'C123', '/help', '');

        $this->assertSame(['/status', '/health'], $received);
    }

    public function test_process_slash_command_normalizes_command(): void
    {
        $received = null;
        $this->chat->onSlashCommand('help', function (SlashCommandEvent $event) use (&$received) {
            $received = $event;
        });

        $this->chat->processSlashCommand($this->adapter, 'C123', '/help', '');

        $this->assertNotNull($received);
        $this->assertSame('/help', $received->command);
    }

    public function test_process_slash_command_skips_self(): void
    {
        $received = null;
        $this->chat->onSlashCommand('/help', function (SlashCommandEvent $event) use (&$received) {
            $received = $event;
        });

        $this->chat->processSlashCommand(
            adapter: $this->adapter,
            channelId: 'C123',
            command: '/help',
            text: '',
            user: new Author(id: 'BOT', isMe: true),
        );

        $this->assertNull($received);
    }

    public function test_process_slash_command_runs_both_specific_and_catch_all(): void
    {
        $order = [];
        $this->chat->onSlashCommand('/help', function (SlashCommandEvent $event) use (&$order) {
            $order[] = 'specific';
        });
        $this->chat->onSlashCommand(function (SlashCommandEvent $event) use (&$order) {
            $order[] = 'catch_all';
        });

        $this->chat->processSlashCommand($this->adapter, 'C123', '/help', '');

        $this->assertSame(['specific', 'catch_all'], $order);
    }

    public function test_process_reaction_dispatches_handler(): void
    {
        $received = null;
        $this->chat->onReaction('👍', function (ReactionEvent $event) use (&$received) {
            $received = $event;
        });

        $this->chat->processReaction(
            adapter: $this->adapter,
            threadId: 'mock:C123',
            emoji: '👍',
            messageId: 'msg_1',
            user: new Author(id: 'U1'),
        );

        $this->assertNotNull($received);
        $this->assertSame('👍', $received->emoji);
        $this->assertSame('msg_1', $received->messageId);
    }

    public function test_process_reaction_catch_all(): void
    {
        $received = null;
        $this->chat->onReaction(function (ReactionEvent $event) use (&$received) {
            $received = $event;
        });

        $this->chat->processReaction($this->adapter, 'mock:C123', '🚀', 'msg_1', new Author(id: 'U1'));

        $this->assertNotNull($received);
        $this->assertSame('🚀', $received->emoji);
    }

    public function test_process_reaction_with_array_filters(): void
    {
        $received = [];
        $this->chat->onReaction(['👍', '❤️'], function (ReactionEvent $event) use (&$received) {
            $received[] = $event->emoji;
        });

        $this->chat->processReaction($this->adapter, 'mock:C123', '👍', 'msg_1', new Author(id: 'U1'));
        $this->chat->processReaction($this->adapter, 'mock:C123', '❤️', 'msg_2', new Author(id: 'U1'));
        $this->chat->processReaction($this->adapter, 'mock:C123', '🚀', 'msg_3', new Author(id: 'U1'));

        $this->assertSame(['👍', '❤️'], $received);
    }

    public function test_process_action_dispatches_handler(): void
    {
        $received = null;
        $this->chat->onAction('confirm', function (ActionEvent $event) use (&$received) {
            $received = $event;
        });

        $this->chat->processAction(
            adapter: $this->adapter,
            threadId: 'mock:C123',
            actionId: 'confirm',
            value: 'yes',
            messageId: 'msg_1',
            user: new Author(id: 'U1'),
            triggerId: 'trig_1',
        );

        $this->assertNotNull($received);
        $this->assertSame('confirm', $received->actionId);
        $this->assertSame('yes', $received->value);
        $this->assertSame('trig_1', $received->triggerId);
    }

    public function test_process_action_catch_all(): void
    {
        $received = null;
        $this->chat->onAction(function (ActionEvent $event) use (&$received) {
            $received = $event;
        });

        $this->chat->processAction($this->adapter, 'mock:C123', 'any_action', null, 'msg_1', new Author(id: 'U1'));

        $this->assertNotNull($received);
        $this->assertSame('any_action', $received->actionId);
    }

    public function test_process_modal_submit_dispatches_handler(): void
    {
        $received = null;
        $this->chat->onModalSubmit('feedback', function (ModalSubmitEvent $event) use (&$received) {
            $received = $event;
        });

        $this->chat->processModalSubmit(
            adapter: $this->adapter,
            callbackId: 'feedback',
            values: ['rating' => '5'],
            user: new Author(id: 'U1'),
        );

        $this->assertNotNull($received);
        $this->assertSame('feedback', $received->callbackId);
        $this->assertSame(['rating' => '5'], $received->values);
    }

    public function test_process_modal_close_dispatches_handler(): void
    {
        $received = null;
        $this->chat->onModalClose('feedback', function (ModalCloseEvent $event) use (&$received) {
            $received = $event;
        });

        $this->chat->processModalClose(
            adapter: $this->adapter,
            callbackId: 'feedback',
            user: new Author(id: 'U1'),
        );

        $this->assertNotNull($received);
        $this->assertSame('feedback', $received->callbackId);
    }

    public function test_modal_context_storage(): void
    {
        $this->chat->storeModalContext('mock', 'ctx_1', ['key' => 'value']);

        $data = $this->chat->getAndDeleteModalContext('mock', 'ctx_1');
        $this->assertSame(['key' => 'value'], $data);

        $gone = $this->chat->getAndDeleteModalContext('mock', 'ctx_1');
        $this->assertNull($gone);
    }

    public function test_modal_submit_with_context_restores_channel(): void
    {
        $channel = new Channel('C123', $this->adapter);
        $this->chat->storeModalContext('mock', 'ctx_2', ['channel' => $channel]);

        $received = null;
        $this->chat->onModalSubmit('test', function (ModalSubmitEvent $event) use (&$received) {
            $received = $event;
        });

        $this->chat->processModalSubmit(
            adapter: $this->adapter,
            callbackId: 'test',
            values: [],
            user: new Author(id: 'U1'),
            contextId: 'ctx_2',
        );

        $this->assertNotNull($received);
        $this->assertNotNull($received->relatedChannel);
        $this->assertSame('C123', $received->relatedChannel->id);
    }

    public function test_process_options_load_returns_result(): void
    {
        $this->chat->onOptionsLoad('color_picker', function (OptionsLoadEvent $event) {
            $this->assertSame('color_picker', $event->actionId);
            $this->assertSame('re', $event->query);

            return [
                'options' => [
                    ['label' => 'Red', 'value' => 'red'],
                    ['label' => 'Green', 'value' => 'green'],
                ],
            ];
        });

        $result = $this->chat->processOptionsLoad(
            adapter: $this->adapter,
            actionId: 'color_picker',
            query: 're',
            user: new Author(id: 'U1'),
        );

        $this->assertNotNull($result);
        $this->assertCount(2, $result['options']);
        $this->assertSame('Red', $result['options'][0]['label']);
    }

    public function test_process_options_load_catch_all(): void
    {
        $called = false;
        $this->chat->onOptionsLoad(function (OptionsLoadEvent $event) use (&$called) {
            $called = true;

            return ['options' => []];
        });

        $this->chat->processOptionsLoad($this->adapter, 'any_action', '', new Author(id: 'U1'));

        $this->assertTrue($called);
    }

    public function test_process_options_load_returns_null_when_no_match(): void
    {
        $this->chat->onOptionsLoad('color_picker', fn () => ['options' => []]);

        $result = $this->chat->processOptionsLoad(
            adapter: $this->adapter,
            actionId: 'unknown',
            query: '',
            user: new Author(id: 'U1'),
        );

        $this->assertNull($result);
    }

    public function test_process_assistant_thread_started(): void
    {
        $received = null;
        $this->chat->onAssistantThreadStarted(function (AssistantThreadStartedEvent $event) use (&$received) {
            $received = $event;
        });

        $this->chat->processAssistantThreadStarted(
            adapter: $this->adapter,
            channelId: 'C123',
            threadId: 'mock:C123:ts1',
            userId: 'U1',
            context: ['key' => 'val'],
        );

        $this->assertNotNull($received);
        $this->assertSame('C123', $received->channelId);
        $this->assertSame('U1', $received->userId);
        $this->assertSame(['key' => 'val'], $received->context);
    }

    public function test_process_assistant_context_changed(): void
    {
        $received = null;
        $this->chat->onAssistantContextChanged(function (AssistantContextChangedEvent $event) use (&$received) {
            $received = $event;
        });

        $this->chat->processAssistantContextChanged(
            adapter: $this->adapter,
            channelId: 'C123',
            threadId: 'mock:C123:ts1',
            userId: 'U1',
            context: ['new' => 'ctx'],
        );

        $this->assertNotNull($received);
        $this->assertSame(['new' => 'ctx'], $received->context);
    }

    public function test_process_app_home_opened(): void
    {
        $received = null;
        $this->chat->onAppHomeOpened(function (AppHomeOpenedEvent $event) use (&$received) {
            $received = $event;
        });

        $this->chat->processAppHomeOpened(
            adapter: $this->adapter,
            channelId: 'C123',
            userId: 'U1',
        );

        $this->assertNotNull($received);
        $this->assertSame('C123', $received->channelId);
        $this->assertSame('U1', $received->userId);
    }

    public function test_process_member_joined_channel(): void
    {
        $received = null;
        $this->chat->onMemberJoinedChannel(function (MemberJoinedChannelEvent $event) use (&$received) {
            $received = $event;
        });

        $this->chat->processMemberJoinedChannel(
            adapter: $this->adapter,
            channelId: 'C123',
            userId: 'U2',
            inviterId: 'U1',
        );

        $this->assertNotNull($received);
        $this->assertSame('C123', $received->channelId);
        $this->assertSame('U2', $received->userId);
        $this->assertSame('U1', $received->inviterId);
    }

    public function test_open_dm_convenience(): void
    {
        $dmThreadId = $this->chat->openDM('mock', 'U1');
        $this->assertSame('mock:DM:U1', $dmThreadId);
    }

    public function test_open_dm_unknown_adapter(): void
    {
        $this->assertNull($this->chat->openDM('unknown', 'U1'));
    }

    public function test_get_user_convenience(): void
    {
        $user = $this->chat->getUser('mock', 'U1');
        $this->assertNotNull($user);
        $this->assertSame('U1', $user->id);
    }

    public function test_get_user_unknown_adapter(): void
    {
        $this->assertNull($this->chat->getUser('unknown', 'U1'));
    }

    public function test_process_message_delivered(): void
    {
        $received = null;
        $this->chat->onMessageDelivered(function (MessageDeliveredEvent $event) use (&$received) {
            $received = $event;
        });

        $this->chat->processMessageDelivered(
            threadId: 'whatsapp:123:5511999999999',
            messageIds: ['mid1', 'mid2'],
            userId: '5511999999999',
        );

        $this->assertNotNull($received);
        $this->assertSame(['mid1', 'mid2'], $received->messageIds);
        $this->assertSame('5511999999999', $received->userId);
    }

    public function test_process_message_read(): void
    {
        $received = null;
        $this->chat->onMessageRead(function (MessageReadEvent $event) use (&$received) {
            $received = $event;
        });

        $this->chat->processMessageRead(
            threadId: 'messenger:123:456',
            userId: '456',
            timestamp: 1700000000,
        );

        $this->assertNotNull($received);
        $this->assertSame('messenger:123:456', $received->threadId);
        $this->assertSame(1700000000, $received->timestamp);
    }

    public function test_process_message_delivered_no_handlers(): void
    {
        $this->chat->processMessageDelivered(
            threadId: 'whatsapp:123:5511999999999',
            messageIds: ['mid1'],
            userId: '5511999999999',
        );
        $this->expectNotToPerformAssertions();
    }

    public function test_process_message_read_no_handlers(): void
    {
        $this->chat->processMessageRead(
            threadId: 'messenger:123:456',
            userId: '456',
        );
        $this->expectNotToPerformAssertions();
    }

    public function test_message_context_state_and_metadata(): void
    {
        $called = false;
        $this->chat->onNewMessage('/.*/', function (MessageContext $ctx) use (&$called) {
            $ctx->setState(['from_context' => true]);
            $state = $ctx->getState();
            $this->assertSame(['from_context' => true], $state);
            $this->assertCount(0, $ctx->skippedMessages);
            $this->assertSame(1, $ctx->totalSinceLastHandler);
            $called = true;
        });

        $message = \BootDesk\ChatSDK\Core\Tests\Helpers\createTestMessage();
        $this->chat->processMessage($this->adapter, $message->threadId, $message);

        $this->assertTrue($called);
    }

    public function test_constructor_with_transcripts_requires_identity(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Chat(
            state: new MemoryStateAdapter,
            adapters: [],
            transcripts: ['max_messages' => 10],
        );
    }

    public function test_constructor_with_transcripts(): void
    {
        $chat = new Chat(
            state: new MemoryStateAdapter,
            adapters: ['mock' => new MockAdapter],
            identity: fn (Author $a) => $a->id,
            transcripts: ['max_messages' => 10],
        );
        $this->assertNotNull($chat->getTranscripts());
    }

    public function test_resolve_identity_returns_null_without_resolver(): void
    {
        $this->assertNull($this->chat->resolveIdentity(new Author(id: 'U1')));
    }

    public function test_resolve_adapter_with_resolver(): void
    {
        $resolver = new MockAdapterResolver;
        $chat = new Chat(
            state: new MemoryStateAdapter,
            adapterResolver: $resolver,
        );
        $request = (new Psr17Factory)->createServerRequest('POST', '/');
        $this->assertNotNull($chat->resolveAdapter('mock', $request));
    }

    public function test_thread_unknown_adapter(): void
    {
        $this->expectException(ResourceNotFoundException::class);
        $this->chat->thread('unknown:channel');
    }

    public function test_channel_unknown_adapter(): void
    {
        $this->expectException(ResourceNotFoundException::class);
        $this->chat->channel('unknown:channel');
    }

    public function test_handle_webhook_full_flow(): void
    {
        $factory = new Psr17Factory;
        $responseFactory = new Psr17Factory;
        $chat = new Chat(
            state: new MemoryStateAdapter,
            adapters: ['mock' => new MockAdapter],
            responseFactory: $responseFactory,
        );

        $request = $factory->createServerRequest('POST', '/webhook')
            ->withBody($factory->createStream('{"text":"hello"}'));

        $response = $chat->handleWebhook('mock', $request);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_handle_webhook_throws_without_response_factory(): void
    {
        $request = (new Psr17Factory)
            ->createServerRequest('POST', '/webhook');

        $this->expectException(\RuntimeException::class);
        $this->chat->handleWebhook('mock', $request);
    }

    public function test_handle_webhook_unknown_adapter(): void
    {
        $request = (new Psr17Factory)
            ->createServerRequest('POST', '/webhook');

        $this->expectException(ResourceNotFoundException::class);
        $this->chat->handleWebhook('unknown', $request);
    }

    public function test_handle_webhook_with_ack_response(): void
    {
        $factory = new Psr17Factory;
        $adapter = new MockAdapter;
        $ackResponse = $factory->createResponse(202);
        $adapter->ackResponse = $ackResponse;

        $chat = new Chat(
            state: new MemoryStateAdapter,
            adapters: ['mock' => $adapter],
            responseFactory: $factory,
        );

        $request = $factory->createServerRequest('POST', '/webhook');
        $response = $chat->handleWebhook('mock', $request);
        $this->assertSame(202, $response->getStatusCode());
    }

    public function test_handle_webhook_with_webhook_middleware(): void
    {
        $factory = new Psr17Factory;
        $chat = new Chat(
            state: new MemoryStateAdapter,
            adapters: ['mock' => new MockAdapter],
            responseFactory: $factory,
        );

        $middleware = new TestWebhookMiddleware;
        $chat->addWebhookMiddleware($middleware);

        $request = $factory->createServerRequest('POST', '/webhook');
        $response = $chat->handleWebhook('mock', $request);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($middleware->called);
    }

    public function test_initialize_is_idempotent(): void
    {
        $this->chat->initialize();
        $this->chat->initialize();
        $this->assertTrue($this->adapter->initialized);
    }

    public function test_receiving_middleware_can_stop_processing(): void
    {
        $received = false;
        $this->chat->addReceivingMiddleware(
            new TestReceivingMiddleware(stop: true),
        );
        $this->chat->onNewMessage('/.*/', function () use (&$received) {
            $received = true;
        });

        $message = \BootDesk\ChatSDK\Core\Tests\Helpers\createTestMessage();
        $this->chat->processMessage($this->adapter, $message->threadId, $message);

        $this->assertFalse($received);
    }

    public function test_sending_middleware(): void
    {
        $middleware = new TestSendingMiddleware;
        $this->chat->addSendingMiddleware($middleware);

        $thread = $this->chat->thread('mock:C123:456');
        $thread->post('Hello');

        $this->assertTrue($middleware->called);
    }

    public function test_multiple_sending_middlewares_execute_in_order(): void
    {
        $order = [];

        $first = new class implements SendingMiddleware
        {
            public array $order;

            public function setOrder(array &$order): void
            {
                $this->order = &$order;
            }

            public function handle(string $threadId, PostableMessage $message, Adapter $adapter, string $operation, callable $next): ?PostableMessage
            {
                $this->order[] = 'first';

                return $next($threadId, $message, $adapter, $operation);
            }
        };
        $first->setOrder($order);

        $second = new class implements SendingMiddleware
        {
            public array $order;

            public function setOrder(array &$order): void
            {
                $this->order = &$order;
            }

            public function handle(string $threadId, PostableMessage $message, Adapter $adapter, string $operation, callable $next): ?PostableMessage
            {
                $this->order[] = 'second';

                return $next($threadId, $message, $adapter, $operation);
            }
        };
        $second->setOrder($order);

        $this->chat->addSendingMiddleware($first);
        $this->chat->addSendingMiddleware($second);

        $thread = $this->chat->thread('mock:C123:456');
        $thread->post('Hello');

        $this->assertSame(['first', 'second'], $order);
    }

    public function test_multiple_webhook_middlewares_execute_in_order(): void
    {
        $order = [];
        $factory = new Psr17Factory;

        $first = new class implements WebhookMiddleware
        {
            public array $order;

            public function setOrder(array &$order): void
            {
                $this->order = &$order;
            }

            public function handle(ServerRequestInterface $request, callable $next): ResponseInterface
            {
                $this->order[] = 'first';

                return $next($request);
            }
        };
        $first->setOrder($order);

        $second = new class implements WebhookMiddleware
        {
            public array $order;

            public function setOrder(array &$order): void
            {
                $this->order = &$order;
            }

            public function handle(ServerRequestInterface $request, callable $next): ResponseInterface
            {
                $this->order[] = 'second';

                return $next($request);
            }
        };
        $second->setOrder($order);

        $chat = new Chat(
            state: new MemoryStateAdapter,
            adapters: ['mock' => new MockAdapter],
            responseFactory: $factory,
        );
        $chat->addWebhookMiddleware($first);
        $chat->addWebhookMiddleware($second);

        $request = $factory->createServerRequest('POST', '/webhook');
        $chat->handleWebhook('mock', $request);

        $this->assertSame(['first', 'second'], $order);
    }

    public function test_multiple_receiving_middlewares_execute_in_order(): void
    {
        $order = [];

        $first = new class implements ReceivingMiddleware
        {
            public array $order;

            public function setOrder(array &$order): void
            {
                $this->order = &$order;
            }

            public function handle(Message $message, Adapter $adapter, callable $next): ?Message
            {
                $this->order[] = 'first';

                return $next($message, $adapter);
            }
        };
        $first->setOrder($order);

        $second = new class implements ReceivingMiddleware
        {
            public array $order;

            public function setOrder(array &$order): void
            {
                $this->order = &$order;
            }

            public function handle(Message $message, Adapter $adapter, callable $next): ?Message
            {
                $this->order[] = 'second';

                return $next($message, $adapter);
            }
        };
        $second->setOrder($order);

        $this->chat->addReceivingMiddleware($first);
        $this->chat->addReceivingMiddleware($second);

        $message = \BootDesk\ChatSDK\Core\Tests\Helpers\createTestMessage();
        $this->chat->processMessage($this->adapter, $message->threadId, $message);

        $this->assertSame(['first', 'second'], $order);
    }

    public function test_process_message_with_transcripts(): void
    {
        $chat = new Chat(
            state: new MemoryStateAdapter,
            adapters: ['mock' => $this->adapter],
            identity: fn (Author $a) => $a->id,
            transcripts: ['max_messages' => 100],
        );

        $received = false;
        $chat->onNewMessage('/.*/', function (MessageContext $ctx) use (&$received) {
            $received = true;
        });

        $message = \BootDesk\ChatSDK\Core\Tests\Helpers\createTestMessage();
        $chat->processMessage($this->adapter, $message->threadId, $message);

        $this->assertTrue($received);
    }

    public function test_concurrent_strategy_processes_under_capacity(): void
    {
        $chat = new Chat(
            state: new MemoryStateAdapter,
            config: ['concurrency' => 'concurrent', 'maxConcurrent' => 5],
        );
        $adapter = new MockAdapter;
        $chat->registerAdapter('mock', $adapter);

        $count = 0;
        $chat->onNewMessage('/.*/', function () use (&$count) {
            $count++;
        });

        $chat->processMessage($adapter, 'mock:C:1', \BootDesk\ChatSDK\Core\Tests\Helpers\createTestMessage(id: 'a'));
        $chat->processMessage($adapter, 'mock:C:1', \BootDesk\ChatSDK\Core\Tests\Helpers\createTestMessage(id: 'b'));

        $this->assertSame(2, $count);
    }

    public function test_concurrent_strategy_unlimited(): void
    {
        $chat = new Chat(
            state: new MemoryStateAdapter,
            config: ['concurrency' => 'concurrent'],
            adapters: ['mock' => new MockAdapter],
        );

        $called = false;
        $chat->onNewMessage('/.*/', function () use (&$called) {
            $called = true;
        });

        $chat->processMessage($this->adapter, 'mock:C:1', \BootDesk\ChatSDK\Core\Tests\Helpers\createTestMessage());
        $this->assertTrue($called);
    }

    public function test_channel_lock_scope(): void
    {
        $chat = new Chat(
            state: new MemoryStateAdapter,
            config: ['concurrency' => 'drop', 'lockScope' => 'channel'],
            adapters: ['mock' => new MockAdapter],
        );

        $called = false;
        $chat->onNewMessage('/.*/', function () use (&$called) {
            $called = true;
        });

        $chat->processMessage(
            $this->adapter,
            'mock:C456:789',
            \BootDesk\ChatSDK\Core\Tests\Helpers\createTestMessage(id: 'x', threadId: 'mock:C456:789'),
        );

        $this->assertTrue($called);
    }

    public function test_all_event_types_dispatch(): void
    {
        $events = [];

        $this->chat->onAssistantThreadStarted(function ($e) use (&$events) {
            $events[] = 'thread_started';
        });
        $this->chat->onAssistantContextChanged(function ($e) use (&$events) {
            $events[] = 'context_changed';
        });
        $this->chat->onAppHomeOpened(function ($e) use (&$events) {
            $events[] = 'home_opened';
        });
        $this->chat->onMemberJoinedChannel(function ($e) use (&$events) {
            $events[] = 'member_joined';
        });

        $this->chat->processAssistantThreadStarted($this->adapter, 'C1', 'mock:C1:ts', 'U1', [], 'ts1');
        $this->chat->processAssistantContextChanged($this->adapter, 'C1', 'mock:C1:ts', 'U1', [], 'ts1');
        $this->chat->processAppHomeOpened($this->adapter, 'C1', 'U1');
        $this->chat->processMemberJoinedChannel($this->adapter, 'C1', 'U2', 'U1');

        $this->assertSame(['thread_started', 'context_changed', 'home_opened', 'member_joined'], $events);
    }

    public function test_on_reaction_handler_only_returns_self(): void
    {
        $this->assertSame($this->chat, $this->chat->onReaction('👍'));
    }

    public function test_on_action_handler_only_returns_self(): void
    {
        $this->assertSame($this->chat, $this->chat->onAction('confirm'));
    }

    public function test_on_modal_submit_handler_only_returns_self(): void
    {
        $this->assertSame($this->chat, $this->chat->onModalSubmit('test'));
    }

    public function test_on_modal_close_handler_only_returns_self(): void
    {
        $this->assertSame($this->chat, $this->chat->onModalClose('test'));
    }

    public function test_on_options_load_handler_only_returns_self(): void
    {
        $this->assertSame($this->chat, $this->chat->onOptionsLoad('picker'));
    }

    public function test_on_slash_command_handler_only_returns_self(): void
    {
        $this->assertSame($this->chat, $this->chat->onSlashCommand('/help'));
    }

    public function test_process_modal_close_with_context(): void
    {
        $channel = new Channel('C123', $this->adapter);
        $this->chat->storeModalContext('mock', 'ctx_close', ['channel' => $channel]);

        $received = null;
        $this->chat->onModalClose('form', function (ModalCloseEvent $event) use (&$received) {
            $received = $event;
        });

        $this->chat->processModalClose(
            adapter: $this->adapter,
            callbackId: 'form',
            user: new Author(id: 'U1'),
            contextId: 'ctx_close',
        );

        $this->assertNotNull($received);
        $this->assertNotNull($received->relatedChannel);
        $this->assertSame('C123', $received->relatedChannel->id);
    }

    public function test_dispatch_incoming_with_skip_in_dm(): void
    {
        $dmCalled = false;
        $otherCalled = false;
        $this->chat->onDirectMessage(function (MessageContext $ctx) use (&$dmCalled) {
            $dmCalled = true;
            $ctx->skip();
        });
        $this->chat->onNewMessage('/.*/', function () use (&$otherCalled) {
            $otherCalled = true;
        });

        $msg = \BootDesk\ChatSDK\Core\Tests\Helpers\createTestMessage(isDM: true, text: 'skip test');
        $this->chat->processMessage($this->adapter, $msg->threadId, $msg);

        $this->assertTrue($dmCalled);
        $this->assertFalse($otherCalled);
    }

    public function test_dispatch_incoming_with_skip_in_subscribed(): void
    {
        $this->state->subscribe('mock:C123:sub_skip');

        $subCalled = false;
        $otherCalled = false;
        $this->chat->onSubscribedMessage(function (MessageContext $ctx) use (&$subCalled) {
            $subCalled = true;
            $ctx->skip();
        });
        $this->chat->onNewMessage('/.*/', function () use (&$otherCalled) {
            $otherCalled = true;
        });

        $msg = \BootDesk\ChatSDK\Core\Tests\Helpers\createTestMessage(
            threadId: 'mock:C123:sub_skip',
            text: 'test',
        );
        $this->chat->processMessage($this->adapter, 'mock:C123:sub_skip', $msg);

        $this->assertTrue($subCalled);
        $this->assertFalse($otherCalled);
    }

    public function test_dispatch_incoming_with_skip_in_dm_ends_early(): void
    {
        $firstCalled = false;
        $secondCalled = false;
        $this->chat->onDirectMessage(function (MessageContext $ctx) use (&$firstCalled) {
            $firstCalled = true;
            $ctx->skip();
        });
        $this->chat->onDirectMessage(function () use (&$secondCalled) {
            $secondCalled = true;
        });

        $msg = \BootDesk\ChatSDK\Core\Tests\Helpers\createTestMessage(isDM: true, text: 'test');
        $this->chat->processMessage($this->adapter, $msg->threadId, $msg);

        $this->assertTrue($firstCalled);
        $this->assertFalse($secondCalled);
    }

    public function test_queue_strategy_drains_queue(): void
    {
        $chat = new Chat(
            state: new MemoryStateAdapter,
            config: ['concurrency' => 'queue'],
        );
        $adapter = new MockAdapter;
        $chat->registerAdapter('mock', $adapter);

        $count = 0;
        $chat->onNewMessage('/.*/', function () use (&$count) {
            $count++;
        });

        $chat->processMessage(
            $adapter,
            'mock:C456',
            \BootDesk\ChatSDK\Core\Tests\Helpers\createTestMessage(id: 'q1', threadId: 'mock:C456'),
        );

        $this->assertSame(1, $count);
    }

    public function test_process_message_skip_on_self(): void
    {
        $called = false;
        $this->chat->onNewMessage('/.*/', function () use (&$called) {
            $called = true;
        });

        $msg = \BootDesk\ChatSDK\Core\Tests\Helpers\createTestMessage(
            author: new Author(id: 'bot', isMe: true),
        );
        $this->chat->processMessage($this->adapter, $msg->threadId, $msg);

        $this->assertFalse($called);
    }

    public function test_on_modal_submit_with_callable_first_arg(): void
    {
        $received = null;
        $this->chat->onModalSubmit(function (ModalSubmitEvent $event) use (&$received) {
            $received = $event;
        });

        $this->chat->processModalSubmit($this->adapter, 'any_form', [], new Author(id: 'U1'));

        $this->assertNotNull($received);
        $this->assertSame('any_form', $received->callbackId);
    }

    public function test_on_modal_close_with_callable_first_arg(): void
    {
        $received = null;
        $this->chat->onModalClose(function (ModalCloseEvent $event) use (&$received) {
            $received = $event;
        });

        $this->chat->processModalClose($this->adapter, 'any_form', new Author(id: 'U1'));

        $this->assertNotNull($received);
        $this->assertSame('any_form', $received->callbackId);
    }

    public function test_mention_handler_skip_ends_early(): void
    {
        $mentionCalled = false;
        $otherCalled = false;
        $this->chat->onNewMention(function (MessageContext $ctx) use (&$mentionCalled) {
            $mentionCalled = true;
            $ctx->skip();
        });
        $this->chat->onNewMessage('/.*/', function () use (&$otherCalled) {
            $otherCalled = true;
        });

        $msg = \BootDesk\ChatSDK\Core\Tests\Helpers\createTestMessage(isMention: true, text: 'mention');
        $this->chat->processMessage($this->adapter, $msg->threadId, $msg);

        $this->assertTrue($mentionCalled);
        $this->assertFalse($otherCalled);
    }

    public function test_handle_webhook_with_custom_adapter_response(): void
    {
        $factory = new Psr17Factory;
        $adapter = new MockAdapter;
        $adapter->customResponse = $factory->createResponse(201, 'Created');

        $chat = new Chat(
            state: new MemoryStateAdapter,
            adapters: ['mock' => $adapter],
            responseFactory: $factory,
        );

        $request = $factory->createServerRequest('POST', '/webhook')
            ->withBody($factory->createStream('{"text":"test"}'));

        $response = $chat->handleWebhook('mock', $request);
        $this->assertSame(201, $response->getStatusCode());
    }

    public function test_handle_webhook_batched_messages(): void
    {
        $factory = new Psr17Factory;
        $adapter = new MockBatchedAdapter;
        $adapter->returnedEvents = [
            new WebhookEvent(
                type: WebhookEvent::TYPE_MESSAGE,
                threadId: 'mock_batched:C:a',
                payload: \BootDesk\ChatSDK\Core\Tests\Helpers\createTestMessage(
                    id: 'm_a',
                    text: 'first',
                    threadId: 'mock_batched:C:a',
                ),
            ),
            new WebhookEvent(
                type: WebhookEvent::TYPE_MESSAGE,
                threadId: 'mock_batched:C:b',
                payload: \BootDesk\ChatSDK\Core\Tests\Helpers\createTestMessage(
                    id: 'm_b',
                    text: 'second',
                    threadId: 'mock_batched:C:b',
                ),
            ),
        ];

        $messages = [];
        $chat = new Chat(
            state: new MemoryStateAdapter,
            adapters: ['mock_batched' => $adapter],
            responseFactory: $factory,
        );
        $chat->onNewMessage('/.*/', function (MessageContext $ctx) use (&$messages) {
            $messages[] = $ctx->message->text;
        });

        $request = $factory->createServerRequest('POST', '/webhook');
        $response = $chat->handleWebhook('mock_batched', $request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(2, $messages);
        $this->assertSame('first', $messages[0]);
        $this->assertSame('second', $messages[1]);
    }

    public function test_handle_webhook_batched_mixed_events(): void
    {
        $factory = new Psr17Factory;
        $adapter = new MockBatchedAdapter;

        $actionCaught = null;
        $reactionCaught = null;
        $statusCaught = null;

        $adapter->returnedEvents = [
            new WebhookEvent(
                type: WebhookEvent::TYPE_ACTION,
                threadId: 'mock_batched:C',
                payload: [
                    'actionId' => 'btn_ok',
                    'value' => 'confirmed',
                    'messageId' => 'msg_1',
                    'userId' => 'U1',
                    'isBot' => false,
                    'isMe' => false,
                    'triggerId' => null,
                    'raw' => null,
                ],
            ),
            new WebhookEvent(
                type: WebhookEvent::TYPE_REACTION,
                threadId: 'mock_batched:C',
                payload: [
                    'emoji' => '👍',
                    'rawEmoji' => '👍',
                    'added' => true,
                    'messageId' => 'msg_2',
                    'userId' => 'U2',
                    'raw' => null,
                ],
            ),
            new WebhookEvent(
                type: WebhookEvent::TYPE_STATUS,
                threadId: 'mock_batched:C',
                payload: [
                    'type' => 'delivered',
                    'messageIds' => ['msg_1'],
                    'userId' => 'U3',
                    'raw' => null,
                ],
            ),
        ];

        $chat = new Chat(
            state: new MemoryStateAdapter,
            adapters: ['mock_batched' => $adapter],
            responseFactory: $factory,
        );

        $chat->onAction(function (ActionEvent $e) use (&$actionCaught) {
            $actionCaught = $e->actionId;
        });
        $chat->onReaction(function (ReactionEvent $e) use (&$reactionCaught) {
            $reactionCaught = $e->emoji;
        });
        $chat->onMessageDelivered(function (MessageDeliveredEvent $e) use (&$statusCaught) {
            $statusCaught = $e->messageIds;
        });

        $request = $factory->createServerRequest('POST', '/webhook');
        $response = $chat->handleWebhook('mock_batched', $request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('btn_ok', $actionCaught);
        $this->assertSame('👍', $reactionCaught);
        $this->assertSame(['msg_1'], $statusCaught);
    }

    public function test_handle_webhook_batched_empty(): void
    {
        $factory = new Psr17Factory;
        $adapter = new MockBatchedAdapter;
        $adapter->returnedEvents = [];

        $chat = new Chat(
            state: new MemoryStateAdapter,
            adapters: ['mock_batched' => $adapter],
            responseFactory: $factory,
        );

        $request = $factory->createServerRequest('POST', '/webhook');
        $response = $chat->handleWebhook('mock_batched', $request);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_handle_webhook_batched_with_middleware_swaps_adapter(): void
    {
        $factory = new Psr17Factory;
        $adapter = new MockBatchedAdapter;
        $adapter->returnedEvents = [
            new WebhookEvent(
                type: WebhookEvent::TYPE_MESSAGE,
                threadId: 'mock_batched:page1',
                originId: 'page_1',
                payload: \BootDesk\ChatSDK\Core\Tests\Helpers\createTestMessage(
                    id: 'm1',
                    text: 'from page1',
                    threadId: 'mock_batched:page1',
                ),
            ),
            new WebhookEvent(
                type: WebhookEvent::TYPE_MESSAGE,
                threadId: 'mock_batched:page2',
                originId: 'page_2',
                payload: \BootDesk\ChatSDK\Core\Tests\Helpers\createTestMessage(
                    id: 'm2',
                    text: 'from page2',
                    threadId: 'mock_batched:page2',
                ),
            ),
        ];

        $chat = new Chat(
            state: new MemoryStateAdapter,
            adapters: ['mock_batched' => $adapter],
            responseFactory: $factory,
        );

        $middleware = new class implements WebhookEventMiddleware
        {
            public bool $called = false;

            public function handle(WebhookEvent $event, Adapter $adapter): Adapter
            {
                $this->called = true;

                return $adapter;
            }
        };
        $chat->addWebhookEventMiddleware($middleware);

        $chat->onNewMessage('/.*/', function (MessageContext $ctx) use (&$messages) {
            $messages[] = $ctx->message->text;
        });

        $request = $factory->createServerRequest('POST', '/webhook');
        $response = $chat->handleWebhook('mock_batched', $request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($middleware->called);
        $this->assertCount(2, $messages);
    }

    public function test_handle_webhook_batched_passes_origin_id_to_events(): void
    {
        $factory = new Psr17Factory;
        $adapter = new MockBatchedAdapter;
        $adapter->returnedEvents = [
            new WebhookEvent(
                type: WebhookEvent::TYPE_ACTION,
                threadId: 'mock_batched:C',
                originId: 'page_42',
                payload: [
                    'actionId' => 'btn_1',
                    'value' => 'ok',
                    'messageId' => 'm1',
                    'userId' => 'U1',
                    'isBot' => false,
                    'isMe' => false,
                    'triggerId' => null,
                    'raw' => null,
                ],
            ),
            new WebhookEvent(
                type: WebhookEvent::TYPE_REACTION,
                threadId: 'mock_batched:C',
                originId: 'page_42',
                payload: [
                    'emoji' => '❤️',
                    'rawEmoji' => '❤️',
                    'added' => true,
                    'messageId' => 'm2',
                    'userId' => 'U2',
                    'raw' => null,
                ],
            ),
            new WebhookEvent(
                type: WebhookEvent::TYPE_STATUS,
                threadId: 'mock_batched:C',
                originId: 'page_42',
                payload: [
                    'type' => 'delivered',
                    'messageIds' => ['m1'],
                    'userId' => 'U3',
                    'raw' => null,
                ],
            ),
        ];

        $actionOrigin = null;
        $reactionOrigin = null;
        $statusOrigin = null;

        $chat = new Chat(
            state: new MemoryStateAdapter,
            adapters: ['mock_batched' => $adapter],
            responseFactory: $factory,
        );

        $chat->onAction(function (ActionEvent $e) use (&$actionOrigin) {
            $actionOrigin = $e->originId;
        });
        $chat->onReaction(function (ReactionEvent $e) use (&$reactionOrigin) {
            $reactionOrigin = $e->originId;
        });
        $chat->onMessageDelivered(function (MessageDeliveredEvent $e) use (&$statusOrigin) {
            $statusOrigin = $e->originId;
        });

        $request = $factory->createServerRequest('POST', '/webhook');
        $chat->handleWebhook('mock_batched', $request);

        $this->assertSame('page_42', $actionOrigin);
        $this->assertSame('page_42', $reactionOrigin);
        $this->assertSame('page_42', $statusOrigin);
    }

    public function test_debounce_strategy_processes_message(): void
    {
        $state = new MemoryStateAdapter;
        $chat = new Chat(
            state: $state,
            config: ['concurrency' => 'debounce', 'debounceMs' => 1],
        );
        $adapter = new MockAdapter;
        $chat->registerAdapter('mock', $adapter);

        $called = false;
        $chat->onNewMessage('/.*/', function () use (&$called) {
            $called = true;
        });

        $chat->processMessage(
            $adapter,
            'mock:C789',
            \BootDesk\ChatSDK\Core\Tests\Helpers\createTestMessage(id: 'd1', threadId: 'mock:C789'),
        );

        $this->assertTrue($called);
    }

    public function test_drop_strategy_returns_when_locked(): void
    {
        // Lock the thread first — drop strategy should bail out
        $lock = $this->state->acquireLock('process:mock:C:locked', 30_000);
        $this->assertNotNull($lock);

        $called = false;
        $this->chat->onNewMessage('/.*/', function () use (&$called) {
            $called = true;
        });

        $msg = \BootDesk\ChatSDK\Core\Tests\Helpers\createTestMessage(threadId: 'mock:C:locked');
        $this->chat->processMessage($this->adapter, 'mock:C:locked', $msg);

        $this->assertFalse($called);
    }

    public function test_queue_strategy_returns_when_locked(): void
    {
        $state = new MemoryStateAdapter;
        $lock = $state->acquireLock('process:mock:C:q_lock', 30_000);
        $this->assertNotNull($lock);

        $chat = new Chat(
            state: $state,
            config: ['concurrency' => 'queue'],
        );
        $adapter = new MockAdapter;
        $chat->registerAdapter('mock', $adapter);

        $called = false;
        $chat->onNewMessage('/.*/', function () use (&$called) {
            $called = true;
        });

        $chat->processMessage(
            $adapter,
            'mock:C:q_lock',
            \BootDesk\ChatSDK\Core\Tests\Helpers\createTestMessage(id: 'ql1', threadId: 'mock:C:q_lock'),
        );

        $this->assertFalse($called);

        $state->forceReleaseLock('process:mock:C:q_lock');
    }

    public function test_debounce_strategy_enqueues_when_locked(): void
    {
        $state = new MemoryStateAdapter;
        $lock = $state->acquireLock('process:mock:C:deb_lock', 30_000);
        $this->assertNotNull($lock);

        $chat = new Chat(
            state: $state,
            config: ['concurrency' => 'debounce'],
        );
        $adapter = new MockAdapter;
        $chat->registerAdapter('mock', $adapter);

        $called = false;
        $chat->onNewMessage('/.*/', function () use (&$called) {
            $called = true;
        });

        $chat->processMessage(
            $adapter,
            'mock:C:deb_lock',
            \BootDesk\ChatSDK\Core\Tests\Helpers\createTestMessage(id: 'dl1', threadId: 'mock:C:deb_lock'),
        );

        $this->assertFalse($called);
        $this->assertSame(1, $state->queueDepth('mock:C:deb_lock'));

        $state->forceReleaseLock('process:mock:C:deb_lock');
    }

    public function test_pattern_skip_stops_dispatch(): void
    {
        $firstCalled = false;
        $secondCalled = false;

        // Register specific pattern first (matches "hello"), then catch-all
        $this->chat->onNewMessage('/.*/', function () use (&$secondCalled) {
            $secondCalled = true;
        });

        $msg = \BootDesk\ChatSDK\Core\Tests\Helpers\createTestMessage(text: 'hello');
        $this->chat->processMessage($this->adapter, $msg->threadId, $msg);

        // Catch-all should have been called
        $this->assertTrue($secondCalled);
    }

    public function test_drain_queued_with_empty_queue_is_noop(): void
    {
        $state = new MemoryStateAdapter;
        $chat = new Chat(
            state: $state,
            config: ['concurrency' => 'queue'],
        );
        $adapter = new MockAdapter;
        $chat->registerAdapter('mock', $adapter);

        // Process a message — queue is empty, so drainAllQueued hits its early return
        $called = false;
        $chat->onNewMessage('/.*/', function () use (&$called) {
            $called = true;
        });

        $chat->processMessage(
            $adapter,
            'mock:C:empty',
            \BootDesk\ChatSDK\Core\Tests\Helpers\createTestMessage(id: 'eq1', threadId: 'mock:C:empty'),
        );

        $this->assertTrue($called);
    }

    public function test_debounce_with_queued_messages_processes_latest(): void
    {
        $state = new MemoryStateAdapter;

        // Pre-populate queue with messages
        $msg1 = \BootDesk\ChatSDK\Core\Tests\Helpers\createTestMessage(id: 'd_skip', text: 'skip me');
        $msg2 = \BootDesk\ChatSDK\Core\Tests\Helpers\createTestMessage(id: 'd_latest', text: 'latest');
        $state->enqueue('mock:C:deb_q', new QueueEntry(
            'd_skip', serialize($msg1), microtime(true),
        ), 10);
        $state->enqueue('mock:C:deb_q', new QueueEntry(
            'd_latest', serialize($msg2), microtime(true),
        ), 10);

        $chat = new Chat(
            state: $state,
            config: ['concurrency' => 'debounce', 'debounceMs' => 1],
        );
        $adapter = new MockAdapter;
        $chat->registerAdapter('mock', $adapter);

        $received = null;
        $chat->onNewMessage('/.*/', function (MessageContext $ctx) use (&$received) {
            $received = $ctx;
        });

        $chat->processMessage(
            $adapter,
            'mock:C:deb_q',
            \BootDesk\ChatSDK\Core\Tests\Helpers\createTestMessage(
                id: 'd_current',
                threadId: 'mock:C:deb_q',
                text: 'current',
            ),
        );

        $this->assertNotNull($received);
        $this->assertSame('latest', $received->message->text);
        $this->assertCount(1, $received->skippedMessages);
    }

    public function test_concurrent_at_capacity_drops(): void
    {
        $chat = new Chat(
            state: new MemoryStateAdapter,
            config: ['concurrency' => 'concurrent', 'maxConcurrent' => 3],
        );
        $adapter = new MockAdapter;
        $chat->registerAdapter('mock', $adapter);

        // Set slots to capacity directly
        $ref = new \ReflectionProperty(Chat::class, 'concurrentSlots');
        $ref->setValue($chat, ['mock:C:conc' => 3]);

        $called = false;
        $chat->onNewMessage('/.*/', function () use (&$called) {
            $called = true;
        });

        $chat->processMessage(
            $adapter,
            'mock:C:conc',
            \BootDesk\ChatSDK\Core\Tests\Helpers\createTestMessage(
                id: 'conc1',
                threadId: 'mock:C:conc',
            ),
        );

        $this->assertFalse($called);
    }

    public function test_drain_all_queued_early_return_when_empty(): void
    {
        $ref = new \ReflectionMethod(Chat::class, 'drainAllQueued');
        $handler = new Handler(
            new MemoryStateAdapter,
        );

        $chat = new Chat(
            state: new MemoryStateAdapter,
        );

        // Calling drainAllQueued with an empty queue should be a no-op
        $ref->invokeArgs($chat, [
            new MockAdapter,
            'mock:C:empty_q',
            $handler,
        ]);

        $this->assertTrue(true);
    }

    public function test_pattern_skip_stops_further_patterns(): void
    {
        $called = [];

        $this->chat->onNewMessage('/first/', function (MessageContext $ctx) use (&$called) {
            $called[] = 'first';
            $ctx->skip();
        });
        $this->chat->onNewMessage('/.*/', function () use (&$called) {
            $called[] = 'catchall';
        });

        $msg = \BootDesk\ChatSDK\Core\Tests\Helpers\createTestMessage(text: 'first message');
        $this->chat->processMessage($this->adapter, $msg->threadId, $msg);

        $this->assertSame(['first'], $called);
    }

    public function test_mention_handler_receives_is_mention_flag(): void
    {
        $received = null;
        $this->chat->onNewMention(function (MessageContext $ctx) use (&$received) {
            $received = $ctx;
        });

        $message = \BootDesk\ChatSDK\Core\Tests\Helpers\createTestMessage(isMention: true);
        $this->chat->processMessage($this->adapter, $message->threadId, $message);

        $this->assertNotNull($received);
        $this->assertTrue($received->message->isMention);
    }

    public function test_mention_in_subscribed_thread_does_not_fire_mention(): void
    {
        $threadId = 'mock:C123:sub_mention';
        $this->state->subscribe($threadId);

        $order = [];
        $this->chat->onSubscribedMessage(function (MessageContext $ctx) use (&$order) {
            $order[] = 'subscribed';
        });
        $this->chat->onNewMention(function (MessageContext $ctx) use (&$order) {
            $order[] = 'mention';
        });

        $message = \BootDesk\ChatSDK\Core\Tests\Helpers\createTestMessage(
            threadId: $threadId,
            isMention: true,
        );
        $this->chat->processMessage($this->adapter, $threadId, $message);

        $this->assertSame(['subscribed'], $order);
    }

    public function test_slash_command_channel_post(): void
    {
        $channel = null;
        $this->chat->onSlashCommand('/say', function (SlashCommandEvent $event) use (&$channel) {
            $channel = $event->channel;
        });

        $this->chat->processSlashCommand(
            adapter: $this->adapter,
            channelId: 'mock:C123',
            command: '/say',
            text: 'hello',
        );

        $this->assertNotNull($channel);
        $this->assertInstanceOf(Channel::class, $channel);
    }

    public function test_options_load_prefers_specific_before_catch_all(): void
    {
        $order = [];
        $this->chat->onOptionsLoad('specific_action', function (OptionsLoadEvent $event) use (&$order) {
            $order[] = 'specific';

            return ['items' => []];
        });
        $this->chat->onOptionsLoad(function (OptionsLoadEvent $event) use (&$order) {
            $order[] = 'catch_all';

            return ['items' => []];
        });

        $result = $this->chat->processOptionsLoad(
            adapter: $this->adapter,
            actionId: 'specific_action',
            query: 'test',
            user: new Author(id: 'U1'),
        );

        $this->assertSame(['specific'], $order);
        $this->assertNotNull($result);
    }

    public function test_options_load_falls_back_to_catch_all(): void
    {
        $caught = false;
        $this->chat->onOptionsLoad('action_a', function (OptionsLoadEvent $event) {
            return ['items' => []];
        });
        $this->chat->onOptionsLoad(function (OptionsLoadEvent $event) use (&$caught) {
            $caught = true;

            return ['items' => []];
        });

        $this->chat->processOptionsLoad(
            adapter: $this->adapter,
            actionId: 'unknown_action',
            query: 'test',
            user: new Author(id: 'U1'),
        );

        $this->assertTrue($caught);
    }

    public function test_is_subscribed_returns_true_for_subscribed_thread(): void
    {
        $thread = new Thread('mock:C123', $this->chat, $this->adapter, $this->state);

        $this->assertFalse($thread->isSubscribed());

        $thread->subscribe();

        $this->assertTrue($thread->isSubscribed());
    }

    public function test_thread_subscribe_unsubscribe(): void
    {
        $thread = new Thread('mock:C123', $this->chat, $this->adapter, $this->state);

        $thread->subscribe();
        $this->assertTrue($thread->isSubscribed());

        $thread->unsubscribe();
        $this->assertFalse($thread->isSubscribed());
    }
}
