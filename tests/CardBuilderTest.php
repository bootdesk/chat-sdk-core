<?php

namespace BootDesk\ChatSDK\Core\Tests;

use BootDesk\ChatSDK\Core\Cards\Button;
use BootDesk\ChatSDK\Core\Cards\Card;
use BootDesk\ChatSDK\Core\Cards\Section;
use PHPUnit\Framework\TestCase;

class CardBuilderTest extends TestCase
{
    public function test_basic_card(): void
    {
        $card = Card::make()
            ->header('Test Card')
            ->section(fn (Section $s) => $s->text('Body text'));

        $this->assertSame('Test Card', $card->getHeader());
        $this->assertCount(1, $card->getSections());
        $this->assertSame('Body text', $card->getSections()[0]->getText());
    }

    public function test_card_with_fields(): void
    {
        $card = Card::make()
            ->section(fn (Section $s) => $s
                ->text('Info')
                ->fields(['Priority' => 'High', 'Status' => 'Open']));

        $fields = $card->getSections()[0]->getFields();
        $this->assertSame('High', $fields['Priority']);
        $this->assertSame('Open', $fields['Status']);
    }

    public function test_card_with_buttons(): void
    {
        $card = Card::make()
            ->actions([
                Button::primary('Submit', 'submit'),
                Button::danger('Cancel', 'cancel'),
            ]);

        $buttons = $card->getButtons();
        $this->assertCount(2, $buttons);
        $this->assertSame('Submit', $buttons[0]->label);
        $this->assertSame('primary', $buttons[0]->style->value);
        $this->assertSame('danger', $buttons[1]->style->value);
    }

    public function test_card_with_image(): void
    {
        $card = Card::make()->image('https://example.com/img.png', 'Alt text');
        $images = $card->getImages();
        $this->assertCount(1, $images);
        $this->assertSame('https://example.com/img.png', $images[0]->url);
    }

    public function test_fallback_text(): void
    {
        $card = Card::make()
            ->header('Ticket #1234')
            ->section(fn (Section $s) => $s
                ->text('A new ticket has been opened.')
                ->fields(['Priority' => 'High']));

        $fallback = $card->getFallbackText();
        $this->assertStringContainsString('Ticket #1234', $fallback);
        $this->assertStringContainsString('A new ticket has been opened.', $fallback);
        $this->assertStringContainsString('Priority: High', $fallback);
    }

    public function test_fluent_builder_chain(): void
    {
        $card = Card::make()
            ->header('Title')
            ->section(fn (Section $s) => $s->text('Body'))
            ->actions([Button::primary('OK', 'ok')])
            ->image('https://example.com/img.png');

        $this->assertSame('Title', $card->getHeader());
        $this->assertCount(1, $card->getSections());
        $this->assertCount(1, $card->getButtons());
        $this->assertCount(1, $card->getImages());
    }
}
