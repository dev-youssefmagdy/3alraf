<?php

return [
    'intro' => 'مفاتيح كانت في ملف .env. القيم المحفوظة تتجاوز إعدادات الخادم الافتراضية، والأسرار تُخزَّن مشفّرة ولا تُعرض مرة أخرى (اترك الحقل فارغاً للإبقاء عليه). تبقى APP_KEY وAPP_ENV ووضع الصيانة والبروكسي الموثوق وإعدادات قاعدة البيانات/الكاش/الطابور/البريد في .env لأنها مطلوبة قبل الوصول لقاعدة البيانات.',
    'secret_keep' => 'محفوظ. اتركه فارغاً للإبقاء عليه',
    'secret_empty' => 'غير محدد',
    'groups' => [
        'app' => 'التطبيق',
        'media' => 'الوسائط والصور',
        'google' => 'تسجيل الدخول بجوجل',
        'whatsapp' => 'بوابة واتساب',
        'reverb' => 'الإشعارات اللحظية (Reverb)',
        'meta' => 'واجهة ميتا للتسويق',
    ],
    'fields' => [
        'app_debug' => 'وضع التصحيح (APP_DEBUG)', 'app_url' => 'رابط التطبيق (APP_URL)', 'app_fallback_locale' => 'اللغة الاحتياطية', 'app_faker_locale' => 'لغة البيانات التجريبية',
        'mobile_legacy_store_slug' => 'معرّف المتجر القديم للجوال', 'soft_delete_retention_days' => 'مدة الاحتفاظ بالمحذوفات (أيام)', 'integration_ips' => 'عناوين IP المسموحة لواجهة التكامل (مفصولة بفاصلة، فارغ = الكل)',
        'media_disk' => 'قرص الوسائط العامة', 'media_private_disk' => 'قرص الملفات الخاصة', 'media_url' => 'رابط الوسائط العام (CDN)', 'media_signed_ttl' => 'مدة الروابط الموقّعة (دقائق)',
        'product_video_max_mb' => 'أقصى حجم لفيديو المنتج (ميجا)', 'product_video_max_per_product' => 'أقصى عدد فيديوهات للمنتج', 'social_media_image_max_kb' => 'أقصى حجم صورة في مكتبة السوشيال (ك.ب)', 'social_media_video_max_kb' => 'أقصى حجم فيديو في مكتبة السوشيال (ك.ب)', 'image_webp_fallback' => 'بديل WebP للصور',
        'google_client_id' => 'معرّف عميل جوجل', 'google_client_secret' => 'سر عميل جوجل', 'google_mobile_client_ids' => 'معرّفات عملاء الجوال (مفصولة بفاصلة)',
        'whatsapp_service_url' => 'رابط الخدمة', 'whatsapp_service_token' => 'رمز الخدمة', 'whatsapp_callback_secret' => 'سر الاستدعاء',
        'reverb_app_id' => 'معرّف التطبيق', 'reverb_app_key' => 'مفتاح التطبيق', 'reverb_app_secret' => 'سر التطبيق', 'reverb_host' => 'المضيف العام', 'reverb_port' => 'المنفذ العام', 'reverb_scheme' => 'البروتوكول (http/https)', 'reverb_server_host' => 'مضيف الخادم', 'reverb_server_port' => 'منفذ الخادم',
        'meta_graph_version' => 'إصدار Graph API', 'meta_graph_url' => 'رابط Graph API', 'meta_access_token' => 'رمز الوصول', 'meta_app_id' => 'معرّف التطبيق', 'meta_app_secret' => 'سر التطبيق', 'meta_ad_account_id' => 'معرّف الحساب الإعلاني', 'meta_page_id' => 'معرّف الصفحة', 'meta_instagram_user_id' => 'معرّف حساب إنستجرام', 'meta_currency' => 'العملة',
        'meta_cache_minutes' => 'مدة الكاش (دقائق)', 'meta_approval_valid_hours' => 'صلاحية الموافقة (ساعات)', 'meta_min_daily_budget' => 'أدنى ميزانية يومية', 'meta_max_daily_budget' => 'أقصى ميزانية يومية', 'meta_max_lifetime_budget' => 'أقصى ميزانية إجمالية',
    ],
];
