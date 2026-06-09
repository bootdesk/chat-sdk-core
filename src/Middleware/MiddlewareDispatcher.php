<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Middleware;

use BootDesk\ChatSDK\Core\Contracts\Adapter;
use BootDesk\ChatSDK\Core\Contracts\HeardMiddleware;
use BootDesk\ChatSDK\Core\Contracts\ReceivingMiddleware;
use BootDesk\ChatSDK\Core\Contracts\SendingMiddleware;
use BootDesk\ChatSDK\Core\Contracts\SentMiddleware;
use BootDesk\ChatSDK\Core\Contracts\WebhookEventMiddleware;
use BootDesk\ChatSDK\Core\Contracts\WebhookMiddleware;
use BootDesk\ChatSDK\Core\Message;
use BootDesk\ChatSDK\Core\MessageContext;
use BootDesk\ChatSDK\Core\PostableMessage;
use BootDesk\ChatSDK\Core\SentMessage;
use BootDesk\ChatSDK\Core\WebhookEvent;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class MiddlewareDispatcher
{
    /** @var array{webhook: array<array{0: object, 1: int}>, receiving: array<array{0: object, 1: int}>, sending: array<array{0: object, 1: int}>, webhook_event: array<array{0: object, 1: int}>, sent: array<array{0: object, 1: int}>, heard: array<array{0: object, 1: int}>} */
    private array $middlewares = [
        'webhook' => [],
        'receiving' => [],
        'sending' => [],
        'webhook_event' => [],
        'sent' => [],
        'heard' => [],
    ];

    /** @var array<string, bool> */
    private array $sorted = [
        'webhook' => true,
        'receiving' => true,
        'sending' => true,
        'webhook_event' => true,
        'sent' => true,
        'heard' => true,
    ];

    public function addWebhook(WebhookMiddleware $middleware, int $priority = 0): void
    {
        $this->middlewares['webhook'][] = [$middleware, $priority];
        $this->sorted['webhook'] = false;
    }

    public function addReceiving(ReceivingMiddleware $middleware, int $priority = 0): void
    {
        $this->middlewares['receiving'][] = [$middleware, $priority];
        $this->sorted['receiving'] = false;
    }

    public function addSending(SendingMiddleware $middleware, int $priority = 0): void
    {
        $this->middlewares['sending'][] = [$middleware, $priority];
        $this->sorted['sending'] = false;
    }

    public function addWebhookEvent(WebhookEventMiddleware $middleware, int $priority = 0): void
    {
        $this->middlewares['webhook_event'][] = [$middleware, $priority];
        $this->sorted['webhook_event'] = false;
    }

    public function addSent(SentMiddleware $middleware, int $priority = 0): void
    {
        $this->middlewares['sent'][] = [$middleware, $priority];
        $this->sorted['sent'] = false;
    }

    public function addHeard(HeardMiddleware $middleware, int $priority = 0): void
    {
        $this->middlewares['heard'][] = [$middleware, $priority];
        $this->sorted['heard'] = false;
    }

    /**
     * @param  'webhook'|'receiving'|'sending'|'webhook_event'|'sent'|'heard'  $type
     * @return WebhookMiddleware[]|ReceivingMiddleware[]|SendingMiddleware[]|WebhookEventMiddleware[]|SentMiddleware[]|HeardMiddleware[]
     */
    public function getMiddlewares(string $type): array
    {
        $this->ensureSorted($type);

        return array_map(fn (array $entry): object => $entry[0], $this->middlewares[$type]);
    }

    /**
     * @param  callable(WebhookEvent, Adapter): Adapter  $handler
     */
    public function processWebhookEvent(WebhookEvent $event, Adapter $adapter, callable $handler): Adapter
    {
        $this->ensureSorted('webhook_event');
        $middlewares = $this->middlewares['webhook_event'];

        if ($middlewares === []) {
            return $handler($event, $adapter);
        }

        $current = $adapter;

        foreach ($middlewares as $entry) {
            $current = $entry[0]->handle($event, $current);
        }

        return $handler($event, $current);
    }

    /**
     * @param  callable(ServerRequestInterface): ResponseInterface  $handler
     */
    public function processWebhook(ServerRequestInterface $request, callable $handler): ResponseInterface
    {
        return $this->process('webhook', $request, $handler);
    }

    /**
     * @param  callable(?Message, Adapter): ?Message  $handler
     */
    public function processReceiving(?Message $message, Adapter $adapter, callable $handler): ?Message
    {
        $result = $this->process('receiving', [$message, $adapter], $handler);

        return $result instanceof Message ? $result : null;
    }

    /**
     * @param  callable(string, PostableMessage, Adapter, string): ?PostableMessage  $handler
     */
    public function processSending(string $threadId, PostableMessage $message, Adapter $adapter, string $operation, callable $handler): ?PostableMessage
    {
        return $this->process('sending', [$threadId, $message, $adapter, $operation], $handler);
    }

    /**
     * @param  callable(string, PostableMessage, SentMessage, Adapter, string): SentMessage  $handler
     */
    public function processSent(string $threadId, PostableMessage $message, SentMessage $result, Adapter $adapter, string $operation, callable $handler): SentMessage
    {
        return $this->process('sent', [$threadId, $message, $result, $adapter, $operation], $handler);
    }

    /**
     * @param  callable(?MessageContext, string, Adapter): ?MessageContext  $handler
     */
    public function processHeard(MessageContext $context, string $pattern, Adapter $adapter, callable $handler): ?MessageContext
    {
        $result = $this->process('heard', [$context, $pattern, $adapter], $handler);

        return $result instanceof MessageContext ? $result : null;
    }

    /**
     * @param  'webhook'|'receiving'|'sending'|'sent'|'heard'  $type
     */
    private function process(string $type, mixed $context, callable $handler): mixed
    {
        $this->ensureSorted($type);
        $middlewares = $this->middlewares[$type];

        if ($middlewares === []) {
            return $type === 'webhook' ? $handler($context) : $handler(...$context);
        }

        return $this->buildPipeline($middlewares, $context, $handler);
    }

    /**
     * @param  array<array{0: object, 1: int}>  $middlewares
     */
    private function buildPipeline(array $middlewares, mixed $context, callable $handler): mixed
    {
        $pipeline = $handler;

        foreach (array_reverse($middlewares) as $entry) {
            $middleware = $entry[0];
            $prev = $pipeline;
            $pipeline = fn (...$args): mixed => $this->callMiddleware($middleware, $args, $prev);
        }

        return is_array($context) ? $pipeline(...$context) : $pipeline($context);
    }

    /**
     * @param  array<mixed>  $args
     */
    private function callMiddleware(object $m, array $args, callable $next): mixed
    {
        return call_user_func_array(
            callback: [$m, 'handle'],
            args: [...$args, $next]
        );
    }

    private function ensureSorted(string $type): void
    {
        if ($this->sorted[$type]) {
            return;
        }

        usort($this->middlewares[$type], fn (array $a, array $b): int => $b[1] <=> $a[1]);
        $this->sorted[$type] = true;
    }
}
