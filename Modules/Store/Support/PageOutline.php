<?php

namespace Modules\Store\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Reads a page body's structure: its h2 sections, for the "On this page" menu of a long
 * policy, and its questions and answers, for the FAQ accordion. The body is already
 * sanitized; this only regroups its nodes.
 */
final class PageOutline
{
    /**
     * The body with an id on every h2, and the table of contents.
     *
     * @return array{html: string, toc: list<array{id: string, title: string}>}
     */
    public static function sections(string $html): array
    {
        $root = self::parse($html);
        $toc = [];

        foreach (iterator_to_array($root->getElementsByTagName('h2')) as $index => $heading) {
            /** @var DOMElement $heading */
            $id = 'section-'.($index + 1);
            $heading->setAttribute('id', $id);
            $toc[] = ['id' => $id, 'title' => self::text($heading)];
        }

        return ['html' => self::inner($root), 'toc' => $toc];
    }

    /**
     * Each h2 is a question; what follows it, up to the next h2, its answer. Text before the
     * first question is the intro.
     *
     * @return array{intro: string, items: list<array{question: string, answer: string}>}
     */
    public static function questions(string $html): array
    {
        $root = self::parse($html);
        $intro = '';
        $items = [];

        foreach (iterator_to_array($root->childNodes) as $node) {
            if ($node instanceof DOMElement && $node->tagName === 'h2') {
                $items[] = ['question' => self::text($node), 'answer' => ''];

                continue;
            }

            $markup = $root->ownerDocument->saveHTML($node);
            if ($items === []) {
                $intro .= $markup;
            } else {
                $items[array_key_last($items)]['answer'] .= $markup;
            }
        }

        $items = array_values(array_filter($items, fn (array $item) => $item['question'] !== ''));

        return ['intro' => trim($intro), 'items' => array_map(fn (array $item) => [...$item, 'answer' => trim($item['answer'])], $items)];
    }

    private static function parse(string $html): DOMElement
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><div>'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        /** @var DOMElement $root */
        $root = $document->getElementsByTagName('div')->item(0);

        return $root;
    }

    private static function inner(DOMNode $root): string
    {
        $html = '';
        foreach ($root->childNodes as $node) {
            $html .= $root->ownerDocument->saveHTML($node);
        }

        return $html;
    }

    private static function text(DOMNode $node): string
    {
        return trim(preg_replace('/\s+/u', ' ', $node->textContent) ?? '');
    }
}
