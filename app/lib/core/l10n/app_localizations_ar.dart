// ignore: unused_import
import 'package:intl/intl.dart' as intl;
import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for Arabic (`ar`).
class AppLocalizationsAr extends AppLocalizations {
  AppLocalizationsAr([String locale = 'ar']) : super(locale);

  @override
  String get appName => 'عالرف';

  @override
  String get retry => 'إعادة المحاولة';

  @override
  String get loading => 'جارٍ التحميل…';

  @override
  String get errorGeneric => 'حدث خطأ ما. حاول مرة أخرى.';

  @override
  String get errorNetwork =>
      'تعذّر الاتصال بالإنترنت. تحقق من الشبكة وحاول مرة أخرى.';

  @override
  String get errorTimeout => 'انتهت مهلة الاتصال. حاول مرة أخرى.';

  @override
  String get errorServer => 'حدث خطأ في الخادم. حاول لاحقًا.';

  @override
  String get errorRateLimited => 'محاولات كثيرة. انتظر قليلًا ثم أعد المحاولة.';

  @override
  String get errorNotFound => 'غير موجود.';

  @override
  String get errorUnauthorized => 'انتهت الجلسة. سجّل الدخول مرة أخرى.';

  @override
  String get emptyTitle => 'لا يوجد شيء هنا بعد';

  @override
  String get navHome => 'الرئيسية';

  @override
  String get navCategories => 'الأقسام';

  @override
  String get navCart => 'السلة';

  @override
  String get navWishlist => 'المفضلة';

  @override
  String get navAccount => 'حسابي';

  @override
  String get comingSoon => 'قريبًا';

  @override
  String get addToCart => 'أضف إلى السلة';

  @override
  String get outOfStock => 'غير متوفر';

  @override
  String currencyAmount(String amount, String currency) {
    return '$amount $currency';
  }

  @override
  String discountBadge(String percent) {
    return 'خصم $percent٪';
  }

  @override
  String ratingSemantics(String rating) {
    return 'التقييم $rating من 5';
  }

  @override
  String get quantityIncrease => 'زيادة الكمية';

  @override
  String get quantityDecrease => 'تقليل الكمية';

  @override
  String quantityLabel(int count) {
    return 'الكمية $count';
  }

  @override
  String get viewAll => 'عرض الكل';

  @override
  String get cancel => 'إلغاء';

  @override
  String get confirm => 'تأكيد';

  @override
  String get close => 'إغلاق';

  @override
  String get menu => 'القائمة';

  @override
  String get startupErrorTitle => 'تعذّر تشغيل التطبيق';

  @override
  String get selectCountryTitle => 'اختر بلدك';

  @override
  String get selectCountrySubtitle => 'اختر المتجر الذي تريد التسوّق منه.';

  @override
  String get searchCountry => 'ابحث عن بلد أو متجر';

  @override
  String get noStoresFound => 'لا توجد متاجر مطابقة';

  @override
  String get noStoresAvailable => 'لا توجد متاجر متاحة حاليًا.';

  @override
  String get continueAction => 'متابعة';

  @override
  String confirmCountryTitle(String store) {
    return 'التسوّق من $store؟';
  }

  @override
  String get confirmCountryMessage =>
      'يمكنك تغيير البلد لاحقًا من القائمة الجانبية.';

  @override
  String get countryInactiveNotice =>
      'متجرك السابق لم يعد متاحًا. اختر بلدًا آخر للمتابعة.';

  @override
  String get currentStore => 'الحالي';

  @override
  String storeCurrency(String currency) {
    return 'العملة: $currency';
  }

  @override
  String storeLanguages(String languages) {
    return 'اللغات: $languages';
  }

  @override
  String get switchCountry => 'تغيير البلد';

  @override
  String get switchCountryWarnTitle => 'تغيير المتجر؟';

  @override
  String switchCountryWarnBody(String store) {
    return 'السلة والمفضلة تخصّ المتجر الحالي. سيتم تسجيل خروجك ومسح بيانات السلة على هذا الجهاز عند التغيير إلى $store.';
  }

  @override
  String get switchConfirm => 'تغيير';

  @override
  String switchedTo(String store) {
    return 'تتسوّق الآن من $store';
  }

  @override
  String get offlineBanner => 'أنت غير متصل. نعرض بيانات محفوظة.';

  @override
  String get updateRequiredTitle => 'يلزم تحديث التطبيق';

  @override
  String get updateRequiredBody =>
      'يرجى تحديث التطبيق إلى أحدث إصدار للمتابعة.';

  @override
  String get updateNow => 'تحديث الآن';

  @override
  String get maintenanceTitle => 'المتجر تحت الصيانة';

  @override
  String get maintenanceDefault => 'نعمل على تحسين الخدمة وسنعود قريبًا.';

  @override
  String get onboarding1Title => 'تسوّق مستحضرات التجميل بسهولة';

  @override
  String get onboarding1Body =>
      'آلاف المنتجات الأصلية من أفضل العلامات في مكان واحد.';

  @override
  String get onboarding2Title => 'عروض وهدايا مميزة';

  @override
  String get onboarding2Body =>
      'تابع العروض اليومية واعثر على الهدية المناسبة.';

  @override
  String get onboarding3Title => 'توصيل سريع ودفع مرن';

  @override
  String get onboarding3Body => 'اختر طريقة الشحن والدفع التي تناسبك.';

  @override
  String get skip => 'تخطي';

  @override
  String get next => 'التالي';

  @override
  String get getStarted => 'ابدأ الآن';

  @override
  String get language => 'اللغة';

  @override
  String get contactWhatsapp => 'تواصل عبر واتساب';

  @override
  String appVersion(String version) {
    return 'الإصدار $version';
  }

  @override
  String currentStoreLabel(String store) {
    return 'المتجر الحالي: $store';
  }

  @override
  String get login => 'تسجيل الدخول';

  @override
  String get register => 'إنشاء حساب';

  @override
  String get logout => 'تسجيل الخروج';

  @override
  String get loginSubtitle => 'أهلًا بعودتك! سجّل الدخول لمتابعة التسوّق.';

  @override
  String get registerSubtitle => 'أنشئ حسابك للاحتفاظ بطلباتك وعناوينك.';

  @override
  String get sessionExpiredNotice => 'انتهت جلستك. سجّل الدخول مرة أخرى.';

  @override
  String get email => 'البريد الإلكتروني';

  @override
  String get password => 'كلمة المرور';

  @override
  String get confirmPassword => 'تأكيد كلمة المرور';

  @override
  String get fullName => 'الاسم الكامل';

  @override
  String get phoneOptional => 'رقم الهاتف (اختياري)';

  @override
  String get forgotPasswordLink => 'نسيت كلمة المرور؟';

  @override
  String get signInWithGoogle => 'المتابعة باستخدام Google';

  @override
  String get orDivider => 'أو';

  @override
  String get noAccountPrompt => 'ليس لديك حساب؟';

  @override
  String get haveAccountPrompt => 'لديك حساب بالفعل؟';

  @override
  String get continueAsGuest => 'المتابعة كزائر';

  @override
  String get forgotTitle => 'استعادة كلمة المرور';

  @override
  String get forgotBody => 'أدخل بريدك الإلكتروني وسنرسل لك رمزًا من 6 أرقام.';

  @override
  String get sendCode => 'إرسال الرمز';

  @override
  String get resetTitle => 'كلمة مرور جديدة';

  @override
  String get resetBody => 'أدخل الرمز الذي وصلك وكلمة المرور الجديدة.';

  @override
  String get codeLabel => 'الرمز (6 أرقام)';

  @override
  String get newPassword => 'كلمة المرور الجديدة';

  @override
  String get resetAction => 'تغيير كلمة المرور';

  @override
  String get resetDone => 'تم تغيير كلمة المرور. سجّل الدخول الآن.';

  @override
  String get verifyTitle => 'تأكيد البريد الإلكتروني';

  @override
  String get verifyBody => 'أرسلنا رمزًا إلى بريدك. أدخله لتأكيد الحساب.';

  @override
  String get verifyAction => 'تأكيد';

  @override
  String get resendCode => 'إعادة إرسال الرمز';

  @override
  String get codeSent => 'تم إرسال الرمز إلى بريدك.';

  @override
  String get emailVerified => 'تم تأكيد بريدك الإلكتروني.';

  @override
  String get skipForNow => 'لاحقًا';

  @override
  String get fieldRequired => 'هذا الحقل مطلوب';

  @override
  String get invalidEmail => 'بريد إلكتروني غير صالح';

  @override
  String get passwordTooShort => '8 أحرف على الأقل';

  @override
  String get passwordMismatch => 'كلمتا المرور غير متطابقتين';

  @override
  String get invalidCode => 'أدخل 6 أرقام';

  @override
  String get accountGuestTitle => 'أهلًا بك في عالرف';

  @override
  String get accountGuestBody => 'سجّل الدخول لمتابعة طلباتك وحفظ مفضلتك.';

  @override
  String signedInAs(String name) {
    return 'مسجّل باسم $name';
  }

  @override
  String get wishlistSignInBody => 'سجّل الدخول لعرض مفضلتك.';

  @override
  String get googleFailed => 'تعذّر تسجيل الدخول عبر Google.';

  @override
  String get emailNotVerified => 'البريد غير مؤكد';

  @override
  String get searchHint => 'ابحث عن المنتجات';

  @override
  String get homeEmptyTitle => 'لا يوجد محتوى بعد';

  @override
  String get homeEmptyBody => 'المتجر قيد التجهيز. اسحب للأسفل للتحديث.';

  @override
  String get flashSale => 'عروض خاطفة';

  @override
  String get flashEndsIn => 'ينتهي خلال';

  @override
  String get railRecommended => 'مقترح لك';

  @override
  String get railMostSold => 'الأكثر مبيعاً';

  @override
  String get railMostReviewed => 'الأكثر تقييماً';

  @override
  String get brands => 'الماركات';

  @override
  String get brandsEmpty => 'لا توجد ماركات بعد';

  @override
  String get bundles => 'العروض المجمّعة';

  @override
  String get bundle => 'عرض مجمّع';

  @override
  String get bundlesEmpty => 'لا توجد عروض مجمّعة حالياً.';

  @override
  String get bundleContains => 'يتضمن هذا العرض';

  @override
  String get bundleUnavailable => 'غير متاح حالياً';

  @override
  String bundleSave(String amount) {
    return 'وفّر $amount';
  }

  @override
  String get giftCtaTitle => 'اعثر على الهدية المثالية';

  @override
  String get giftCtaBody => 'أخبرنا بميزانيتك والمناسبة';

  @override
  String get categoriesEmpty => 'لا توجد أقسام بعد';

  @override
  String allInCategory(String name) {
    return 'كل $name';
  }

  @override
  String get products => 'المنتجات';

  @override
  String productsCount(int count) {
    return '$count منتج';
  }

  @override
  String searchResultsFor(String q) {
    return 'نتائج \"$q\"';
  }

  @override
  String get noProducts => 'لا توجد منتجات';

  @override
  String get noProductsFiltered => 'جرّب إزالة بعض عوامل التصفية.';

  @override
  String get resetFilters => 'إعادة ضبط التصفية';

  @override
  String get reset => 'إعادة ضبط';

  @override
  String get filters => 'تصفية';

  @override
  String get applyFilters => 'عرض النتائج';

  @override
  String get sortBy => 'ترتيب حسب';

  @override
  String get sortNewest => 'الأحدث';

  @override
  String get sortPriceAsc => 'السعر: من الأقل للأعلى';

  @override
  String get sortPriceDesc => 'السعر: من الأعلى للأقل';

  @override
  String get sortBestSelling => 'الأكثر مبيعاً';

  @override
  String get sortTopRated => 'الأعلى تقييماً';

  @override
  String get price => 'السعر';

  @override
  String get priceFrom => 'من';

  @override
  String get priceTo => 'إلى';

  @override
  String get clear => 'مسح';

  @override
  String get recentSearches => 'عمليات البحث الأخيرة';

  @override
  String get searchEmptyTitle => 'ابحث في المتجر';

  @override
  String get searchEmptyBody => 'اكتب اسم منتج أو ماركة أو قسم.';

  @override
  String searchFor(String term) {
    return 'بحث عن \"$term\"';
  }

  @override
  String get noSuggestions => 'لا توجد اقتراحات';

  @override
  String get addedToCart => 'تمت الإضافة إلى السلة.';

  @override
  String get viewCart => 'عرض السلة';

  @override
  String get chooseOptions => 'اختر الخيارات';

  @override
  String get unavailableCombo => 'هذه التشكيلة غير متاحة.';

  @override
  String get quantity => 'الكمية';

  @override
  String get description => 'الوصف';

  @override
  String get sku => 'رمز المنتج';

  @override
  String get inStock => 'متوفر';

  @override
  String lowStock(int count) {
    return 'متبقي $count فقط';
  }

  @override
  String get relatedProducts => 'قد يعجبك أيضاً';

  @override
  String get share => 'مشاركة';

  @override
  String get wishlistAdd => 'أضف إلى المفضلة';

  @override
  String get wishlistRemove => 'إزالة من المفضلة';

  @override
  String get wishlistAdded => 'تمت الإضافة إلى المفضلة.';

  @override
  String get wishlistRemoved => 'تمت الإزالة من المفضلة.';

  @override
  String get wishlistEmptyTitle => 'قائمة المفضلة فارغة';

  @override
  String get wishlistEmptyBody => 'اضغط على القلب في أي منتج لحفظه هنا.';

  @override
  String get startShopping => 'ابدأ التسوق';

  @override
  String get reviewsTitle => 'التقييمات';

  @override
  String get noReviews => 'لا توجد تقييمات بعد.';

  @override
  String get writeReview => 'اكتب تقييماً';

  @override
  String get reviewBody => 'تقييمك (اختياري)';

  @override
  String get reviewPickRating => 'يرجى اختيار عدد النجوم.';

  @override
  String get submitReview => 'إرسال التقييم';

  @override
  String get reviewThanks => 'شكراً! سيظهر تقييمك بعد المراجعة.';

  @override
  String get notifyMe => 'أعلمني عند التوفر';

  @override
  String get notifyMeBody =>
      'هذا المنتج غير متوفر. اترك بياناتك وسنتواصل معك عند توفره.';

  @override
  String get notifyMeSend => 'إرسال الطلب';

  @override
  String get notifyMeSent => 'تم إرسال طلبك وسنتواصل معك قريباً.';

  @override
  String get phone => 'رقم الهاتف';

  @override
  String get noteOptional => 'ملاحظة (اختياري)';

  @override
  String get back => 'رجوع';

  @override
  String get featureUnavailable => 'هذه الخدمة غير متاحة في متجرك.';

  @override
  String get giftFinder => 'مرشد الهدايا';

  @override
  String get giftStartOver => 'ابدأ من جديد';

  @override
  String get giftStepBudget => 'ما هي ميزانيتك؟';

  @override
  String get giftBudget => 'الميزانية';

  @override
  String get giftBudgetRequired => 'أدخل ميزانية أكبر من الصفر.';

  @override
  String get giftStepOccasion => 'ما هي المناسبة؟';

  @override
  String get giftStepRecipient => 'لمن الهدية؟';

  @override
  String get giftStepExtras => 'أي تفاصيل أخرى؟';

  @override
  String get giftRestrictions => 'أشياء تتجنبها (اختياري)';

  @override
  String get giftRestrictionsHint => 'مثال: عطور';

  @override
  String get giftFind => 'ابحث عن هدايا';

  @override
  String get giftNoResults => 'لم نجد هدايا';

  @override
  String get giftNoResultsBody => 'جرّب ميزانية أعلى أو قيوداً أقل.';

  @override
  String get giftSuggestions => 'اقتراحات لك';

  @override
  String get giftNearBudget => 'أعلى قليلاً من ميزانيتك';

  @override
  String get giftChooseVariant => 'اختر خياراً';

  @override
  String giftAddSelected(int count) {
    return 'أضف المحدد ($count)';
  }

  @override
  String giftAdded(int count) {
    return 'تمت إضافة $count هدايا إلى السلة.';
  }

  @override
  String get occBirthday => 'عيد ميلاد';

  @override
  String get occMothersDay => 'عيد الأم';

  @override
  String get occValentine => 'عيد الحب';

  @override
  String get occWedding => 'زفاف';

  @override
  String get occEidFitr => 'عيد الفطر';

  @override
  String get occEidAdha => 'عيد الأضحى';

  @override
  String get occRamadan => 'رمضان';

  @override
  String get occGraduation => 'تخرّج';

  @override
  String get occThanks => 'شكر';

  @override
  String get occSelf => 'مكافأة لنفسي';

  @override
  String get recMother => 'الأم';

  @override
  String get recPartner => 'الشريك/ة';

  @override
  String get recFriend => 'صديق/ة';

  @override
  String get recSister => 'الأخت';

  @override
  String get recColleague => 'زميل/ة';

  @override
  String get recChild => 'طفل';

  @override
  String get recSelf => 'نفسي';

  @override
  String get recOther => 'شخص آخر';

  @override
  String get cartTitle => 'السلة';

  @override
  String get cartEmptyTitle => 'سلتك فارغة';

  @override
  String get cartEmptyBody => 'أضف منتجات لتبدأ التسوّق.';

  @override
  String get cartSubtotal => 'المجموع الفرعي';

  @override
  String get cartDiscount => 'الخصم';

  @override
  String get cartShipping => 'الشحن';

  @override
  String get cartShippingAtCheckout => 'يُحسب عند إتمام الطلب';

  @override
  String get cartTax => 'الضريبة';

  @override
  String get cartPaymentFee => 'رسوم الدفع';

  @override
  String get cartPackagingFee => 'رسوم التغليف';

  @override
  String get cartCodFee => 'رسوم الدفع عند الاستلام';

  @override
  String get cartTotal => 'الإجمالي';

  @override
  String get cartCheckout => 'إتمام الطلب';

  @override
  String get cartRemove => 'حذف من السلة';

  @override
  String get cartRemoved => 'تمت إزالة المنتج من السلة.';

  @override
  String get cartUnavailable => 'غير متوفر';

  @override
  String get cartUnavailableNotice => 'أزل المنتجات غير المتوفرة للمتابعة';

  @override
  String cartComponent(String name, int count) {
    return '$name × $count';
  }

  @override
  String get discountCode => 'كود الخصم';

  @override
  String get discountApply => 'تطبيق';

  @override
  String discountApplied(String code) {
    return 'تم تطبيق الكود $code';
  }

  @override
  String get discountRemove => 'إزالة';

  @override
  String get delete => 'حذف';

  @override
  String get edit => 'تعديل';

  @override
  String get save => 'حفظ';

  @override
  String get copy => 'نسخ';

  @override
  String get copied => 'تم النسخ';

  @override
  String get checkoutTitle => 'إتمام الطلب';

  @override
  String get stepAddress => 'العنوان';

  @override
  String get stepShipping => 'الشحن';

  @override
  String get stepPackaging => 'التغليف';

  @override
  String get stepPayment => 'الدفع';

  @override
  String get stepReview => 'المراجعة';

  @override
  String get addressNone => 'لا توجد عناوين محفوظة. أضف عنوانًا للمتابعة.';

  @override
  String get shippingNoOptions => 'لا تتوفر شركات شحن لهذه المدينة.';

  @override
  String get shippingFree => 'شحن مجاني';

  @override
  String shippingEta(String days) {
    return 'التوصيل خلال $days يوم';
  }

  @override
  String get packagingNone => 'بدون تغليف خاص';

  @override
  String get paymentNoMethods => 'لا تتوفر طرق دفع حاليًا.';

  @override
  String get paymentPrepayTitle => 'ادفع الشحن مقدمًا';

  @override
  String get paymentPrepayBody =>
      'اختر طريقة تحويل لدفع مصاريف الشحن الآن، والباقي عند الاستلام.';

  @override
  String get proofRequired => 'ارفع صورة إيصال التحويل لإتمام الطلب.';

  @override
  String get proofChoose => 'اختر صورة الإيصال';

  @override
  String get proofChange => 'تغيير';

  @override
  String get proofRemove => 'إزالة الصورة';

  @override
  String get proofAttached => 'تم إرفاق الإيصال';

  @override
  String get proofGallery => 'من المعرض';

  @override
  String get proofCamera => 'التقاط صورة';

  @override
  String get reviewAddress => 'عنوان التوصيل';

  @override
  String get reviewShipping => 'شركة الشحن';

  @override
  String get reviewPayment => 'طريقة الدفع';

  @override
  String get reviewNotes => 'ملاحظات للطلب';

  @override
  String get reviewNotesHint => 'اختياري';

  @override
  String get placeOrder => 'تأكيد الطلب';

  @override
  String get checkoutFixErrors => 'راجع البيانات المحددة ثم حاول مجددًا.';

  @override
  String get orderPlacedTitle => 'تم استلام طلبك';

  @override
  String orderPlacedBody(String number) {
    return 'شكرًا لك! رقم طلبك $number';
  }

  @override
  String get orderPlacedAwaiting => 'سنراجع طلبك ونؤكده قريبًا.';

  @override
  String get orderPlacedAt => 'تاريخ الطلب';

  @override
  String get orderViewOrder => 'عرض الطلب';

  @override
  String get ordersTitle => 'طلباتي';

  @override
  String get ordersEmptyTitle => 'لا توجد طلبات بعد';

  @override
  String get ordersEmptyBody => 'ستظهر طلباتك هنا بعد أول عملية شراء.';

  @override
  String orderItemsCount(int count) {
    return '$count منتج';
  }

  @override
  String get orderDetailTitle => 'تفاصيل الطلب';

  @override
  String get orderItems => 'المنتجات';

  @override
  String get orderTimeline => 'تتبع الطلب';

  @override
  String get orderReturns => 'المرتجعات';

  @override
  String get orderPayNow => 'ادفع الآن';

  @override
  String orderPayNowAmount(String amount) {
    return 'ادفع الآن ($amount)';
  }

  @override
  String get orderUploadProof => 'رفع إيصال التحويل';

  @override
  String get orderProofUploaded => 'تم رفع الإيصال، سنراجعه قريبًا.';

  @override
  String get orderInvoice => 'الفاتورة';

  @override
  String orderInvoiceSubject(String number) {
    return 'فاتورة الطلب $number';
  }

  @override
  String get orderCancel => 'إلغاء الطلب';

  @override
  String get orderCancelTitle => 'إلغاء الطلب';

  @override
  String get orderCancelReason => 'ما سبب الإلغاء؟';

  @override
  String get orderCancelled => 'تم إلغاء الطلب.';

  @override
  String get orderReturn => 'طلب إرجاع';

  @override
  String get orderReturnTitle => 'طلب إرجاع';

  @override
  String get orderReturnItems => 'اختر المنتجات والكميات المراد إرجاعها';

  @override
  String get orderReturnReason => 'سبب الإرجاع (اختياري)';

  @override
  String get orderReturnSent => 'تم إرسال طلب الإرجاع.';

  @override
  String get payUnpaid => 'غير مدفوع';

  @override
  String get payPaid => 'مدفوع';

  @override
  String get payPartial => 'مدفوع جزئيًا';

  @override
  String get payRefunded => 'تم الاسترداد';

  @override
  String get proofPending => 'الإيصال قيد المراجعة';

  @override
  String get proofApproved => 'تم قبول الإيصال';

  @override
  String get proofRejected => 'تم رفض الإيصال';

  @override
  String get paymentWebTitle => 'الدفع الإلكتروني';

  @override
  String get paymentConfirming => 'جارٍ تأكيد الدفع...';

  @override
  String get paymentSuccess => 'تم الدفع بنجاح';

  @override
  String get paymentPendingTitle => 'ننتظر تأكيد الدفع';

  @override
  String get paymentPendingBody =>
      'سيتم تحديث حالة الطلب فور تأكيد بوابة الدفع.';

  @override
  String get reasonColorDiffers => 'اللون مختلف عن الصورة';

  @override
  String get reasonSizeUnclear => 'المقاس غير واضح';

  @override
  String get reasonDamagedPackaging => 'عبوة تالفة';

  @override
  String get reasonDeliveryDelay => 'تأخر التوصيل';

  @override
  String get reasonDescriptionInaccurate => 'الوصف غير دقيق';

  @override
  String get reasonQuality => 'الجودة';

  @override
  String get reasonWrongItem => 'منتج خاطئ';

  @override
  String get reasonChangedMind => 'غيرت رأيي';

  @override
  String get reasonOther => 'سبب آخر';

  @override
  String get accountOrders => 'طلباتي';

  @override
  String get accountAddresses => 'عناويني';

  @override
  String get addressesTitle => 'عناويني';

  @override
  String get addressesEmpty => 'لا توجد عناوين';

  @override
  String get addressesEmptyBody => 'أضف عنوانًا لتسريع إتمام الطلبات.';

  @override
  String get addressAdd => 'إضافة عنوان';

  @override
  String get addressEdit => 'تعديل العنوان';

  @override
  String get addressLabel => 'اسم العنوان (مثال: المنزل)';

  @override
  String get addressCity => 'المدينة';

  @override
  String get addressCityPick => 'اختر المدينة';

  @override
  String get addressCitySearch => 'ابحث عن مدينة';

  @override
  String get addressLine => 'العنوان بالتفصيل';

  @override
  String get addressDefault => 'الافتراضي';

  @override
  String get addressSetDefault => 'تعيين كعنوان افتراضي';

  @override
  String get addressSaved => 'تم حفظ العنوان.';

  @override
  String get addressDeleted => 'تم حذف العنوان.';

  @override
  String get addressDeleteTitle => 'حذف العنوان';

  @override
  String get addressDeleteBody => 'هل تريد حذف هذا العنوان؟';

  @override
  String get profileTitle => 'تعديل الملف الشخصي';

  @override
  String get profileSaved => 'تم تحديث الملف الشخصي.';

  @override
  String get profileEmailChangeHint =>
      'لتغيير البريد الإلكتروني أكد كلمة المرور الحالية.';

  @override
  String get currentPassword => 'كلمة المرور الحالية';

  @override
  String get changePasswordTitle => 'تغيير كلمة المرور';

  @override
  String get passwordChanged =>
      'تم تغيير كلمة المرور. تم تسجيل الخروج من الأجهزة الأخرى.';

  @override
  String get deleteAccountTitle => 'حذف الحساب';

  @override
  String get deleteAccountWarning =>
      'حذف الحساب نهائي. سيتم مسح بياناتك الشخصية وعناوينك ومفضلتك وتسجيل خروجك من كل الأجهزة. تبقى الطلبات السابقة بدون بياناتك لأغراض المحاسبة.';

  @override
  String get deleteAccountNoPassword =>
      'حسابي يستخدم تسجيل الدخول بجوجل (بدون كلمة مرور)';

  @override
  String get deleteAccountConfirmTitle => 'حذف حسابك؟';

  @override
  String get deleteAccountConfirmBody => 'لا يمكن التراجع عن هذا الإجراء.';

  @override
  String get deleteAccountAction => 'احذف حسابي';

  @override
  String get deleteAccountDone => 'تم حذف حسابك.';

  @override
  String get themeTitle => 'المظهر';

  @override
  String get themeLight => 'فاتح';

  @override
  String get themeDark => 'داكن';

  @override
  String get themeSystem => 'حسب النظام';

  @override
  String get aboutTitle => 'حول التطبيق';

  @override
  String get rateApp => 'قيّم التطبيق';

  @override
  String get rateAppUnavailable => 'صفحة التطبيق في المتجر غير متاحة بعد.';

  @override
  String get contactTitle => 'تواصل معنا';

  @override
  String get contactPhone => 'الهاتف';

  @override
  String get contactEmpty => 'لا توجد بيانات تواصل متاحة.';

  @override
  String get blogTitle => 'المدونة';

  @override
  String get blogEmpty => 'لا توجد مقالات بعد.';

  @override
  String get drawerCollections => 'المجموعات والعروض';

  @override
  String get drawerInfoPages => 'معلومات';

  @override
  String get trackOrder => 'تتبع طلب';

  @override
  String get trackOrderHint => 'رابط الطلب أو رمزه';

  @override
  String get trackOrderInvalid => 'هذا ليس رابط أو رمز طلب صالح.';

  @override
  String get launchTitle => 'إطلاق محدود';

  @override
  String get launchContact => 'الهاتف أو البريد الإلكتروني';

  @override
  String get launchQuantity => 'الكمية';

  @override
  String get launchConsent => 'أوافق على التواصل معي بخصوص هذا الإطلاق.';

  @override
  String get launchConsentRequired => 'الموافقة مطلوبة.';

  @override
  String get launchAcceptTerms => 'أوافق على شروط الطلب المسبق.';

  @override
  String get launchTermsRequired => 'وافق على الشروط للمتابعة.';

  @override
  String get launchJoin => 'انضم';

  @override
  String get launchThanks => 'شكرا لك، تم تسجيل اهتمامك.';

  @override
  String get launchClosed => 'هذا الإطلاق مغلق.';

  @override
  String get linkCopied => 'تم نسخ الرابط.';

  @override
  String get copyLink => 'نسخ الرابط';

  @override
  String get affiliateTitle => 'برنامج التسويق بالعمولة';

  @override
  String get affiliateIntro => 'شارك المنتجات واربح عمولة على كل طلب.';

  @override
  String get affiliateApply => 'قدّم كمسوّق';

  @override
  String get affiliateApplied => 'تم إرسال الطلب';

  @override
  String get affiliateAppliedBody =>
      'حسابك بانتظار الموافقة. يمكنك تسجيل الدخول بعد الموافقة عليه.';

  @override
  String affiliateWelcome(String name) {
    return 'أهلا، $name';
  }

  @override
  String get affiliateClicks => 'النقرات';

  @override
  String get affiliateOrders => 'الطلبات';

  @override
  String get affiliateConversion => 'التحويل';

  @override
  String get affiliateSales => 'إجمالي المبيعات';

  @override
  String affiliateTier(String name, String percent) {
    return 'المستوى $name - عمولة $percent%';
  }

  @override
  String affiliateNextTier(String name, String amount) {
    return 'التالي: $name عند $amount';
  }

  @override
  String get affiliateCommissions => 'العمولات';

  @override
  String get affiliateRecentCommissions => 'آخر العمولات';

  @override
  String get commissionPending => 'قيد المراجعة';

  @override
  String get commissionApproved => 'معتمدة';

  @override
  String get commissionPaid => 'مدفوعة';

  @override
  String get commissionAvailable => 'متاحة للسحب';

  @override
  String get affiliateProducts => 'المنتجات والروابط';

  @override
  String get affiliateQr => 'رمز QR';

  @override
  String get affiliatePayouts => 'المدفوعات';

  @override
  String payoutAvailable(String amount) {
    return 'المتاح: $amount';
  }

  @override
  String get payoutRequest => 'اطلب سحب الرصيد';

  @override
  String get payoutRequested => 'تم طلب السحب.';

  @override
  String get payoutsEmpty => 'لا توجد مدفوعات بعد.';

  @override
  String get ambassadorTitle => 'برنامج السفراء';

  @override
  String get ambassadorUnavailable => 'برنامج السفراء غير متاح في هذا المتجر.';

  @override
  String get ambassadorIntro => 'أنشئ روابط متتبعة لكل قناة وتابع أداء محتواك.';

  @override
  String get ambassadorDisclosure => 'الإفصاح الذي يظهر مع منشوراتك';

  @override
  String get ambassadorTerms => 'أوافق على شروط برنامج السفراء.';

  @override
  String get ambassadorJoin => 'انضم للبرنامج';

  @override
  String ambassadorReport(int days) {
    return 'آخر $days يوم';
  }

  @override
  String get ambassadorValid => 'صالحة';

  @override
  String get ambassadorPending => 'معلقة';

  @override
  String get ambassadorExcluded => 'مستبعدة';

  @override
  String get ambassadorNewLink => 'رابط متتبع جديد';

  @override
  String get ambassadorProduct => 'المنتج';

  @override
  String get ambassadorCreateLink => 'أنشئ الرابط';

  @override
  String get ambassadorLinks => 'روابطي';

  @override
  String get ambassadorNoLinks => 'لا توجد روابط بعد.';

  @override
  String get ambassadorIdeas => 'أفكار للمحتوى';

  @override
  String get showPassword => 'إظهار كلمة المرور';

  @override
  String get hidePassword => 'إخفاء كلمة المرور';
}
