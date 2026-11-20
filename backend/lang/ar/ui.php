<?php

// نصوص مكتبة واجهة اللوحات المشتركة (resources/views/components/ui و components/panel). حافظ على تطابق المفاتيح مع lang/en/ui.php.
return [
    'close' => 'إغلاق', 'loading' => 'جارٍ التحميل', 'required' => 'مطلوب', 'skip' => 'تخطَّ إلى المحتوى', 'undo' => 'تراجع', 'copied' => 'تم النسخ', 'copy_link' => 'نسخ الرابط',
    'share_whatsapp' => 'مشاركة عبر واتساب', 'toggle_theme' => 'تبديل الوضع الداكن', 'show_password' => 'إظهار كلمة المرور', 'hide_password' => 'إخفاء كلمة المرور',
    'first_is_main' => 'الصورة الأولى هي الرئيسية. اسحب لإضافة المزيد.', 'missing_translation' => 'الترجمة ناقصة', 'otp_code' => 'رمز التحقق',
    'otp_digit' => 'الرقم :n', 'unsaved_changes' => 'لديك تغييرات غير محفوظة', 'steps' => 'الخطوات', 'breadcrumb' => 'مسار التنقل', 'selected' => 'محدد', 'filters' => 'تصفية',
    'pagination' => 'ترقيم الصفحات', 'range' => ':from-:to من :total', 'page_of' => 'صفحة :page من :pages', 'prev' => 'السابق', 'next' => 'التالي',
    'signin_email_help' => 'أدخل بريدك الإلكتروني وكلمة المرور.',
    'referral' => ['title' => 'رابط الإحالة الخاص بك'],
    'cmdk' => ['navigate' => 'تنقّل', 'open' => 'فتح', 'quick' => 'إجراءات سريعة', 'pages' => 'الصفحات'],
    'empty' => [
        'nodata' => ['title' => 'لا يوجد شيء بعد', 'message' => 'ما تضيفه سيظهر هنا.'],
        'noresults' => ['title' => 'لا نتائج', 'message' => 'جرّب بحثاً مختلفاً أو امسح عوامل التصفية.'],
        'error' => ['title' => 'حدث خطأ ما', 'message' => 'تعذّر التحميل. حاول مرة أخرى.'],
        'forbidden' => ['title' => 'لا صلاحية', 'message' => 'ليست لديك صلاحية لعرض هذا.'],
    ],
    'auth_side' => [
        'affiliate' => ['headline' => 'اربح من كل توصية.', 'points' => ['روابطك الخاصة ونقراتها لحظة بلحظة', 'العمولات والمكافآت في مكان واحد', 'اطلب سحب أرباحك بنقرة']],
    ],
    'guide' => [
        'title' => 'مكتبة الواجهة', 'intro' => 'كل مكوّنات وأشكال نظام تصميم اللوحات (صفحات Figma 11-13). صفحة للمطوّرين فقط.',
        'panel' => 'اللوحة', 'theme' => 'المظهر', 'light' => 'فاتح', 'dark' => 'داكن',
    ],
    'sections' => [
        'basic' => 'المعلومات الأساسية',
        'details' => 'التفاصيل',
        'media' => 'الوسائط',
        'status' => 'الحالة والظهور',
        'permissions' => 'الصلاحيات',
        'localization' => 'اللغة والمنطقة',
        'programs' => 'البرامج',
        'pricing' => 'الأسعار',
        'inventory' => 'المخزون',
        'organization' => 'التصنيف',
    ],
    // نصوص DataTables الأصلية (resources/views/components/layouts/base.blade.php -> window.__t.datatable)
    'datatable' => [
        'search' => 'بحث:', 'lengthMenu' => '_MENU_ في الصفحة', 'info' => 'عرض _START_ إلى _END_ من _TOTAL_', 'infoEmpty' => 'لا توجد سجلات', 'infoFiltered' => '(مصفّاة من _MAX_)',
        'first' => 'الأولى', 'last' => 'الأخيرة', 'next' => 'التالي', 'previous' => 'السابق',
    ],
];
