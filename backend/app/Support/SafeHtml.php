<?php

namespace App\Support;

/** Whitelist sanitiser for untrusted rich text (AI output, blog bodies): tiny tag set, every attribute stripped, no scripts/links/handlers. */
class SafeHtml
{
    public const TAGS = '<p><br><ul><ol><li><strong><em><b><i><h2><h3><h4><blockquote>';

    public static function clean(string $value): string
    {
        $v = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $v = preg_replace('#<(script|style|iframe|object|embed)\b.*?(</\1\s*>|$)#is', '', $v);
        $v = strip_tags($v, self::TAGS);
        $v = preg_replace('/<\s*(\/?)\s*([a-z0-9]+)\b[^>]*>/i', '<$1$2>', $v);

        return trim(str_ireplace('javascript:', '', $v));
    }

    /** Safe HTML for output: plain text becomes paragraphs, markup is whitelisted. */
    public static function render(?string $value): string
    {
        $value = (string) $value;

        return $value === strip_tags($value) ? nl2br(e($value)) : self::clean($value);
    }
}
