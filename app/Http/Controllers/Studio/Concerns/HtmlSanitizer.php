<?php

namespace App\Http\Controllers\Studio\Concerns;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Allow-list HTML cleaner for CMS bodies. Editors may write plain text
 * (blank lines become paragraphs) or simple HTML from the toolbar.
 * Scripts, styles, iframes, event handlers and javascript: URLs are removed.
 */
class HtmlSanitizer
{
    private const ALLOWED = [
        'p' => [], 'br' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [],
        'ul' => [], 'ol' => [], 'li' => [], 'blockquote' => [], 'hr' => [], 'span' => [], 'small' => [],
        'a' => ['href', 'title', 'target'], 'img' => ['src', 'alt'],
        'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => ['colspan', 'rowspan'], 'td' => ['colspan', 'rowspan'],
    ];

    private const DROP_WITH_CONTENT = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'noscript', 'svg', 'math', 'link', 'meta', 'base', 'frame', 'frameset'];

    public static function clean(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }

        // Plain text → paragraphs
        if ($html === strip_tags($html)) {
            $paras = preg_split("/\R{2,}/", $html);

            return implode("\n", array_map(fn ($p) => '<p>'.nl2br(e(trim($p)), false).'</p>', array_filter($paras, fn ($p) => trim($p) !== '')));
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="__root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();

        $root = $doc->getElementById('__root') ?? $doc->documentElement;
        self::walk($root);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $doc->saveHTML($child);
        }

        return trim($out);
    }

    private static function walk(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE) {
                $node->removeChild($child);

                continue;
            }
            if (! $child instanceof DOMElement) {
                continue;
            }
            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                $node->removeChild($child);

                continue;
            }

            self::walk($child);

            if (! array_key_exists($tag, self::ALLOWED)) {
                // unwrap: keep text/children, drop the tag
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);

                continue;
            }

            foreach (iterator_to_array($child->attributes) as $attr) {
                $name = strtolower($attr->name);
                if (! in_array($name, self::ALLOWED[$tag], true)) {
                    $child->removeAttribute($attr->name);

                    continue;
                }
                if (in_array($name, ['href', 'src'], true) && ! self::safeUrl($attr->value)) {
                    $child->removeAttribute($attr->name);
                }
            }
            if ($tag === 'a' && $child->getAttribute('target') === '_blank') {
                $child->setAttribute('rel', 'noopener');
            } elseif ($tag === 'a') {
                $child->removeAttribute('target');
            }
        }
    }

    private static function safeUrl(string $url): bool
    {
        $url = trim(preg_replace('/[\x00-\x20]+/', '', $url));

        return (bool) preg_match('#^(https?://|mailto:|tel:|/|\#)#i', $url) || ! preg_match('#^[a-z][a-z0-9+.-]*:#i', $url);
    }
}
