<?php

return [
    'menu' => ['group' => 'الذكاء الاصطناعي', 'providers' => 'المزوّدون', 'models' => 'النماذج', 'tenants' => 'الذكاء للمتاجر', 'usage' => 'الاستخدام والتكلفة', 'settings' => 'إعدادات الذكاء الاصطناعي'],
    'fields' => [
        'name' => 'الاسم', 'driver' => 'المزوّد', 'api_key' => 'مفتاح API', 'base_url' => 'الرابط الأساسي', 'provider' => 'المزوّد', 'model_id' => 'معرّف النموذج', 'label' => 'التسمية', 'kind' => 'النوع',
        'capabilities' => 'القدرات', 'input_cost' => 'تكلفة الإدخال / مليون رمز', 'output_cost' => 'تكلفة الإخراج / مليون رمز', 'max_output' => 'الحد الأقصى لرموز الإخراج',
        'is_default' => 'افتراضي للنوع', 'tenant' => 'المتجر', 'country' => 'الدولة', 'ai_enabled' => 'تفعيل الذكاء الاصطناعي', 'monthly_token_quota' => 'حصة الرموز الشهرية',
        'monthly_image_quota' => 'حصة الصور الشهرية', 'monthly_video_quota' => 'حصة الفيديو الشهرية (ثوانٍ)', 'features' => 'الميزات', 'allowed_models' => 'النماذج المسموحة',
        'date' => 'التاريخ', 'feature' => 'الميزة', 'model' => 'النموذج', 'default_model' => 'النموذج الافتراضي', 'brand_voice' => 'نبرة العلامة التجارية', 'forbidden_words' => 'كلمات ممنوعة (مفصولة بفواصل)',
        'default_platforms' => 'المنصات الافتراضية', 'default_ai_model_id' => 'النموذج الافتراضي', 'allowed_model_ids' => 'النماذج المسموحة', 'ai_provider_id' => 'المزوّد',
    ],
    'enums' => [
        'ai_driver_type' => ['anthropic' => 'Anthropic (Claude)', 'openai' => 'OpenAI', 'moonshot' => 'Moonshot (Kimi)', 'custom_openai' => 'مخصص (متوافق مع OpenAI)'],
        'ai_model_kind' => ['text' => 'نص', 'image' => 'صورة', 'video' => 'فيديو'],
        'ai_feature' => ['chat' => 'المساعد', 'trends' => 'الترندات', 'studio' => 'الاستوديو', 'editor' => 'محرر المنتجات', 'generators' => 'المولّدات'],
        'ai_generation_type' => ['social_post' => 'منشور اجتماعي', 'marketing_post' => 'منشور تسويقي', 'banner' => 'بانر', 'offer' => 'عرض', 'bundle' => 'حزمة', 'package' => 'باقة', 'product_edit' => 'تعديل منتج', 'trend_report' => 'تقرير ترندات'],
        'ai_generation_status' => ['queued' => 'في الانتظار', 'draft' => 'مسودة', 'approved' => 'معتمد', 'rejected' => 'مرفوض', 'failed' => 'فشل'],
    ],
    'capabilities' => ['tools' => 'استدعاء الأدوات', 'vision' => 'الرؤية', 'web_search' => 'البحث في الويب', 'json_mode' => 'وضع JSON'],
    'platforms' => ['tiktok' => 'تيك توك', 'instagram' => 'إنستغرام', 'facebook' => 'فيسبوك', 'x' => 'إكس', 'pinterest' => 'بينترست', 'whatsapp' => 'واتساب'],
    'connection' => 'الاتصال', 'test_connection' => 'اختبار الاتصال', 'test_ok' => 'الاتصال سليم', 'test_failed' => 'فشل الاتصال',
    'key_keep' => 'اتركه فارغاً للإبقاء على المفتاح الحالي.', 'base_url_hint' => 'اختياري للمزوّدين المدمجين؛ مطلوب للمزوّد المخصص.',
    'on' => 'مفعّل', 'off' => 'متوقف', 'unlimited' => 'غير محدود', 'quota_hint' => 'فارغ = غير محدود.', 'allowed_hint' => 'لا تحدد شيئاً للسماح بكل النماذج النشطة.',
    'usage_this_month' => 'هذا الشهر', 'tokens' => 'الرموز', 'images' => 'الصور', 'video_secs' => 'ثوانٍ فيديو', 'cost' => 'التكلفة التقديرية', 'calls' => 'الطلبات',
    'platform_default' => 'افتراضي المنصة',
    'errors' => [
        'disabled' => 'الذكاء الاصطناعي غير مفعّل لمتجرك أو لهذه الميزة. تواصل مع مسؤول المنصة.',
        'quota' => 'استُهلكت حصتك الشهرية من الذكاء الاصطناعي.',
        'throttled' => 'طلبات كثيرة جداً. يرجى الانتظار قليلاً ثم المحاولة.',
        'unsupported' => 'هذا النموذج أو المزوّد لا يدعم هذه العملية.',
        'provider' => 'خدمة الذكاء الاصطناعي غير متاحة مؤقتاً. حاول مرة أخرى.',
        'model_unavailable' => 'النموذج المحدد غير متاح.',
    ],
];
