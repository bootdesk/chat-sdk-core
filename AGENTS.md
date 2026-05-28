# core

Framework-agnostic PHP Chat SDK core. Namespace: `BootDesk\ChatSDK\Core`

## entrypoints
- `Chat` — orchestrator (handleWebhook, processMessage, onNewMessage, onSlashCommand, etc.)
- `Thread` — primary send/receive interface (post, edit, delete, fetchMessages, subscribe, startTyping)
- `Channel` — channel-level operations
- `Message` — immutable incoming message value object
- `PostableMessage` — outgoing message builder (text, markdown, card, template)
- `SentMessage` — result of posting with id/threadId/timestamp

## key contracts (src/Contracts/)
- `Adapter` — implement for each platform (getName, verifyWebhook, parseWebhook, encodeThreadId, postMessage, etc.)
- `StateAdapter` — pluggable state backend (locks, subscribe, queue, modal context, key-value)
- `ConcurrencyHandler` — pluggable concurrency control. Default: `DefaultConcurrencyHandler` (sync strategies with locks/queues/usleep). Framework packages replace with async implementations (e.g., `QueueConcurrencyHandler` in Laravel).
- `FormatConverter` — platform markdown ↔ CommonMark AST
- `AdapterResolver` — dynamic adapter resolution (multi-tenant)
- `FileUploadConverter` — convert binary `FileUpload` to URL-based `Attachment` (for adapters without native uploads)
- `ReceivingMiddleware` / `SendingMiddleware` / `WebhookMiddleware` / `WebhookEventMiddleware` / `SentMiddleware` / `HeardMiddleware` — middleware pipeline
- `HandlesActions` / `HandlesSlashCommands` / `HandlesReactions` — optional adapter contracts for incoming events
- `HandlesModals` / `HandlesOptionsLoad` / `HandlesSlackEvents` — optional adapter contracts for modals, external selects, Slack events
- `SupportsModals` — optional adapter contract for opening modals from handlers
- `SupportsEditMessages` / `SupportsDeleteMessages` — marker contracts for adapters that support editing/deleting messages (use `instanceof` instead of catching exceptions)
- `AdapterHasMessagingWindow` — optional adapter contract for platforms with limited messaging windows (e.g., WhatsApp 24h)
- `RequiresSyncResponse` / `RequiresAsyncResponse` — marker contracts declaring adapter's sync/async preference for concurrency handling

## architecture notes
- Thread IDs are canonical: `"{adapter}:{platformChannelId}:{platformThreadId}"`
- Concurrency: pluggable via `ConcurrencyHandler` interface + `Strategy` enum. `DefaultConcurrencyHandler` handles all 4 strategies (drop/queue/debounce/concurrent) synchronously with locks. Framework packages can replace with async implementations.
- `Strategy` enum: `Drop`, `Queue`, `Debounce`, `Concurrent` — config key `concurrency` maps to these.
- `RequiresSyncResponse` adapters always process inline (WebAdapter, DiscordAdapter). `RequiresAsyncResponse` adapters always defer to async (Slack, Telegram, Meta platforms). No marker = adaptive (inline when no contention, strategy on contention).
- Deduplication via `StateAdapter::setIfNotExists` (300s TTL)
- Event system: ReactionEvent, ActionEvent, SlashCommandEvent, ModalSubmitEvent, ModalCloseEvent, OptionsLoadEvent, AssistantThreadStartedEvent, AssistantContextChangedEvent, AppHomeOpenedEvent, MemberJoinedChannelEvent
- `ActionEvent` and `SlashCommandEvent` have `openModal(Modal $modal)` via `OpensModals` trait
- `ReactionEvent` has `added: bool` and `rawEmoji: string` properties

## conversations
- `Conversations/Conversation` — base class for multi-turn dialogs
- `Conversations/ConversationManager` — intercept + lifecycle
- `Conversations/AskResponse` — user reply value object

## cards
- `Cards/Card` + Section, Button, Image, CardElement, ButtonStyle — cross-platform interactive messages
- Each adapter has a `XxxCards` class that converts to platform-native format

## modals
- `Modals/Modal` — modal form builder with children (TextInput, Select, ExternalSelect, RadioSelect)
- `Modals/TextInput`, `Modals/Select`, `Modals/ExternalSelect`, `Modals/RadioSelect`, `Modals/SelectOption`
- Platform-agnostic value objects converted to platform-native via each adapter
- Slack uses `SlackModalConverter` to convert to Block Kit views

## concerns
- `Concerns/OpensModals` — trait used by `ActionEvent` and `SlashCommandEvent` to expose `openModal(Modal $modal): ?array`

## support
- `Support/AdapterRegistry` — static register(name, class) / get(name); populated by adapter register.php files
- `Support/Arr` / `Support/Str` — polyfill helpers
- `Support/NullFileUploadConverter` — default `FileUploadConverter` that throws `AdapterException`

## attachments
- `Attachment` — URL-based media value object (type, url, name, mimeType, size, fetchData, fetchMetadata)
- `FileUpload` — binary file upload value object (data, filename, mimeType); supports resource or string data
- `FileUpload::fromFilename(string $path)` — factory that opens file, infers MIME via `mime_content_type()`
- Adapters with native upload support (Slack, Telegram, Discord) handle `FileUpload` directly
- Other adapters convert via `FileUploadConverter` (if registered) or throw `AdapterException`

## markdown
- `Markdown/` — CommonMark-based conversion pipeline for cross-platform formatting

## testing
- Tests use `MemoryStateAdapter` + `MockAdapter` from `tests/Helpers/`
- `createTestMessage(text:, threadId:, author:, isMention:, isDM:)` helper in `tests/Helpers/functions.php`
- Named phpunit suites: `Core` suite for this package

## constants
- PHP 8.2+ (readonly properties, enums, match)
- `declare(strict_types=1)` used in contracts and helpers (inconsistent in core classes)
- PSR deps: http-client, http-message, http-factory, psr/log
- `league/commonmark` for AST formatting, `ramsey/uuid` for IDs
