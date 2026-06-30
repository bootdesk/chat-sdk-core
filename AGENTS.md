# core

Framework-agnostic PHP Chat SDK core. Namespace: `BootDesk\ChatSDK\Core`

## entrypoints

- `Chat` — orchestrator (handleWebhook, openDM, processMessage, onNewMessage, onSlashCommand, etc.)
- `Thread` — primary send/receive interface (post, edit, delete, fetchMessages, subscribe, startTyping)
- `Channel` — channel-level operations
- `Message` — immutable incoming message value object
- `PostableMessage` — outgoing message builder (text, markdown, card, template)
- `SentMessage` — result of posting with id/threadId/timestamp

## key contracts (src/Contracts/)

- `Adapter` — implement for each platform (getName, verifyWebhook, parseWebhook, encodeThreadId, postMessage, etc.)
- `StateAdapter` — pluggable state backend (locks, subscribe, queue, modal context, key-value)
- `ConcurrencyHandler` — pluggable concurrency control. Default: `DefaultConcurrencyHandler` (sync strategies with locks/queues/usleep). `process()` accepts optional `?ServerRequestInterface $request` — framework packages serialize it for async job processing so `AdapterResolver` receives the original request even in queued context. Framework packages replace with async implementations (e.g., `QueueConcurrencyHandler` in Laravel).
- `TranscriptsApi` — per-user message history (append, list, count, delete); `DefaultTranscriptsApi` in `Transcript/`
- `IdentityResolver` — resolves `Author` → user key string for transcripts
- `FormatConverter` — platform markdown ↔ CommonMark AST
- `AdapterResolver` — dynamic adapter resolution (multi-tenant)
- `FileUploadConverter` — convert binary `FileUpload` to URL-based `Attachment` (for adapters without native uploads)
- `ReceivingMiddleware` / `SendingMiddleware` / `WebhookMiddleware` / `WebhookEventMiddleware` / `SentMiddleware` / `HeardMiddleware` — middleware pipeline. All `Chat::add*Middleware()` methods accept optional `int $priority` (default `0`, higher = earlier). `MiddlewareDispatcher` sorts lazily by priority descending with stable insertion order. Built-in `TranscriptSentMiddleware` registered at `-100`.
- `HandlesActions` / `HandlesSlashCommands` / `HandlesReactions` — optional adapter contracts for incoming events
- `HandlesModals` / `HandlesOptionsLoad` / `HandlesSlackEvents` — optional adapter contracts for modals, external selects, Slack events
- `SupportsModals` — optional adapter contract for opening modals from handlers
- `SupportsEditMessages` / `SupportsDeleteMessages` / `SupportsEditThread` — marker contracts for adapters that support editing/deleting messages and threads (use `instanceof` instead of catching exceptions)
- `AdapterHasMessagingWindow` — optional adapter contract for platforms with limited messaging windows (e.g., WhatsApp 24h)
- `RequiresSyncResponse` / `RequiresAsyncResponse` — marker contracts declaring adapter's sync/async preference for concurrency handling
- `HasDynamicSyncPreference` — optional contract for adapters whose sync/async preference is runtime-dynamic (e.g., WebAdapter with `asyncMode`). ConcurrencyHandlers check this first, then fall back to marker interfaces. Method: `requiresSyncResponse(): bool`.
- `MustRehydrateAttachments` — adapter contract for auto-rehydrating `Attachment::fetchData` after queue deserialization. `Chat::dispatchIncomingMessage()` checks this interface and calls `rehydrateAttachment()` on each attachment.
- **CompositeInterfaces** (`src/Contracts/CompositeInterfaces/`): `HandlesInteractions` (extends Actions+Reactions+SlashCommands), `SupportsMessageMutability` (extends EditMessages+DeleteMessages+EditThread) — group common contracts for cleaner `implements` declarations

## architecture notes

- Thread IDs are canonical: `"{adapter}:{platformChannelId}:{platformThreadId}"`
- Concurrency: pluggable via `ConcurrencyHandler` interface + `Strategy` enum. `DefaultConcurrencyHandler` handles all 4 strategies (drop/queue/debounce/concurrent) synchronously with locks. Framework packages can replace with async implementations.
- `Strategy` enum: `Drop`, `Queue`, `Debounce`, `Concurrent` — config key `concurrency` maps to these.
- `RequiresSyncResponse` adapters always process inline (DiscordAdapter). `RequiresAsyncResponse` adapters always defer to async (Slack, Telegram, Meta platforms). `HasDynamicSyncPreference` adapters decide at runtime (WebAdapter depends on `asyncMode`). No marker = adaptive (inline when no contention, strategy on contention).
- Deduplication via `StateAdapter::setIfNotExists` (300s TTL)
- Event system: ReactionEvent, ActionEvent, SlashCommandEvent, ModalSubmitEvent, ModalCloseEvent, OptionsLoadEvent, AssistantThreadStartedEvent, AssistantContextChangedEvent, AppHomeOpenedEvent, MemberJoinedChannelEvent, MessageCostEvent, UnsupportedOperationEvent
- `ActionEvent` and `SlashCommandEvent` have `openModal(Modal $modal)` via `OpensModals` trait
- `ReactionEvent` has `added: bool` and `rawEmoji: string` properties

## conversations

- `Conversations/Conversation` — base class for multi-turn dialogs.
  Entry: `abstract public function run(Thread, Message): void`.
  Helpers (use `$this->thread`, no thread param): `ask(question, step, data)`,
  `repeat(?message)` (re-post last question), `skip(step, message, ?data)`
  (jump to step immediately), `say(text)`, `startConversation(class, message)`
  (replace — no stack, calls run now), `pause(childClass, message)` (stack —
  child runs now, end restores parent + replays last question), `end()`
  (pop stack or clear).
  Non-message intercepts: `onAction(Thread, ActionEvent): ?bool`,
  `onSlashCommand(Thread, SlashCommandEvent): ?bool`,
  `onReaction(Thread, ReactionEvent): ?bool`. Return true to consume,
  null to fall through to normal event dispatch.
- `Conversations/ConversationManager` — intercept + lifecycle.
  `start(class, thread, message)` clears state, calls `$conv->run()`.
  `intercept(thread, message)` calls stored step, loops for skip chains
  (max depth 10). `interceptAction/Reaction/SlashCommand()` check active
  conv before event dispatch.
- `Conversations/ConversationState` — static helpers for state
  read/write/clear under the `_conversation` key.

## cards

- `Cards/Card` + Section, Button, Image, CardElement, ButtonStyle — cross-platform interactive messages
- Each adapter has a `XxxCards` class that converts to platform-native format

## modals

- `Modals/Modal` — modal form builder with children (TextInput, Select, ExternalSelect, RadioSelect)
- `Modals/TextInput`, `Modals/Select`, `Modals/ExternalSelect`, `Modals/RadioSelect`, `Modals/SelectOption`
- Platform-agnostic value objects converted to platform-native via each adapter
- Slack uses `SlackModalConverter` to convert to Block Kit views

## transcripts

- `Contracts/TranscriptsApi` — interface for per-user message history
- `Transcript/DefaultTranscriptsApi` — state-backed impl, stores entries with `direction: 'incoming'|'outgoing'`
- `Transcript/TranscriptSentMiddleware` — auto-wired `SentMiddleware` that records outgoing bot replies
- Incoming messages recorded in `Chat::dispatchIncomingMessage()`; outgoing recorded via `TranscriptSentMiddleware` using `transcript_user:{threadId}` state mapping
- Override by passing a `TranscriptsApi` instance directly to `Chat` constructor's `$transcripts` param

## concerns

- `Concerns/OpensModals` — trait used by `ActionEvent` and `SlashCommandEvent` to expose `openModal(Modal $modal): ?array`

## support

- `Support/AdapterRegistry` — static register(name, class) / get(name); populated by adapter register.php files
- `Support/Arr` / `Support/Str` — polyfill helpers
- `Support/NullFileUploadConverter` — default `FileUploadConverter` that throws `AdapterException`

## emoji

- `data/emoji.json` — 94-entry emoji map with slack/gchat/github formats per normalized name
- `Support/EmojiValue` — singleton immutable value object with `get(string): self`, `__toString()`, `toJson()`
- `Support/EmojiResolver` — platform↔normalized conversion; loads from emoji.json at runtime
  - `fromSlack()`, `toSlack()`, `fromGChat()`, `toGChat()`, `fromTeams()`, `toDiscord()`, `fromGithub()`, `toGithub()`
  - `matches(rawEmoji, normalized): bool` — check equivalence across formats
  - `extend(array $customMap): void` — add/override mappings
  - `static convertPlaceholders(text, platform, ?resolver): string` — replace `{{emoji:name}}` with platform format
  - `static default(): self` — singleton loaded from emoji.json
- Adapters auto-normalize `emoji` field in `ReactionEvent`; `rawEmoji` preserves original
- Adapters auto-convert `{{emoji:...}}` placeholders in outgoing message text via `convertPlaceholders()`
- Slack/Discord/Telegram/Messenger/WhatsApp/Instagram all accept optional `?EmojiResolver $emojiResolver = null` constructor param

## attachments

- `Attachment` — URL-based media value object (type, url, name, mimeType, size, fetchData, fetchMetadata, lat, lng, address)
- `Attachment::fetchData` — typed `(callable(Attachment): StreamInterface)|null` via PHPDoc. Constructor rejects non-null, non-callable values. Stores `[$adapter, 'fetchMedia']` pattern (no closures) for serialization safety.
- `Attachment::read(): ?StreamInterface` — decodes `data:` URLs via `Nyholm\Psr7\Stream`; otherwise calls `($this->fetchData)($this)`. Returns PSR-7 StreamInterface.
- `Attachment::isDataUrl(): bool` — returns true when `url` starts with `data:`
- `Attachment::location(float $lat, float $lng, ?string $name = null, ?string $address = null): self` — factory that creates a `type: 'location'` attachment with GeoJSON data URL (`data:application/geo+json;base64,...`)
- `Attachment::withFetchOptions(callable $fetchData, ?array $fetchMetadata = null): self` — immutable helper that creates new Attachment with same type/url/name/mimeType/size/width/height/lat/lng/address but overridden fetchData/fetchMetadata. When fetchMetadata is null, preserves existing metadata from original.
- `Attachment::__serialize()` — excludes `fetchData` (not serializable). Includes `lat`, `lng`, `address`.
- `Attachment::__unserialize()` — restores props, sets `fetchData = null` and `lat`/`lng`/`address` with null coalesce for backward compat. Adapter's `MustRehydrateAttachments::rehydrateAttachment()` restores fetchData after deserialization.
- `FileUpload` — binary file upload value object (data, filename, mimeType); supports resource or string data
- `FileUpload::fromFilename(string $path)` — factory that opens file, infers MIME via `mime_content_type()`
- Adapters with native upload support (Slack, Telegram, Discord) handle `FileUpload` directly
- Other adapters convert via `FileUploadConverter` (if registered) or throw `AdapterException`

## threads

- `ThreadInfo` — immutable value object with fields: `id`, `channelId`, `title`, `messageCount`, `topic`, `iconCustomEmojiId`, `isArchived`
- `ThreadInfo::withParameters(array $overrides)` — returns new `ThreadInfo` with selected fields overridden (identity fields `id`/`channelId` preserved)
- `SupportsEditThread` contract — adapter declares it can update threads via `editThread(string $threadId, ThreadInfo $threadInfo): ThreadInfo`
- `Thread::update(ThreadInfo $threadInfo)` — convenience method, checks `instanceof SupportsEditThread` before calling adapter
- `editThread` implementations (Telegram: `editForumTopic`/`setChatTitle`/`setChatDescription`/`closeForumTopic`/`reopenForumTopic`; Slack: `conversations.rename`/`setTopic`/`archive`/`unarchive`; Discord: PATCH `/channels/{id}`; GitHub: PATCH issue/PR title; Linear: GraphQL `issueUpdate`)

## markdown

- `Markdown/` — CommonMark-based conversion pipeline for cross-platform formatting
- `Markdown/BaseFormatConverter` — base class. `toAst()`/`fromAst()` cycle. `renderMarkdown()` uses `MarkdownRenderer` (node-renderer-dispatcher). `registerRenderers()` sets up GFM extensions + default renderers. Override in adapters to swap renderers for platform-specific output.
- `Markdown/Renderer/MarkdownRenderer` — main renderer. Implements `ChildNodeRendererInterface` + `DocumentRendererInterface`. Dispatches each AST node to registered renderers by class. `renderNodes()` adds `\n\n` between `AbstractBlock` siblings.
- `Markdown/Renderer/` — one file per node type (TextRenderer, StrongRenderer, EmphasisRenderer, etc.). Each implements `NodeRendererInterface`. Core provides 12 CommonMark + 3 GFM renderers.
- `Markdown/Renderer/Meta/` — Meta platform renderers (`.`, `_`, `~`, plain link/image/heading, skip thematic break, `*`-bullet lists). Used by WhatsApp/Messenger/Instagram adapters via `registerRenderers()` override.
- Platform-specific renderers live in adapter package (e.g., `adapter-telegram/src/Renderer/` for Telegram MarkdownV2 with char escaping).
- `renderNodes()` adds `\n\n` separator between `AbstractBlock` children. Node renderers that need different joining (e.g., list items with `\n`) must iterate children directly.
- `renderAsGFM(string|Document $input): string` — converts platform text or AST to clean GFM using a separate `MarkdownRenderer` instance registered with only core renderers (never affected by platform overrides).

## testing

- Tests use `MemoryStateAdapter` + `MockAdapter` from `tests/Helpers/`
- `createTestMessage(text:, threadId:, author:, isMention:, isDM:)` helper in `tests/Helpers/functions.php`
- Named phpunit suites: `Core` suite for this package

## constants

- PHP 8.2+ (readonly properties, enums, match)
- `declare(strict_types=1)` used in contracts and helpers (inconsistent in core classes)
- PSR deps: http-client, http-message, http-factory, psr/log
- `league/commonmark` for AST formatting, `ramsey/uuid` for IDs
