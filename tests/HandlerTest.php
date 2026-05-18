<?php

namespace BootDesk\ChatSDK\Core\Tests;

use BootDesk\ChatSDK\Core\Concurrency\Handler;
use BootDesk\ChatSDK\Core\Concurrency\Strategy;
use BootDesk\ChatSDK\Core\QueueEntry;
use BootDesk\ChatSDK\Core\Tests\Helpers\MemoryStateAdapter;
use PHPUnit\Framework\TestCase;

class HandlerTest extends TestCase
{
    private MemoryStateAdapter $state;

    protected function setUp(): void
    {
        $this->state = new MemoryStateAdapter;
    }

    public function test_acquire_drop_returns_lock(): void
    {
        $handler = new Handler($this->state, Strategy::Drop);
        $lock = $handler->acquire('test_thread');
        $this->assertNotNull($lock);
    }

    public function test_acquire_concurrent_returns_null(): void
    {
        $handler = new Handler($this->state, Strategy::Concurrent);
        $lock = $handler->acquire('test_thread');
        $this->assertNull($lock);
    }

    public function test_acquire_debounce_returns_lock(): void
    {
        $handler = new Handler($this->state, Strategy::Debounce);
        $lock = $handler->acquire('debounce_test');
        $this->assertNotNull($lock);
    }

    public function test_release_null_is_noop(): void
    {
        $handler = new Handler($this->state, Strategy::Drop);
        $handler->release(null);
        $this->assertTrue(true);
    }

    public function test_enqueue_dequeue(): void
    {
        $handler = new Handler($this->state, Strategy::Queue);
        $entry = new QueueEntry('msg_1', 'payload', microtime(true));

        $depth = $handler->enqueue('q_thread', $entry);
        $this->assertSame(1, $depth);

        $dequeued = $handler->dequeue('q_thread');
        $this->assertNotNull($dequeued);
        $this->assertSame('msg_1', $dequeued->messageId);
    }

    public function test_queue_depth(): void
    {
        $handler = new Handler($this->state, Strategy::Queue);
        $handler->enqueue('depth_test', new QueueEntry('m1', 'p1', 1.0));
        $handler->enqueue('depth_test', new QueueEntry('m2', 'p2', 2.0));

        $this->assertSame(2, $handler->queueDepth('depth_test'));
    }

    public function test_extend_lock(): void
    {
        $handler = new Handler($this->state, Strategy::Drop);
        $lock = $handler->acquire('ext_test');

        $result = $handler->extendLock($lock, 60_000);
        $this->assertTrue($result);
    }

    public function test_release_held_lock(): void
    {
        $handler = new Handler($this->state, Strategy::Drop);
        $lock = $handler->acquire('release_test');
        $handler->release($lock);

        $lock2 = $handler->acquire('release_test');
        $this->assertNotNull($lock2);
    }

    public function test_debounce_acquire_returns_null_when_locked(): void
    {
        $handler = new Handler($this->state, Strategy::Debounce);
        $lock = $handler->acquire('locked_thread');

        // Second acquire should fail
        $lock2 = $handler->acquire('locked_thread');
        $this->assertNull($lock2);

        $handler->release($lock);
    }
}
