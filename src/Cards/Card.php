<?php

namespace BootDesk\ChatSDK\Core\Cards;

class Card implements CardElement
{
    private ?string $header = null;

    private ?string $imageUrl = null;

    private ?string $imageAlt = null;

    /** @var CardElement[] */
    private array $children = [];

    public static function make(): self
    {
        return new self;
    }

    public function header(string $header): self
    {
        $this->header = $header;

        return $this;
    }

    public function imageUrl(string $url, string $alt = ''): self
    {
        $this->imageUrl = $url;
        $this->imageAlt = $alt;

        return $this;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function getImageAlt(): string
    {
        return $this->imageAlt ?? '';
    }

    public function section(callable $builder): self
    {
        $section = new Section;
        $builder($section);
        $this->children[] = $section;

        return $this;
    }

    public function actions(array $buttons): self
    {
        foreach ($buttons as $button) {
            $this->children[] = $button;
        }

        return $this;
    }

    public function image(string $url, string $alt = ''): self
    {
        $this->children[] = new Image($url, $alt);

        return $this;
    }

    public function text(string $content, TextStyle $style = TextStyle::Plain): self
    {
        $this->children[] = new Text($content, $style);

        return $this;
    }

    public function divider(): self
    {
        $this->children[] = new Divider;

        return $this;
    }

    public function link(string $label, string $url): self
    {
        $this->children[] = new Link($label, $url);

        return $this;
    }

    public function table(array $headers, array $rows, array $align = []): self
    {
        $this->children[] = new Table($headers, $rows, $align);

        return $this;
    }

    public function linkButton(string $label, string $url, ButtonStyle $style = ButtonStyle::Secondary): self
    {
        $this->children[] = new LinkButton($label, $url, $style);

        return $this;
    }

    public function getHeader(): ?string
    {
        return $this->header;
    }

    /** @return CardElement[] */
    public function getChildren(): array
    {
        return $this->children;
    }

    /** @return Section[] */
    public function getSections(): array
    {
        return array_values(array_filter(
            $this->children,
            fn (CardElement $e): bool => $e instanceof Section,
        ));
    }

    /** @return Button[] */
    public function getButtons(): array
    {
        return array_values(array_filter(
            $this->children,
            fn (CardElement $e): bool => $e instanceof Button,
        ));
    }

    /** @return Image[] */
    public function getImages(): array
    {
        return array_values(array_filter(
            $this->children,
            fn (CardElement $e): bool => $e instanceof Image,
        ));
    }

    /** @return Text[] */
    public function getTexts(): array
    {
        return array_values(array_filter(
            $this->children,
            fn (CardElement $e): bool => $e instanceof Text,
        ));
    }

    /** @return Link[] */
    public function getLinks(): array
    {
        return array_values(array_filter(
            $this->children,
            fn (CardElement $e): bool => $e instanceof Link,
        ));
    }

    /** @return Table[] */
    public function getTables(): array
    {
        return array_values(array_filter(
            $this->children,
            fn (CardElement $e): bool => $e instanceof Table,
        ));
    }

    /** @return LinkButton[] */
    public function getLinkButtons(): array
    {
        return array_values(array_filter(
            $this->children,
            fn (CardElement $e): bool => $e instanceof LinkButton,
        ));
    }

    public function getFallbackText(): string
    {
        $parts = [];

        if ($this->imageUrl !== null) {
            $parts[] = $this->imageAlt !== '' ? "{$this->imageAlt}: {$this->imageUrl}" : $this->imageUrl;
        }

        if ($this->header !== null) {
            $parts[] = $this->header;
        }

        foreach ($this->children as $child) {
            if ($child instanceof Section) {
                if ($child->getText() !== null) {
                    $parts[] = $child->getText();
                }
                foreach ($child->getFields() as $label => $value) {
                    $parts[] = "{$label}: {$value}";
                }
            } elseif ($child instanceof Text) {
                $parts[] = $child->content;
            } elseif ($child instanceof Link) {
                $parts[] = "{$child->label}: {$child->url}";
            } elseif ($child instanceof Table) {
                $parts[] = implode(' | ', $child->headers);
                foreach ($child->rows as $row) {
                    $parts[] = implode(' | ', $row);
                }
            }
        }

        return implode("\n", $parts);
    }
}
