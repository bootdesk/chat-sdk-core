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
    /** @var array{webhook: WebhookMiddleware[], receiving: ReceivingMiddleware[], sending: SendingMiddleware[], webhook_event: WebhookEventMiddleware[], sent: SentMiddleware[], heard: HeardMiddleware[]} */
    private array $middlewares = [
        'webhook' => [],
        'receiving' => [],
        'sending' => [],
        'webhook_event' => [],
        'sent' => [],
        'heard' => [],
    ];

    public function addWebhook(WebhookMiddleware $middleware): void
    {
        $this->middlewares['webhook'][] = $middleware;
    }

    public function addReceiving(ReceivingMiddleware $middleware): void
    {
        $this->middlewares['receiving'][] = $middleware;
    }

    public function addSending(SendingMiddleware $middleware): void
    {
        $this->middlewares['sending'][] = $middleware;
    }

    public function addWebhookEvent(WebhookEventMiddleware $middleware): void
    {
        $this->middlewares['webhook_event'][] = $middleware;
    }

    public function addSent(SentMiddleware $middleware): void
    {
        $this->middlewares['sent'][] = $middleware;
    }

    public function addHeard(HeardMiddleware $middleware): void
    {
        $this->middlewares['heard'][] = $middleware;
    }

    /**
     * @param  'webhook'|'receiving'|'sending'|'webhook_event'|'sent'|'heard'  $type
     * @return WebhookMiddleware[]|ReceivingMiddleware[]|SendingMiddleware[]|WebhookEventMiddleware[]|SentMiddleware[]|HeardMiddleware[]
     */
    public function getMiddlewares(string $type): array
    {
        return $this->middlewares[$type];
    }

    /**
     * @param  callable(WebhookEvent, Adapter): Adapter  $handler
     */
    public function processWebhookEvent(WebhookEvent $event, Adapter $adapter, callable $handler): Adapter
    {
        $middlewares = $this->middlewares['webhook_event'];

        if ($middlewares === []) {
            return $handler($event, $adapter);
        }

        $current = $adapter;

        foreach ($middlewares as $middleware) {
            $current = $middleware->handle($event, $current);
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
        $middlewares = $this->middlewares[$type];

        if ($middlewares === []) {
            return $type === 'webhook' ? $handler($context) : $handler(...$context);
        }

        return $this->buildPipeline($middlewares, $context, $handler);
    }

    /**
     * @param  array<object>  $middlewares
     */
    private function buildPipeline(array $middlewares, mixed $context, callable $handler): mixed
    {
        $pipeline = $handler;

        foreach (array_reverse($middlewares) as $middleware) {
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
}
