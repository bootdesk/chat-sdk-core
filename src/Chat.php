<?php

namespace BootDesk\ChatSDK\Core;

use BootDesk\ChatSDK\Core\Concurrency\Handler;
use BootDesk\ChatSDK\Core\Concurrency\Strategy;
use BootDesk\ChatSDK\Core\Contracts\Adapter;
use BootDesk\ChatSDK\Core\Contracts\AdapterResolver;
use BootDesk\ChatSDK\Core\Contracts\HandlesActions;
use BootDesk\ChatSDK\Core\Contracts\HandlesModals;
use BootDesk\ChatSDK\Core\Contracts\HandlesOptionsLoad;
use BootDesk\ChatSDK\Core\Contracts\HandlesReactions;
use BootDesk\ChatSDK\Core\Contracts\HandlesSlackEvents;
use BootDesk\ChatSDK\Core\Contracts\HandlesSlashCommands;
use BootDesk\ChatSDK\Core\Contracts\HandlesStatuses;
use BootDesk\ChatSDK\Core\Contracts\ReceivingMiddleware;
use BootDesk\ChatSDK\Core\Contracts\SendingMiddleware;
use BootDesk\ChatSDK\Core\Contracts\StateAdapter;
use BootDesk\ChatSDK\Core\Contracts\WebhookMiddleware;
use BootDesk\ChatSDK\Core\Conversations\ConversationManager;
use BootDesk\ChatSDK\Core\Exceptions\ResourceNotFoundException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Chat
{
    public readonly ConversationManager $conversationManager;

    /** @var array<string, Adapter> */
    private array $adapters = [];

    private ?AdapterResolver $adapterResolver = null;

    /** @var WebhookMiddleware[] */
    private array $webhookMiddleware = [];

    private ?ResponseFactoryInterface $responseFactory = null;

    private bool $initialized = false;

    private bool $stateInitialized = false;

    private ?\Closure $identityResolver = null;

    private ?TranscriptsApi $transcriptsApi = null;

    /** @var ReceivingMiddleware[] */
    private array $receivingMiddleware = [];

    /** @var SendingMiddleware[] */
    private array $sendingMiddleware = [];

    /** @var array<string, callable> */
    private array $messageHandlers = [];

    /** @var callable[] */
    private array $mentionHandlers = [];

    /** @var callable[] */
    private array $dmHandlers = [];

    /** @var callable[] */
    private array $subscribedHandlers = [];

    /**
     * @var array<int, array{filters: string[], handler: callable(ReactionEvent): void}>
     */
    private array $reactionHandlers = [];

    /**
     * @var array<int, array{filters: string[], handler: callable(ActionEvent): void}>
     */
    private array $actionHandlers = [];

    /**
     * @var array<int, array{commands: string[], handler: callable(SlashCommandEvent): void}>
     */
    private array $slashCommandHandlers = [];

    /**
     * @var array<int, array{filters: string[], handler: callable(ModalSubmitEvent): void}>
     */
    private array $modalSubmitHandlers = [];

    /**
     * @var array<int, array{filters: string[], handler: callable(ModalCloseEvent): void}>
     */
    private array $modalCloseHandlers = [];

    /**
     * @var array<int, array{filters: string[], handler: callable(OptionsLoadEvent): ?array}>
     */
    private array $optionsLoadHandlers = [];

    /** @var callable[] */
    private array $assistantThreadStartedHandlers = [];

    /** @var callable[] */
    private array $assistantContextChangedHandlers = [];

    /** @var callable[] */
    private array $appHomeOpenedHandlers = [];

    /** @var callable[] */
    private array $memberJoinedChannelHandlers = [];

    /** @var callable[] */
    private array $messageDeliveredHandlers = [];

    /** @var callable[] */
    private array $messageReadHandlers = [];

    /** @var array<string, int> */
    private array $concurrentSlots = [];

    public function __construct(
        public readonly StateAdapter $state,
        array $adapters = [],
        private readonly array $config = [],
        ?AdapterResolver $adapterResolver = null,
        ?ResponseFactoryInterface $responseFactory = null,
        ?callable $identity = null,
        ?array $transcripts = null,
    ) {
        $this->adapters = $adapters;
        $this->adapterResolver = $adapterResolver;
        $this->responseFactory = $responseFactory;
        $this->conversationManager = new ConversationManager(
            logger: $config['logger'] ?? null,
            factory: $config['conversation_factory'] ?? null,
        );

        if ($identity !== null) {
            $this->identityResolver = $identity instanceof \Closure ? $identity : \Closure::fromCallable($identity);
        }

        if ($transcripts !== null) {
            if (! $this->identityResolver instanceof \Closure) {
                throw new \InvalidArgumentException('transcripts config requires identity resolver');
            }
            $this->transcriptsApi = new TranscriptsApi($this->state, $transcripts);
        }
    }

    public function getTranscripts(): ?TranscriptsApi
    {
        return $this->transcriptsApi;
    }

    public function resolveIdentity(Author $author): ?string
    {
        return $this->identityResolver instanceof \Closure
            ? ($this->identityResolver)($author)
            : null;
    }

    public function resolveAdapter(string $name, ?ServerRequestInterface $request = null): ?Adapter
    {
        if ($this->adapterResolver instanceof AdapterResolver) {
            $adapter = $this->adapterResolver->resolve($name, $request);
            if ($adapter instanceof Adapter) {
                return $adapter;
            }
        }

        return $this->adapters[$name] ?? null;
    }

    public function registerAdapter(string $name, Adapter $adapter): self
    {
        $this->adapters[$name] = $adapter;

        return $this;
    }

    public function thread(string $threadId): Thread
    {
        $parts = explode(':', $threadId, 2);
        $adapterName = $parts[0];
        $adapter = $this->resolveAdapter($adapterName);

        if (! $adapter instanceof Adapter) {
            throw new ResourceNotFoundException("No adapter found for '{$adapterName}'");
        }

        return new Thread($threadId, $this, $adapter, $this->state);
    }

    public function channel(string $channelId): Channel
    {
        $parts = explode(':', $channelId, 2);
        $adapterName = $parts[0];
        $adapter = $this->resolveAdapter($adapterName);

        if (! $adapter instanceof Adapter) {
            throw new ResourceNotFoundException("No adapter found for '{$adapterName}'");
        }

        return new Channel($channelId, $adapter);
    }

    public function onNewMessage(string $pattern, callable $handler): self
    {
        if (preg_match($pattern, '') === false) {
            throw new \InvalidArgumentException("Invalid regex pattern: {$pattern}");
        }

        $this->messageHandlers[$pattern] = $handler;

        return $this;
    }

    public function onNewMention(callable $handler): self
    {
        $this->mentionHandlers[] = $handler;

        return $this;
    }

    public function onDirectMessage(callable $handler): self
    {
        $this->dmHandlers[] = $handler;

        return $this;
    }

    public function onSubscribedMessage(callable $handler): self
    {
        $this->subscribedHandlers[] = $handler;

        return $this;
    }

    public function onReaction(string|array|callable $emoji, ?callable $handler = null): self
    {
        if (is_callable($emoji)) {
            $handler = $emoji;
            $filters = [];
        } elseif ($handler === null) {
            return $this;
        } else {
            $filters = is_array($emoji) ? $emoji : [$emoji];
        }

        $this->reactionHandlers[] = ['filters' => $filters, 'handler' => $handler];

        return $this;
    }

    public function onAction(string|array|callable $actionId, ?callable $handler = null): self
    {
        if (is_callable($actionId)) {
            $handler = $actionId;
            $filters = [];
        } elseif ($handler === null) {
            return $this;
        } else {
            $filters = is_array($actionId) ? $actionId : [$actionId];
        }

        $this->actionHandlers[] = ['filters' => $filters, 'handler' => $handler];

        return $this;
    }

    public function onModalSubmit(string|array|callable $callbackId, ?callable $handler = null): self
    {
        if (is_callable($callbackId)) {
            $handler = $callbackId;
            $filters = [];
        } elseif ($handler === null) {
            return $this;
        } else {
            $filters = is_array($callbackId) ? $callbackId : [$callbackId];
        }

        $this->modalSubmitHandlers[] = ['filters' => $filters, 'handler' => $handler];

        return $this;
    }

    public function onModalClose(string|array|callable $callbackId, ?callable $handler = null): self
    {
        if (is_callable($callbackId)) {
            $handler = $callbackId;
            $filters = [];
        } elseif ($handler === null) {
            return $this;
        } else {
            $filters = is_array($callbackId) ? $callbackId : [$callbackId];
        }

        $this->modalCloseHandlers[] = ['filters' => $filters, 'handler' => $handler];

        return $this;
    }

    public function onSlashCommand(string|array|callable $command, ?callable $handler = null): self
    {
        if (is_callable($command)) {
            $handler = $command;
            $commands = [];
        } elseif ($handler === null) {
            return $this;
        } else {
            $commands = is_array($command) ? $command : [$command];
            $commands = array_map(fn (string $cmd): string => str_starts_with($cmd, '/') ? $cmd : "/{$cmd}", $commands);
        }

        $this->slashCommandHandlers[] = ['commands' => $commands, 'handler' => $handler];

        return $this;
    }

    public function onOptionsLoad(string|array|callable $actionId, ?callable $handler = null): self
    {
        if (is_callable($actionId)) {
            $handler = $actionId;
            $filters = [];
        } elseif ($handler === null) {
            return $this;
        } else {
            $filters = is_array($actionId) ? $actionId : [$actionId];
        }

        $this->optionsLoadHandlers[] = ['filters' => $filters, 'handler' => $handler];

        return $this;
    }

    public function onAssistantThreadStarted(callable $handler): self
    {
        $this->assistantThreadStartedHandlers[] = $handler;

        return $this;
    }

    public function onAssistantContextChanged(callable $handler): self
    {
        $this->assistantContextChangedHandlers[] = $handler;

        return $this;
    }

    public function onAppHomeOpened(callable $handler): self
    {
        $this->appHomeOpenedHandlers[] = $handler;

        return $this;
    }

    public function onMemberJoinedChannel(callable $handler): self
    {
        $this->memberJoinedChannelHandlers[] = $handler;

        return $this;
    }

    public function onMessageDelivered(callable $handler): self
    {
        $this->messageDeliveredHandlers[] = $handler;

        return $this;
    }

    public function onMessageRead(callable $handler): self
    {
        $this->messageReadHandlers[] = $handler;

        return $this;
    }

    public function storeModalContext(string $adapterName, string $contextId, array $data, int $ttlMs = 86400000): void
    {
        $this->state->storeModalContext($adapterName, $contextId, $data, $ttlMs);
    }

    public function getAndDeleteModalContext(string $adapterName, string $contextId): ?array
    {
        return $this->state->getAndDeleteModalContext($adapterName, $contextId);
    }

    public function openDM(string $adapterName, string $userId): ?string
    {
        $adapter = $this->resolveAdapter($adapterName);
        if (! $adapter instanceof Adapter) {
            return null;
        }

        return $adapter->openDM($userId);
    }

    public function getUser(string $adapterName, string $userId): ?UserInfo
    {
        $adapter = $this->resolveAdapter($adapterName);
        if (! $adapter instanceof Adapter) {
            return null;
        }

        return $adapter->getUser($userId);
    }

    public function processReaction(
        Adapter $adapter,
        string $threadId,
        string $emoji,
        string $messageId,
        Author $user,
        bool $added = true,
        string $rawEmoji = '',
        mixed $raw = null,
    ): void {
        $thread = new Thread($threadId, $this, $adapter, $this->state);

        $event = new ReactionEvent(
            emoji: $emoji,
            messageId: $messageId,
            thread: $thread,
            user: $user,
            added: $added,
            rawEmoji: $rawEmoji,
            raw: $raw,
        );

        $this->dispatchReactionHandlers($event);
    }

    public function processAction(
        Adapter $adapter,
        string $threadId,
        string $actionId,
        ?string $value,
        string $messageId,
        Author $user,
        ?string $triggerId = null,
        mixed $raw = null,
    ): void {
        $thread = new Thread($threadId, $this, $adapter, $this->state);

        $event = new ActionEvent(
            actionId: $actionId,
            value: $value,
            messageId: $messageId,
            triggerId: $triggerId,
            thread: $thread,
            user: $user,
            raw: $raw,
        );

        $this->dispatchActionHandlers($event);
    }

    public function processModalSubmit(
        Adapter $adapter,
        string $callbackId,
        array $values,
        Author $user,
        mixed $raw = null,
        ?string $viewId = null,
        ?string $contextId = null,
    ): void {
        $relatedChannel = null;
        $relatedThread = null;
        $relatedMessage = null;

        if ($contextId !== null) {
            $context = $this->getAndDeleteModalContext($adapter->getName(), $contextId);
            if ($context !== null) {
                $relatedChannel = $context['channel'] ?? null;
                $relatedMessage = $context['message'] ?? null;
                if (isset($context['threadId'])) {
                    $relatedThread = new Thread($context['threadId'], $this, $adapter, $this->state);
                } else {
                    $relatedThread = $context['thread'] ?? null;
                }
            }
        }

        $event = new ModalSubmitEvent(
            callbackId: $callbackId,
            values: $values,
            user: $user,
            raw: $raw,
            viewId: $viewId,
            relatedChannel: $relatedChannel,
            relatedThread: $relatedThread,
            relatedMessage: $relatedMessage,
        );

        $this->dispatchModalSubmitHandlers($event);
    }

    public function processModalClose(
        Adapter $adapter,
        string $callbackId,
        Author $user,
        mixed $raw = null,
        ?string $viewId = null,
        ?string $contextId = null,
    ): void {
        $relatedChannel = null;
        $relatedThread = null;
        $relatedMessage = null;

        if ($contextId !== null) {
            $context = $this->getAndDeleteModalContext($adapter->getName(), $contextId);
            if ($context !== null) {
                $relatedChannel = $context['channel'] ?? null;
                $relatedMessage = $context['message'] ?? null;
                if (isset($context['threadId'])) {
                    $relatedThread = new Thread($context['threadId'], $this, $adapter, $this->state);
                } else {
                    $relatedThread = $context['thread'] ?? null;
                }
            }
        }

        $event = new ModalCloseEvent(
            callbackId: $callbackId,
            user: $user,
            raw: $raw,
            viewId: $viewId,
            relatedChannel: $relatedChannel,
            relatedThread: $relatedThread,
            relatedMessage: $relatedMessage,
        );

        $this->dispatchModalCloseHandlers($event);
    }

    public function processSlashCommand(Adapter $adapter, string $channelId, string $command, string $text, ?Author $user = null, mixed $raw = null, ?string $triggerId = null): void
    {
        $user ??= new Author(id: '');

        if ($user->isMe) {
            return;
        }

        $threadId = $channelId;
        $thread = new Thread($threadId, $this, $adapter, $this->state);
        $channel = new Channel($threadId, $adapter);
        $message = new Message(
            id: uniqid('slash_'),
            threadId: $threadId,
            author: $user,
            text: $text,
            raw: $raw,
        );

        $event = new SlashCommandEvent(
            adapter: $adapter,
            channel: $channel,
            thread: $thread,
            message: $message,
            user: $user,
            command: $command,
            text: $text,
            raw: $raw,
            triggerId: $triggerId,
        );

        $this->dispatchSlashCommandHandlers($event);
    }

    public function processOptionsLoad(
        Adapter $adapter,
        string $actionId,
        string $query,
        Author $user,
        mixed $raw = null,
    ): ?array {
        $event = new OptionsLoadEvent(
            adapter: $adapter,
            actionId: $actionId,
            query: $query,
            user: $user,
            raw: $raw,
        );

        return $this->dispatchOptionsLoadHandlers($event);
    }

    public function processAssistantThreadStarted(
        Adapter $adapter,
        string $channelId,
        string $threadId,
        string $userId,
        mixed $context,
        ?string $threadTs = null,
        mixed $raw = null,
    ): void {
        $event = new AssistantThreadStartedEvent(
            adapter: $adapter,
            channelId: $channelId,
            threadId: $threadId,
            threadTs: $threadTs,
            userId: $userId,
            context: $context,
            raw: $raw,
        );

        $this->dispatchAssistantThreadStartedHandlers($event);
    }

    public function processAssistantContextChanged(
        Adapter $adapter,
        string $channelId,
        string $threadId,
        string $userId,
        mixed $context,
        ?string $threadTs = null,
        mixed $raw = null,
    ): void {
        $event = new AssistantContextChangedEvent(
            adapter: $adapter,
            channelId: $channelId,
            threadId: $threadId,
            threadTs: $threadTs,
            userId: $userId,
            context: $context,
            raw: $raw,
        );

        $this->dispatchAssistantContextChangedHandlers($event);
    }

    public function processAppHomeOpened(
        Adapter $adapter,
        string $channelId,
        string $userId,
        mixed $raw = null,
    ): void {
        $event = new AppHomeOpenedEvent(
            adapter: $adapter,
            channelId: $channelId,
            userId: $userId,
            raw: $raw,
        );

        $this->dispatchAppHomeOpenedHandlers($event);
    }

    public function processMemberJoinedChannel(
        Adapter $adapter,
        string $channelId,
        string $userId,
        ?string $inviterId = null,
        mixed $raw = null,
    ): void {
        $event = new MemberJoinedChannelEvent(
            adapter: $adapter,
            channelId: $channelId,
            userId: $userId,
            inviterId: $inviterId,
            raw: $raw,
        );

        $this->dispatchMemberJoinedChannelHandlers($event);
    }

    public function processMessageDelivered(
        string $threadId,
        array $messageIds,
        string $userId,
        mixed $raw = null,
    ): void {
        $event = new MessageDeliveredEvent(
            messageIds: $messageIds,
            threadId: $threadId,
            userId: $userId,
            raw: $raw,
        );

        $this->dispatchMessageDeliveredHandlers($event);
    }

    public function processMessageRead(
        string $threadId,
        string $userId,
        mixed $raw = null,
        ?int $timestamp = null,
    ): void {
        $event = new MessageReadEvent(
            threadId: $threadId,
            userId: $userId,
            raw: $raw,
            timestamp: $timestamp,
        );

        $this->dispatchMessageReadHandlers($event);
    }

    public function processMessage(Adapter $adapter, string $threadId, Message $message): void
    {
        // 1. Self-filter
        if ($message->author->isMe) {
            return;
        }

        // 2. Deduplication
        $dedupeKey = "dedupe:{$adapter->getName()}:{$message->id}";
        if (! $this->state->setIfNotExists($dedupeKey, true, 300_000)) {
            return;
        }

        // 3. Run receiving middleware
        $message = $this->runReceivingMiddleware($message, $adapter);
        if (! $message instanceof Message) {
            return;
        }

        // 4. Concurrency strategy
        $strategy = Strategy::tryFrom($this->config['concurrency'] ?? 'drop') ?? Strategy::Drop;
        $debounceMs = (int) ($this->config['debounceMs'] ?? 1500);
        $maxConcurrent = (int) ($this->config['maxConcurrent'] ?? 0);
        $maxQueueSize = (int) ($this->config['maxQueueSize'] ?? 10);
        $lockScope = $this->config['lockScope'] ?? 'thread';

        $lockKey = $lockScope === 'channel'
            ? $adapter->getName().':'.$adapter->channelIdFromThreadId($threadId)
            : $threadId;

        $handler = new Handler($this->state, $strategy);

        match ($strategy) {
            Strategy::Drop => $this->processDrop($adapter, $threadId, $lockKey, $message, $handler),
            Strategy::Queue => $this->processQueue($adapter, $threadId, $lockKey, $message, $handler, $maxQueueSize),
            Strategy::Debounce => $this->processDebounce($adapter, $threadId, $lockKey, $message, $handler, $debounceMs, $maxQueueSize),
            Strategy::Concurrent => $this->processConcurrent($adapter, $threadId, $message, $maxConcurrent),
        };
    }

    private function processDrop(Adapter $adapter, string $threadId, string $lockKey, Message $message, Handler $handler): void
    {
        $lock = $handler->acquire($lockKey);
        if (! $lock instanceof Lock) {
            return;
        }

        try {
            $this->dispatchIncomingMessage($adapter, $threadId, $message, [], 1);
        } finally {
            $handler->release($lock);
        }
    }

    private function processQueue(Adapter $adapter, string $threadId, string $lockKey, Message $message, Handler $handler, int $maxQueueSize): void
    {
        $entry = new QueueEntry($message->id, serialize($message), microtime(true));
        $handler->enqueue($threadId, $entry, $maxQueueSize);

        $lock = $handler->acquire($lockKey);
        if (! $lock instanceof Lock) {
            return;
        }

        try {
            $this->drainAllQueued($adapter, $threadId, $handler);
        } finally {
            $handler->release($lock);
        }
    }

    private function processDebounce(Adapter $adapter, string $threadId, string $lockKey, Message $message, Handler $handler, int $debounceMs, int $maxQueueSize): void
    {
        $lock = $handler->acquire($lockKey);
        if (! $lock instanceof Lock) {
            $handler->enqueue($threadId, new QueueEntry($message->id, serialize($message), microtime(true)), $maxQueueSize);

            return;
        }

        try {
            usleep($debounceMs * 1000);

            $handler->extendLock($lock, 30_000);

            $messages = $this->dequeueAll($threadId, $handler);

            if ($messages !== []) {
                $latest = array_pop($messages);
                $this->dispatchIncomingMessage($adapter, $threadId, $latest, $messages, count($messages) + 1);

                return;
            }

            $this->dispatchIncomingMessage($adapter, $threadId, $message, [], 1);
        } finally {
            $handler->release($lock);
        }
    }

    private function processConcurrent(Adapter $adapter, string $threadId, Message $message, int $maxConcurrent): void
    {
        $slotKey = $threadId;

        if ($maxConcurrent > 0) {
            $current = $this->concurrentSlots[$slotKey] ?? 0;
            if ($current >= $maxConcurrent) {
                return;
            }
            $this->concurrentSlots[$slotKey] = $current + 1;
        }

        try {
            $this->dispatchIncomingMessage($adapter, $threadId, $message, [], 1);
        } finally {
            if ($maxConcurrent > 0) {
                $this->concurrentSlots[$slotKey]--;
                if ($this->concurrentSlots[$slotKey] <= 0) {
                    unset($this->concurrentSlots[$slotKey]);
                }
            }
        }
    }

    private function drainAllQueued(Adapter $adapter, string $threadId, Handler $handler): void
    {
        $messages = $this->dequeueAll($threadId, $handler);
        if ($messages === []) {
            return;
        }

        foreach ($messages as $msg) {
            $this->dispatchIncomingMessage($adapter, $threadId, $msg, [], 1);
        }
    }

    /**
     * @return Message[]
     */
    private function dequeueAll(string $threadId, Handler $handler): array
    {
        $messages = [];
        while ($entry = $handler->dequeue($threadId)) {
            $msg = unserialize($entry->payload);
            if ($msg instanceof Message) {
                $msg = new Message(
                    id: $entry->messageId,
                    threadId: $threadId,
                    author: $msg->author,
                    text: $msg->text,
                    formatted: $msg->formatted,
                    attachments: $msg->attachments,
                    isMention: $msg->isMention,
                    isDM: $msg->isDM,
                    raw: $msg->raw,
                );
                $messages[] = $msg;
            }
        }

        return $messages;
    }

    private function dispatchIncomingMessage(Adapter $adapter, string $threadId, Message $message, array $skippedMessages, int $totalSinceLastHandler): void
    {
        // Conversation intercept
        $thread = new Thread($threadId, $this, $adapter, $this->state);
        if ($this->conversationManager->intercept($thread, $message)) {
            return;
        }

        // Persist to transcripts
        if ($this->transcriptsApi instanceof TranscriptsApi) {
            $userKey = $this->resolveIdentity($message->author);
            if ($userKey !== null) {
                $this->transcriptsApi->append($userKey, $message);
            }
        }

        $context = new MessageContext(
            thread: $thread,
            message: $message,
            transcripts: $this->transcriptsApi,
            skippedMessages: $skippedMessages,
            totalSinceLastHandler: $totalSinceLastHandler,
        );

        // DM routing
        if ($message->isDM && $this->dmHandlers !== []) {
            $this->dispatchHandlers($this->dmHandlers, $context);

            return;
        }

        // Subscribed
        if ($this->state->isSubscribed($threadId)) {
            $this->dispatchHandlers($this->subscribedHandlers, $context);

            return;
        }

        // Mention
        if ($message->isMention) {
            $this->dispatchHandlers($this->mentionHandlers, $context);

            return;
        }

        // Pattern match
        foreach ($this->messageHandlers as $pattern => $handler) {
            if (preg_match($pattern, $message->text)) {
                $handler($context);
                if ($context->isSkipped()) {
                    return;
                }
            }
        }
    }

    public function handleWebhook(string $adapterName, ServerRequestInterface $request): ResponseInterface
    {
        $adapter = $this->resolveAdapter($adapterName, $request);

        if (! $adapter instanceof Adapter) {
            throw new ResourceNotFoundException("Adapter '{$adapterName}' is not configured.");
        }

        $this->initAdapter($adapter);

        $handler = function (ServerRequestInterface $request) use ($adapter): ResponseInterface {

            $ack = $adapter->verifyWebhook($request);
            if ($ack instanceof ResponseInterface) {
                return $ack;
            }

            // Check for action (button clicks, etc.)
            if ($adapter instanceof HandlesActions) {
                $actionData = $adapter->parseAction($request);
                if ($actionData !== null) {
                    $this->processAction(
                        adapter: $adapter,
                        threadId: $actionData['threadId'],
                        actionId: $actionData['actionId'],
                        value: $actionData['value'] ?? null,
                        messageId: $actionData['messageId'],
                        user: new Author(
                            id: $actionData['userId'],
                            isBot: $actionData['isBot'],
                        ),
                        triggerId: $actionData['triggerId'] ?? null,
                        raw: $actionData['raw'] ?? null,
                    );

                    $ack = $adapter->acknowledgeAction($actionData['callbackQueryId'] ?? null);
                    if ($ack instanceof ResponseInterface) {
                        return $ack;
                    }

                    return $this->webhookResponse($adapter);
                }
            }

            // Check for native slash command
            if ($adapter instanceof HandlesSlashCommands) {
                $slashData = $adapter->parseSlashCommand($request);
                if ($slashData !== null) {
                    $this->processSlashCommand(
                        adapter: $adapter,
                        channelId: $slashData['channelId'],
                        command: $slashData['command'],
                        text: $slashData['text'],
                        user: new Author(
                            id: $slashData['userId'],
                            isBot: $slashData['isBot'],
                        ),
                        raw: $slashData['raw'] ?? null,
                        triggerId: $slashData['triggerId'] ?? null,
                    );

                    return $this->webhookResponse($adapter);
                }
            }

            // Check for modals (view_submission, view_closed)
            if ($adapter instanceof HandlesModals) {
                $modalData = $adapter->parseModalSubmit($request);
                if ($modalData !== null) {
                    $this->processModalSubmit(
                        adapter: $adapter,
                        callbackId: $modalData['callbackId'],
                        values: $modalData['values'],
                        user: new Author(
                            id: $modalData['userId'],
                        ),
                        raw: $modalData['raw'] ?? null,
                        viewId: $modalData['viewId'],
                        contextId: $modalData['contextId'] ?? null,
                    );

                    return $this->webhookResponse($adapter);
                }

                $modalData = $adapter->parseModalClose($request);
                if ($modalData !== null) {
                    $this->processModalClose(
                        adapter: $adapter,
                        callbackId: $modalData['callbackId'],
                        user: new Author(
                            id: $modalData['userId'],
                        ),
                        raw: $modalData['raw'] ?? null,
                        viewId: $modalData['viewId'],
                        contextId: $modalData['contextId'] ?? null,
                    );

                    return $this->webhookResponse($adapter);
                }
            }

            // Check for options load (block_suggestion)
            if ($adapter instanceof HandlesOptionsLoad) {
                $optionsData = $adapter->parseOptionsLoad($request);
                if ($optionsData !== null) {
                    $result = $this->processOptionsLoad(
                        adapter: $adapter,
                        actionId: $optionsData['actionId'],
                        query: $optionsData['query'],
                        user: new Author(
                            id: $optionsData['userId'],
                        ),
                        raw: $optionsData['raw'] ?? null,
                    );

                    $ack = $adapter->respondToOptionsLoad($result);
                    if ($ack instanceof ResponseInterface) {
                        return $ack;
                    }

                    return $this->webhookResponse($adapter);
                }
            }

            // Check for reactions (emoji added/removed)
            if ($adapter instanceof HandlesReactions) {
                $reactionData = $adapter->parseReaction($request);
                if ($reactionData !== null) {
                    $this->processReaction(
                        adapter: $adapter,
                        threadId: $reactionData['threadId'],
                        emoji: $reactionData['emoji'],
                        messageId: $reactionData['messageId'],
                        user: new Author(
                            id: $reactionData['userId'],
                        ),
                        added: $reactionData['added'],
                        rawEmoji: $reactionData['rawEmoji'],
                        raw: $reactionData['raw'] ?? null,
                    );

                    return $this->webhookResponse($adapter);
                }
            }

            // Check for Slack Events (assistant threads, app home, member joined)
            if ($adapter instanceof HandlesSlackEvents) {
                $eventData = $adapter->parseAssistantThreadStarted($request);
                if ($eventData !== null) {
                    $this->processAssistantThreadStarted(
                        adapter: $adapter,
                        channelId: $eventData['channelId'],
                        threadId: $eventData['threadId'],
                        userId: $eventData['userId'],
                        context: $eventData['context'],
                        threadTs: $eventData['threadTs'],
                        raw: $eventData['raw'] ?? null,
                    );

                    return $this->webhookResponse($adapter);
                }

                $eventData = $adapter->parseAssistantContextChanged($request);
                if ($eventData !== null) {
                    $this->processAssistantContextChanged(
                        adapter: $adapter,
                        channelId: $eventData['channelId'],
                        threadId: $eventData['threadId'],
                        userId: $eventData['userId'],
                        context: $eventData['context'],
                        threadTs: $eventData['threadTs'],
                        raw: $eventData['raw'] ?? null,
                    );

                    return $this->webhookResponse($adapter);
                }

                $eventData = $adapter->parseAppHomeOpened($request);
                if ($eventData !== null) {
                    $this->processAppHomeOpened(
                        adapter: $adapter,
                        channelId: $eventData['channelId'],
                        userId: $eventData['userId'],
                        raw: $eventData['raw'] ?? null,
                    );

                    return $this->webhookResponse($adapter);
                }

                $eventData = $adapter->parseMemberJoinedChannel($request);
                if ($eventData !== null) {
                    $this->processMemberJoinedChannel(
                        adapter: $adapter,
                        channelId: $eventData['channelId'],
                        userId: $eventData['userId'],
                        inviterId: $eventData['inviterId'] ?? null,
                        raw: $eventData['raw'] ?? null,
                    );

                    return $this->webhookResponse($adapter);
                }
            }

            // Check for message statuses (delivered/read)
            if ($adapter instanceof HandlesStatuses) {
                $statusData = $adapter->parseStatus($request);
                if ($statusData !== null) {
                    if ($statusData['type'] === 'delivered') {
                        $this->processMessageDelivered(
                            threadId: $statusData['threadId'],
                            messageIds: $statusData['messageIds'],
                            userId: $statusData['userId'],
                            raw: $statusData['raw'] ?? null,
                        );
                    } elseif ($statusData['type'] === 'read') {
                        $this->processMessageRead(
                            threadId: $statusData['threadId'],
                            userId: $statusData['userId'],
                            raw: $statusData['raw'] ?? null,
                            timestamp: $statusData['timestamp'] ?? null,
                        );
                    }

                    return $this->webhookResponse($adapter);
                }
            }

            $message = $adapter->parseWebhook($request);
            $this->processMessage($adapter, $message->threadId, $message);

            return $this->webhookResponse($adapter);
        };

        foreach (array_reverse($this->webhookMiddleware) as $middleware) {
            $handler = function (ServerRequestInterface $request) use ($middleware, $handler): ResponseInterface {
                return $middleware->handle($request, $handler);
            };
        }

        return $handler($request);
    }

    private function webhookResponse(Adapter $adapter): ResponseInterface
    {
        $adapterResponse = $adapter->createResponse();
        if ($adapterResponse instanceof ResponseInterface) {
            return $adapterResponse;
        }

        if (! $this->responseFactory instanceof ResponseFactoryInterface) {
            throw new \RuntimeException(
                'No PSR-17 ResponseFactoryInterface provided. Pass one to the Chat constructor.'
            );
        }

        return $this->responseFactory->createResponse(200);
    }

    public function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        $this->state->connect();
        $this->stateInitialized = true;

        foreach ($this->adapters as $adapter) {
            $adapter->initialize($this);
        }

        $this->initialized = true;
    }

    private function initAdapter(Adapter $adapter): void
    {
        if (! $this->stateInitialized) {
            $this->state->connect();
            $this->stateInitialized = true;
        }

        $adapter->initialize($this);
    }

    public function shutdown(): void
    {
        foreach ($this->adapters as $adapter) {
            $adapter->disconnect();
        }

        $this->state->disconnect();
    }

    public function addWebhookMiddleware(WebhookMiddleware $middleware): self
    {
        $this->webhookMiddleware[] = $middleware;

        return $this;
    }

    public function addReceivingMiddleware(ReceivingMiddleware $middleware): self
    {
        $this->receivingMiddleware[] = $middleware;

        return $this;
    }

    public function addSendingMiddleware(SendingMiddleware $middleware): self
    {
        $this->sendingMiddleware[] = $middleware;

        return $this;
    }

    /**
     * @return SendingMiddleware[]
     */
    public function getSendingMiddleware(): array
    {
        return $this->sendingMiddleware;
    }

    private function runReceivingMiddleware(?Message $message, Adapter $adapter): ?Message
    {
        foreach ($this->receivingMiddleware as $middleware) {
            $message = $middleware->handle($message, $adapter, fn ($msg) => $msg);
            if ($message === null) {
                return null;
            }
        }

        return $message;
    }

    /**
     * @param  callable[]  $handlers
     */
    private function dispatchHandlers(array $handlers, MessageContext $context): void
    {
        foreach ($handlers as $handler) {
            $handler($context);
            if ($context->isSkipped()) {
                return;
            }
        }
    }

    private function dispatchSlashCommandHandlers(SlashCommandEvent $event): void
    {
        foreach ($this->slashCommandHandlers as ['commands' => $commands, 'handler' => $handler]) {
            if ($commands === [] || in_array($event->command, $commands, true)) {
                $handler($event);
            }
        }
    }

    private function dispatchReactionHandlers(ReactionEvent $event): void
    {
        foreach ($this->reactionHandlers as ['filters' => $filters, 'handler' => $handler]) {
            if ($filters === [] || in_array($event->emoji, $filters, true)) {
                $handler($event);
            }
        }
    }

    private function dispatchActionHandlers(ActionEvent $event): void
    {
        foreach ($this->actionHandlers as ['filters' => $filters, 'handler' => $handler]) {
            if ($filters === [] || in_array($event->actionId, $filters, true)) {
                $handler($event);
            }
        }
    }

    private function dispatchModalSubmitHandlers(ModalSubmitEvent $event): void
    {
        foreach ($this->modalSubmitHandlers as ['filters' => $filters, 'handler' => $handler]) {
            if ($filters === [] || in_array($event->callbackId, $filters, true)) {
                $handler($event);
            }
        }
    }

    private function dispatchModalCloseHandlers(ModalCloseEvent $event): void
    {
        foreach ($this->modalCloseHandlers as ['filters' => $filters, 'handler' => $handler]) {
            if ($filters === [] || in_array($event->callbackId, $filters, true)) {
                $handler($event);
            }
        }
    }

    private function dispatchOptionsLoadHandlers(OptionsLoadEvent $event): ?array
    {
        foreach ($this->optionsLoadHandlers as ['filters' => $filters, 'handler' => $handler]) {
            if ($filters === [] || in_array($event->actionId, $filters, true)) {
                $result = $handler($event);
                if (is_array($result)) {
                    return $result;
                }
            }
        }

        return null;
    }

    private function dispatchAssistantThreadStartedHandlers(AssistantThreadStartedEvent $event): void
    {
        foreach ($this->assistantThreadStartedHandlers as $handler) {
            $handler($event);
        }
    }

    private function dispatchAssistantContextChangedHandlers(AssistantContextChangedEvent $event): void
    {
        foreach ($this->assistantContextChangedHandlers as $handler) {
            $handler($event);
        }
    }

    private function dispatchAppHomeOpenedHandlers(AppHomeOpenedEvent $event): void
    {
        foreach ($this->appHomeOpenedHandlers as $handler) {
            $handler($event);
        }
    }

    private function dispatchMemberJoinedChannelHandlers(MemberJoinedChannelEvent $event): void
    {
        foreach ($this->memberJoinedChannelHandlers as $handler) {
            $handler($event);
        }
    }

    private function dispatchMessageDeliveredHandlers(MessageDeliveredEvent $event): void
    {
        foreach ($this->messageDeliveredHandlers as $handler) {
            $handler($event);
        }
    }

    private function dispatchMessageReadHandlers(MessageReadEvent $event): void
    {
        foreach ($this->messageReadHandlers as $handler) {
            $handler($event);
        }
    }
}
