<?php

namespace BootDesk\ChatSDK\Core;

use BootDesk\ChatSDK\Core\Concurrency\DefaultConcurrencyHandler;
use BootDesk\ChatSDK\Core\Contracts\Adapter;
use BootDesk\ChatSDK\Core\Contracts\AdapterResolver;
use BootDesk\ChatSDK\Core\Contracts\BroadcastAdapter;
use BootDesk\ChatSDK\Core\Contracts\ConcurrencyHandler;
use BootDesk\ChatSDK\Core\Contracts\HandlesActions;
use BootDesk\ChatSDK\Core\Contracts\HandlesBatchedWebhooks;
use BootDesk\ChatSDK\Core\Contracts\HandlesMessageCosts;
use BootDesk\ChatSDK\Core\Contracts\HandlesModals;
use BootDesk\ChatSDK\Core\Contracts\HandlesOptionsLoad;
use BootDesk\ChatSDK\Core\Contracts\HandlesReactions;
use BootDesk\ChatSDK\Core\Contracts\HandlesSlackEvents;
use BootDesk\ChatSDK\Core\Contracts\HandlesSlashCommands;
use BootDesk\ChatSDK\Core\Contracts\HandlesStatuses;
use BootDesk\ChatSDK\Core\Contracts\HeardMiddleware;
use BootDesk\ChatSDK\Core\Contracts\IdentityResolver;
use BootDesk\ChatSDK\Core\Contracts\ReceivingMiddleware;
use BootDesk\ChatSDK\Core\Contracts\SendingMiddleware;
use BootDesk\ChatSDK\Core\Contracts\SentMiddleware;
use BootDesk\ChatSDK\Core\Contracts\StateAdapter;
use BootDesk\ChatSDK\Core\Contracts\TranscriptsApi as TranscriptsApiContract;
use BootDesk\ChatSDK\Core\Contracts\WebhookEventMiddleware;
use BootDesk\ChatSDK\Core\Contracts\WebhookMiddleware;
use BootDesk\ChatSDK\Core\Conversations\ConversationManager;
use BootDesk\ChatSDK\Core\Events\DmEvent;
use BootDesk\ChatSDK\Core\Events\EventDispatcher;
use BootDesk\ChatSDK\Core\Events\ListenerProvider;
use BootDesk\ChatSDK\Core\Events\MentionEvent;
use BootDesk\ChatSDK\Core\Events\SubscribedEvent;
use BootDesk\ChatSDK\Core\Exceptions\ResourceNotFoundException;
use BootDesk\ChatSDK\Core\Middleware\MiddlewareDispatcher;
use BootDesk\ChatSDK\Core\Transcript\DefaultTranscriptsApi;
use BootDesk\ChatSDK\Core\Transcript\TranscriptSentMiddleware;
use Money\Money;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Chat
{
    public readonly ConversationManager $conversationManager;

    /** @var array<string, Adapter> */
    private array $adapters = [];

    private ?AdapterResolver $adapterResolver = null;

    private ?BroadcastAdapter $broadcaster = null;

    private ?ResponseFactoryInterface $responseFactory = null;

    private bool $initialized = false;

    private bool $stateInitialized = false;

    private ?IdentityResolver $identityResolver = null;

    private ?TranscriptsApiContract $transcriptsApi = null;

    private readonly ListenerProvider $listenerProvider;

    private readonly EventDispatcher $dispatcher;

    private readonly MiddlewareDispatcher $middleware;

    /** @var array<string, callable> */
    private array $messageHandlers = [];

    private ConcurrencyHandler $concurrencyHandler;

    /**
     * @param  array<string, Adapter>  $adapters
     * @param  array<string, mixed>  $config
     * @param  array{logger?: mixed, conversation_factory?: mixed}|null  $transcripts
     */
    public function __construct(
        public readonly StateAdapter $state,
        array $adapters = [],
        array $config = [],
        ?AdapterResolver $adapterResolver = null,
        ?ResponseFactoryInterface $responseFactory = null,
        ?IdentityResolver $identity = null,
        null|array|TranscriptsApiContract $transcripts = null,
        ?BroadcastAdapter $broadcaster = null,
        ?ConcurrencyHandler $concurrencyHandler = null,
    ) {
        $this->adapters = $adapters;
        $this->adapterResolver = $adapterResolver;
        $this->responseFactory = $responseFactory;
        $this->broadcaster = $broadcaster;
        $this->conversationManager = new ConversationManager(
            logger: $config['logger'] ?? null,
            factory: $config['conversation_factory'] ?? null,
        );
        $this->listenerProvider = new ListenerProvider;
        $this->dispatcher = new EventDispatcher($this->listenerProvider);
        $this->middleware = new MiddlewareDispatcher;
        $this->concurrencyHandler = $concurrencyHandler ?? new DefaultConcurrencyHandler($state, $config);

        $this->identityResolver = $identity;

        if ($transcripts instanceof TranscriptsApiContract) {
            $this->transcriptsApi = $transcripts;
        } elseif (is_array($transcripts)) {
            if (! $this->identityResolver instanceof IdentityResolver) {
                throw new \InvalidArgumentException('transcripts config requires identity resolver');
            }
            $this->transcriptsApi = new DefaultTranscriptsApi($this->state, $transcripts);
        }

        if ($this->transcriptsApi instanceof TranscriptsApiContract) {
            $this->addSentMiddleware(new TranscriptSentMiddleware($this->transcriptsApi, $this->state), -100);
        }
    }

    public function getTranscripts(): ?TranscriptsApiContract
    {
        return $this->transcriptsApi;
    }

    public function getBroadcastAdapter(): ?BroadcastAdapter
    {
        return $this->broadcaster;
    }

    public function setBroadcastAdapter(BroadcastAdapter $broadcaster): void
    {
        $this->broadcaster = $broadcaster;
    }

    public function resolveIdentity(Author $author): ?string
    {
        return $this->identityResolver?->resolve($author);
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

    /**
     * @param  string|array<string>|null  $filter
     */
    public function listen(
        string $eventClass,
        callable $listener,
        string|array|null $filter = null,
        int $priority = 0,
    ): self {
        $wrappedListener = function (object $event) use ($filter, $listener) {
            // Unwrap events that contain MessageContext
            $actualEvent = $event;
            if ($event instanceof DmEvent || $event instanceof MentionEvent || $event instanceof SubscribedEvent) {
                $actualEvent = $event->context;
            }

            // Handle filtering
            if ($filter !== null) {
                $filters = is_array($filter) ? $filter : [$filter];
                $value = match ($event::class) {
                    ReactionEvent::class => $event->emoji,
                    ActionEvent::class => $event->actionId,
                    SlashCommandEvent::class => $event->command,
                    ModalSubmitEvent::class,
                    ModalCloseEvent::class => $event->callbackId,
                    OptionsLoadEvent::class => $event->actionId,
                    default => null,
                };

                if ($filters !== [] && ($value === null || ! in_array($value, $filters, true))) {
                    return null;
                }
            }

            return $listener($actualEvent);
        };

        $this->listenerProvider->addListener($eventClass, $wrappedListener, $priority);

        return $this;
    }

    /** @deprecated Use listen(MentionEvent::class, $handler) instead */
    public function onNewMention(callable $handler): self
    {
        return $this->listen(MentionEvent::class, $handler);
    }

    /** @deprecated Use listen(DmEvent::class, $handler) instead */
    public function onDirectMessage(callable $handler): self
    {
        return $this->listen(DmEvent::class, $handler);
    }

    /** @deprecated Use listen(SubscribedEvent::class, $handler) instead */
    public function onSubscribedMessage(callable $handler): self
    {
        return $this->listen(SubscribedEvent::class, $handler);
    }

    /**
     * @param  string|array<string>|callable  $emoji
     *
     * @deprecated Use listen(ReactionEvent::class, $handler, $emoji) instead
     */
    public function onReaction(string|array|callable $emoji, ?callable $handler = null): self
    {
        if (is_callable($emoji)) {
            $handler = $emoji;
            $filter = null;
        } elseif (is_array($emoji)) {
            $filter = $emoji;
        } else {
            $filter = [$emoji];
        }

        if ($handler === null) {
            return $this;
        }

        return $this->listen(ReactionEvent::class, $handler, $filter);
    }

    /**
     * @param  string|array<string>|callable  $actionId
     *
     * @deprecated Use listen(ActionEvent::class, $handler, $actionId) instead
     */
    public function onAction(string|array|callable $actionId, ?callable $handler = null): self
    {
        if (is_callable($actionId)) {
            $handler = $actionId;
            $filter = null;
        } elseif (is_array($actionId)) {
            $filter = $actionId;
        } else {
            $filter = [$actionId];
        }

        if ($handler === null) {
            return $this;
        }

        return $this->listen(ActionEvent::class, $handler, $filter);
    }

    /**
     * @param  string|array<string>|callable  $callbackId
     *
     * @deprecated Use listen(ModalSubmitEvent::class, $handler, $callbackId) instead
     */
    public function onModalSubmit(string|array|callable $callbackId, ?callable $handler = null): self
    {
        if (is_callable($callbackId)) {
            $handler = $callbackId;
            $filter = null;
        } elseif (is_array($callbackId)) {
            $filter = $callbackId;
        } else {
            $filter = [$callbackId];
        }

        if ($handler === null) {
            return $this;
        }

        return $this->listen(ModalSubmitEvent::class, $handler, $filter);
    }

    /**
     * @param  string|array<string>|callable  $callbackId
     *
     * @deprecated Use listen(ModalCloseEvent::class, $handler, $callbackId) instead
     */
    public function onModalClose(string|array|callable $callbackId, ?callable $handler = null): self
    {
        if (is_callable($callbackId)) {
            $handler = $callbackId;
            $filter = null;
        } elseif (is_array($callbackId)) {
            $filter = $callbackId;
        } else {
            $filter = [$callbackId];
        }

        if ($handler === null) {
            return $this;
        }

        return $this->listen(ModalCloseEvent::class, $handler, $filter);
    }

    /**
     * @param  string|array<string>|callable  $command
     *
     * @deprecated Use listen(SlashCommandEvent::class, $handler, $command) instead
     */
    public function onSlashCommand(string|array|callable $command, ?callable $handler = null): self
    {
        if (is_callable($command)) {
            $handler = $command;
            $filter = null;
        } elseif (is_array($command)) {
            $filter = array_map(fn (string $cmd): string => str_starts_with($cmd, '/') ? $cmd : "/{$cmd}", $command);
        } else {
            $filter = [str_starts_with($command, '/') ? $command : "/{$command}"];
        }

        if ($handler === null) {
            return $this;
        }

        return $this->listen(SlashCommandEvent::class, $handler, $filter);
    }

    /**
     * @param  string|array<string>|callable  $actionId
     *
     * @deprecated Use listen(OptionsLoadEvent::class, $handler, $actionId) instead
     */
    public function onOptionsLoad(string|array|callable $actionId, ?callable $handler = null): self
    {
        if (is_callable($actionId)) {
            $handler = $actionId;
            $filter = null;
        } elseif (is_array($actionId)) {
            $filter = $actionId;
        } else {
            $filter = [$actionId];
        }

        if ($handler === null) {
            return $this;
        }

        return $this->listen(OptionsLoadEvent::class, $handler, $filter);
    }

    /** @deprecated Use listen(AssistantThreadStartedEvent::class, $handler) instead */
    public function onAssistantThreadStarted(callable $handler): self
    {
        return $this->listen(AssistantThreadStartedEvent::class, $handler);
    }

    /** @deprecated Use listen(AssistantContextChangedEvent::class, $handler) instead */
    public function onAssistantContextChanged(callable $handler): self
    {
        return $this->listen(AssistantContextChangedEvent::class, $handler);
    }

    /** @deprecated Use listen(AppHomeOpenedEvent::class, $handler) instead */
    public function onAppHomeOpened(callable $handler): self
    {
        return $this->listen(AppHomeOpenedEvent::class, $handler);
    }

    /** @deprecated Use listen(MemberJoinedChannelEvent::class, $handler) instead */
    public function onMemberJoinedChannel(callable $handler): self
    {
        return $this->listen(MemberJoinedChannelEvent::class, $handler);
    }

    /** @deprecated Use listen(MessageDeliveredEvent::class, $handler) instead */
    public function onMessageDelivered(callable $handler): self
    {
        return $this->listen(MessageDeliveredEvent::class, $handler);
    }

    /** @deprecated Use listen(MessageReadEvent::class, $handler) instead */
    public function onMessageRead(callable $handler): self
    {
        return $this->listen(MessageReadEvent::class, $handler);
    }

    /** @deprecated Use listen(MessageFailedEvent::class, $handler) instead */
    public function onMessageFailed(callable $handler): self
    {
        return $this->listen(MessageFailedEvent::class, $handler);
    }

    /** @deprecated Use listen(MessageCostEvent::class, $handler) instead */
    public function onMessageCost(callable $handler): self
    {
        return $this->listen(MessageCostEvent::class, $handler);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function storeModalContext(string $adapterName, string $contextId, array $data, int $ttlMs = 86400000): void
    {
        $this->state->storeModalContext($adapterName, $contextId, $data, $ttlMs);
    }

    /**
     * @return array<string, mixed>|null
     */
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
        ?string $originId = null,
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
            originId: $originId,
        );

        $this->dispatch($event);
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
        ?string $originId = null,
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
            originId: $originId,
        );

        $this->dispatch($event);
    }

    /**
     * @param  array<string, mixed>  $values
     */
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

        $this->dispatch($event);
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

        $this->dispatch($event);
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

        $this->dispatch($event);
    }

    /**
     * @return array<int, array{label: string, value: string}>|null
     */
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

        // OptionsLoadEvent handlers return arrays, need special handling
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

        $this->dispatch($event);
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

        $this->dispatch($event);
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

        $this->dispatch($event);
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

        $this->dispatch($event);
    }

    /**
     * @param  array<string>  $messageIds
     */
    public function processMessageDelivered(
        string $threadId,
        array $messageIds,
        string $userId,
        mixed $raw = null,
        ?string $originId = null,
    ): void {
        $event = new MessageDeliveredEvent(
            messageIds: $messageIds,
            threadId: $threadId,
            userId: $userId,
            raw: $raw,
            originId: $originId,
        );

        $this->dispatch($event);
    }

    public function processMessageRead(
        string $threadId,
        string $userId,
        mixed $raw = null,
        ?int $timestamp = null,
        ?string $originId = null,
    ): void {
        $event = new MessageReadEvent(
            threadId: $threadId,
            userId: $userId,
            raw: $raw,
            timestamp: $timestamp,
            originId: $originId,
        );

        $this->dispatch($event);
    }

    /**
     * @param  array<string>  $messageIds
     */
    public function processMessageFailed(
        string $threadId,
        array $messageIds,
        string $userId,
        mixed $raw = null,
        ?string $originId = null,
    ): void {
        $event = new MessageFailedEvent(
            messageIds: $messageIds,
            threadId: $threadId,
            userId: $userId,
            raw: $raw,
            originId: $originId,
        );

        $this->dispatch($event);
    }

    /**
     * @param  array<string>  $messageIds
     */
    public function processMessageCost(
        string $threadId,
        array $messageIds,
        string $userId,
        ?Money $price = null,
        mixed $raw = null,
        ?string $originId = null,
    ): void {
        $event = new MessageCostEvent(
            messageIds: $messageIds,
            threadId: $threadId,
            userId: $userId,
            price: $price,
            raw: $raw,
            originId: $originId,
        );

        $this->dispatch($event);
    }

    public function processMessage(Adapter $adapter, string $threadId, Message $message, ?ServerRequestInterface $request = null): void
    {
        if ($message->author->isMe) {
            return;
        }

        $dedupeKey = "dedupe:{$adapter->getName()}:{$message->id}";
        if (! $this->state->setIfNotExists($dedupeKey, true, 300_000)) {
            return;
        }

        $message = $this->runReceivingMiddleware($message, $adapter);
        if (! $message instanceof Message) {
            return;
        }

        $this->concurrencyHandler->process(
            $adapter,
            $threadId,
            $message,
            fn (Adapter $a, string $tid, Message $msg, array $skipped, int $total) => $this->dispatchIncomingMessage($a, $tid, $msg, $skipped, $total),
            $request,
        );
    }

    /**
     * @param  array<string>  $skippedMessages
     */
    public function processMessageInJob(
        Adapter $adapter,
        string $threadId,
        Message $message,
        array $skippedMessages = [],
        int $totalSinceLastHandler = 1,
    ): void {
        $event = new WebhookEvent(
            type: WebhookEvent::TYPE_MESSAGE,
            threadId: $threadId,
            payload: $message,
            originId: $message->originId,
        );
        $adapter = $this->middleware->processWebhookEvent(
            $event,
            $adapter,
            fn (WebhookEvent $e, Adapter $a): Adapter => $a,
        );
        $this->dispatchIncomingMessage($adapter, $threadId, $message, $skippedMessages, $totalSinceLastHandler);
    }

    /**
     * @param  array<string>  $skippedMessages
     */
    private function dispatchIncomingMessage(Adapter $adapter, string $threadId, Message $message, array $skippedMessages, int $totalSinceLastHandler): void
    {
        // Conversation intercept
        $thread = new Thread($threadId, $this, $adapter, $this->state);
        if ($this->conversationManager->intercept($thread, $message)) {
            return;
        }

        // Persist to transcripts
        $userKey = $this->resolveIdentity($message->author);
        if ($this->transcriptsApi instanceof TranscriptsApiContract && $userKey !== null) {
            $this->transcriptsApi->append($userKey, $message);
            $this->state->set("transcript_user:{$threadId}", $userKey, 86400_000);
        }

        $context = new MessageContext(
            thread: $thread,
            message: $message,
            transcripts: $this->transcriptsApi,
            skippedMessages: $skippedMessages,
            totalSinceLastHandler: $totalSinceLastHandler,
        );

        // DM routing
        if ($message->isDM && $this->listenerProvider->hasListeners(DmEvent::class)) {
            $this->dispatch(new DmEvent($context));

            return;
        }

        // Subscribed
        if ($this->state->isSubscribed($threadId) && $this->listenerProvider->hasListeners(SubscribedEvent::class)) {
            $this->dispatch(new SubscribedEvent($context));

            return;
        }

        // Mention
        if ($message->isMention && $this->listenerProvider->hasListeners(MentionEvent::class)) {
            $this->dispatch(new MentionEvent($context));

            return;
        }

        // Pattern match
        foreach ($this->messageHandlers as $pattern => $handler) {
            if (preg_match($pattern, $message->text)) {
                $context = $this->runHeardMiddleware($context, $pattern, $adapter);
                if (! $context instanceof MessageContext) {
                    continue;
                }
                $handler($context);
                if ($context->isSkipped()) {
                    return;
                }
            }
        }
    }

    public function handleWebhook(string $adapterName, ServerRequestInterface $request): ResponseInterface
    {
        $adapter = $this->resolveAdapter(
            name: $adapterName,
            request: $request
        );

        if (! $adapter instanceof Adapter) {
            throw new ResourceNotFoundException("Adapter '{$adapterName}' is not configured.");
        }

        $this->initAdapter(
            adapter: $adapter
        );

        return $this->middleware->processWebhook(
            request: $request,
            handler: function (ServerRequestInterface $request) use ($adapter): ResponseInterface {
                $ack = $adapter->verifyWebhook($request);

                if ($ack instanceof ResponseInterface) {
                    return $ack;
                }

                // Batched webhook processing — handles multiple events in one payload
                // (Messenger, Instagram, WhatsApp batch entries for efficiency)
                if ($adapter instanceof HandlesBatchedWebhooks) {
                    foreach ($adapter->parseBatchedWebhook($request) as $event) {
                        $eventAdapter = $this->middleware->processWebhookEvent(
                            $event,
                            $adapter,
                            fn (WebhookEvent $e, Adapter $a): Adapter => $a,
                        );
                        $this->dispatchWebhookEvent($eventAdapter, $event, $request);
                    }

                    return $this->webhookResponse($adapter);
                }

                // Check for action (button clicks, etc.)
                if ($adapter instanceof HandlesActions) {
                    $actionData = $adapter->parseAction($request);
                    if ($actionData !== null) {
                        $user = $actionData['author'] ?? new Author(
                            id: $actionData['userId'],
                            isMe: $actionData['isMe'],
                            isBot: $actionData['isBot'],
                        );

                        $this->processAction(
                            adapter: $adapter,
                            threadId: $actionData['threadId'],
                            actionId: $actionData['actionId'],
                            value: $actionData['value'] ?? null,
                            messageId: $actionData['messageId'],
                            user: $user,
                            triggerId: $actionData['triggerId'] ?? null,
                            raw: $actionData['raw'] ?? null,
                            originId: $actionData['originId'] ?? null,
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
                        $user = $slashData['author'] ?? new Author(
                            id: $slashData['userId'],
                            isMe: $slashData['isMe'],
                            isBot: $slashData['isBot'],
                        );

                        $this->processSlashCommand(
                            adapter: $adapter,
                            channelId: $slashData['channelId'],
                            command: $slashData['command'],
                            text: $slashData['text'],
                            user: $user,
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
                            user: $modalData['author'] ?? new Author(
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
                            user: $modalData['author'] ?? new Author(
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
                            user: $optionsData['author'] ?? new Author(
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
                            user: $reactionData['author'] ?? new Author(
                                id: $reactionData['userId'],
                            ),
                            added: $reactionData['added'],
                            rawEmoji: $reactionData['rawEmoji'],
                            raw: $reactionData['raw'] ?? null,
                            originId: $reactionData['originId'] ?? null,
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

                // Check for message costs (non-terminal — continue to other checks)
                if ($adapter instanceof HandlesMessageCosts) {
                    $costData = $adapter->parseMessageCost($request);
                    if ($costData !== null) {
                        $this->processMessageCost(
                            threadId: $costData['threadId'],
                            messageIds: $costData['messageIds'],
                            userId: $costData['userId'],
                            price: $costData['price'],
                            raw: $costData['raw'] ?? null,
                            originId: $costData['originId'] ?? null,
                        );
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
                                originId: $statusData['originId'] ?? null,
                            );
                        } elseif ($statusData['type'] === 'read') {
                            $this->processMessageRead(
                                threadId: $statusData['threadId'],
                                userId: $statusData['userId'],
                                raw: $statusData['raw'] ?? null,
                                timestamp: $statusData['timestamp'] ?? null,
                                originId: $statusData['originId'] ?? null,
                            );
                        } elseif ($statusData['type'] === 'failed') {
                            $this->processMessageFailed(
                                threadId: $statusData['threadId'],
                                messageIds: $statusData['messageIds'],
                                userId: $statusData['userId'],
                                raw: $statusData['raw'] ?? null,
                                originId: $statusData['originId'] ?? null,
                            );
                        }

                        return $this->webhookResponse($adapter);
                    }
                }

                $message = $adapter->parseWebhook($request);
                $this->processMessage($adapter, $message->threadId, $message, $request);

                return $this->webhookResponse($adapter);
            }
        );
    }

    private function dispatchWebhookEvent(Adapter $adapter, WebhookEvent $event, ?ServerRequestInterface $request = null): void
    {
        match ($event->type) {
            WebhookEvent::TYPE_MESSAGE => $this->processMessage(
                adapter: $adapter,
                threadId: $event->threadId,
                message: $event->payload,
                request: $request,
            ),
            WebhookEvent::TYPE_ACTION => $this->processAction(
                adapter: $adapter,
                threadId: $event->threadId,
                actionId: $event->payload['actionId'],
                value: $event->payload['value'] ?? null,
                messageId: $event->payload['messageId'],
                user: $event->payload['author'] ?? new Author(
                    id: $event->payload['userId'],
                    isMe: $event->payload['isMe'],
                    isBot: $event->payload['isBot'],
                ),
                triggerId: $event->payload['triggerId'] ?? null,
                raw: $event->payload['raw'] ?? null,
                originId: $event->originId,
            ),
            WebhookEvent::TYPE_REACTION => $this->processReaction(
                adapter: $adapter,
                threadId: $event->threadId,
                emoji: $event->payload['emoji'],
                messageId: $event->payload['messageId'],
                user: $event->payload['author'] ?? new Author(id: $event->payload['userId']),
                added: $event->payload['added'],
                rawEmoji: $event->payload['rawEmoji'],
                raw: $event->payload['raw'] ?? null,
                originId: $event->originId,
            ),
            WebhookEvent::TYPE_SLASH_COMMAND => $this->processSlashCommand(
                adapter: $adapter,
                channelId: $event->payload['channelId'],
                command: $event->payload['command'],
                text: $event->payload['text'],
                user: $event->payload['author'] ?? new Author(
                    id: $event->payload['userId'],
                    isMe: $event->payload['isMe'],
                    isBot: $event->payload['isBot'],
                ),
                raw: $event->payload['raw'] ?? null,
                triggerId: $event->payload['triggerId'] ?? null,
            ),
            WebhookEvent::TYPE_STATUS => $this->dispatchStatusEvent($event),
            WebhookEvent::TYPE_MESSAGE_COST => $this->processMessageCost(
                threadId: $event->threadId,
                messageIds: $event->payload['messageIds'],
                userId: $event->payload['userId'],
                price: $event->payload['price'] ?? null,
                raw: $event->payload['raw'] ?? null,
                originId: $event->originId,
            ),
        };
    }

    private function dispatchStatusEvent(WebhookEvent $event): void
    {
        match ($event->payload['type']) {
            'delivered' => $this->processMessageDelivered(
                threadId: $event->threadId,
                messageIds: $event->payload['messageIds'],
                userId: $event->payload['userId'],
                raw: $event->payload['raw'] ?? null,
                originId: $event->originId,
            ),
            'read' => $this->processMessageRead(
                threadId: $event->threadId,
                userId: $event->payload['userId'],
                raw: $event->payload['raw'] ?? null,
                timestamp: $event->payload['timestamp'] ?? null,
                originId: $event->originId,
            ),
            'failed' => $this->processMessageFailed(
                threadId: $event->threadId,
                messageIds: $event->payload['messageIds'],
                userId: $event->payload['userId'],
                raw: $event->payload['raw'] ?? null,
                originId: $event->originId,
            ),
            default => null,
        };
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

        if ($this->broadcaster instanceof BroadcastAdapter) {
            $this->broadcaster->connect();
        }

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

        if ($this->broadcaster instanceof BroadcastAdapter && ! $this->initialized) {
            $this->broadcaster->connect();
        }

        $adapter->initialize($this);
    }

    public function shutdown(): void
    {
        if (! $this->initialized && ! $this->stateInitialized) {
            return;
        }

        foreach ($this->adapters as $adapter) {
            $adapter->disconnect();
        }

        if ($this->broadcaster instanceof BroadcastAdapter) {
            $this->broadcaster->disconnect();
        }

        $this->state->disconnect();

        $this->initialized = false;
        $this->stateInitialized = false;
    }

    public function addWebhookMiddleware(WebhookMiddleware $middleware, int $priority = 0): self
    {
        $this->middleware->addWebhook($middleware, $priority);

        return $this;
    }

    public function addReceivingMiddleware(ReceivingMiddleware $middleware, int $priority = 0): self
    {
        $this->middleware->addReceiving($middleware, $priority);

        return $this;
    }

    public function addSendingMiddleware(SendingMiddleware $middleware, int $priority = 0): self
    {
        $this->middleware->addSending($middleware, $priority);

        return $this;
    }

    public function addWebhookEventMiddleware(WebhookEventMiddleware $middleware, int $priority = 0): self
    {
        $this->middleware->addWebhookEvent($middleware, $priority);

        return $this;
    }

    public function addSentMiddleware(SentMiddleware $middleware, int $priority = 0): self
    {
        $this->middleware->addSent($middleware, $priority);

        return $this;
    }

    public function addHeardMiddleware(HeardMiddleware $middleware, int $priority = 0): self
    {
        $this->middleware->addHeard($middleware, $priority);

        return $this;
    }

    public function getMiddleware(): MiddlewareDispatcher
    {
        return $this->middleware;
    }

    /**
     * @return array<SendingMiddleware>
     *
     * @deprecated Use getMiddleware()->getMiddlewares('sending') instead
     */
    public function getSendingMiddleware(): array
    {
        return $this->middleware->getMiddlewares('sending');
    }

    private function runReceivingMiddleware(?Message $message, Adapter $adapter): ?Message
    {
        return $this->middleware->processReceiving(
            message: $message,
            adapter: $adapter,
            handler: fn (?Message $msg): ?Message => $msg
        );
    }

    private function runHeardMiddleware(MessageContext $context, string $pattern, Adapter $adapter): ?MessageContext
    {
        return $this->middleware->processHeard(
            context: $context,
            pattern: $pattern,
            adapter: $adapter,
            handler: fn (?MessageContext $c): ?MessageContext => $c
        );
    }

    private function dispatch(object $event): object
    {
        return $this->dispatcher->dispatch($event);
    }

    /**
     * @return array<int, array{label: string, value: string}>|null
     */
    private function dispatchOptionsLoadHandlers(OptionsLoadEvent $event): ?array
    {
        /**
         * @var iterable<callable> $listeners
         */
        $listeners = $this->listenerProvider->getListenersForEvent($event);

        foreach ($listeners as $listener) {
            $result = $listener($event);
            if (is_array($result)) {
                return $result;
            }
        }

        return null;
    }
}
