<?php

// Phase 6 roles / staff accounts UI
return [
    'audit_log' => 'سجل التدقيق',
    'roles' => 'الأدوار',
    'admins' => 'حسابات الموظفين',
    'permissions' => 'الصلاحيات',
    'users' => 'المستخدمون',
    'system' => 'نظام',
    'all' => 'الكل',
    'select_all' => 'تحديد الكل',
    'role_in_use' => 'هذا الدور مسند لحسابات موظفين.',
    'role_not_assignable' => 'لا يمكنك إسناد دور يتضمن صلاحيات لا تملكها.',
    'cannot_delete_self' => 'لا يمكنك حذف حسابك.',
    'password_hint' => 'اتركه فارغا للإبقاء على كلمة المرور الحالية.',
    'fields' => ['name' => 'الاسم', 'email' => 'البريد الإلكتروني', 'phone' => 'الهاتف', 'country' => 'الدولة', 'password' => 'كلمة المرور', 'permissions' => 'الصلاحيات', 'roles' => 'الأدوار'],
    'modules' => ['general_settings' => 'الإعدادات العامة', 'erp_dashboard' => 'ERP - لوحة المؤشرات', 'erp_accounts' => 'ERP - دليل الحسابات والعملاء والموردون', 'erp_journals' => 'ERP - القيود اليومية', 'erp_expenses' => 'ERP - ربط المصروفات بالحسابات', 'erp_reports' => 'ERP - التقارير المالية', 'erp_profits' => 'ERP - توزيع الأرباح', 'erp_owners' => 'ERP - الملاك وحقوق الملكية', 'erp_branches' => 'ERP - محاسبة الفروع', 'erp_settings' => 'ERP - الإعدادات والترحيل', 'erp_periods' => 'ERP - الفترات المحاسبية', 'erp_assets' => 'ERP - الأصول الثابتة', 'erp_audit' => 'ERP - سجل التدقيق', 'whatsapp' => 'اتصال واتساب', 'whatsapp_chat' => 'محادثات واتساب', 'tags' => 'الوسوم', 'audit' => 'سجل التدقيق', 'languages' => 'اللغات', 'brands' => 'العلامات التجارية', 'attributes' => 'الخصائص', 'categories' => 'الأقسام', 'products' => 'المنتجات', 'admins' => 'حسابات الموظفين', 'roles' => 'الأدوار', 'reports' => 'التقارير', 'settings' => 'الإعدادات', 'cities' => 'المدن', 'branches' => 'الفروع', 'suppliers' => 'الموردون', 'shipping' => 'الشحن', 'payment_methods' => 'طرق الدفع', 'banners' => 'البنرات', 'expense_categories' => 'تصنيفات المصروفات', 'expenses' => 'المصروفات', 'stock' => 'المخزون', 'purchases' => 'المشتريات', 'stock_transfers' => 'تحويلات المخزون', 'stock_adjustments' => 'تسويات المخزون', 'orders' => 'الطلبات', 'pos' => 'نقطة البيع', 'returns' => 'المرتجعات', 'customers' => 'العملاء', 'discount_codes' => 'أكواد الخصم', 'flash_sales' => 'العروض الخاطفة', 'bundles' => 'الباقات', 'packaging' => 'التغليف الخاص', 'affiliates' => 'المسوقون', 'affiliate_tiers' => 'شرائح العمولة', 'affiliate_payouts' => 'مدفوعات المسوقين', 'product_lists' => 'قوائم المنتجات', 'reviews' => 'التقييمات', 'stock_requests' => 'طلبات توفير المنتجات', 'custom_pages' => 'الصفحات المخصصة', 'static_pages' => 'الصفحات الثابتة', 'blog_posts' => 'مقالات المدونة', 'growth' => 'نظام النمو (n8n)', 'marketing' => 'لوحة التسويق', 'ai_suggestions' => 'اقتراحات Claude (مركز الاقتراحات)', 'ai_media' => 'مكتبة وسائط السوشيال ميديا', 'ai_seo' => 'اقتراحات SEO', 'ai_blog' => 'اقتراحات المدونة', 'ai_theme' => 'اقتراحات الألوان والمواسم', 'meta_ads' => 'حملات ميتا', 'gift_finder' => 'مستكشف الهدايا', 'integration_clients' => 'عملاء التكامل (n8n)'],
    'actions' => ['post' => 'ترحيل', 'reverse' => 'عكس', 'close' => 'إقفال', 'reopen' => 'إعادة فتح', 'distribute' => 'توزيع', 'export' => 'تصدير', 'reports' => 'التقارير', 'audit' => 'التدقيق', 'view' => 'عرض', 'create' => 'إنشاء', 'edit' => 'تعديل', 'delete' => 'حذف', 'receive' => 'استلام', 'send' => 'إرسال', 'approve' => 'اعتماد', 'refund' => 'استرداد', 'payments' => 'المدفوعات', 'email_notifications' => 'إشعارات البريد', 'manage' => 'إدارة', 'media' => 'الوسائط', 'analytics' => 'التحليلات', 'tokens_manage' => 'إدارة مفاتيح API', 'audit_view' => 'عرض سجل API', 'reject' => 'رفض', 'apply' => 'تطبيق', 'archive' => 'أرشفة', 'upload' => 'رفع', 'draft' => 'مسودة', 'publish' => 'نشر'],
    'audit_when' => 'التاريخ', 'audit_who' => 'بواسطة', 'audit_area' => 'القسم', 'audit_event' => 'الحدث', 'audit_subject' => 'العنصر', 'audit_changes' => 'التغييرات',
    'cannot_remove_own_system_role' => 'لا يمكنك إزالة دور المالك من حسابك.',
    'otp_exempt' => 'تسجيل الدخول بدون رمز تحقق (كلمة المرور فقط)',
    'otp_exempt_hint' => 'أقل أمانًا: لن يُطلب من هذا المسؤول رمز واتساب.',
];
