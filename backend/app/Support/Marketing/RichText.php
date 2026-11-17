<?php

namespace App\Support\Marketing;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Strict allowlist sanitiser for AI-generated / suggested rich text (blog articles, product descriptions). Parsed with DOMDocument (not regex):
 * allowed tags p, br, ul, ol, li, strong, em, b, i, h2, h3, h4, blockquote, a; every attribute is dropped except `a[href]`, which must be an
 * internal store link (InternalLink, stored site-relative). script/style/iframe/object/embed/svg/math/template/form contents are removed entirely,
 * any other tag is unwrapped (its text kept, escaped). Comments are dropped.
 */
final class RichText
{
    public const ALLOWED = ['p', 'br', 'ul', 'ol', 'li', 'strong', 'em', 'b', 'i', 'h2', 'h3', 'h4', 'blockquote', 'a'];

    private const DROP = ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'template', 'form', 'input', 'button', 'textarea', 'select',
        'noscript', 'link', 'meta', 'base', 'frame', 'frameset', 'applet', 'video', 'audio', 'img', 'picture', 'source', 'canvas', 'head', 'title'];

    /** Tags renamed to an allowed equivalent (pasted editor / supplier HTML). */
    private const RENAME = ['h1' => 'h2', 'h5' => 'h4', 'h6' => 'h4'];

    /** Layout containers: become a paragraph when they hold only inline content, otherwise unwrapped (never nested <p>). */
    private const BLOCKS = ['div', 'section', 'article', 'header', 'footer', 'main', 'aside', 'figure', 'figcaption', 'center', 'tr', 'dd', 'dt'];

    private const BLOCK_OUT = '#<(p|ul|ol|li|h2|h3|h4|blockquote)>#';

    public static function clean(?string $html): string
    {
        $html = (string) $html;
        if (trim($html) === '') {
            return '';
        }
        $html = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $html) ?? '';
        // markup without any block structure (e.g. "<b>Title</b>\nline"): keep the author's line breaks
        if (! preg_match('#<(p|br|div|ul|ol|li|h[1-6]|blockquote|table|tr|section|article)\b#i', $html)) {
            $html = nl2br(trim($html), false);
        }
        $doc = new DOMDocument;
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div>'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $root = $doc->getElementsByTagName('div')->item(0);
        if (! $root) {
            return '';
        }

        $out = self::walk($root);
        $out = preg_replace('#<(p|h2|h3|h4|li|blockquote|strong|em|b|i)>(\s|&nbsp;|\x{00A0}|<br>)*</\1>#u', '', $out) ?? $out;   // empty blocks

        return trim(preg_replace('#(<br>\s*){3,}#', '<br><br>', $out) ?? $out);
    }

    /** True when the value contains real HTML tags (not just "<", "a < b" or "<3"). */
    public static function isHtml(?string $value): bool
    {
        return (bool) preg_match('#</?[a-z][a-z0-9]*(\s[^<>]*)?/?>#i', (string) $value);
    }

    /** Output helper: plain text becomes escaped line breaks (entities decoded once, never double-escaped), markup goes through clean(). */
    public static function render(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        return self::isHtml($value) ? self::clean($value) : nl2br(e(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    private static function walk(DOMNode $node): string
    {
        $out = '';
        foreach ($node->childNodes as $c) {
            if ($c instanceof DOMText) {
                $out .= htmlspecialchars($c->nodeValue ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
            } elseif ($c instanceof DOMElement) {
                $tag = strtolower($c->tagName);
                if (in_array($tag, self::DROP, true)) {
                    continue;
                }
                if ($tag === 'br') {
                    $out .= '<br>';

                    continue;
                }
                $inner = self::walk($c);
                $tag = self::RENAME[$tag] ?? $tag;
                if (in_array($tag, ['td', 'th'], true)) {
                    $out .= $inner.' ';

                    continue;
                }
                if (in_array($tag, self::BLOCKS, true)) {
                    $out .= preg_match(self::BLOCK_OUT, $inner) ? $inner : '<p>'.trim($inner).'</p>';

                    continue;
                }
                if ($tag === 'a') {
                    $href = InternalLink::normalize($c->getAttribute('href'));
                    $out .= $href !== null ? '<a href="'.htmlspecialchars($href, ENT_QUOTES | ENT_HTML5, 'UTF-8').'">'.$inner.'</a>' : $inner;
                } elseif (in_array($tag, self::ALLOWED, true)) {
                    $out .= "<{$tag}>{$inner}</{$tag}>";
                } else {
                    $out .= $inner;
                }
            }
        }

        return $out;
    }
}
