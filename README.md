# bootdesk/chat-sdk-core

Framework-agnostic core SDK for building chat bots in PHP.

## Installation

```bash
composer require bootdesk/chat-sdk-core
```

## Chat class

The main entry point. Accepts a state adapter, adapters, configuration, and optional `ConcurrencyHandler`.

```php
use BootDesk\ChatSDK\Core\Chat;
use BootDesk\ChatSDK\Core\State\MemoryStateAdapter;
use BootDesk\ChatSDK\Core\Concurrency\DefaultConcurrencyHandler;

$chat = new Chat(
    state: new MemoryStateAdapter(),
    userName: 'MyBot',
    config: [],
    concurrencyHandler: new DefaultConcurrencyHandler($state, ['concurrency' => 'drop']),
);

// Register handlers
$chat->onNewMessage('/^hello$/i', function (MessageContext $ctx) {
    $ctx->thread->post('Hey there!');
});

$chat->onDirectMessage(function (MessageContext $ctx) {
    $ctx->thread->post('You DMd me!');
});

$chat->onNewMention(function (MessageContext $ctx) {
    $ctx->thread->post('You mentioned me!');
});
```

## Thread

Represents a conversation thread on any platform. Retrieved by platform-specific identifier.

```php
$thread = $chat->thread('slack:C12345');

$thread->post('Hello!');
$thread->edit('msg-id', 'Updated text');
$thread->delete('msg-id');
$thread->subscribe();
$thread->startTyping();

$thread->setState(['step' => 2]);
$state = $thread->getState();
```

## Cards

Build rich, platform-adaptive message cards with text, tables, dividers, links, buttons, and more.

### Element types

| Builder method                  | Type         | Description                               |
| ------------------------------- | ------------ | ----------------------------------------- |
| `header(string)`                | —            | Card title (rendered as bold header)      |
| `section(callable)`             | `Section`    | Grouped text + fields                     |
| `text(string, TextStyle)`       | `Text`       | Styled text (`Bold`, `Muted`, `Plain`)    |
| `divider()`                     | `Divider`    | Horizontal separator (`---`)              |
| `link(label, url)`              | `Link`       | Inline hyperlink                          |
| `table(headers, rows, align)`   | `Table`      | Data table with optional column alignment |
| `image(url, alt)`               | `Image`      | Embedded image                            |
| `actions(Button[])`             | `Button`     | Action buttons (triggers `onAction`)      |
| `linkButton(label, url, style)` | `LinkButton` | URL button (opens link)                   |

### Text styles

```php
use BootDesk\ChatSDK\Core\Cards\TextStyle;

TextStyle::Plain;  // default
TextStyle::Bold;   // rendered as **bold** or <b> depending on platform
TextStyle::Muted;  // rendered as _italic_ or muted color
```

### Buttons

```php
use BootDesk\ChatSDK\Core\Cards\Button;
use BootDesk\ChatSDK\Core\Cards\ButtonStyle;
use BootDesk\ChatSDK\Core\Cards\LinkButton;

// Action button — triggers onAction handler when clicked
Button::primary('Confirm', 'order_confirm', ['item' => 'Pizza']);
Button::danger('Cancel', 'order_cancel');
Button::secondary('Maybe', 'order_maybe');

// Link button — opens a URL in the client
LinkButton::primary('Open Dashboard', 'https://dash.example.com');
LinkButton::danger('Report Issue', 'https://github.com/org/repo/issues');
```

### Table with alignment

```php
use BootDesk\ChatSDK\Core\Cards\TableAlignment;

$card = Card::make()
    ->table(
        ['Service', 'Status', 'Uptime'],
        [
            ['API', '✅ Healthy', '99.9%'],
            ['Database', '✅ Connected', '99.8%'],
            ['Queue', '✅ Running', '100%'],
        ],
        [TableAlignment::Left, TableAlignment::Center, TableAlignment::Right],
    );
```

### Full example

```php
use BootDesk\ChatSDK\Core\Cards\Card;
use BootDesk\ChatSDK\Core\Cards\Button;
use BootDesk\ChatSDK\Core\Cards\ButtonStyle;
use BootDesk\ChatSDK\Core\Cards\TextStyle;
use BootDesk\ChatSDK\Core\Cards\TableAlignment;

$card = Card::make()
    ->header('System Status')
    ->text('All services are operational.', TextStyle::Bold)
    ->divider()
    ->table(
        ['Service', 'Status', 'Uptime'],
        [
            ['API', '✅ Healthy', '99.9%'],
            ['Database', '✅ Connected', '99.8%'],
            ['Queue', '✅ Running', '100%'],
        ],
        [TableAlignment::Left, TableAlignment::Center, TableAlignment::Right],
    )
    ->divider()
    ->link('View details', 'https://status.example.com')
    ->linkButton('Dashboard', 'https://dash.example.com', ButtonStyle::Primary)
    ->actions([Button::primary('Refresh', 'refresh_status')]);

$thread->post($card);
```

### Card imageUrl

Set a header image that renders as a native image block on supported platforms:

```php
$card = Card::make()
    ->imageUrl('https://picsum.photos/seed/demo/800/200', 'Demo banner')
    ->header('Status')
    ->text('All good!');
```

- **Slack**: renders as a `type: image` Block Kit block before the header
- **Telegram**: uses `sendPhoto` with the card text as caption
- **Discord**: renders as `embed.image.url`

### Sections with fields

```php
$card = Card::make()
    ->header('Deploy Ready')
    ->section(fn ($s) => $s
        ->text('Build passed on main')
        ->fields(['Branch' => 'main', 'Status' => 'passing'])
    )
    ->actions([Button::primary('Deploy', 'deploy')]);
```

### Platform rendering

Each adapter converts cards to its native format:

| Adapter   | Format                                               |
| --------- | ---------------------------------------------------- |
| Slack     | Block Kit (header, section, divider, image, actions) |
| Discord   | Embed + Action Row components                        |
| Telegram  | HTML text + inline keyboard                          |
| WhatsApp  | Interactive reply buttons or text fallback           |
| Messenger | Generic/Button template                              |
| GitHub    | Markdown (headings, pipe tables, links)              |
| Linear    | Markdown (same as GitHub)                            |

## Attachments & File Uploads

Send URL-based media attachments or binary file uploads with any message.

### URL-based Attachments

```php
use BootDesk\ChatSDK\Core\Attachment;
use BootDesk\ChatSDK\Core\PostableMessage;

$message = new PostableMessage(
    content: 'Here is a photo:',
    attachments: [
        new Attachment('image', 'https://picsum.photos/seed/foo/800/600', 'Photo', 'image/jpeg'),
    ],
);
$thread->post($message);
```

All adapters support URL-based attachments. Platforms without native attachment rendering fall back to text links.

### Binary File Uploads

```php
use BootDesk\ChatSDK\Core\FileUpload;
use BootDesk\ChatSDK\Core\PostableMessage;

// From file path
$upload = FileUpload::fromFilename('/path/to/document.pdf');

// From string data
$upload = new FileUpload(file_get_contents($url), 'photo.jpg', 'image/jpeg');

$message = new PostableMessage(
    content: 'Here is your file:',
    files: [$upload],
);
$thread->post($message);
```

**Native support:** Slack (3-step API), Telegram (`sendDocument`), Discord (`files[N]` multipart).

**Other platforms:** Binary files are converted to URL-based attachments via a `FileUploadConverter`. If no converter is registered, `AdapterException` is thrown.

### FileUploadConverter

```php
use BootDesk\ChatSDK\Core\Contracts\FileUploadConverter;
use BootDesk\ChatSDK\Core\Attachment;
use BootDesk\ChatSDK\Core\FileUpload;

class S3FileUploader implements FileUploadConverter
{
    public function upload(FileUpload $file, Adapter $adapter): Attachment
    {
        $url = $this->s3->upload($file->getData(), $file->filename);
        return new Attachment('file', $url, $file->filename, $file->mimeType);
    }
}
```

In Laravel, bind it in your service provider:

```php
$this->app->bind(FileUploadConverter::class, S3FileUploader::class);
```

## Conversations

Define multi-turn dialog flows by extending the `Conversation` class.

```php
use BootDesk\ChatSDK\Core\Conversations\Conversation;
use BootDesk\ChatSDK\Core\Thread;
use BootDesk\ChatSDK\Core\Message;

class OrderConversation extends Conversation
{
    public function start(Thread $thread, Message $message): void
    {
        $this->ask($thread, 'What would you like to order?', 'handleOrder');
    }

    public function handleOrder(Thread $thread, Message $message): void
    {
        $this->say($thread, "You ordered: {$message->text}");
        $this->end($thread);
    }
}
```

Start a conversation:

```php
$chat->conversationManager->start(OrderConversation::class, $thread, $message);
```

## Middleware

Three middleware interfaces for intercepting different stages:

- **ReceivingMiddleware** -- Intercept inbound messages before handlers run
- **SendingMiddleware** -- Intercept outbound messages before they are delivered
- **WebhookMiddleware** -- Intercept raw webhook payloads before parsing

## Extending Adapters

All adapters use `protected` members for extensibility. Extend any adapter to customize behavior:

```php
use BootDesk\ChatSDK\Telegram\TelegramAdapter;

class MyTelegramAdapter extends TelegramAdapter
{
    protected function apiCall(string $method, array $params): array
    {
        // Add custom logging, retry logic, etc.
        return parent::apiCall($method, $params);
    }

    protected function buildMessageParams(PostableMessage $message): array
    {
        $params = parent::buildMessageParams($message);

        // Add custom parameters
        $params['disable_web_page_preview'] = true;

        return $params;
    }
}
```

Register your custom adapter via `AdapterRegistry`:

```php
use BootDesk\ChatSDK\Core\Support\AdapterRegistry;

// Register in a service provider or bootstrap file

// Replace an existing adapter
AdapterRegistry::register('telegram', MyTelegramAdapter::class);

// Or register as a new adapter
AdapterRegistry::register('telegram-custom', MyTelegramAdapter::class);
```

**With AdapterResolver:** Dynamic resolution tries resolver first (tenant-specific), then falls back to static adapters from config (global default). This allows tenants to override specific adapters while using global defaults for others.

## StateAdapter interface

The state adapter handles persistence, pub/sub, locking, and queuing. Methods:

| Method        | Purpose                                                  |
| ------------- | -------------------------------------------------------- |
| `connect`     | Establish connection to state store                      |
| `disconnect`  | Close connection                                         |
| `subscribe`   | Subscribe to a channel                                   |
| `unsubscribe` | Unsubscribe from a channel                               |
| `acquireLock` | Acquire a named lock with TTL (returns `Lock` or `null`) |
| `releaseLock` | Release a previously acquired lock                       |
| `extendLock`  | Extend a lock's TTL (returns `bool`)                     |
| `get`         | Retrieve a value by key                                  |
| `set`         | Store a value by key with optional TTL                   |
| `delete`      | Remove a value by key                                    |
| `enqueue`     | Add item to a queue (max size configurable)              |
| `dequeue`     | Remove and return item from a queue                      |
| `queueDepth`  | Get current queue size                                   |

**Locks** are used for concurrency control (drop/queue/debounce strategies). **Queues** store pending messages when `concurrency: queue` is set.

Concurrency is pluggable via `ConcurrencyHandler`. The core provides `DefaultConcurrencyHandler` (sync/blocking with `usleep` for debounce). Framework packages (e.g., Laravel) replace it with async implementations that dispatch jobs to workers. Adapters can declare sync/async preference via `RequiresSyncResponse` and `RequiresAsyncResponse` marker interfaces.

## MessageContext

Passed to every event handler.

- **Properties:** `thread`, `message`, `transcripts`
- **Methods:** `skip()`, `setState()`, `getState()`

## Event handlers

| Method                      | Pattern    | Description                       |
| --------------------------- | ---------- | --------------------------------- |
| `onNewMessage`              | regex      | Match text messages               |
| `onDirectMessage`           | -          | DM-only messages                  |
| `onNewMention`              | -          | Bot was mentioned                 |
| `onSubscribedMessage`       | -          | Subscribed thread messages        |
| `onReaction`                | emoji      | Reaction added/removed            |
| `onAction`                  | actionId   | Button/action triggered           |
| `onSlashCommand`            | command    | Slash command                     |
| `onModalSubmit`             | callbackId | Modal form submitted              |
| `onModalClose`              | callbackId | Modal form closed                 |
| `onOptionsLoad`             | actionId   | External select options requested |
| `onAssistantThreadStarted`  | -          | Slack assistant thread created    |
| `onAssistantContextChanged` | -          | Slack assistant context changed   |
| `onAppHomeOpened`           | -          | Slack App Home tab opened         |
| `onMemberJoinedChannel`     | -          | User joined a channel             |

## Modals

Build and open platform-agnostic modal forms.

### Value Objects

```php
use BootDesk\ChatSDK\Core\Modals\Modal;
use BootDesk\ChatSDK\Core\Modals\TextInput;
use BootDesk\ChatSDK\Core\Modals\Select;
use BootDesk\ChatSDK\Core\Modals\ExternalSelect;
use BootDesk\ChatSDK\Core\Modals\RadioSelect;
use BootDesk\ChatSDK\Core\Modals\SelectOption;

$modal = new Modal(
    callbackId: 'feedback',
    title: 'Submit Feedback',
    submitLabel: 'Send',
    closeLabel: 'Cancel',
    notifyOnClose: true,
    children: [
        new TextInput(
            id: 'comment',
            label: 'Comment',
            placeholder: 'Enter your feedback...',
            multiline: true,
        ),
        new ExternalSelect(
            id: 'category',
            label: 'Category',
            placeholder: 'Start typing...',
            minQueryLength: 1,
        ),
    ],
);
```

### Opening Modals from Handlers

Both `ActionEvent` and `SlashCommandEvent` expose `openModal()` via the `OpensModals` trait:

```php
use BootDesk\ChatSDK\Core\Modals\Modal;
use BootDesk\ChatSDK\Core\Modals\TextInput;

$chat->onAction('feedback', function (ActionEvent $event) {
    $event->openModal(new Modal(
        callbackId: 'feedback',
        title: 'Submit Feedback',
        submitLabel: 'Send',
        children: [
            new TextInput(id: 'comment', label: 'Comment', multiline: true),
        ],
    ));
});
```

Modal context (thread info) is stored server-side and restored when the modal is submitted or closed, so handlers have access to `$event->relatedThread`.

### External Selects / Options Load

When using `ExternalSelect`, Slack sends `block_suggestion` events as the user types. Handle them with `onOptionsLoad`:

```php
$chat->onOptionsLoad(function (OptionsLoadEvent $event) {
    $prefix = strtolower($event->query);
    $all = [
        ['text' => 'Bug Report', 'value' => 'bug'],
        ['text' => 'Feature Request', 'value' => 'feature'],
    ];

    return $prefix === ''
        ? $all
        : array_values(array_filter($all, fn ($o) => str_starts_with(strtolower($o['text']), $prefix)));
});
```

The return value must be an array of `['text' => string, 'value' => string]`. The adapter converts to platform format.

### Modal Events

```php
$chat->onModalSubmit(function (ModalSubmitEvent $event) {
    $event->relatedThread?->post("Form submitted: " . json_encode($event->values));
});

$chat->onModalClose(function (ModalCloseEvent $event) {
    $event->relatedThread?->post("Form closed without submitting.");
});
```

- `ModalSubmitEvent`: `callbackId`, `values` (map of actionId → value), `user`, `viewId`, `relatedThread`
- `ModalCloseEvent`: `callbackId`, `user`, `viewId`, `relatedThread`

## Documentationn

Full API documentation: https://bootdesk.github.io/chat-sdk

## License

MIT
