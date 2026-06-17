<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Contracts;

/**
 * Optional interface for adapters whose sync/async preference is
 * determined at runtime rather than at compile time (via marker
 * interfaces RequiresSyncResponse / RequiresAsyncResponse).
 *
 * When a ConcurrencyHandler encounters an adapter implementing this
 * interface, it calls requiresSyncResponse() instead of checking
 * instanceof markers. This enables adapters like WebAdapter to
 * dynamically switch behavior based on configuration (e.g., asyncMode).
 */
interface HasDynamicSyncPreference
{
    /**
     * Return true if this adapter's messages must be processed
     * inline (sync), false if they can be deferred (async).
     */
    public function requiresSyncResponse(): bool;
}
