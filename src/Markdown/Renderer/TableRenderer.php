<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Markdown\Renderer;

use League\CommonMark\Extension\Table\Table;
use League\CommonMark\Extension\Table\TableCell;
use League\CommonMark\Extension\Table\TableRow;
use League\CommonMark\Extension\Table\TableSection;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;

final class TableRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): ?string
    {
        if (! $node instanceof Table) {
            return null;
        }

        $headerRow = null;
        $bodyRows = [];

        foreach ($node->children() as $section) {
            if (! $section instanceof TableSection) {
                continue;
            }

            foreach ($section->children() as $row) {
                if (! $row instanceof TableRow) {
                    continue;
                }

                if ($section->isHead()) {
                    $headerRow = $this->renderRow($row, $childRenderer);
                } else {
                    $bodyRows[] = $this->renderRow($row, $childRenderer);
                }
            }
        }

        if ($headerRow === null) {
            return '';
        }

        $parts = [];
        $parts[] = '| '.implode(' | ', $headerRow['cells']).' |';

        $separators = array_map(function (int $i, ?string $align): string {
            return match ($align) {
                'left' => ':'.str_repeat('-', max(3, ...[$i]) - 1),
                'center' => ':'.str_repeat('-', max(3, ...[$i]) - 2).':',
                'right' => str_repeat('-', max(3, ...[$i]) - 1).':',
                default => str_repeat('-', max(3, ...[$i])),
            };
        }, array_keys($headerRow['aligns']), $headerRow['aligns']);

        $parts[] = '| '.implode(' | ', $separators).' |';

        foreach ($bodyRows as $row) {
            $parts[] = '| '.implode(' | ', $row['cells']).' |';
        }

        return implode("\n", $parts);
    }

    private function renderRow(TableRow $row, ChildNodeRendererInterface $childRenderer): array
    {
        $cells = [];
        $aligns = [];

        foreach ($row->children() as $cell) {
            if (! $cell instanceof TableCell) {
                continue;
            }
            $cells[] = trim($childRenderer->renderNodes($cell->children()));
            $aligns[] = $cell->getAlign();
        }

        return ['cells' => $cells, 'aligns' => $aligns];
    }
}
