<?php

namespace Modules\Core\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Safe HTML from the rich text editor. Everything saved or shown as HTML passes through here:
 * scripts, event handlers and inline styles are dropped, links and images are limited to safe schemes.
 */
class RichText
{
    private static ?HtmlSanitizer $sanitizer = null;

    public function __construct(private readonly ?string $html) {}

    public static function make(?string $html): self
    {
        return new self($html);
    }

    public function toHtml(): string
    {
        if (blank($this->html)) {
            return '';
        }

        return self::sanitizer()->sanitize($this->html);
    }

    private static function sanitizer(): HtmlSanitizer
    {
        return self::$sanitizer ??= new HtmlSanitizer(
            (new HtmlSanitizerConfig)
                ->allowSafeElements()
                ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
                ->allowMediaSchemes(['http', 'https'])
                ->allowRelativeLinks()
                ->allowRelativeMedias()
                ->forceAttribute('a', 'rel', 'noopener noreferrer')
                ->withMaxInputLength(500_000)
        );
    }
}
