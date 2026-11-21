<?php

use App\Models\StaticPage;
use Illuminate\Database\Migrations\Migration;

/**
 * Public account-deletion page required by Google Play's Data safety form:
 * /{locale}/page/delete-account. Content mirrors CustomerAccountService::deleteAccount().
 */
return new class extends Migration
{
    private const EMAIL = 'support@3alraf.com';

    public function up(): void
    {
        $email = self::EMAIL;

        StaticPage::updateOrCreate(['slug' => 'delete-account'], [
            'is_active' => true,
            'title' => ['ar' => 'حذف الحساب', 'en' => 'Delete your account'],
            'content' => [
                'ar' => <<<AR
تطبيق ومتجر عالرف (3alraf) يتيح لك حذف حسابك والبيانات المرتبطة به في أي وقت.

طريقة حذف الحساب من التطبيق:
1. افتح تطبيق عالرف وسجّل الدخول.
2. اذهب إلى «حسابي».
3. اختر «حذف الحساب» ثم أكّد الحذف.

إذا لم تتمكن من استخدام التطبيق:
أرسل رسالة إلى {$email} من البريد الإلكتروني المسجّل في حسابك بعنوان «طلب حذف الحساب». سننفّذ الطلب خلال 30 يومًا ونرسل لك تأكيدًا.

البيانات التي تُحذف فورًا:
- الاسم والبريد الإلكتروني ورقم الهاتف وصورة الحساب
- ربط تسجيل الدخول بحساب Google وكلمة المرور
- العناوين المحفوظة وقائمة الأمنيات
- جلسات الدخول وبيانات الأجهزة والإشعارات

البيانات التي نحتفظ بها:
- سجلات الطلبات والفواتير تُحفظ دون بيانات تعريفية (مجهولة الهوية) لأغراض المحاسبة والالتزامات القانونية.
- تُحذف بقايا الحساب نهائيًا من قواعد البيانات خلال 60 يومًا من الحذف.
AR,
                'en' => <<<EN
The 3alraf app and store let you delete your account and its associated data at any time.

How to delete your account in the app:
1. Open the 3alraf app and sign in.
2. Go to "Account".
3. Tap "Delete account" and confirm.

If you can't use the app:
Email {$email} from the email address registered on your account with the subject "Account deletion request". We will complete the request within 30 days and send you a confirmation.

Data deleted immediately:
- Name, email address, phone number and profile photo
- Google sign-in link and password
- Saved addresses and wishlist
- Login sessions, device and notification data

Data we keep:
- Order and invoice records are kept without identifying details (anonymised) for accounting and legal obligations.
- Remaining account records are permanently purged from our databases within 60 days of deletion.
EN,
            ],
        ]);
    }

    public function down(): void
    {
        StaticPage::where('slug', 'delete-account')->delete();
    }
};
