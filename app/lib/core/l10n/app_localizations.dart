import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:intl/intl.dart' as intl;

import 'app_localizations_ar.dart';
import 'app_localizations_en.dart';

// ignore_for_file: type=lint

/// Callers can lookup localized strings with an instance of AppLocalizations
/// returned by `AppLocalizations.of(context)`.
///
/// Applications need to include `AppLocalizations.delegate()` in their app's
/// `localizationDelegates` list, and the locales they support in the app's
/// `supportedLocales` list. For example:
///
/// ```dart
/// import 'l10n/app_localizations.dart';
///
/// return MaterialApp(
///   localizationsDelegates: AppLocalizations.localizationsDelegates,
///   supportedLocales: AppLocalizations.supportedLocales,
///   home: MyApplicationHome(),
/// );
/// ```
///
/// ## Update pubspec.yaml
///
/// Please make sure to update your pubspec.yaml to include the following
/// packages:
///
/// ```yaml
/// dependencies:
///   # Internationalization support.
///   flutter_localizations:
///     sdk: flutter
///   intl: any # Use the pinned version from flutter_localizations
///
///   # Rest of dependencies
/// ```
///
/// ## iOS Applications
///
/// iOS applications define key application metadata, including supported
/// locales, in an Info.plist file that is built into the application bundle.
/// To configure the locales supported by your app, you’ll need to edit this
/// file.
///
/// First, open your project’s ios/Runner.xcworkspace Xcode workspace file.
/// Then, in the Project Navigator, open the Info.plist file under the Runner
/// project’s Runner folder.
///
/// Next, select the Information Property List item, select Add Item from the
/// Editor menu, then select Localizations from the pop-up menu.
///
/// Select and expand the newly-created Localizations item then, for each
/// locale your application supports, add a new item and select the locale
/// you wish to add from the pop-up menu in the Value field. This list should
/// be consistent with the languages listed in the AppLocalizations.supportedLocales
/// property.
abstract class AppLocalizations {
  AppLocalizations(String locale)
    : localeName = intl.Intl.canonicalizedLocale(locale.toString());

  final String localeName;

  static AppLocalizations of(BuildContext context) {
    return Localizations.of<AppLocalizations>(context, AppLocalizations)!;
  }

  static const LocalizationsDelegate<AppLocalizations> delegate =
      _AppLocalizationsDelegate();

  /// A list of this localizations delegate along with the default localizations
  /// delegates.
  ///
  /// Returns a list of localizations delegates containing this delegate along with
  /// GlobalMaterialLocalizations.delegate, GlobalCupertinoLocalizations.delegate,
  /// and GlobalWidgetsLocalizations.delegate.
  ///
  /// Additional delegates can be added by appending to this list in
  /// MaterialApp. This list does not have to be used at all if a custom list
  /// of delegates is preferred or required.
  static const List<LocalizationsDelegate<dynamic>> localizationsDelegates =
      <LocalizationsDelegate<dynamic>>[
        delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
      ];

  /// A list of this localizations delegate's supported locales.
  static const List<Locale> supportedLocales = <Locale>[
    Locale('ar'),
    Locale('en'),
  ];

  /// Application display name
  ///
  /// In ar, this message translates to:
  /// **'عالرف'**
  String get appName;

  /// No description provided for @retry.
  ///
  /// In ar, this message translates to:
  /// **'إعادة المحاولة'**
  String get retry;

  /// No description provided for @loading.
  ///
  /// In ar, this message translates to:
  /// **'جارٍ التحميل…'**
  String get loading;

  /// No description provided for @errorGeneric.
  ///
  /// In ar, this message translates to:
  /// **'حدث خطأ ما. حاول مرة أخرى.'**
  String get errorGeneric;

  /// No description provided for @errorNetwork.
  ///
  /// In ar, this message translates to:
  /// **'تعذّر الاتصال بالإنترنت. تحقق من الشبكة وحاول مرة أخرى.'**
  String get errorNetwork;

  /// No description provided for @errorTimeout.
  ///
  /// In ar, this message translates to:
  /// **'انتهت مهلة الاتصال. حاول مرة أخرى.'**
  String get errorTimeout;

  /// No description provided for @errorServer.
  ///
  /// In ar, this message translates to:
  /// **'حدث خطأ في الخادم. حاول لاحقًا.'**
  String get errorServer;

  /// No description provided for @errorRateLimited.
  ///
  /// In ar, this message translates to:
  /// **'محاولات كثيرة. انتظر قليلًا ثم أعد المحاولة.'**
  String get errorRateLimited;

  /// No description provided for @errorNotFound.
  ///
  /// In ar, this message translates to:
  /// **'غير موجود.'**
  String get errorNotFound;

  /// No description provided for @errorUnauthorized.
  ///
  /// In ar, this message translates to:
  /// **'انتهت الجلسة. سجّل الدخول مرة أخرى.'**
  String get errorUnauthorized;

  /// No description provided for @emptyTitle.
  ///
  /// In ar, this message translates to:
  /// **'لا يوجد شيء هنا بعد'**
  String get emptyTitle;

  /// No description provided for @navHome.
  ///
  /// In ar, this message translates to:
  /// **'الرئيسية'**
  String get navHome;

  /// No description provided for @navCategories.
  ///
  /// In ar, this message translates to:
  /// **'الأقسام'**
  String get navCategories;

  /// No description provided for @navCart.
  ///
  /// In ar, this message translates to:
  /// **'السلة'**
  String get navCart;

  /// No description provided for @navWishlist.
  ///
  /// In ar, this message translates to:
  /// **'المفضلة'**
  String get navWishlist;

  /// No description provided for @navAccount.
  ///
  /// In ar, this message translates to:
  /// **'حسابي'**
  String get navAccount;

  /// No description provided for @comingSoon.
  ///
  /// In ar, this message translates to:
  /// **'قريبًا'**
  String get comingSoon;

  /// No description provided for @addToCart.
  ///
  /// In ar, this message translates to:
  /// **'أضف إلى السلة'**
  String get addToCart;

  /// No description provided for @outOfStock.
  ///
  /// In ar, this message translates to:
  /// **'غير متوفر'**
  String get outOfStock;

  /// No description provided for @currencyAmount.
  ///
  /// In ar, this message translates to:
  /// **'{amount} {currency}'**
  String currencyAmount(String amount, String currency);

  /// No description provided for @discountBadge.
  ///
  /// In ar, this message translates to:
  /// **'خصم {percent}٪'**
  String discountBadge(String percent);

  /// No description provided for @ratingSemantics.
  ///
  /// In ar, this message translates to:
  /// **'التقييم {rating} من 5'**
  String ratingSemantics(String rating);

  /// No description provided for @quantityIncrease.
  ///
  /// In ar, this message translates to:
  /// **'زيادة الكمية'**
  String get quantityIncrease;

  /// No description provided for @quantityDecrease.
  ///
  /// In ar, this message translates to:
  /// **'تقليل الكمية'**
  String get quantityDecrease;

  /// No description provided for @quantityLabel.
  ///
  /// In ar, this message translates to:
  /// **'الكمية {count}'**
  String quantityLabel(int count);

  /// No description provided for @viewAll.
  ///
  /// In ar, this message translates to:
  /// **'عرض الكل'**
  String get viewAll;

  /// No description provided for @cancel.
  ///
  /// In ar, this message translates to:
  /// **'إلغاء'**
  String get cancel;

  /// No description provided for @confirm.
  ///
  /// In ar, this message translates to:
  /// **'تأكيد'**
  String get confirm;

  /// No description provided for @close.
  ///
  /// In ar, this message translates to:
  /// **'إغلاق'**
  String get close;

  /// No description provided for @menu.
  ///
  /// In ar, this message translates to:
  /// **'القائمة'**
  String get menu;

  /// No description provided for @startupErrorTitle.
  ///
  /// In ar, this message translates to:
  /// **'تعذّر تشغيل التطبيق'**
  String get startupErrorTitle;

  /// No description provided for @selectCountryTitle.
  ///
  /// In ar, this message translates to:
  /// **'اختر بلدك'**
  String get selectCountryTitle;

  /// No description provided for @selectCountrySubtitle.
  ///
  /// In ar, this message translates to:
  /// **'اختر المتجر الذي تريد التسوّق منه.'**
  String get selectCountrySubtitle;

  /// No description provided for @searchCountry.
  ///
  /// In ar, this message translates to:
  /// **'ابحث عن بلد أو متجر'**
  String get searchCountry;

  /// No description provided for @noStoresFound.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد متاجر مطابقة'**
  String get noStoresFound;

  /// No description provided for @noStoresAvailable.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد متاجر متاحة حاليًا.'**
  String get noStoresAvailable;

  /// No description provided for @continueAction.
  ///
  /// In ar, this message translates to:
  /// **'متابعة'**
  String get continueAction;

  /// No description provided for @confirmCountryTitle.
  ///
  /// In ar, this message translates to:
  /// **'التسوّق من {store}؟'**
  String confirmCountryTitle(String store);

  /// No description provided for @confirmCountryMessage.
  ///
  /// In ar, this message translates to:
  /// **'يمكنك تغيير البلد لاحقًا من القائمة الجانبية.'**
  String get confirmCountryMessage;

  /// No description provided for @countryInactiveNotice.
  ///
  /// In ar, this message translates to:
  /// **'متجرك السابق لم يعد متاحًا. اختر بلدًا آخر للمتابعة.'**
  String get countryInactiveNotice;

  /// No description provided for @currentStore.
  ///
  /// In ar, this message translates to:
  /// **'الحالي'**
  String get currentStore;

  /// No description provided for @storeCurrency.
  ///
  /// In ar, this message translates to:
  /// **'العملة: {currency}'**
  String storeCurrency(String currency);

  /// No description provided for @storeLanguages.
  ///
  /// In ar, this message translates to:
  /// **'اللغات: {languages}'**
  String storeLanguages(String languages);

  /// No description provided for @switchCountry.
  ///
  /// In ar, this message translates to:
  /// **'تغيير البلد'**
  String get switchCountry;

  /// No description provided for @switchCountryWarnTitle.
  ///
  /// In ar, this message translates to:
  /// **'تغيير المتجر؟'**
  String get switchCountryWarnTitle;

  /// No description provided for @switchCountryWarnBody.
  ///
  /// In ar, this message translates to:
  /// **'السلة والمفضلة تخصّ المتجر الحالي. سيتم تسجيل خروجك ومسح بيانات السلة على هذا الجهاز عند التغيير إلى {store}.'**
  String switchCountryWarnBody(String store);

  /// No description provided for @switchConfirm.
  ///
  /// In ar, this message translates to:
  /// **'تغيير'**
  String get switchConfirm;

  /// No description provided for @switchedTo.
  ///
  /// In ar, this message translates to:
  /// **'تتسوّق الآن من {store}'**
  String switchedTo(String store);

  /// No description provided for @offlineBanner.
  ///
  /// In ar, this message translates to:
  /// **'أنت غير متصل. نعرض بيانات محفوظة.'**
  String get offlineBanner;

  /// No description provided for @updateRequiredTitle.
  ///
  /// In ar, this message translates to:
  /// **'يلزم تحديث التطبيق'**
  String get updateRequiredTitle;

  /// No description provided for @updateRequiredBody.
  ///
  /// In ar, this message translates to:
  /// **'يرجى تحديث التطبيق إلى أحدث إصدار للمتابعة.'**
  String get updateRequiredBody;

  /// No description provided for @updateNow.
  ///
  /// In ar, this message translates to:
  /// **'تحديث الآن'**
  String get updateNow;

  /// No description provided for @maintenanceTitle.
  ///
  /// In ar, this message translates to:
  /// **'المتجر تحت الصيانة'**
  String get maintenanceTitle;

  /// No description provided for @maintenanceDefault.
  ///
  /// In ar, this message translates to:
  /// **'نعمل على تحسين الخدمة وسنعود قريبًا.'**
  String get maintenanceDefault;

  /// No description provided for @onboarding1Title.
  ///
  /// In ar, this message translates to:
  /// **'تسوّق مستحضرات التجميل بسهولة'**
  String get onboarding1Title;

  /// No description provided for @onboarding1Body.
  ///
  /// In ar, this message translates to:
  /// **'آلاف المنتجات الأصلية من أفضل العلامات في مكان واحد.'**
  String get onboarding1Body;

  /// No description provided for @onboarding2Title.
  ///
  /// In ar, this message translates to:
  /// **'عروض وهدايا مميزة'**
  String get onboarding2Title;

  /// No description provided for @onboarding2Body.
  ///
  /// In ar, this message translates to:
  /// **'تابع العروض اليومية واعثر على الهدية المناسبة.'**
  String get onboarding2Body;

  /// No description provided for @onboarding3Title.
  ///
  /// In ar, this message translates to:
  /// **'توصيل سريع ودفع مرن'**
  String get onboarding3Title;

  /// No description provided for @onboarding3Body.
  ///
  /// In ar, this message translates to:
  /// **'اختر طريقة الشحن والدفع التي تناسبك.'**
  String get onboarding3Body;

  /// No description provided for @skip.
  ///
  /// In ar, this message translates to:
  /// **'تخطي'**
  String get skip;

  /// No description provided for @next.
  ///
  /// In ar, this message translates to:
  /// **'التالي'**
  String get next;

  /// No description provided for @getStarted.
  ///
  /// In ar, this message translates to:
  /// **'ابدأ الآن'**
  String get getStarted;

  /// No description provided for @language.
  ///
  /// In ar, this message translates to:
  /// **'اللغة'**
  String get language;

  /// No description provided for @contactWhatsapp.
  ///
  /// In ar, this message translates to:
  /// **'تواصل عبر واتساب'**
  String get contactWhatsapp;

  /// No description provided for @appVersion.
  ///
  /// In ar, this message translates to:
  /// **'الإصدار {version}'**
  String appVersion(String version);

  /// No description provided for @currentStoreLabel.
  ///
  /// In ar, this message translates to:
  /// **'المتجر الحالي: {store}'**
  String currentStoreLabel(String store);

  /// No description provided for @login.
  ///
  /// In ar, this message translates to:
  /// **'تسجيل الدخول'**
  String get login;

  /// No description provided for @register.
  ///
  /// In ar, this message translates to:
  /// **'إنشاء حساب'**
  String get register;

  /// No description provided for @logout.
  ///
  /// In ar, this message translates to:
  /// **'تسجيل الخروج'**
  String get logout;

  /// No description provided for @loginSubtitle.
  ///
  /// In ar, this message translates to:
  /// **'أهلًا بعودتك! سجّل الدخول لمتابعة التسوّق.'**
  String get loginSubtitle;

  /// No description provided for @registerSubtitle.
  ///
  /// In ar, this message translates to:
  /// **'أنشئ حسابك للاحتفاظ بطلباتك وعناوينك.'**
  String get registerSubtitle;

  /// No description provided for @sessionExpiredNotice.
  ///
  /// In ar, this message translates to:
  /// **'انتهت جلستك. سجّل الدخول مرة أخرى.'**
  String get sessionExpiredNotice;

  /// No description provided for @email.
  ///
  /// In ar, this message translates to:
  /// **'البريد الإلكتروني'**
  String get email;

  /// No description provided for @password.
  ///
  /// In ar, this message translates to:
  /// **'كلمة المرور'**
  String get password;

  /// No description provided for @confirmPassword.
  ///
  /// In ar, this message translates to:
  /// **'تأكيد كلمة المرور'**
  String get confirmPassword;

  /// No description provided for @fullName.
  ///
  /// In ar, this message translates to:
  /// **'الاسم الكامل'**
  String get fullName;

  /// No description provided for @phoneOptional.
  ///
  /// In ar, this message translates to:
  /// **'رقم الهاتف (اختياري)'**
  String get phoneOptional;

  /// No description provided for @forgotPasswordLink.
  ///
  /// In ar, this message translates to:
  /// **'نسيت كلمة المرور؟'**
  String get forgotPasswordLink;

  /// No description provided for @signInWithGoogle.
  ///
  /// In ar, this message translates to:
  /// **'المتابعة باستخدام Google'**
  String get signInWithGoogle;

  /// No description provided for @orDivider.
  ///
  /// In ar, this message translates to:
  /// **'أو'**
  String get orDivider;

  /// No description provided for @noAccountPrompt.
  ///
  /// In ar, this message translates to:
  /// **'ليس لديك حساب؟'**
  String get noAccountPrompt;

  /// No description provided for @haveAccountPrompt.
  ///
  /// In ar, this message translates to:
  /// **'لديك حساب بالفعل؟'**
  String get haveAccountPrompt;

  /// No description provided for @continueAsGuest.
  ///
  /// In ar, this message translates to:
  /// **'المتابعة كزائر'**
  String get continueAsGuest;

  /// No description provided for @forgotTitle.
  ///
  /// In ar, this message translates to:
  /// **'استعادة كلمة المرور'**
  String get forgotTitle;

  /// No description provided for @forgotBody.
  ///
  /// In ar, this message translates to:
  /// **'أدخل بريدك الإلكتروني وسنرسل لك رمزًا من 6 أرقام.'**
  String get forgotBody;

  /// No description provided for @sendCode.
  ///
  /// In ar, this message translates to:
  /// **'إرسال الرمز'**
  String get sendCode;

  /// No description provided for @resetTitle.
  ///
  /// In ar, this message translates to:
  /// **'كلمة مرور جديدة'**
  String get resetTitle;

  /// No description provided for @resetBody.
  ///
  /// In ar, this message translates to:
  /// **'أدخل الرمز الذي وصلك وكلمة المرور الجديدة.'**
  String get resetBody;

  /// No description provided for @codeLabel.
  ///
  /// In ar, this message translates to:
  /// **'الرمز (6 أرقام)'**
  String get codeLabel;

  /// No description provided for @newPassword.
  ///
  /// In ar, this message translates to:
  /// **'كلمة المرور الجديدة'**
  String get newPassword;

  /// No description provided for @resetAction.
  ///
  /// In ar, this message translates to:
  /// **'تغيير كلمة المرور'**
  String get resetAction;

  /// No description provided for @resetDone.
  ///
  /// In ar, this message translates to:
  /// **'تم تغيير كلمة المرور. سجّل الدخول الآن.'**
  String get resetDone;

  /// No description provided for @verifyTitle.
  ///
  /// In ar, this message translates to:
  /// **'تأكيد البريد الإلكتروني'**
  String get verifyTitle;

  /// No description provided for @verifyBody.
  ///
  /// In ar, this message translates to:
  /// **'أرسلنا رمزًا إلى بريدك. أدخله لتأكيد الحساب.'**
  String get verifyBody;

  /// No description provided for @verifyAction.
  ///
  /// In ar, this message translates to:
  /// **'تأكيد'**
  String get verifyAction;

  /// No description provided for @resendCode.
  ///
  /// In ar, this message translates to:
  /// **'إعادة إرسال الرمز'**
  String get resendCode;

  /// No description provided for @codeSent.
  ///
  /// In ar, this message translates to:
  /// **'تم إرسال الرمز إلى بريدك.'**
  String get codeSent;

  /// No description provided for @emailVerified.
  ///
  /// In ar, this message translates to:
  /// **'تم تأكيد بريدك الإلكتروني.'**
  String get emailVerified;

  /// No description provided for @skipForNow.
  ///
  /// In ar, this message translates to:
  /// **'لاحقًا'**
  String get skipForNow;

  /// No description provided for @fieldRequired.
  ///
  /// In ar, this message translates to:
  /// **'هذا الحقل مطلوب'**
  String get fieldRequired;

  /// No description provided for @invalidEmail.
  ///
  /// In ar, this message translates to:
  /// **'بريد إلكتروني غير صالح'**
  String get invalidEmail;

  /// No description provided for @passwordTooShort.
  ///
  /// In ar, this message translates to:
  /// **'8 أحرف على الأقل'**
  String get passwordTooShort;

  /// No description provided for @passwordMismatch.
  ///
  /// In ar, this message translates to:
  /// **'كلمتا المرور غير متطابقتين'**
  String get passwordMismatch;

  /// No description provided for @invalidCode.
  ///
  /// In ar, this message translates to:
  /// **'أدخل 6 أرقام'**
  String get invalidCode;

  /// No description provided for @accountGuestTitle.
  ///
  /// In ar, this message translates to:
  /// **'أهلًا بك في عالرف'**
  String get accountGuestTitle;

  /// No description provided for @accountGuestBody.
  ///
  /// In ar, this message translates to:
  /// **'سجّل الدخول لمتابعة طلباتك وحفظ مفضلتك.'**
  String get accountGuestBody;

  /// No description provided for @signedInAs.
  ///
  /// In ar, this message translates to:
  /// **'مسجّل باسم {name}'**
  String signedInAs(String name);

  /// No description provided for @wishlistSignInBody.
  ///
  /// In ar, this message translates to:
  /// **'سجّل الدخول لعرض مفضلتك.'**
  String get wishlistSignInBody;

  /// No description provided for @googleFailed.
  ///
  /// In ar, this message translates to:
  /// **'تعذّر تسجيل الدخول عبر Google.'**
  String get googleFailed;

  /// No description provided for @emailNotVerified.
  ///
  /// In ar, this message translates to:
  /// **'البريد غير مؤكد'**
  String get emailNotVerified;

  /// No description provided for @searchHint.
  ///
  /// In ar, this message translates to:
  /// **'ابحث عن المنتجات'**
  String get searchHint;

  /// No description provided for @homeEmptyTitle.
  ///
  /// In ar, this message translates to:
  /// **'لا يوجد محتوى بعد'**
  String get homeEmptyTitle;

  /// No description provided for @homeEmptyBody.
  ///
  /// In ar, this message translates to:
  /// **'المتجر قيد التجهيز. اسحب للأسفل للتحديث.'**
  String get homeEmptyBody;

  /// No description provided for @flashSale.
  ///
  /// In ar, this message translates to:
  /// **'عروض خاطفة'**
  String get flashSale;

  /// No description provided for @flashEndsIn.
  ///
  /// In ar, this message translates to:
  /// **'ينتهي خلال'**
  String get flashEndsIn;

  /// No description provided for @railRecommended.
  ///
  /// In ar, this message translates to:
  /// **'مقترح لك'**
  String get railRecommended;

  /// No description provided for @railMostSold.
  ///
  /// In ar, this message translates to:
  /// **'الأكثر مبيعاً'**
  String get railMostSold;

  /// No description provided for @railMostReviewed.
  ///
  /// In ar, this message translates to:
  /// **'الأكثر تقييماً'**
  String get railMostReviewed;

  /// No description provided for @brands.
  ///
  /// In ar, this message translates to:
  /// **'الماركات'**
  String get brands;

  /// No description provided for @brandsEmpty.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد ماركات بعد'**
  String get brandsEmpty;

  /// No description provided for @bundles.
  ///
  /// In ar, this message translates to:
  /// **'العروض المجمّعة'**
  String get bundles;

  /// No description provided for @bundle.
  ///
  /// In ar, this message translates to:
  /// **'عرض مجمّع'**
  String get bundle;

  /// No description provided for @bundlesEmpty.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد عروض مجمّعة حالياً.'**
  String get bundlesEmpty;

  /// No description provided for @bundleContains.
  ///
  /// In ar, this message translates to:
  /// **'يتضمن هذا العرض'**
  String get bundleContains;

  /// No description provided for @bundleUnavailable.
  ///
  /// In ar, this message translates to:
  /// **'غير متاح حالياً'**
  String get bundleUnavailable;

  /// No description provided for @bundleSave.
  ///
  /// In ar, this message translates to:
  /// **'وفّر {amount}'**
  String bundleSave(String amount);

  /// No description provided for @giftCtaTitle.
  ///
  /// In ar, this message translates to:
  /// **'اعثر على الهدية المثالية'**
  String get giftCtaTitle;

  /// No description provided for @giftCtaBody.
  ///
  /// In ar, this message translates to:
  /// **'أخبرنا بميزانيتك والمناسبة'**
  String get giftCtaBody;

  /// No description provided for @categoriesEmpty.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد أقسام بعد'**
  String get categoriesEmpty;

  /// No description provided for @allInCategory.
  ///
  /// In ar, this message translates to:
  /// **'كل {name}'**
  String allInCategory(String name);

  /// No description provided for @products.
  ///
  /// In ar, this message translates to:
  /// **'المنتجات'**
  String get products;

  /// No description provided for @productsCount.
  ///
  /// In ar, this message translates to:
  /// **'{count} منتج'**
  String productsCount(int count);

  /// No description provided for @searchResultsFor.
  ///
  /// In ar, this message translates to:
  /// **'نتائج \"{q}\"'**
  String searchResultsFor(String q);

  /// No description provided for @noProducts.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد منتجات'**
  String get noProducts;

  /// No description provided for @noProductsFiltered.
  ///
  /// In ar, this message translates to:
  /// **'جرّب إزالة بعض عوامل التصفية.'**
  String get noProductsFiltered;

  /// No description provided for @resetFilters.
  ///
  /// In ar, this message translates to:
  /// **'إعادة ضبط التصفية'**
  String get resetFilters;

  /// No description provided for @reset.
  ///
  /// In ar, this message translates to:
  /// **'إعادة ضبط'**
  String get reset;

  /// No description provided for @filters.
  ///
  /// In ar, this message translates to:
  /// **'تصفية'**
  String get filters;

  /// No description provided for @applyFilters.
  ///
  /// In ar, this message translates to:
  /// **'عرض النتائج'**
  String get applyFilters;

  /// No description provided for @sortBy.
  ///
  /// In ar, this message translates to:
  /// **'ترتيب حسب'**
  String get sortBy;

  /// No description provided for @sortNewest.
  ///
  /// In ar, this message translates to:
  /// **'الأحدث'**
  String get sortNewest;

  /// No description provided for @sortPriceAsc.
  ///
  /// In ar, this message translates to:
  /// **'السعر: من الأقل للأعلى'**
  String get sortPriceAsc;

  /// No description provided for @sortPriceDesc.
  ///
  /// In ar, this message translates to:
  /// **'السعر: من الأعلى للأقل'**
  String get sortPriceDesc;

  /// No description provided for @sortBestSelling.
  ///
  /// In ar, this message translates to:
  /// **'الأكثر مبيعاً'**
  String get sortBestSelling;

  /// No description provided for @sortTopRated.
  ///
  /// In ar, this message translates to:
  /// **'الأعلى تقييماً'**
  String get sortTopRated;

  /// No description provided for @price.
  ///
  /// In ar, this message translates to:
  /// **'السعر'**
  String get price;

  /// No description provided for @priceFrom.
  ///
  /// In ar, this message translates to:
  /// **'من'**
  String get priceFrom;

  /// No description provided for @priceTo.
  ///
  /// In ar, this message translates to:
  /// **'إلى'**
  String get priceTo;

  /// No description provided for @clear.
  ///
  /// In ar, this message translates to:
  /// **'مسح'**
  String get clear;

  /// No description provided for @recentSearches.
  ///
  /// In ar, this message translates to:
  /// **'عمليات البحث الأخيرة'**
  String get recentSearches;

  /// No description provided for @searchEmptyTitle.
  ///
  /// In ar, this message translates to:
  /// **'ابحث في المتجر'**
  String get searchEmptyTitle;

  /// No description provided for @searchEmptyBody.
  ///
  /// In ar, this message translates to:
  /// **'اكتب اسم منتج أو ماركة أو قسم.'**
  String get searchEmptyBody;

  /// No description provided for @searchFor.
  ///
  /// In ar, this message translates to:
  /// **'بحث عن \"{term}\"'**
  String searchFor(String term);

  /// No description provided for @noSuggestions.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد اقتراحات'**
  String get noSuggestions;

  /// No description provided for @addedToCart.
  ///
  /// In ar, this message translates to:
  /// **'تمت الإضافة إلى السلة.'**
  String get addedToCart;

  /// No description provided for @viewCart.
  ///
  /// In ar, this message translates to:
  /// **'عرض السلة'**
  String get viewCart;

  /// No description provided for @chooseOptions.
  ///
  /// In ar, this message translates to:
  /// **'اختر الخيارات'**
  String get chooseOptions;

  /// No description provided for @unavailableCombo.
  ///
  /// In ar, this message translates to:
  /// **'هذه التشكيلة غير متاحة.'**
  String get unavailableCombo;

  /// No description provided for @quantity.
  ///
  /// In ar, this message translates to:
  /// **'الكمية'**
  String get quantity;

  /// No description provided for @description.
  ///
  /// In ar, this message translates to:
  /// **'الوصف'**
  String get description;

  /// No description provided for @sku.
  ///
  /// In ar, this message translates to:
  /// **'رمز المنتج'**
  String get sku;

  /// No description provided for @inStock.
  ///
  /// In ar, this message translates to:
  /// **'متوفر'**
  String get inStock;

  /// No description provided for @lowStock.
  ///
  /// In ar, this message translates to:
  /// **'متبقي {count} فقط'**
  String lowStock(int count);

  /// No description provided for @relatedProducts.
  ///
  /// In ar, this message translates to:
  /// **'قد يعجبك أيضاً'**
  String get relatedProducts;

  /// No description provided for @share.
  ///
  /// In ar, this message translates to:
  /// **'مشاركة'**
  String get share;

  /// No description provided for @wishlistAdd.
  ///
  /// In ar, this message translates to:
  /// **'أضف إلى المفضلة'**
  String get wishlistAdd;

  /// No description provided for @wishlistRemove.
  ///
  /// In ar, this message translates to:
  /// **'إزالة من المفضلة'**
  String get wishlistRemove;

  /// No description provided for @wishlistAdded.
  ///
  /// In ar, this message translates to:
  /// **'تمت الإضافة إلى المفضلة.'**
  String get wishlistAdded;

  /// No description provided for @wishlistRemoved.
  ///
  /// In ar, this message translates to:
  /// **'تمت الإزالة من المفضلة.'**
  String get wishlistRemoved;

  /// No description provided for @wishlistEmptyTitle.
  ///
  /// In ar, this message translates to:
  /// **'قائمة المفضلة فارغة'**
  String get wishlistEmptyTitle;

  /// No description provided for @wishlistEmptyBody.
  ///
  /// In ar, this message translates to:
  /// **'اضغط على القلب في أي منتج لحفظه هنا.'**
  String get wishlistEmptyBody;

  /// No description provided for @startShopping.
  ///
  /// In ar, this message translates to:
  /// **'ابدأ التسوق'**
  String get startShopping;

  /// No description provided for @reviewsTitle.
  ///
  /// In ar, this message translates to:
  /// **'التقييمات'**
  String get reviewsTitle;

  /// No description provided for @noReviews.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد تقييمات بعد.'**
  String get noReviews;

  /// No description provided for @writeReview.
  ///
  /// In ar, this message translates to:
  /// **'اكتب تقييماً'**
  String get writeReview;

  /// No description provided for @reviewBody.
  ///
  /// In ar, this message translates to:
  /// **'تقييمك (اختياري)'**
  String get reviewBody;

  /// No description provided for @reviewPickRating.
  ///
  /// In ar, this message translates to:
  /// **'يرجى اختيار عدد النجوم.'**
  String get reviewPickRating;

  /// No description provided for @submitReview.
  ///
  /// In ar, this message translates to:
  /// **'إرسال التقييم'**
  String get submitReview;

  /// No description provided for @reviewThanks.
  ///
  /// In ar, this message translates to:
  /// **'شكراً! سيظهر تقييمك بعد المراجعة.'**
  String get reviewThanks;

  /// No description provided for @notifyMe.
  ///
  /// In ar, this message translates to:
  /// **'أعلمني عند التوفر'**
  String get notifyMe;

  /// No description provided for @notifyMeBody.
  ///
  /// In ar, this message translates to:
  /// **'هذا المنتج غير متوفر. اترك بياناتك وسنتواصل معك عند توفره.'**
  String get notifyMeBody;

  /// No description provided for @notifyMeSend.
  ///
  /// In ar, this message translates to:
  /// **'إرسال الطلب'**
  String get notifyMeSend;

  /// No description provided for @notifyMeSent.
  ///
  /// In ar, this message translates to:
  /// **'تم إرسال طلبك وسنتواصل معك قريباً.'**
  String get notifyMeSent;

  /// No description provided for @phone.
  ///
  /// In ar, this message translates to:
  /// **'رقم الهاتف'**
  String get phone;

  /// No description provided for @noteOptional.
  ///
  /// In ar, this message translates to:
  /// **'ملاحظة (اختياري)'**
  String get noteOptional;

  /// No description provided for @back.
  ///
  /// In ar, this message translates to:
  /// **'رجوع'**
  String get back;

  /// No description provided for @featureUnavailable.
  ///
  /// In ar, this message translates to:
  /// **'هذه الخدمة غير متاحة في متجرك.'**
  String get featureUnavailable;

  /// No description provided for @giftFinder.
  ///
  /// In ar, this message translates to:
  /// **'مرشد الهدايا'**
  String get giftFinder;

  /// No description provided for @giftStartOver.
  ///
  /// In ar, this message translates to:
  /// **'ابدأ من جديد'**
  String get giftStartOver;

  /// No description provided for @giftStepBudget.
  ///
  /// In ar, this message translates to:
  /// **'ما هي ميزانيتك؟'**
  String get giftStepBudget;

  /// No description provided for @giftBudget.
  ///
  /// In ar, this message translates to:
  /// **'الميزانية'**
  String get giftBudget;

  /// No description provided for @giftBudgetRequired.
  ///
  /// In ar, this message translates to:
  /// **'أدخل ميزانية أكبر من الصفر.'**
  String get giftBudgetRequired;

  /// No description provided for @giftStepOccasion.
  ///
  /// In ar, this message translates to:
  /// **'ما هي المناسبة؟'**
  String get giftStepOccasion;

  /// No description provided for @giftStepRecipient.
  ///
  /// In ar, this message translates to:
  /// **'لمن الهدية؟'**
  String get giftStepRecipient;

  /// No description provided for @giftStepExtras.
  ///
  /// In ar, this message translates to:
  /// **'أي تفاصيل أخرى؟'**
  String get giftStepExtras;

  /// No description provided for @giftRestrictions.
  ///
  /// In ar, this message translates to:
  /// **'أشياء تتجنبها (اختياري)'**
  String get giftRestrictions;

  /// No description provided for @giftRestrictionsHint.
  ///
  /// In ar, this message translates to:
  /// **'مثال: عطور'**
  String get giftRestrictionsHint;

  /// No description provided for @giftFind.
  ///
  /// In ar, this message translates to:
  /// **'ابحث عن هدايا'**
  String get giftFind;

  /// No description provided for @giftNoResults.
  ///
  /// In ar, this message translates to:
  /// **'لم نجد هدايا'**
  String get giftNoResults;

  /// No description provided for @giftNoResultsBody.
  ///
  /// In ar, this message translates to:
  /// **'جرّب ميزانية أعلى أو قيوداً أقل.'**
  String get giftNoResultsBody;

  /// No description provided for @giftSuggestions.
  ///
  /// In ar, this message translates to:
  /// **'اقتراحات لك'**
  String get giftSuggestions;

  /// No description provided for @giftNearBudget.
  ///
  /// In ar, this message translates to:
  /// **'أعلى قليلاً من ميزانيتك'**
  String get giftNearBudget;

  /// No description provided for @giftChooseVariant.
  ///
  /// In ar, this message translates to:
  /// **'اختر خياراً'**
  String get giftChooseVariant;

  /// No description provided for @giftAddSelected.
  ///
  /// In ar, this message translates to:
  /// **'أضف المحدد ({count})'**
  String giftAddSelected(int count);

  /// No description provided for @giftAdded.
  ///
  /// In ar, this message translates to:
  /// **'تمت إضافة {count} هدايا إلى السلة.'**
  String giftAdded(int count);

  /// No description provided for @occBirthday.
  ///
  /// In ar, this message translates to:
  /// **'عيد ميلاد'**
  String get occBirthday;

  /// No description provided for @occMothersDay.
  ///
  /// In ar, this message translates to:
  /// **'عيد الأم'**
  String get occMothersDay;

  /// No description provided for @occValentine.
  ///
  /// In ar, this message translates to:
  /// **'عيد الحب'**
  String get occValentine;

  /// No description provided for @occWedding.
  ///
  /// In ar, this message translates to:
  /// **'زفاف'**
  String get occWedding;

  /// No description provided for @occEidFitr.
  ///
  /// In ar, this message translates to:
  /// **'عيد الفطر'**
  String get occEidFitr;

  /// No description provided for @occEidAdha.
  ///
  /// In ar, this message translates to:
  /// **'عيد الأضحى'**
  String get occEidAdha;

  /// No description provided for @occRamadan.
  ///
  /// In ar, this message translates to:
  /// **'رمضان'**
  String get occRamadan;

  /// No description provided for @occGraduation.
  ///
  /// In ar, this message translates to:
  /// **'تخرّج'**
  String get occGraduation;

  /// No description provided for @occThanks.
  ///
  /// In ar, this message translates to:
  /// **'شكر'**
  String get occThanks;

  /// No description provided for @occSelf.
  ///
  /// In ar, this message translates to:
  /// **'مكافأة لنفسي'**
  String get occSelf;

  /// No description provided for @recMother.
  ///
  /// In ar, this message translates to:
  /// **'الأم'**
  String get recMother;

  /// No description provided for @recPartner.
  ///
  /// In ar, this message translates to:
  /// **'الشريك/ة'**
  String get recPartner;

  /// No description provided for @recFriend.
  ///
  /// In ar, this message translates to:
  /// **'صديق/ة'**
  String get recFriend;

  /// No description provided for @recSister.
  ///
  /// In ar, this message translates to:
  /// **'الأخت'**
  String get recSister;

  /// No description provided for @recColleague.
  ///
  /// In ar, this message translates to:
  /// **'زميل/ة'**
  String get recColleague;

  /// No description provided for @recChild.
  ///
  /// In ar, this message translates to:
  /// **'طفل'**
  String get recChild;

  /// No description provided for @recSelf.
  ///
  /// In ar, this message translates to:
  /// **'نفسي'**
  String get recSelf;

  /// No description provided for @recOther.
  ///
  /// In ar, this message translates to:
  /// **'شخص آخر'**
  String get recOther;

  /// No description provided for @cartTitle.
  ///
  /// In ar, this message translates to:
  /// **'السلة'**
  String get cartTitle;

  /// No description provided for @cartEmptyTitle.
  ///
  /// In ar, this message translates to:
  /// **'سلتك فارغة'**
  String get cartEmptyTitle;

  /// No description provided for @cartEmptyBody.
  ///
  /// In ar, this message translates to:
  /// **'أضف منتجات لتبدأ التسوّق.'**
  String get cartEmptyBody;

  /// No description provided for @cartSubtotal.
  ///
  /// In ar, this message translates to:
  /// **'المجموع الفرعي'**
  String get cartSubtotal;

  /// No description provided for @cartDiscount.
  ///
  /// In ar, this message translates to:
  /// **'الخصم'**
  String get cartDiscount;

  /// No description provided for @cartShipping.
  ///
  /// In ar, this message translates to:
  /// **'الشحن'**
  String get cartShipping;

  /// No description provided for @cartShippingAtCheckout.
  ///
  /// In ar, this message translates to:
  /// **'يُحسب عند إتمام الطلب'**
  String get cartShippingAtCheckout;

  /// No description provided for @cartTax.
  ///
  /// In ar, this message translates to:
  /// **'الضريبة'**
  String get cartTax;

  /// No description provided for @cartPaymentFee.
  ///
  /// In ar, this message translates to:
  /// **'رسوم الدفع'**
  String get cartPaymentFee;

  /// No description provided for @cartPackagingFee.
  ///
  /// In ar, this message translates to:
  /// **'رسوم التغليف'**
  String get cartPackagingFee;

  /// No description provided for @cartCodFee.
  ///
  /// In ar, this message translates to:
  /// **'رسوم الدفع عند الاستلام'**
  String get cartCodFee;

  /// No description provided for @cartTotal.
  ///
  /// In ar, this message translates to:
  /// **'الإجمالي'**
  String get cartTotal;

  /// No description provided for @cartCheckout.
  ///
  /// In ar, this message translates to:
  /// **'إتمام الطلب'**
  String get cartCheckout;

  /// No description provided for @cartRemove.
  ///
  /// In ar, this message translates to:
  /// **'حذف من السلة'**
  String get cartRemove;

  /// No description provided for @cartRemoved.
  ///
  /// In ar, this message translates to:
  /// **'تمت إزالة المنتج من السلة.'**
  String get cartRemoved;

  /// No description provided for @cartUnavailable.
  ///
  /// In ar, this message translates to:
  /// **'غير متوفر'**
  String get cartUnavailable;

  /// No description provided for @cartUnavailableNotice.
  ///
  /// In ar, this message translates to:
  /// **'أزل المنتجات غير المتوفرة للمتابعة'**
  String get cartUnavailableNotice;

  /// No description provided for @cartComponent.
  ///
  /// In ar, this message translates to:
  /// **'{name} × {count}'**
  String cartComponent(String name, int count);

  /// No description provided for @discountCode.
  ///
  /// In ar, this message translates to:
  /// **'كود الخصم'**
  String get discountCode;

  /// No description provided for @discountApply.
  ///
  /// In ar, this message translates to:
  /// **'تطبيق'**
  String get discountApply;

  /// No description provided for @discountApplied.
  ///
  /// In ar, this message translates to:
  /// **'تم تطبيق الكود {code}'**
  String discountApplied(String code);

  /// No description provided for @discountRemove.
  ///
  /// In ar, this message translates to:
  /// **'إزالة'**
  String get discountRemove;

  /// No description provided for @delete.
  ///
  /// In ar, this message translates to:
  /// **'حذف'**
  String get delete;

  /// No description provided for @edit.
  ///
  /// In ar, this message translates to:
  /// **'تعديل'**
  String get edit;

  /// No description provided for @save.
  ///
  /// In ar, this message translates to:
  /// **'حفظ'**
  String get save;

  /// No description provided for @copy.
  ///
  /// In ar, this message translates to:
  /// **'نسخ'**
  String get copy;

  /// No description provided for @copied.
  ///
  /// In ar, this message translates to:
  /// **'تم النسخ'**
  String get copied;

  /// No description provided for @checkoutTitle.
  ///
  /// In ar, this message translates to:
  /// **'إتمام الطلب'**
  String get checkoutTitle;

  /// No description provided for @stepAddress.
  ///
  /// In ar, this message translates to:
  /// **'العنوان'**
  String get stepAddress;

  /// No description provided for @stepShipping.
  ///
  /// In ar, this message translates to:
  /// **'الشحن'**
  String get stepShipping;

  /// No description provided for @stepPackaging.
  ///
  /// In ar, this message translates to:
  /// **'التغليف'**
  String get stepPackaging;

  /// No description provided for @stepPayment.
  ///
  /// In ar, this message translates to:
  /// **'الدفع'**
  String get stepPayment;

  /// No description provided for @stepReview.
  ///
  /// In ar, this message translates to:
  /// **'المراجعة'**
  String get stepReview;

  /// No description provided for @addressNone.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد عناوين محفوظة. أضف عنوانًا للمتابعة.'**
  String get addressNone;

  /// No description provided for @shippingNoOptions.
  ///
  /// In ar, this message translates to:
  /// **'لا تتوفر شركات شحن لهذه المدينة.'**
  String get shippingNoOptions;

  /// No description provided for @shippingFree.
  ///
  /// In ar, this message translates to:
  /// **'شحن مجاني'**
  String get shippingFree;

  /// No description provided for @shippingEta.
  ///
  /// In ar, this message translates to:
  /// **'التوصيل خلال {days} يوم'**
  String shippingEta(String days);

  /// No description provided for @packagingNone.
  ///
  /// In ar, this message translates to:
  /// **'بدون تغليف خاص'**
  String get packagingNone;

  /// No description provided for @paymentNoMethods.
  ///
  /// In ar, this message translates to:
  /// **'لا تتوفر طرق دفع حاليًا.'**
  String get paymentNoMethods;

  /// No description provided for @paymentPrepayTitle.
  ///
  /// In ar, this message translates to:
  /// **'ادفع الشحن مقدمًا'**
  String get paymentPrepayTitle;

  /// No description provided for @paymentPrepayBody.
  ///
  /// In ar, this message translates to:
  /// **'اختر طريقة تحويل لدفع مصاريف الشحن الآن، والباقي عند الاستلام.'**
  String get paymentPrepayBody;

  /// No description provided for @proofRequired.
  ///
  /// In ar, this message translates to:
  /// **'ارفع صورة إيصال التحويل لإتمام الطلب.'**
  String get proofRequired;

  /// No description provided for @proofChoose.
  ///
  /// In ar, this message translates to:
  /// **'اختر صورة الإيصال'**
  String get proofChoose;

  /// No description provided for @proofChange.
  ///
  /// In ar, this message translates to:
  /// **'تغيير'**
  String get proofChange;

  /// No description provided for @proofRemove.
  ///
  /// In ar, this message translates to:
  /// **'إزالة الصورة'**
  String get proofRemove;

  /// No description provided for @proofAttached.
  ///
  /// In ar, this message translates to:
  /// **'تم إرفاق الإيصال'**
  String get proofAttached;

  /// No description provided for @proofGallery.
  ///
  /// In ar, this message translates to:
  /// **'من المعرض'**
  String get proofGallery;

  /// No description provided for @proofCamera.
  ///
  /// In ar, this message translates to:
  /// **'التقاط صورة'**
  String get proofCamera;

  /// No description provided for @reviewAddress.
  ///
  /// In ar, this message translates to:
  /// **'عنوان التوصيل'**
  String get reviewAddress;

  /// No description provided for @reviewShipping.
  ///
  /// In ar, this message translates to:
  /// **'شركة الشحن'**
  String get reviewShipping;

  /// No description provided for @reviewPayment.
  ///
  /// In ar, this message translates to:
  /// **'طريقة الدفع'**
  String get reviewPayment;

  /// No description provided for @reviewNotes.
  ///
  /// In ar, this message translates to:
  /// **'ملاحظات للطلب'**
  String get reviewNotes;

  /// No description provided for @reviewNotesHint.
  ///
  /// In ar, this message translates to:
  /// **'اختياري'**
  String get reviewNotesHint;

  /// No description provided for @placeOrder.
  ///
  /// In ar, this message translates to:
  /// **'تأكيد الطلب'**
  String get placeOrder;

  /// No description provided for @checkoutFixErrors.
  ///
  /// In ar, this message translates to:
  /// **'راجع البيانات المحددة ثم حاول مجددًا.'**
  String get checkoutFixErrors;

  /// No description provided for @orderPlacedTitle.
  ///
  /// In ar, this message translates to:
  /// **'تم استلام طلبك'**
  String get orderPlacedTitle;

  /// No description provided for @orderPlacedBody.
  ///
  /// In ar, this message translates to:
  /// **'شكرًا لك! رقم طلبك {number}'**
  String orderPlacedBody(String number);

  /// No description provided for @orderPlacedAwaiting.
  ///
  /// In ar, this message translates to:
  /// **'سنراجع طلبك ونؤكده قريبًا.'**
  String get orderPlacedAwaiting;

  /// No description provided for @orderPlacedAt.
  ///
  /// In ar, this message translates to:
  /// **'تاريخ الطلب'**
  String get orderPlacedAt;

  /// No description provided for @orderViewOrder.
  ///
  /// In ar, this message translates to:
  /// **'عرض الطلب'**
  String get orderViewOrder;

  /// No description provided for @ordersTitle.
  ///
  /// In ar, this message translates to:
  /// **'طلباتي'**
  String get ordersTitle;

  /// No description provided for @ordersEmptyTitle.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد طلبات بعد'**
  String get ordersEmptyTitle;

  /// No description provided for @ordersEmptyBody.
  ///
  /// In ar, this message translates to:
  /// **'ستظهر طلباتك هنا بعد أول عملية شراء.'**
  String get ordersEmptyBody;

  /// No description provided for @orderItemsCount.
  ///
  /// In ar, this message translates to:
  /// **'{count} منتج'**
  String orderItemsCount(int count);

  /// No description provided for @orderDetailTitle.
  ///
  /// In ar, this message translates to:
  /// **'تفاصيل الطلب'**
  String get orderDetailTitle;

  /// No description provided for @orderItems.
  ///
  /// In ar, this message translates to:
  /// **'المنتجات'**
  String get orderItems;

  /// No description provided for @orderTimeline.
  ///
  /// In ar, this message translates to:
  /// **'تتبع الطلب'**
  String get orderTimeline;

  /// No description provided for @orderReturns.
  ///
  /// In ar, this message translates to:
  /// **'المرتجعات'**
  String get orderReturns;

  /// No description provided for @orderPayNow.
  ///
  /// In ar, this message translates to:
  /// **'ادفع الآن'**
  String get orderPayNow;

  /// No description provided for @orderPayNowAmount.
  ///
  /// In ar, this message translates to:
  /// **'ادفع الآن ({amount})'**
  String orderPayNowAmount(String amount);

  /// No description provided for @orderUploadProof.
  ///
  /// In ar, this message translates to:
  /// **'رفع إيصال التحويل'**
  String get orderUploadProof;

  /// No description provided for @orderProofUploaded.
  ///
  /// In ar, this message translates to:
  /// **'تم رفع الإيصال، سنراجعه قريبًا.'**
  String get orderProofUploaded;

  /// No description provided for @orderInvoice.
  ///
  /// In ar, this message translates to:
  /// **'الفاتورة'**
  String get orderInvoice;

  /// No description provided for @orderInvoiceSubject.
  ///
  /// In ar, this message translates to:
  /// **'فاتورة الطلب {number}'**
  String orderInvoiceSubject(String number);

  /// No description provided for @orderCancel.
  ///
  /// In ar, this message translates to:
  /// **'إلغاء الطلب'**
  String get orderCancel;

  /// No description provided for @orderCancelTitle.
  ///
  /// In ar, this message translates to:
  /// **'إلغاء الطلب'**
  String get orderCancelTitle;

  /// No description provided for @orderCancelReason.
  ///
  /// In ar, this message translates to:
  /// **'ما سبب الإلغاء؟'**
  String get orderCancelReason;

  /// No description provided for @orderCancelled.
  ///
  /// In ar, this message translates to:
  /// **'تم إلغاء الطلب.'**
  String get orderCancelled;

  /// No description provided for @orderReturn.
  ///
  /// In ar, this message translates to:
  /// **'طلب إرجاع'**
  String get orderReturn;

  /// No description provided for @orderReturnTitle.
  ///
  /// In ar, this message translates to:
  /// **'طلب إرجاع'**
  String get orderReturnTitle;

  /// No description provided for @orderReturnItems.
  ///
  /// In ar, this message translates to:
  /// **'اختر المنتجات والكميات المراد إرجاعها'**
  String get orderReturnItems;

  /// No description provided for @orderReturnReason.
  ///
  /// In ar, this message translates to:
  /// **'سبب الإرجاع (اختياري)'**
  String get orderReturnReason;

  /// No description provided for @orderReturnSent.
  ///
  /// In ar, this message translates to:
  /// **'تم إرسال طلب الإرجاع.'**
  String get orderReturnSent;

  /// No description provided for @payUnpaid.
  ///
  /// In ar, this message translates to:
  /// **'غير مدفوع'**
  String get payUnpaid;

  /// No description provided for @payPaid.
  ///
  /// In ar, this message translates to:
  /// **'مدفوع'**
  String get payPaid;

  /// No description provided for @payPartial.
  ///
  /// In ar, this message translates to:
  /// **'مدفوع جزئيًا'**
  String get payPartial;

  /// No description provided for @payRefunded.
  ///
  /// In ar, this message translates to:
  /// **'تم الاسترداد'**
  String get payRefunded;

  /// No description provided for @proofPending.
  ///
  /// In ar, this message translates to:
  /// **'الإيصال قيد المراجعة'**
  String get proofPending;

  /// No description provided for @proofApproved.
  ///
  /// In ar, this message translates to:
  /// **'تم قبول الإيصال'**
  String get proofApproved;

  /// No description provided for @proofRejected.
  ///
  /// In ar, this message translates to:
  /// **'تم رفض الإيصال'**
  String get proofRejected;

  /// No description provided for @paymentWebTitle.
  ///
  /// In ar, this message translates to:
  /// **'الدفع الإلكتروني'**
  String get paymentWebTitle;

  /// No description provided for @paymentConfirming.
  ///
  /// In ar, this message translates to:
  /// **'جارٍ تأكيد الدفع...'**
  String get paymentConfirming;

  /// No description provided for @paymentSuccess.
  ///
  /// In ar, this message translates to:
  /// **'تم الدفع بنجاح'**
  String get paymentSuccess;

  /// No description provided for @paymentPendingTitle.
  ///
  /// In ar, this message translates to:
  /// **'ننتظر تأكيد الدفع'**
  String get paymentPendingTitle;

  /// No description provided for @paymentPendingBody.
  ///
  /// In ar, this message translates to:
  /// **'سيتم تحديث حالة الطلب فور تأكيد بوابة الدفع.'**
  String get paymentPendingBody;

  /// No description provided for @reasonColorDiffers.
  ///
  /// In ar, this message translates to:
  /// **'اللون مختلف عن الصورة'**
  String get reasonColorDiffers;

  /// No description provided for @reasonSizeUnclear.
  ///
  /// In ar, this message translates to:
  /// **'المقاس غير واضح'**
  String get reasonSizeUnclear;

  /// No description provided for @reasonDamagedPackaging.
  ///
  /// In ar, this message translates to:
  /// **'عبوة تالفة'**
  String get reasonDamagedPackaging;

  /// No description provided for @reasonDeliveryDelay.
  ///
  /// In ar, this message translates to:
  /// **'تأخر التوصيل'**
  String get reasonDeliveryDelay;

  /// No description provided for @reasonDescriptionInaccurate.
  ///
  /// In ar, this message translates to:
  /// **'الوصف غير دقيق'**
  String get reasonDescriptionInaccurate;

  /// No description provided for @reasonQuality.
  ///
  /// In ar, this message translates to:
  /// **'الجودة'**
  String get reasonQuality;

  /// No description provided for @reasonWrongItem.
  ///
  /// In ar, this message translates to:
  /// **'منتج خاطئ'**
  String get reasonWrongItem;

  /// No description provided for @reasonChangedMind.
  ///
  /// In ar, this message translates to:
  /// **'غيرت رأيي'**
  String get reasonChangedMind;

  /// No description provided for @reasonOther.
  ///
  /// In ar, this message translates to:
  /// **'سبب آخر'**
  String get reasonOther;

  /// No description provided for @accountOrders.
  ///
  /// In ar, this message translates to:
  /// **'طلباتي'**
  String get accountOrders;

  /// No description provided for @accountAddresses.
  ///
  /// In ar, this message translates to:
  /// **'عناويني'**
  String get accountAddresses;

  /// No description provided for @addressesTitle.
  ///
  /// In ar, this message translates to:
  /// **'عناويني'**
  String get addressesTitle;

  /// No description provided for @addressesEmpty.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد عناوين'**
  String get addressesEmpty;

  /// No description provided for @addressesEmptyBody.
  ///
  /// In ar, this message translates to:
  /// **'أضف عنوانًا لتسريع إتمام الطلبات.'**
  String get addressesEmptyBody;

  /// No description provided for @addressAdd.
  ///
  /// In ar, this message translates to:
  /// **'إضافة عنوان'**
  String get addressAdd;

  /// No description provided for @addressEdit.
  ///
  /// In ar, this message translates to:
  /// **'تعديل العنوان'**
  String get addressEdit;

  /// No description provided for @addressLabel.
  ///
  /// In ar, this message translates to:
  /// **'اسم العنوان (مثال: المنزل)'**
  String get addressLabel;

  /// No description provided for @addressCity.
  ///
  /// In ar, this message translates to:
  /// **'المدينة'**
  String get addressCity;

  /// No description provided for @addressCityPick.
  ///
  /// In ar, this message translates to:
  /// **'اختر المدينة'**
  String get addressCityPick;

  /// No description provided for @addressCitySearch.
  ///
  /// In ar, this message translates to:
  /// **'ابحث عن مدينة'**
  String get addressCitySearch;

  /// No description provided for @addressLine.
  ///
  /// In ar, this message translates to:
  /// **'العنوان بالتفصيل'**
  String get addressLine;

  /// No description provided for @addressDefault.
  ///
  /// In ar, this message translates to:
  /// **'الافتراضي'**
  String get addressDefault;

  /// No description provided for @addressSetDefault.
  ///
  /// In ar, this message translates to:
  /// **'تعيين كعنوان افتراضي'**
  String get addressSetDefault;

  /// No description provided for @addressSaved.
  ///
  /// In ar, this message translates to:
  /// **'تم حفظ العنوان.'**
  String get addressSaved;

  /// No description provided for @addressDeleted.
  ///
  /// In ar, this message translates to:
  /// **'تم حذف العنوان.'**
  String get addressDeleted;

  /// No description provided for @addressDeleteTitle.
  ///
  /// In ar, this message translates to:
  /// **'حذف العنوان'**
  String get addressDeleteTitle;

  /// No description provided for @addressDeleteBody.
  ///
  /// In ar, this message translates to:
  /// **'هل تريد حذف هذا العنوان؟'**
  String get addressDeleteBody;

  /// No description provided for @profileTitle.
  ///
  /// In ar, this message translates to:
  /// **'تعديل الملف الشخصي'**
  String get profileTitle;

  /// No description provided for @profileSaved.
  ///
  /// In ar, this message translates to:
  /// **'تم تحديث الملف الشخصي.'**
  String get profileSaved;

  /// No description provided for @profileEmailChangeHint.
  ///
  /// In ar, this message translates to:
  /// **'لتغيير البريد الإلكتروني أكد كلمة المرور الحالية.'**
  String get profileEmailChangeHint;

  /// No description provided for @currentPassword.
  ///
  /// In ar, this message translates to:
  /// **'كلمة المرور الحالية'**
  String get currentPassword;

  /// No description provided for @changePasswordTitle.
  ///
  /// In ar, this message translates to:
  /// **'تغيير كلمة المرور'**
  String get changePasswordTitle;

  /// No description provided for @passwordChanged.
  ///
  /// In ar, this message translates to:
  /// **'تم تغيير كلمة المرور. تم تسجيل الخروج من الأجهزة الأخرى.'**
  String get passwordChanged;

  /// No description provided for @deleteAccountTitle.
  ///
  /// In ar, this message translates to:
  /// **'حذف الحساب'**
  String get deleteAccountTitle;

  /// No description provided for @deleteAccountWarning.
  ///
  /// In ar, this message translates to:
  /// **'حذف الحساب نهائي. سيتم مسح بياناتك الشخصية وعناوينك ومفضلتك وتسجيل خروجك من كل الأجهزة. تبقى الطلبات السابقة بدون بياناتك لأغراض المحاسبة.'**
  String get deleteAccountWarning;

  /// No description provided for @deleteAccountNoPassword.
  ///
  /// In ar, this message translates to:
  /// **'حسابي يستخدم تسجيل الدخول بجوجل (بدون كلمة مرور)'**
  String get deleteAccountNoPassword;

  /// No description provided for @deleteAccountConfirmTitle.
  ///
  /// In ar, this message translates to:
  /// **'حذف حسابك؟'**
  String get deleteAccountConfirmTitle;

  /// No description provided for @deleteAccountConfirmBody.
  ///
  /// In ar, this message translates to:
  /// **'لا يمكن التراجع عن هذا الإجراء.'**
  String get deleteAccountConfirmBody;

  /// No description provided for @deleteAccountAction.
  ///
  /// In ar, this message translates to:
  /// **'احذف حسابي'**
  String get deleteAccountAction;

  /// No description provided for @deleteAccountDone.
  ///
  /// In ar, this message translates to:
  /// **'تم حذف حسابك.'**
  String get deleteAccountDone;

  /// No description provided for @themeTitle.
  ///
  /// In ar, this message translates to:
  /// **'المظهر'**
  String get themeTitle;

  /// No description provided for @themeLight.
  ///
  /// In ar, this message translates to:
  /// **'فاتح'**
  String get themeLight;

  /// No description provided for @themeDark.
  ///
  /// In ar, this message translates to:
  /// **'داكن'**
  String get themeDark;

  /// No description provided for @themeSystem.
  ///
  /// In ar, this message translates to:
  /// **'حسب النظام'**
  String get themeSystem;

  /// No description provided for @aboutTitle.
  ///
  /// In ar, this message translates to:
  /// **'حول التطبيق'**
  String get aboutTitle;

  /// No description provided for @rateApp.
  ///
  /// In ar, this message translates to:
  /// **'قيّم التطبيق'**
  String get rateApp;

  /// No description provided for @rateAppUnavailable.
  ///
  /// In ar, this message translates to:
  /// **'صفحة التطبيق في المتجر غير متاحة بعد.'**
  String get rateAppUnavailable;

  /// No description provided for @contactTitle.
  ///
  /// In ar, this message translates to:
  /// **'تواصل معنا'**
  String get contactTitle;

  /// No description provided for @contactPhone.
  ///
  /// In ar, this message translates to:
  /// **'الهاتف'**
  String get contactPhone;

  /// No description provided for @contactEmpty.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد بيانات تواصل متاحة.'**
  String get contactEmpty;

  /// No description provided for @blogTitle.
  ///
  /// In ar, this message translates to:
  /// **'المدونة'**
  String get blogTitle;

  /// No description provided for @blogEmpty.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد مقالات بعد.'**
  String get blogEmpty;

  /// No description provided for @drawerCollections.
  ///
  /// In ar, this message translates to:
  /// **'المجموعات والعروض'**
  String get drawerCollections;

  /// No description provided for @drawerInfoPages.
  ///
  /// In ar, this message translates to:
  /// **'معلومات'**
  String get drawerInfoPages;

  /// No description provided for @trackOrder.
  ///
  /// In ar, this message translates to:
  /// **'تتبع طلب'**
  String get trackOrder;

  /// No description provided for @trackOrderHint.
  ///
  /// In ar, this message translates to:
  /// **'رابط الطلب أو رمزه'**
  String get trackOrderHint;

  /// No description provided for @trackOrderInvalid.
  ///
  /// In ar, this message translates to:
  /// **'هذا ليس رابط أو رمز طلب صالح.'**
  String get trackOrderInvalid;

  /// No description provided for @launchTitle.
  ///
  /// In ar, this message translates to:
  /// **'إطلاق محدود'**
  String get launchTitle;

  /// No description provided for @launchContact.
  ///
  /// In ar, this message translates to:
  /// **'الهاتف أو البريد الإلكتروني'**
  String get launchContact;

  /// No description provided for @launchQuantity.
  ///
  /// In ar, this message translates to:
  /// **'الكمية'**
  String get launchQuantity;

  /// No description provided for @launchConsent.
  ///
  /// In ar, this message translates to:
  /// **'أوافق على التواصل معي بخصوص هذا الإطلاق.'**
  String get launchConsent;

  /// No description provided for @launchConsentRequired.
  ///
  /// In ar, this message translates to:
  /// **'الموافقة مطلوبة.'**
  String get launchConsentRequired;

  /// No description provided for @launchAcceptTerms.
  ///
  /// In ar, this message translates to:
  /// **'أوافق على شروط الطلب المسبق.'**
  String get launchAcceptTerms;

  /// No description provided for @launchTermsRequired.
  ///
  /// In ar, this message translates to:
  /// **'وافق على الشروط للمتابعة.'**
  String get launchTermsRequired;

  /// No description provided for @launchJoin.
  ///
  /// In ar, this message translates to:
  /// **'انضم'**
  String get launchJoin;

  /// No description provided for @launchThanks.
  ///
  /// In ar, this message translates to:
  /// **'شكرا لك، تم تسجيل اهتمامك.'**
  String get launchThanks;

  /// No description provided for @launchClosed.
  ///
  /// In ar, this message translates to:
  /// **'هذا الإطلاق مغلق.'**
  String get launchClosed;

  /// No description provided for @linkCopied.
  ///
  /// In ar, this message translates to:
  /// **'تم نسخ الرابط.'**
  String get linkCopied;

  /// No description provided for @copyLink.
  ///
  /// In ar, this message translates to:
  /// **'نسخ الرابط'**
  String get copyLink;

  /// No description provided for @affiliateTitle.
  ///
  /// In ar, this message translates to:
  /// **'برنامج التسويق بالعمولة'**
  String get affiliateTitle;

  /// No description provided for @affiliateIntro.
  ///
  /// In ar, this message translates to:
  /// **'شارك المنتجات واربح عمولة على كل طلب.'**
  String get affiliateIntro;

  /// No description provided for @affiliateApply.
  ///
  /// In ar, this message translates to:
  /// **'قدّم كمسوّق'**
  String get affiliateApply;

  /// No description provided for @affiliateApplied.
  ///
  /// In ar, this message translates to:
  /// **'تم إرسال الطلب'**
  String get affiliateApplied;

  /// No description provided for @affiliateAppliedBody.
  ///
  /// In ar, this message translates to:
  /// **'حسابك بانتظار الموافقة. يمكنك تسجيل الدخول بعد الموافقة عليه.'**
  String get affiliateAppliedBody;

  /// No description provided for @affiliateWelcome.
  ///
  /// In ar, this message translates to:
  /// **'أهلا، {name}'**
  String affiliateWelcome(String name);

  /// No description provided for @affiliateClicks.
  ///
  /// In ar, this message translates to:
  /// **'النقرات'**
  String get affiliateClicks;

  /// No description provided for @affiliateOrders.
  ///
  /// In ar, this message translates to:
  /// **'الطلبات'**
  String get affiliateOrders;

  /// No description provided for @affiliateConversion.
  ///
  /// In ar, this message translates to:
  /// **'التحويل'**
  String get affiliateConversion;

  /// No description provided for @affiliateSales.
  ///
  /// In ar, this message translates to:
  /// **'إجمالي المبيعات'**
  String get affiliateSales;

  /// No description provided for @affiliateTier.
  ///
  /// In ar, this message translates to:
  /// **'المستوى {name} - عمولة {percent}%'**
  String affiliateTier(String name, String percent);

  /// No description provided for @affiliateNextTier.
  ///
  /// In ar, this message translates to:
  /// **'التالي: {name} عند {amount}'**
  String affiliateNextTier(String name, String amount);

  /// No description provided for @affiliateCommissions.
  ///
  /// In ar, this message translates to:
  /// **'العمولات'**
  String get affiliateCommissions;

  /// No description provided for @affiliateRecentCommissions.
  ///
  /// In ar, this message translates to:
  /// **'آخر العمولات'**
  String get affiliateRecentCommissions;

  /// No description provided for @commissionPending.
  ///
  /// In ar, this message translates to:
  /// **'قيد المراجعة'**
  String get commissionPending;

  /// No description provided for @commissionApproved.
  ///
  /// In ar, this message translates to:
  /// **'معتمدة'**
  String get commissionApproved;

  /// No description provided for @commissionPaid.
  ///
  /// In ar, this message translates to:
  /// **'مدفوعة'**
  String get commissionPaid;

  /// No description provided for @commissionAvailable.
  ///
  /// In ar, this message translates to:
  /// **'متاحة للسحب'**
  String get commissionAvailable;

  /// No description provided for @affiliateProducts.
  ///
  /// In ar, this message translates to:
  /// **'المنتجات والروابط'**
  String get affiliateProducts;

  /// No description provided for @affiliateQr.
  ///
  /// In ar, this message translates to:
  /// **'رمز QR'**
  String get affiliateQr;

  /// No description provided for @affiliatePayouts.
  ///
  /// In ar, this message translates to:
  /// **'المدفوعات'**
  String get affiliatePayouts;

  /// No description provided for @payoutAvailable.
  ///
  /// In ar, this message translates to:
  /// **'المتاح: {amount}'**
  String payoutAvailable(String amount);

  /// No description provided for @payoutRequest.
  ///
  /// In ar, this message translates to:
  /// **'اطلب سحب الرصيد'**
  String get payoutRequest;

  /// No description provided for @payoutRequested.
  ///
  /// In ar, this message translates to:
  /// **'تم طلب السحب.'**
  String get payoutRequested;

  /// No description provided for @payoutsEmpty.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد مدفوعات بعد.'**
  String get payoutsEmpty;

  /// No description provided for @ambassadorTitle.
  ///
  /// In ar, this message translates to:
  /// **'برنامج السفراء'**
  String get ambassadorTitle;

  /// No description provided for @ambassadorUnavailable.
  ///
  /// In ar, this message translates to:
  /// **'برنامج السفراء غير متاح في هذا المتجر.'**
  String get ambassadorUnavailable;

  /// No description provided for @ambassadorIntro.
  ///
  /// In ar, this message translates to:
  /// **'أنشئ روابط متتبعة لكل قناة وتابع أداء محتواك.'**
  String get ambassadorIntro;

  /// No description provided for @ambassadorDisclosure.
  ///
  /// In ar, this message translates to:
  /// **'الإفصاح الذي يظهر مع منشوراتك'**
  String get ambassadorDisclosure;

  /// No description provided for @ambassadorTerms.
  ///
  /// In ar, this message translates to:
  /// **'أوافق على شروط برنامج السفراء.'**
  String get ambassadorTerms;

  /// No description provided for @ambassadorJoin.
  ///
  /// In ar, this message translates to:
  /// **'انضم للبرنامج'**
  String get ambassadorJoin;

  /// No description provided for @ambassadorReport.
  ///
  /// In ar, this message translates to:
  /// **'آخر {days} يوم'**
  String ambassadorReport(int days);

  /// No description provided for @ambassadorValid.
  ///
  /// In ar, this message translates to:
  /// **'صالحة'**
  String get ambassadorValid;

  /// No description provided for @ambassadorPending.
  ///
  /// In ar, this message translates to:
  /// **'معلقة'**
  String get ambassadorPending;

  /// No description provided for @ambassadorExcluded.
  ///
  /// In ar, this message translates to:
  /// **'مستبعدة'**
  String get ambassadorExcluded;

  /// No description provided for @ambassadorNewLink.
  ///
  /// In ar, this message translates to:
  /// **'رابط متتبع جديد'**
  String get ambassadorNewLink;

  /// No description provided for @ambassadorProduct.
  ///
  /// In ar, this message translates to:
  /// **'المنتج'**
  String get ambassadorProduct;

  /// No description provided for @ambassadorCreateLink.
  ///
  /// In ar, this message translates to:
  /// **'أنشئ الرابط'**
  String get ambassadorCreateLink;

  /// No description provided for @ambassadorLinks.
  ///
  /// In ar, this message translates to:
  /// **'روابطي'**
  String get ambassadorLinks;

  /// No description provided for @ambassadorNoLinks.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد روابط بعد.'**
  String get ambassadorNoLinks;

  /// No description provided for @ambassadorIdeas.
  ///
  /// In ar, this message translates to:
  /// **'أفكار للمحتوى'**
  String get ambassadorIdeas;

  /// No description provided for @showPassword.
  ///
  /// In ar, this message translates to:
  /// **'إظهار كلمة المرور'**
  String get showPassword;

  /// No description provided for @hidePassword.
  ///
  /// In ar, this message translates to:
  /// **'إخفاء كلمة المرور'**
  String get hidePassword;
}

class _AppLocalizationsDelegate
    extends LocalizationsDelegate<AppLocalizations> {
  const _AppLocalizationsDelegate();

  @override
  Future<AppLocalizations> load(Locale locale) {
    return SynchronousFuture<AppLocalizations>(lookupAppLocalizations(locale));
  }

  @override
  bool isSupported(Locale locale) =>
      <String>['ar', 'en'].contains(locale.languageCode);

  @override
  bool shouldReload(_AppLocalizationsDelegate old) => false;
}

AppLocalizations lookupAppLocalizations(Locale locale) {
  // Lookup logic when only language code is specified.
  switch (locale.languageCode) {
    case 'ar':
      return AppLocalizationsAr();
    case 'en':
      return AppLocalizationsEn();
  }

  throw FlutterError(
    'AppLocalizations.delegate failed to load unsupported locale "$locale". This is likely '
    'an issue with the localizations generation tool. Please file an issue '
    'on GitHub with a reproducible sample app and the gen-l10n configuration '
    'that was used.',
  );
}
