<?php

namespace BootDesk\ChatSDK\Core\Tests;

use BootDesk\ChatSDK\Core\Attachment;
use BootDesk\ChatSDK\Core\Author;
use BootDesk\ChatSDK\Core\ChannelInfo;
use BootDesk\ChatSDK\Core\ChannelVisibility;
use BootDesk\ChatSDK\Core\Contracts\Adapter;
use BootDesk\ChatSDK\Core\Exceptions\AdapterException;
use BootDesk\ChatSDK\Core\FetchOptions;
use BootDesk\ChatSDK\Core\FetchResult;
use BootDesk\ChatSDK\Core\FileUpload;
use BootDesk\ChatSDK\Core\Lock;
use BootDesk\ChatSDK\Core\Message;
use BootDesk\ChatSDK\Core\MessageCostEvent;
use BootDesk\ChatSDK\Core\MessageDeliveredEvent;
use BootDesk\ChatSDK\Core\MessageReadEvent;
use BootDesk\ChatSDK\Core\Modals\ExternalSelect;
use BootDesk\ChatSDK\Core\Modals\Modal;
use BootDesk\ChatSDK\Core\Modals\RadioSelect;
use BootDesk\ChatSDK\Core\Modals\Select;
use BootDesk\ChatSDK\Core\Modals\SelectOption;
use BootDesk\ChatSDK\Core\Modals\TextInput;
use BootDesk\ChatSDK\Core\QueueEntry;
use BootDesk\ChatSDK\Core\ReactionEvent;
use BootDesk\ChatSDK\Core\SentMessage;
use BootDesk\ChatSDK\Core\Support\NullFileUploadConverter;
use BootDesk\ChatSDK\Core\Thread;
use BootDesk\ChatSDK\Core\ThreadInfo;
use BootDesk\ChatSDK\Core\UserInfo;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Parser\MarkdownParser;
use Money\Currency;
use Money\Money;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

class ValueObjectsTest extends TestCase
{
    public function test_author(): void
    {
        $author = new Author(id: 'U1', name: 'Test', email: 't@t.com', isMe: false, isBot: true);
        $this->assertSame('U1', $author->id);
        $this->assertTrue($author->isBot);
        $this->assertFalse($author->isMe);
    }

    public function test_message(): void
    {
        $author = new Author(id: 'U1');
        $msg = new Message(
            id: 'm1',
            threadId: 'slack:C1:1234',
            author: $author,
            text: 'hello',
            isMention: true,
            isDM: false,
            raw: '{"event":"message"}',
        );
        $this->assertSame('m1', $msg->id);
        $this->assertTrue($msg->isMention);
        $this->assertFalse($msg->isDM);
        $this->assertSame('{"event":"message"}', $msg->raw);
    }

    public function test_message_with_formatted_ast_serializes_and_unserializes(): void
    {
        $env = new Environment;
        $env->addExtension(new CommonMarkCoreExtension);
        $parser = new MarkdownParser($env);
        $doc = $parser->parse('Hello **world**!');

        $msg = new Message(
            id: 'm1',
            threadId: 'slack:C1:1234',
            author: new Author(id: 'U1'),
            text: 'Hello **world**!',
            formatted: $doc,
        );

        $restored = unserialize(serialize($msg));

        $this->assertInstanceOf(Document::class, $restored->formatted);
        $this->assertSame('Hello **world**!', $restored->text);
        $this->assertSame('m1', $restored->id);
        // Walk the AST to confirm node graph is intact
        $this->assertSame('world', $restored->formatted->firstChild()?->firstChild()?->next()?->firstChild()?->getLiteral());
    }

    public function test_sent_message(): void
    {
        $sent = new SentMessage(id: 's1', threadId: 't1', timestamp: '1234567890');
        $this->assertSame('s1', $sent->id);
        $this->assertSame('1234567890', $sent->timestamp);
        $this->assertSame([], $sent->additionalMessages);
        $this->assertNull($sent->raw);
    }

    public function test_sent_message_with_additional_messages(): void
    {
        $additional = [
            new SentMessage(id: 's2', threadId: 't1', timestamp: '1234567891'),
            new SentMessage(id: 's3', threadId: 't1', timestamp: '1234567892'),
        ];

        $sent = new SentMessage(
            id: 's1',
            threadId: 't1',
            timestamp: '1234567890',
            additionalMessages: $additional,
        );

        $this->assertSame('s1', $sent->id);
        $this->assertCount(2, $sent->additionalMessages);
        $this->assertSame('s2', $sent->additionalMessages[0]->id);
        $this->assertSame('s3', $sent->additionalMessages[1]->id);
        $this->assertNull($sent->raw);
    }

    public function test_sent_message_with_raw(): void
    {
        $raw = ['message_id' => 'mid.123', 'recipient_id' => 'U999'];

        $sent = new SentMessage(
            id: 's1',
            threadId: 't1',
            raw: $raw,
        );

        $this->assertSame('s1', $sent->id);
        $this->assertSame($raw, $sent->raw);
        $this->assertSame([], $sent->additionalMessages);
    }

    public function test_sent_message_with_raw_and_additional(): void
    {
        $raw = ['data' => ['id' => 'api-001']];
        $additional = [new SentMessage(id: 's2', threadId: 't1')];

        $sent = new SentMessage(
            id: 's1',
            threadId: 't1',
            additionalMessages: $additional,
            raw: $raw,
        );

        $this->assertSame($raw, $sent->raw);
        $this->assertCount(1, $sent->additionalMessages);
        $this->assertSame('s2', $sent->additionalMessages[0]->id);
    }

    public function test_sent_message_price_defaults_to_null(): void
    {
        $sent = new SentMessage(id: 's1', threadId: 't1');

        $this->assertNull($sent->price);
    }

    public function test_sent_message_with_price(): void
    {
        $price = new Money(2999, new Currency('USD'));

        $sent = new SentMessage(
            id: 's1',
            threadId: 't1',
            price: $price,
        );

        $this->assertSame($price, $sent->price);
        $this->assertSame('2999', $sent->price?->getAmount());
        $this->assertSame('USD', $sent->price?->getCurrency()->getCode());
    }

    public function test_sent_message_with_price_and_other_fields(): void
    {
        $price = new Money(1500, new Currency('EUR'));
        $raw = ['transaction_id' => 'tx_123'];
        $additional = [new SentMessage(id: 's2', threadId: 't1')];

        $sent = new SentMessage(
            id: 's1',
            threadId: 't1',
            timestamp: '1234567890',
            additionalMessages: $additional,
            raw: $raw,
            price: $price,
        );

        $this->assertSame($price, $sent->price);
        $this->assertSame('1500', $sent->price->getAmount());
        $this->assertSame('EUR', $sent->price->getCurrency()->getCode());
        $this->assertSame($raw, $sent->raw);
        $this->assertCount(1, $sent->additionalMessages);
    }

    public function test_message_cost_event(): void
    {
        $price = new Money(5, new Currency('USD'));
        $event = new MessageCostEvent(
            messageIds: ['mid1', 'mid2'],
            threadId: 'telnyx:+15551234567:+15559876543',
            userId: '+15559876543',
            price: $price,
            raw: ['some' => 'data'],
            originId: null,
        );

        $this->assertSame(['mid1', 'mid2'], $event->messageIds);
        $this->assertSame('telnyx:+15551234567:+15559876543', $event->threadId);
        $this->assertSame('+15559876543', $event->userId);
        $this->assertSame($price, $event->price);
        $this->assertSame('5', $event->price->getAmount());
        $this->assertSame('USD', $event->price->getCurrency()->getCode());
        $this->assertSame(['some' => 'data'], $event->raw);
        $this->assertNull($event->originId);
    }

    public function test_message_cost_event_null_price(): void
    {
        $event = new MessageCostEvent(
            messageIds: ['mid1'],
            threadId: 'whatsapp:phone123:16505551234',
            userId: '16505551234',
            raw: ['pricing' => ['category' => 'marketing']],
        );

        $this->assertSame(['mid1'], $event->messageIds);
        $this->assertSame('whatsapp:phone123:16505551234', $event->threadId);
        $this->assertSame('16505551234', $event->userId);
        $this->assertNull($event->price);
        $this->assertSame('marketing', $event->raw['pricing']['category']);
    }

    public function test_lock(): void
    {
        $lock = new Lock(key: 'process:t1', token: 'abc123', ttlMs: 30000);
        $this->assertSame('process:t1', $lock->key);
        $this->assertSame('abc123', $lock->token);
        $this->assertSame(30000, $lock->ttlMs);
    }

    public function test_queue_entry(): void
    {
        $entry = new QueueEntry(messageId: 'm1', payload: '{"data":1}', enqueuedAt: 1234567890.0);
        $this->assertSame('m1', $entry->messageId);
    }

    public function test_fetch_options_defaults(): void
    {
        $opts = new FetchOptions;
        $this->assertNull($opts->before);
        $this->assertNull($opts->after);
        $this->assertSame(50, $opts->limit);
    }

    public function test_fetch_result(): void
    {
        $result = new FetchResult(messages: [], nextCursor: 'cursor1');
        $this->assertEmpty($result->messages);
        $this->assertSame('cursor1', $result->nextCursor);
    }

    public function test_thread_info(): void
    {
        $info = new ThreadInfo(id: 't1', channelId: 'C1', title: 'Test Thread', messageCount: 42);
        $this->assertSame('Test Thread', $info->title);
        $this->assertSame(42, $info->messageCount);
    }

    public function test_channel_visibility_enum(): void
    {
        $this->assertSame('private', ChannelVisibility::Private->value);
        $this->assertSame('workspace', ChannelVisibility::Workspace->value);
        $this->assertSame('external', ChannelVisibility::External->value);
        $this->assertSame('unknown', ChannelVisibility::Unknown->value);
    }

    public function test_channel_info(): void
    {
        $info = new ChannelInfo(
            id: 'C1',
            name: 'general',
            topic: 'General discussion',
            isPrivate: false,
            visibility: ChannelVisibility::Workspace,
        );
        $this->assertSame('general', $info->name);
        $this->assertFalse($info->isPrivate);
    }

    public function test_user_info(): void
    {
        $user = new UserInfo(id: 'U1', name: 'Alice', email: 'alice@test.com');
        $this->assertSame('Alice', $user->name);
    }

    public function test_reaction_event_added(): void
    {
        $event = new ReactionEvent(
            emoji: '👍',
            messageId: 'm1',
            thread: $this->createStub(Thread::class),
            user: new Author(id: 'U1'),
            added: true,
            rawEmoji: '+1',
        );
        $this->assertTrue($event->added);
        $this->assertSame('+1', $event->rawEmoji);
        $this->assertSame('👍', $event->emoji);
    }

    public function test_message_delivered_event(): void
    {
        $event = new MessageDeliveredEvent(
            messageIds: ['mid1', 'mid2'],
            threadId: 'whatsapp:123:5511999999999',
            userId: '5511999999999',
        );
        $this->assertSame(['mid1', 'mid2'], $event->messageIds);
        $this->assertSame('5511999999999', $event->userId);
    }

    public function test_message_read_event(): void
    {
        $event = new MessageReadEvent(
            threadId: 'messenger:123:456',
            userId: '456',
            timestamp: 1700000000,
        );
        $this->assertSame('messenger:123:456', $event->threadId);
        $this->assertSame(1700000000, $event->timestamp);
    }

    /* Modal value objects */
    public function test_modal_with_text_input(): void
    {
        $modal = new Modal(
            callbackId: 'feedback',
            title: 'Feedback',
            children: [
                new TextInput(id: 'comment', label: 'Comment', multiline: true),
            ],
        );
        $this->assertSame('feedback', $modal->callbackId);
        $this->assertCount(1, $modal->children);
        $this->assertInstanceOf(TextInput::class, $modal->children[0]);
    }

    public function test_modal_with_select_and_options(): void
    {
        $modal = new Modal(
            callbackId: 'picker',
            title: 'Pick',
            children: [
                new Select(
                    id: 'fruit',
                    label: 'Fruit',
                    options: [
                        new SelectOption(label: 'Apple', value: 'apple'),
                        new SelectOption(label: 'Banana', value: 'banana', description: 'Yellow fruit'),
                    ],
                ),
                new ExternalSelect(id: 'color', label: 'Color', minQueryLength: 2),
                new RadioSelect(
                    id: 'size',
                    label: 'Size',
                    options: [
                        new SelectOption(label: 'Small', value: 's'),
                        new SelectOption(label: 'Large', value: 'l'),
                    ],
                    initialOption: 's',
                ),
            ],
        );
        $this->assertSame('picker', $modal->callbackId);
        $this->assertCount(3, $modal->children);
        $this->assertInstanceOf(Select::class, $modal->children[0]);
        $this->assertInstanceOf(ExternalSelect::class, $modal->children[1]);
        $this->assertInstanceOf(RadioSelect::class, $modal->children[2]);
    }

    public function test_select_option_description(): void
    {
        $opt = new SelectOption(label: 'Test', value: 't', description: 'A test option');
        $this->assertSame('A test option', $opt->description);
    }

    public function test_text_input_defaults(): void
    {
        $input = new TextInput(id: 'name', label: 'Name');
        $this->assertFalse($input->multiline);
        $this->assertFalse($input->optional);
        $this->assertNull($input->maxLength);
    }

    public function test_modal_with_notify_on_close(): void
    {
        $modal = new Modal(
            callbackId: 'test',
            title: 'Test',
            notifyOnClose: true,
        );
        $this->assertTrue($modal->notifyOnClose);
    }

    public function test_file_upload_from_string(): void
    {
        $upload = new FileUpload(data: 'hello', filename: 'test.txt', mimeType: 'text/plain');
        $this->assertSame('test.txt', $upload->filename);
        $this->assertSame('text/plain', $upload->mimeType);
        $this->assertSame(5, $upload->getSize());
    }

    public function test_file_upload_from_filename(): void
    {
        $path = sys_get_temp_dir().'/php_test_upload_'.uniqid();
        file_put_contents($path, 'test content');
        $upload = FileUpload::fromFilename($path);
        $this->assertSame('test content', $upload->getData());
        unlink($path);
    }

    public function test_attachment_value_object(): void
    {
        $att = new Attachment(type: 'image', url: 'https://example.com/photo.jpg', name: 'Photo');
        $this->assertSame('image', $att->type);
        $this->assertSame('https://example.com/photo.jpg', $att->url);
        $this->assertSame('Photo', $att->name);
    }

    public function test_attachment_location_factory(): void
    {
        $att = Attachment::location(37.7749, -122.4194, name: 'Golden Gate', address: 'San Francisco');

        $this->assertSame('location', $att->type);
        $this->assertSame(37.7749, $att->lat);
        $this->assertSame(-122.4194, $att->lng);
        $this->assertSame('Golden Gate', $att->name);
        $this->assertSame('San Francisco', $att->address);
        $this->assertStringStartsWith('data:application/geo+json;base64,', $att->url);
        $this->assertTrue($att->isDataUrl());

        $decoded = json_decode(base64_decode(substr($att->url, 33)), true);
        $this->assertSame('Feature', $decoded['type']);
        $this->assertSame('Point', $decoded['geometry']['type']);
        $this->assertSame([-122.4194, 37.7749], $decoded['geometry']['coordinates']);
        $this->assertSame('Golden Gate', $decoded['properties']['name']);
        $this->assertSame('San Francisco', $decoded['properties']['address']);

        $restored = unserialize(serialize($att));
        $this->assertSame('location', $restored->type);
        $this->assertSame(37.7749, $restored->lat);
        $this->assertSame(-122.4194, $restored->lng);
        $this->assertSame('Golden Gate', $restored->name);
        $this->assertSame('San Francisco', $restored->address);

        $bare = Attachment::location(51.5074, -0.1278);
        $this->assertSame(51.5074, $bare->lat);
        $this->assertSame(-0.1278, $bare->lng);
        $this->assertNull($bare->name);
        $this->assertNull($bare->address);

        $normal = new Attachment(type: 'image', url: 'https://example.com/pic.jpg');
        $this->assertFalse($normal->isDataUrl());

        $stream = $att->read();
        $this->assertNotNull($stream);
        $this->assertSame('Feature', json_decode((string) $stream, true)['type']);
        $this->assertNull($normal->read());
    }

    public function test_attachment_with_fetch_options(): void
    {
        $att = new Attachment(type: 'image', url: 'https://example.com/photo.jpg', name: 'Photo', mimeType: 'image/jpeg', size: 1024, width: 100, height: 200, fetchMetadata: ['old' => 'meta']);

        $callback = fn (Attachment $a): StreamInterface => throw new \RuntimeException('not called');
        $with = $att->withFetchOptions(fetchData: $callback, fetchMetadata: ['new' => 'meta']);

        $this->assertNotSame($att, $with);
        $this->assertSame('image', $with->type);
        $this->assertSame('https://example.com/photo.jpg', $with->url);
        $this->assertSame('Photo', $with->name);
        $this->assertSame('image/jpeg', $with->mimeType);
        $this->assertSame(1024, $with->size);
        $this->assertSame(100, $with->width);
        $this->assertSame(200, $with->height);
        $this->assertSame($callback, $with->fetchData);
        $this->assertSame(['new' => 'meta'], $with->fetchMetadata);
    }

    public function test_attachment_with_fetch_options_preserves_metadata_when_null(): void
    {
        $att = new Attachment(type: 'file', url: 'https://example.com/doc.pdf', fetchMetadata: ['file_id' => 'abc123']);
        $callback = fn (Attachment $a): StreamInterface => throw new \RuntimeException('not called');

        $with = $att->withFetchOptions(fetchData: $callback);

        $this->assertSame(['file_id' => 'abc123'], $with->fetchMetadata);
    }

    public function test_null_file_upload_converter_throws(): void
    {
        $this->expectException(AdapterException::class);
        (new NullFileUploadConverter)->upload(
            new FileUpload(data: 'test', filename: 't.txt', mimeType: 'text/plain'),
            $this->createStub(Adapter::class),
        );
    }
}
