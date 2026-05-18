<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Tests;

use BootDesk\ChatSDK\Core\ActionEvent;
use BootDesk\ChatSDK\Core\Author;
use BootDesk\ChatSDK\Core\Channel;
use BootDesk\ChatSDK\Core\ChannelInfo;
use BootDesk\ChatSDK\Core\Chat;
use BootDesk\ChatSDK\Core\Contracts\Adapter;
use BootDesk\ChatSDK\Core\Contracts\FormatConverter;
use BootDesk\ChatSDK\Core\Contracts\SupportsModals;
use BootDesk\ChatSDK\Core\FetchOptions;
use BootDesk\ChatSDK\Core\FetchResult;
use BootDesk\ChatSDK\Core\Message;
use BootDesk\ChatSDK\Core\Modals\Modal;
use BootDesk\ChatSDK\Core\Modals\TextInput;
use BootDesk\ChatSDK\Core\PostableMessage;
use BootDesk\ChatSDK\Core\SentMessage;
use BootDesk\ChatSDK\Core\SlashCommandEvent;
use BootDesk\ChatSDK\Core\Tests\Helpers\MemoryStateAdapter;
use BootDesk\ChatSDK\Core\Thread;
use BootDesk\ChatSDK\Core\ThreadInfo;
use BootDesk\ChatSDK\Core\UserInfo;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class OpensModalsTest extends TestCase
{
    public function test_open_modal_requires_trigger_id(): void
    {
        $chat = new Chat(new MemoryStateAdapter);
        $adapter = $this->createMock(Adapter::class);
        $thread = new Thread('mock:C123', $chat, $adapter, new MemoryStateAdapter);

        $event = new ActionEvent(
            actionId: 'test',
            value: null,
            messageId: 'm1',
            triggerId: null,
            thread: $thread,
            user: new Author(id: 'U1'),
        );

        $this->assertNull($event->openModal(new Modal(callbackId: 't', title: 'T')));
    }

    public function test_open_modal_requires_supports_modals(): void
    {
        $chat = new Chat(new MemoryStateAdapter);
        $adapter = $this->createMock(Adapter::class);
        $thread = new Thread('mock:C123', $chat, $adapter, new MemoryStateAdapter);

        $event = new ActionEvent(
            actionId: 'test',
            value: null,
            messageId: 'm1',
            triggerId: 'trg123',
            thread: $thread,
            user: new Author(id: 'U1'),
        );

        $this->assertNull($event->openModal(new Modal(callbackId: 't', title: 'T')));
    }

    public function test_open_modal_calls_adapter(): void
    {
        $chat = new Chat(new MemoryStateAdapter);
        $adapter = new class implements Adapter, SupportsModals
        {
            public ?string $lastTriggerId = null;

            public ?Modal $lastModal = null;

            public ?string $lastContextId = null;

            public function openModal(string $triggerId, Modal $modal, ?string $contextId = null): ?array
            {
                $this->lastTriggerId = $triggerId;
                $this->lastModal = $modal;
                $this->lastContextId = $contextId;

                return ['viewId' => 'V123'];
            }

            public function getName(): string
            {
                return 'mock';
            }

            public function getBotUserId(): ?string
            {
                return null;
            }

            public function verifyWebhook(ServerRequestInterface $request): ?ResponseInterface
            {
                return null;
            }

            public function parseWebhook(ServerRequestInterface $request): Message
            {
                return new Message('', '', new Author(''), '');
            }

            public function encodeThreadId(mixed $d): string
            {
                return 'mock:C123';
            }

            public function decodeThreadId(string $id): mixed
            {
                return ['channel' => 'C123', 'thread_ts' => ''];
            }

            public function channelIdFromThreadId(string $id): string
            {
                return 'C123';
            }

            public function postMessage(string $t, PostableMessage $m): SentMessage
            {
                return new SentMessage('', $t);
            }

            public function editMessage(string $t, string $i, PostableMessage $m): SentMessage
            {
                return new SentMessage($i, $t);
            }

            public function deleteMessage(string $t, string $i): void {}

            public function addReaction(string $t, string $i, string $e): void {}

            public function removeReaction(string $t, string $i, string $e): void {}

            public function startTyping(string $t): void {}

            public function fetchMessages(string $t, ?FetchOptions $o = null): FetchResult
            {
                return new FetchResult([]);
            }

            public function fetchThread(string $t): ThreadInfo
            {
                return new ThreadInfo($t, '');
            }

            public function fetchChannelInfo(string $c): ?ChannelInfo
            {
                return null;
            }

            public function getUser(string $u): ?UserInfo
            {
                return null;
            }

            public function openDM(string $u): ?string
            {
                return null;
            }

            public function getFormatConverter(): ?FormatConverter
            {
                return null;
            }

            public function initialize(Chat $chat): void {}

            public function disconnect(): void {}

            public function stream(string $t, iterable $s, array $o = []): ?SentMessage
            {
                return null;
            }

            public function createResponse(): ?ResponseInterface
            {
                return null;
            }
        };

        $thread = new Thread('mock:C123', $chat, $adapter, new MemoryStateAdapter);

        $event = new ActionEvent(
            actionId: 'test',
            value: null,
            messageId: 'm1',
            triggerId: 'trg123',
            thread: $thread,
            user: new Author(id: 'U1'),
        );

        $modal = new Modal(callbackId: 't', title: 'T', children: [new TextInput(id: 'n', label: 'N')]);
        $result = $event->openModal($modal);

        $this->assertNotNull($result);
        $this->assertSame('V123', $result['viewId']);
        $this->assertSame('trg123', $adapter->lastTriggerId);
        $this->assertSame($modal, $adapter->lastModal);
        $this->assertNotNull($adapter->lastContextId);
    }

    public function test_slash_command_open_modal(): void
    {
        $chat = new Chat(new MemoryStateAdapter);
        $adapter = $this->createMock(Adapter::class);
        $thread = new Thread('mock:C123', $chat, $adapter, new MemoryStateAdapter);

        $event = new SlashCommandEvent(
            adapter: $adapter,
            channel: new Channel('mock:C123', $adapter),
            thread: $thread,
            message: new Message('', '', new Author(''), ''),
            user: new Author(id: 'U1'),
            command: '/test',
            text: '',
            triggerId: null,
        );

        $this->assertNull($event->openModal(new Modal(callbackId: 't', title: 'T')));
    }
}
