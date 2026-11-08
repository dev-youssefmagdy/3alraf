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

    public static function clean(?string $html): string
    {
        $html = (string) $html;
        if (trim($html) === '') {
            return '';
        }
        $html = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $html) ?? '';
        $doc = new DOMDocument;
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div>'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $root = $doc->getElementsByTagName('div')->item(0);
        if (! $root) {
            return '';
        }

        return trim(self::walk($root));
    }

    /** Output helper: plain text becomes escaped paragraphs/line breaks, markup goes through clean(). */
    public static function render(?string $value): string
    {
        $value = (string) $value;

        return $value === strip_tags($value) ? nl2br(e($value)) : self::clean($value);
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
