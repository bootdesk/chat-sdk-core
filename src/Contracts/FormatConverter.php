<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Contracts;

use BootDesk\ChatSDK\Core\PostableMessage;
use League\CommonMark\Node\Block\Document;

interface FormatConverter
{
    public function toAst(string $platformText): Document;

    public function fromAst(Document $ast): string;

    public function extractPlainText(string $platformText): string;

    public function renderPostable(PostableMessage $message): string;

    public function fromMarkdown(string $markdown): string;
}
