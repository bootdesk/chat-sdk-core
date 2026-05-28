<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Contracts;

/**
 * Marker interface for adapters that expect a quick 200 ACK and
 * should always defer message processing to async execution.
 *
 * The concurrency handler will never process messages inline for
 * these adapters — it will always apply the configured strategy
 * (queue, debounce, etc.) or dispatch a job.
 *
 * Examples: Slack, Telegram, WhatsApp, Messenger, Instagram
 */
interface RequiresAsyncResponse {}
