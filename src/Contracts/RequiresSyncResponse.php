<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Contracts;

/**
 * Marker interface for adapters that require the bot's response
 * within the same HTTP request.
 *
 * The concurrency handler will always process messages inline for
 * these adapters, never deferring to async execution.
 *
 * Examples: WebAdapter (UI waits for reply), DiscordAdapter (3s timeout)
 */
interface RequiresSyncResponse {}
