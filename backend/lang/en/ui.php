<?php

// Shared panel UI kit strings (resources/views/components/ui, components/panel). Keep keys in step with lang/ar/ui.php.
return [
    'close' => 'Close', 'loading' => 'Loading', 'required' => 'required', 'skip' => 'Skip to content', 'undo' => 'Undo', 'copied' => 'Copied', 'copy_link' => 'Copy link',
    'share_whatsapp' => 'Share on WhatsApp', 'toggle_theme' => 'Toggle dark mode', 'show_password' => 'Show password', 'hide_password' => 'Hide password',
    'first_is_main' => 'The first image is the main one. Drag to add more.', 'missing_translation' => 'Translation missing', 'otp_code' => 'Verification code',
    'otp_digit' => 'Digit :n', 'unsaved_changes' => 'You have unsaved changes', 'steps' => 'Steps', 'breadcrumb' => 'Breadcrumb', 'selected' => 'selected', 'filters' => 'Filters',
    'pagination' => 'Pagination', 'range' => ':from-:to of :total', 'page_of' => 'Page :page of :pages', 'prev' => 'Previous', 'next' => 'Next',
    'signin_email_help' => 'Enter your email and password.',
    'referral' => ['title' => 'Your referral link'],
    'cmdk' => ['navigate' => 'navigate', 'open' => 'open', 'quick' => 'Quick actions', 'pages' => 'Pages'],
    'empty' => [
        'nodata' => ['title' => 'Nothing here yet', 'message' => 'Items you add will appear here.'],
        'noresults' => ['title' => 'No results', 'message' => 'Try a different search or clear the filters.'],
        'error' => ['title' => 'Something went wrong', 'message' => 'We could not load this. Please try again.'],
        'forbidden' => ['title' => 'No access', 'message' => 'You do not have permission to see this.'],
    ],
    'auth_side' => [
        'affiliate' => ['headline' => 'Earn from every recommendation.', 'points' => ['Your own links and live clicks', 'Commissions and bonuses in one place', 'Request a payout in one tap']],
    ],
    'guide' => [
        'title' => 'UI kit', 'intro' => 'Every component and variant of the panel design system (Figma pages 11-13). Dev-only page.',
        'panel' => 'Panel', 'theme' => 'Theme', 'light' => 'Light', 'dark' => 'Dark',
    ],
    'sections' => [
        'basic' => 'Basic information',
        'details' => 'Details',
        'media' => 'Media',
        'status' => 'Status and visibility',
        'permissions' => 'Permissions',
        'localization' => 'Localization',
        'programs' => 'Programs',
        'pricing' => 'Pricing',
        'inventory' => 'Inventory',
        'organization' => 'Organization',
    ],
    // DataTables built-in strings (resources/views/components/layouts/base.blade.php -> window.__t.datatable)
    'datatable' => [
        'search' => 'Search:', 'lengthMenu' => '_MENU_ per page', 'info' => 'Showing _START_ to _END_ of _TOTAL_', 'infoEmpty' => 'No entries', 'infoFiltered' => '(filtered from _MAX_)',
        'first' => 'First', 'last' => 'Last', 'next' => 'Next', 'previous' => 'Previous',
    ],
];
