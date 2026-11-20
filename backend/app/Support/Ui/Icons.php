<?php

namespace App\Support\Ui;

/**
 * Icon registry for <x-ui.icon name="...">: 24px grid, 1.8 stroke, round caps (Figma section "icon/*").
 * Values are the inner SVG markup. Unknown names fall back to "circle"; a name that is itself an SVG path ("M...") is drawn as is,
 * which keeps the old nav data (raw path strings) working.
 */
final class Icons
{
    public static function markup(string $name): string
    {
        if (preg_match('/^[Mm][\d\s.,-]/', $name)) {
            return '<path d="'.e($name).'"/>';
        }

        return self::all()[$name] ?? self::all()['circle'];
    }

    /** Directional icons mirror in RTL (spec 5: chevrons, back/forward, arrows, external-link). */
    public static function mirrors(string $name): bool
    {
        return in_array($name, ['chevron-end', 'chevron-start', 'arrow-end', 'back', 'external', 'logout', 'undo'], true);
    }

    /** @return array<string,string> */
    public static function all(): array
    {
        return [
            'circle' => '<circle cx="12" cy="12" r="9"/>',
            'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
            'home' => '<path d="M3 11l9-8 9 8"/><path d="M5 9.5V20h14V9.5"/><path d="M10 20v-6h4v6"/>',
            'pos' => '<path d="M4 8V5a1 1 0 011-1h3M20 8V5a1 1 0 00-1-1h-3M4 16v3a1 1 0 001 1h3M20 16v3a1 1 0 01-1 1h-3"/><path d="M8 12h8"/>',
            'cart' => '<circle cx="9" cy="20" r="1.4"/><circle cx="17" cy="20" r="1.4"/><path d="M3 4h2.2l2.2 11h10.2L20 7.5H6.2"/>',
            'chart' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
            'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0113 0"/><path d="M16 4.6a3.5 3.5 0 010 6.8M18 14.3A6.5 6.5 0 0121.5 20"/>',
            'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0116 0"/>',
            'box' => '<path d="M21 8l-9-5-9 5v8l9 5 9-5z"/><path d="M3 8l9 5 9-5M12 13v8"/>',
            'layers' => '<path d="M12 3l9 5-9 5-9-5z"/><path d="M3 12.5l9 5 9-5"/>',
            'tag' => '<path d="M3 12V4a1 1 0 011-1h8l9 9-9 9z"/><circle cx="7.5" cy="7.5" r="1.2"/>',
            'megaphone' => '<path d="M3 10v4a1 1 0 001 1h3l8 4V5L7 9H4a1 1 0 00-1 1z"/><path d="M19 9a4 4 0 010 6"/>',
            'wallet' => '<path d="M4 7a2 2 0 012-2h12v4"/><path d="M4 7v11a2 2 0 002 2h14V9H6a2 2 0 01-2-2z"/><circle cx="16.5" cy="14.5" r="1.2"/>',
            'truck' => '<path d="M3 6h11v10H3zM14 9h4l3 3v4h-7"/><circle cx="7" cy="18" r="1.8"/><circle cx="17" cy="18" r="1.8"/>',
            'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 00.3 1.8l.1.1a2 2 0 11-2.8 2.8l-.1-.1a1.7 1.7 0 00-1.8-.3 1.7 1.7 0 00-1 1.5V21a2 2 0 11-4 0v-.1a1.7 1.7 0 00-1.1-1.5 1.7 1.7 0 00-1.8.3l-.1.1a2 2 0 11-2.8-2.8l.1-.1a1.7 1.7 0 00.3-1.8 1.7 1.7 0 00-1.5-1H3a2 2 0 110-4h.1a1.7 1.7 0 001.5-1.1 1.7 1.7 0 00-.3-1.8l-.1-.1a2 2 0 112.8-2.8l.1.1a1.7 1.7 0 001.8.3H9a1.7 1.7 0 001-1.5V3a2 2 0 114 0v.1a1.7 1.7 0 001 1.5 1.7 1.7 0 001.8-.3l.1-.1a2 2 0 112.8 2.8l-.1.1a1.7 1.7 0 00-.3 1.8V9a1.7 1.7 0 001.5 1H21a2 2 0 110 4h-.1a1.7 1.7 0 00-1.5 1z"/>',
            'shield' => '<path d="M12 3l8 3v6c0 5-3.4 8-8 9-4.6-1-8-4-8-9V6z"/>',
            'doc' => '<path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5M10 13h6M10 17h4"/>',
            'edit' => '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="M14 6l4 4"/>',
            'trash' => '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>',
            'plus' => '<path d="M12 5v14M5 12h14"/>',
            'minus' => '<path d="M5 12h14"/>',
            'check' => '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
            'x' => '<path d="M6 6l12 12M18 6L6 18"/>',
            'search' => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>',
            'bell' => '<path d="M6 16V11a6 6 0 1112 0v5l1.6 1.6a.6.6 0 01-.4 1H4.8a.6.6 0 01-.4-1z"/><path d="M10 21a2 2 0 004 0"/>',
            'moon' => '<path d="M20 14.5A8 8 0 019.5 4 8 8 0 1020 14.5z"/>',
            'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
            'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 010 18M12 3a14 14 0 000 18"/>',
            'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
            'more' => '<circle cx="5" cy="12" r="1.4"/><circle cx="12" cy="12" r="1.4"/><circle cx="19" cy="12" r="1.4"/>',
            'more-v' => '<circle cx="12" cy="5" r="1.4"/><circle cx="12" cy="12" r="1.4"/><circle cx="12" cy="19" r="1.4"/>',
            'chevron-down' => '<path d="M6 9l6 6 6-6"/>',
            'chevron-up' => '<path d="M6 15l6-6 6 6"/>',
            'chevron-end' => '<path d="M9 6l6 6-6 6"/>',
            'chevron-start' => '<path d="M15 6l-6 6 6 6"/>',
            'arrow-up' => '<path d="M12 19V5M6 11l6-6 6 6"/>',
            'arrow-down' => '<path d="M12 5v14M6 13l6 6 6-6"/>',
            'arrow-end' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
            'back' => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
            'external' => '<path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 01-1 1H5a1 1 0 01-1-1V7a1 1 0 011-1h5"/>',
            'copy' => '<rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V5a1 1 0 00-1-1H5a1 1 0 00-1 1v10a1 1 0 001 1h3"/>',
            'download' => '<path d="M12 4v11M7 11l5 5 5-5M5 20h14"/>',
            'upload' => '<path d="M12 16V5M7 9l5-5 5 5M5 20h14"/>',
            'image' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="1.6"/><path d="M21 16l-5-5-9 9"/>',
            'filter' => '<path d="M4 5h16l-6 8v6l-4-2v-4z"/>',
            'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
            'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
            'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
            'alert' => '<path d="M12 3l10 18H2z"/><path d="M12 10v5M12 18h.01"/>',
            'check-circle' => '<circle cx="12" cy="12" r="9"/><path d="M8 12.5l3 3 5-6"/>',
            'x-circle' => '<circle cx="12" cy="12" r="9"/><path d="M9 9l6 6M15 9l-6 6"/>',
            'lock' => '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 018 0v3"/>',
            'eye' => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
            'eye-off' => '<path d="M3 3l18 18M10.6 5.1A10 10 0 0112 5c6.5 0 10 7 10 7a17 17 0 01-3.3 4.2M6.6 6.6A17 17 0 002 12s3.5 7 10 7a9.7 9.7 0 004.4-1"/>',
            'link' => '<path d="M10 14a4 4 0 005.7 0l3-3a4 4 0 00-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 00-5.7 0l-3 3A4 4 0 0011 18.7l1-1"/>',
            'share' => '<circle cx="6" cy="12" r="2.5"/><circle cx="18" cy="6" r="2.5"/><circle cx="18" cy="18" r="2.5"/><path d="M8.2 10.8l7.6-3.6M8.2 13.2l7.6 3.6"/>',
            'whatsapp' => '<path d="M3 21l1.7-5A8.5 8.5 0 1112 20.5a8.4 8.4 0 01-4-1z"/><path d="M9 9.5c0 3 2.5 5.5 5.5 5.5l1-1.5-2-1-1 .8a4 4 0 01-1.8-1.8l.8-1-1-2z"/>',
            'sparkles' => '<path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9zM19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z"/>',
            'bulb' => '<path d="M9 18h6M10 21h4M12 3a6 6 0 00-3.5 10.9c.6.5 1 1.2 1 2V17h5v-1.1c0-.8.4-1.5 1-2A6 6 0 0012 3z"/>',
            'gift' => '<path d="M20 12v9H4v-9M2 7h20v5H2zM12 21V7M12 7H7.5a2.5 2.5 0 110-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 100-5C13 2 12 7 12 7z"/>',
            'star' => '<path d="M12 3l2.9 6 6.6.9-4.8 4.6 1.2 6.5L12 17.8 6.1 21l1.2-6.5L2.5 9.9 9.1 9z"/>',
            'logout' => '<path d="M15 4h3a2 2 0 012 2v12a2 2 0 01-2 2h-3M10 17l-5-5 5-5M5 12h11"/>',
            'receipt' => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6"/>',
            'percent' => '<path d="M19 5L5 19"/><circle cx="7" cy="7" r="2.2"/><circle cx="17" cy="17" r="2.2"/>',
            'zap' => '<path d="M13 2L4 14h7l-1 8 9-12h-7z"/>',
            'building' => '<path d="M4 21V5a1 1 0 011-1h9a1 1 0 011 1v16M15 10h4a1 1 0 011 1v10M2 21h20M8 8h3M8 12h3M8 16h3"/>',
            'map-pin' => '<path d="M12 21s-7-6.2-7-11a7 7 0 0114 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/>',
            'activity' => '<path d="M3 12h4l3-8 4 16 3-8h4"/>',
            'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
            'phone' => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 005 5L15 13l5 2v4a2 2 0 01-2 2A16 16 0 013 6a2 2 0 012-2z"/>',
            'grid' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
            'list' => '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
            'refresh' => '<path d="M20 11a8 8 0 00-14.5-3.5L4 9M4 4v5h5M4 13a8 8 0 0014.5 3.5L20 15M20 20v-5h-5"/>',
            'sliders' => '<path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12M20 18h0"/><circle cx="16" cy="6" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="18" cy="18" r="2"/>',
            'undo' => '<path d="M9 14l-4-4 4-4"/><path d="M5 10h9a5 5 0 010 10h-3"/>',
        ];
    }
}
