// ignore: unused_import
import 'package:intl/intl.dart' as intl;
import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for English (`en`).
class AppLocalizationsEn extends AppLocalizations {
  AppLocalizationsEn([String locale = 'en']) : super(locale);

  @override
  String get appName => '3alraf';

  @override
  String get retry => 'Retry';

  @override
  String get loading => 'Loading…';

  @override
  String get errorGeneric => 'Something went wrong. Please try again.';

  @override
  String get errorNetwork =>
      'Can\'t reach the internet. Check your connection and try again.';

  @override
  String get errorTimeout => 'The connection timed out. Please try again.';

  @override
  String get errorServer => 'Server error. Please try again later.';

  @override
  String get errorRateLimited =>
      'Too many attempts. Wait a moment and try again.';

  @override
  String get errorNotFound => 'Not found.';

  @override
  String get errorUnauthorized =>
      'Your session has expired. Please sign in again.';

  @override
  String get emptyTitle => 'Nothing here yet';

  @override
  String get navHome => 'Home';

  @override
  String get navCategories => 'Categories';

  @override
  String get navCart => 'Cart';

  @override
  String get navWishlist => 'Wishlist';

  @override
  String get navAccount => 'Account';

  @override
  String get comingSoon => 'Coming soon';

  @override
  String get addToCart => 'Add to cart';

  @override
  String get outOfStock => 'Out of stock';

  @override
  String currencyAmount(String amount, String currency) {
    return '$amount $currency';
  }

  @override
  String discountBadge(String percent) {
    return '$percent% off';
  }

  @override
  String ratingSemantics(String rating) {
    return 'Rated $rating out of 5';
  }

  @override
  String get quantityIncrease => 'Increase quantity';

  @override
  String get quantityDecrease => 'Decrease quantity';

  @override
  String quantityLabel(int count) {
    return 'Quantity $count';
  }

  @override
  String get viewAll => 'View all';

  @override
  String get cancel => 'Cancel';

  @override
  String get confirm => 'Confirm';

  @override
  String get close => 'Close';

  @override
  String get menu => 'Menu';

  @override
  String get startupErrorTitle => 'Could not start the app';

  @override
  String get selectCountryTitle => 'Choose your country';

  @override
  String get selectCountrySubtitle => 'Pick the store you want to shop from.';

  @override
  String get searchCountry => 'Search a country or store';

  @override
  String get noStoresFound => 'No matching stores';

  @override
  String get noStoresAvailable => 'No stores are available right now.';

  @override
  String get continueAction => 'Continue';

  @override
  String confirmCountryTitle(String store) {
    return 'Shop from $store?';
  }

  @override
  String get confirmCountryMessage =>
      'You can change the country later from the side menu.';

  @override
  String get countryInactiveNotice =>
      'Your previous store is no longer available. Please choose another one.';

  @override
  String get currentStore => 'Current';

  @override
  String storeCurrency(String currency) {
    return 'Currency: $currency';
  }

  @override
  String storeLanguages(String languages) {
    return 'Languages: $languages';
  }

  @override
  String get switchCountry => 'Switch country';

  @override
  String get switchCountryWarnTitle => 'Switch store?';

  @override
  String switchCountryWarnBody(String store) {
    return 'Your cart and wishlist belong to the current store. Switching to $store signs you out and clears the cart on this device.';
  }

  @override
  String get switchConfirm => 'Switch';

  @override
  String switchedTo(String store) {
    return 'Now shopping from $store';
  }

  @override
  String get offlineBanner => 'You are offline. Showing saved data.';

  @override
  String get updateRequiredTitle => 'Update required';

  @override
  String get updateRequiredBody =>
      'Please update the app to the latest version to continue.';

  @override
  String get updateNow => 'Update now';

  @override
  String get maintenanceTitle => 'Under maintenance';

  @override
  String get maintenanceDefault =>
      'We are improving the service and will be back soon.';

  @override
  String get onboarding1Title => 'Shop beauty with ease';

  @override
  String get onboarding1Body =>
      'Thousands of original products from top brands in one place.';

  @override
  String get onboarding2Title => 'Offers and gift ideas';

  @override
  String get onboarding2Body =>
      'Follow daily offers and find the perfect gift.';

  @override
  String get onboarding3Title => 'Fast delivery, flexible payment';

  @override
  String get onboarding3Body =>
      'Choose the shipping and payment options that suit you.';

  @override
  String get skip => 'Skip';

  @override
  String get next => 'Next';

  @override
  String get getStarted => 'Get started';

  @override
  String get language => 'Language';

  @override
  String get contactWhatsapp => 'Contact us on WhatsApp';

  @override
  String appVersion(String version) {
    return 'Version $version';
  }

  @override
  String currentStoreLabel(String store) {
    return 'Current store: $store';
  }

  @override
  String get login => 'Sign in';

  @override
  String get register => 'Create account';

  @override
  String get logout => 'Sign out';

  @override
  String get loginSubtitle => 'Welcome back! Sign in to keep shopping.';

  @override
  String get registerSubtitle =>
      'Create an account to keep your orders and addresses.';

  @override
  String get sessionExpiredNotice =>
      'Your session expired. Please sign in again.';

  @override
  String get email => 'Email';

  @override
  String get password => 'Password';

  @override
  String get confirmPassword => 'Confirm password';

  @override
  String get fullName => 'Full name';

  @override
  String get phoneOptional => 'Phone (optional)';

  @override
  String get forgotPasswordLink => 'Forgot password?';

  @override
  String get signInWithGoogle => 'Continue with Google';

  @override
  String get orDivider => 'or';

  @override
  String get noAccountPrompt => 'Don\'t have an account?';

  @override
  String get haveAccountPrompt => 'Already have an account?';

  @override
  String get continueAsGuest => 'Continue as guest';

  @override
  String get forgotTitle => 'Reset your password';

  @override
  String get forgotBody =>
      'Enter your email and we will send you a 6-digit code.';

  @override
  String get sendCode => 'Send code';

  @override
  String get resetTitle => 'New password';

  @override
  String get resetBody => 'Enter the code we sent you and your new password.';

  @override
  String get codeLabel => 'Code (6 digits)';

  @override
  String get newPassword => 'New password';

  @override
  String get resetAction => 'Change password';

  @override
  String get resetDone => 'Password changed. Please sign in.';

  @override
  String get verifyTitle => 'Verify your email';

  @override
  String get verifyBody =>
      'We sent a code to your email. Enter it to verify your account.';

  @override
  String get verifyAction => 'Verify';

  @override
  String get resendCode => 'Resend code';

  @override
  String get codeSent => 'The code was sent to your email.';

  @override
  String get emailVerified => 'Your email is verified.';

  @override
  String get skipForNow => 'Not now';

  @override
  String get fieldRequired => 'This field is required';

  @override
  String get invalidEmail => 'Invalid email address';

  @override
  String get passwordTooShort => 'At least 8 characters';

  @override
  String get passwordMismatch => 'Passwords do not match';

  @override
  String get invalidCode => 'Enter 6 digits';

  @override
  String get accountGuestTitle => 'Welcome to 3alraf';

  @override
  String get accountGuestBody =>
      'Sign in to track orders and keep your wishlist.';

  @override
  String signedInAs(String name) {
    return 'Signed in as $name';
  }

  @override
  String get wishlistSignInBody => 'Sign in to see your wishlist.';

  @override
  String get googleFailed => 'Google sign-in failed.';

  @override
  String get emailNotVerified => 'Email not verified';

  @override
  String get searchHint => 'Search products';

  @override
  String get homeEmptyTitle => 'Nothing to show yet';

  @override
  String get homeEmptyBody =>
      'The store is being prepared. Pull down to refresh.';

  @override
  String get flashSale => 'Flash sale';

  @override
  String get flashEndsIn => 'Ends in';

  @override
  String get railRecommended => 'Recommended for you';

  @override
  String get railMostSold => 'Best sellers';

  @override
  String get railMostReviewed => 'Most reviewed';

  @override
  String get brands => 'Brands';

  @override
  String get brandsEmpty => 'No brands yet';

  @override
  String get bundles => 'Bundles';

  @override
  String get bundle => 'Bundle';

  @override
  String get bundlesEmpty => 'No bundles available right now.';

  @override
  String get bundleContains => 'This bundle includes';

  @override
  String get bundleUnavailable => 'Currently unavailable';

  @override
  String bundleSave(String amount) {
    return 'Save $amount';
  }

  @override
  String get giftCtaTitle => 'Find the perfect gift';

  @override
  String get giftCtaBody => 'Tell us your budget and occasion';

  @override
  String get categoriesEmpty => 'No categories yet';

  @override
  String allInCategory(String name) {
    return 'All of $name';
  }

  @override
  String get products => 'Products';

  @override
  String productsCount(int count) {
    return '$count products';
  }

  @override
  String searchResultsFor(String q) {
    return 'Results for \"$q\"';
  }

  @override
  String get noProducts => 'No products found';

  @override
  String get noProductsFiltered => 'Try removing some filters.';

  @override
  String get resetFilters => 'Reset filters';

  @override
  String get reset => 'Reset';

  @override
  String get filters => 'Filters';

  @override
  String get applyFilters => 'Show results';

  @override
  String get sortBy => 'Sort by';

  @override
  String get sortNewest => 'Newest';

  @override
  String get sortPriceAsc => 'Price: low to high';

  @override
  String get sortPriceDesc => 'Price: high to low';

  @override
  String get sortBestSelling => 'Best selling';

  @override
  String get sortTopRated => 'Top rated';

  @override
  String get price => 'Price';

  @override
  String get priceFrom => 'From';

  @override
  String get priceTo => 'To';

  @override
  String get clear => 'Clear';

  @override
  String get recentSearches => 'Recent searches';

  @override
  String get searchEmptyTitle => 'Search the store';

  @override
  String get searchEmptyBody => 'Type a product, brand or category name.';

  @override
  String searchFor(String term) {
    return 'Search for \"$term\"';
  }

  @override
  String get noSuggestions => 'No suggestions';

  @override
  String get addedToCart => 'Added to cart.';

  @override
  String get viewCart => 'View cart';

  @override
  String get chooseOptions => 'Choose options';

  @override
  String get unavailableCombo => 'This combination is unavailable.';

  @override
  String get quantity => 'Quantity';

  @override
  String get description => 'Description';

  @override
  String get sku => 'SKU';

  @override
  String get inStock => 'In stock';

  @override
  String lowStock(int count) {
    return 'Only $count left';
  }

  @override
  String get relatedProducts => 'You may also like';

  @override
  String get share => 'Share';

  @override
  String get wishlistAdd => 'Add to wishlist';

  @override
  String get wishlistRemove => 'Remove from wishlist';

  @override
  String get wishlistAdded => 'Added to your wishlist.';

  @override
  String get wishlistRemoved => 'Removed from your wishlist.';

  @override
  String get wishlistEmptyTitle => 'Your wishlist is empty';

  @override
  String get wishlistEmptyBody =>
      'Tap the heart on any product to save it here.';

  @override
  String get startShopping => 'Start shopping';

  @override
  String get reviewsTitle => 'Reviews';

  @override
  String get noReviews => 'No reviews yet.';

  @override
  String get writeReview => 'Write a review';

  @override
  String get reviewBody => 'Your review (optional)';

  @override
  String get reviewPickRating => 'Please choose a star rating.';

  @override
  String get submitReview => 'Submit review';

  @override
  String get reviewThanks =>
      'Thanks! Your review will appear after moderation.';

  @override
  String get notifyMe => 'Notify me';

  @override
  String get notifyMeBody =>
      'This item is out of stock. Leave your details and we will contact you when it is available.';

  @override
  String get notifyMeSend => 'Send request';

  @override
  String get notifyMeSent => 'Your request was sent. We will contact you soon.';

  @override
  String get phone => 'Phone number';

  @override
  String get noteOptional => 'Note (optional)';

  @override
  String get back => 'Back';

  @override
  String get featureUnavailable =>
      'This feature is not available in your store.';

  @override
  String get giftFinder => 'Gift finder';

  @override
  String get giftStartOver => 'Start over';

  @override
  String get giftStepBudget => 'What is your budget?';

  @override
  String get giftBudget => 'Budget';

  @override
  String get giftBudgetRequired => 'Enter a budget greater than zero.';

  @override
  String get giftStepOccasion => 'What is the occasion?';

  @override
  String get giftStepRecipient => 'Who is it for?';

  @override
  String get giftStepExtras => 'Anything else?';

  @override
  String get giftRestrictions => 'Things to avoid (optional)';

  @override
  String get giftRestrictionsHint => 'e.g. perfume, nuts';

  @override
  String get giftFind => 'Find gifts';

  @override
  String get giftNoResults => 'No gifts found';

  @override
  String get giftNoResultsBody => 'Try a higher budget or fewer restrictions.';

  @override
  String get giftSuggestions => 'Suggestions for you';

  @override
  String get giftNearBudget => 'Slightly above your budget';

  @override
  String get giftChooseVariant => 'Choose an option';

  @override
  String giftAddSelected(int count) {
    return 'Add selected ($count)';
  }

  @override
  String giftAdded(int count) {
    return '$count gifts added to your cart.';
  }

  @override
  String get occBirthday => 'Birthday';

  @override
  String get occMothersDay => 'Mother\'s Day';

  @override
  String get occValentine => 'Valentine';

  @override
  String get occWedding => 'Wedding';

  @override
  String get occEidFitr => 'Eid al-Fitr';

  @override
  String get occEidAdha => 'Eid al-Adha';

  @override
  String get occRamadan => 'Ramadan';

  @override
  String get occGraduation => 'Graduation';

  @override
  String get occThanks => 'Thank you';

  @override
  String get occSelf => 'Treat myself';

  @override
  String get recMother => 'Mother';

  @override
  String get recPartner => 'Partner';

  @override
  String get recFriend => 'Friend';

  @override
  String get recSister => 'Sister';

  @override
  String get recColleague => 'Colleague';

  @override
  String get recChild => 'Child';

  @override
  String get recSelf => 'Myself';

  @override
  String get recOther => 'Someone else';

  @override
  String get cartTitle => 'Cart';

  @override
  String get cartEmptyTitle => 'Your cart is empty';

  @override
  String get cartEmptyBody => 'Add products to get started.';

  @override
  String get cartSubtotal => 'Subtotal';

  @override
  String get cartDiscount => 'Discount';

  @override
  String get cartShipping => 'Shipping';

  @override
  String get cartShippingAtCheckout => 'Calculated at checkout';

  @override
  String get cartTax => 'Tax';

  @override
  String get cartPaymentFee => 'Payment fee';

  @override
  String get cartPackagingFee => 'Packaging';

  @override
  String get cartCodFee => 'Cash on delivery fee';

  @override
  String get cartTotal => 'Total';

  @override
  String get cartCheckout => 'Checkout';

  @override
  String get cartRemove => 'Remove from cart';

  @override
  String get cartRemoved => 'Item removed from your cart.';

  @override
  String get cartUnavailable => 'Unavailable';

  @override
  String get cartUnavailableNotice => 'Remove unavailable items to continue';

  @override
  String cartComponent(String name, int count) {
    return '$name × $count';
  }

  @override
  String get discountCode => 'Discount code';

  @override
  String get discountApply => 'Apply';

  @override
  String discountApplied(String code) {
    return 'Code $code applied';
  }

  @override
  String get discountRemove => 'Remove';

  @override
  String get delete => 'Delete';

  @override
  String get edit => 'Edit';

  @override
  String get save => 'Save';

  @override
  String get copy => 'Copy';

  @override
  String get copied => 'Copied';

  @override
  String get checkoutTitle => 'Checkout';

  @override
  String get stepAddress => 'Address';

  @override
  String get stepShipping => 'Shipping';

  @override
  String get stepPackaging => 'Packaging';

  @override
  String get stepPayment => 'Payment';

  @override
  String get stepReview => 'Review';

  @override
  String get addressNone => 'No saved addresses. Add one to continue.';

  @override
  String get shippingNoOptions => 'No shipping options for this city.';

  @override
  String get shippingFree => 'Free shipping';

  @override
  String shippingEta(String days) {
    return 'Delivery in $days days';
  }

  @override
  String get packagingNone => 'No special packaging';

  @override
  String get paymentNoMethods => 'No payment methods available.';

  @override
  String get paymentPrepayTitle => 'Pay shipping upfront';

  @override
  String get paymentPrepayBody =>
      'Choose a transfer method to pay shipping now; the rest is paid on delivery.';

  @override
  String get proofRequired =>
      'Upload your transfer receipt to place the order.';

  @override
  String get proofChoose => 'Choose receipt image';

  @override
  String get proofChange => 'Change';

  @override
  String get proofRemove => 'Remove image';

  @override
  String get proofAttached => 'Receipt attached';

  @override
  String get proofGallery => 'From gallery';

  @override
  String get proofCamera => 'Take a photo';

  @override
  String get reviewAddress => 'Delivery address';

  @override
  String get reviewShipping => 'Shipping';

  @override
  String get reviewPayment => 'Payment method';

  @override
  String get reviewNotes => 'Order notes';

  @override
  String get reviewNotesHint => 'Optional';

  @override
  String get placeOrder => 'Place order';

  @override
  String get checkoutFixErrors =>
      'Please fix the highlighted details and try again.';

  @override
  String get orderPlacedTitle => 'Order received';

  @override
  String orderPlacedBody(String number) {
    return 'Thank you! Your order number is $number';
  }

  @override
  String get orderPlacedAwaiting =>
      'We will review and confirm your order shortly.';

  @override
  String get orderPlacedAt => 'Placed on';

  @override
  String get orderViewOrder => 'View order';

  @override
  String get ordersTitle => 'My orders';

  @override
  String get ordersEmptyTitle => 'No orders yet';

  @override
  String get ordersEmptyBody =>
      'Your orders will show up here after your first purchase.';

  @override
  String orderItemsCount(int count) {
    return '$count items';
  }

  @override
  String get orderDetailTitle => 'Order details';

  @override
  String get orderItems => 'Items';

  @override
  String get orderTimeline => 'Order tracking';

  @override
  String get orderReturns => 'Returns';

  @override
  String get orderPayNow => 'Pay now';

  @override
  String orderPayNowAmount(String amount) {
    return 'Pay now ($amount)';
  }

  @override
  String get orderUploadProof => 'Upload transfer receipt';

  @override
  String get orderProofUploaded =>
      'Receipt uploaded. We will review it shortly.';

  @override
  String get orderInvoice => 'Invoice';

  @override
  String orderInvoiceSubject(String number) {
    return 'Invoice for order $number';
  }

  @override
  String get orderCancel => 'Cancel order';

  @override
  String get orderCancelTitle => 'Cancel this order';

  @override
  String get orderCancelReason => 'Why are you cancelling?';

  @override
  String get orderCancelled => 'Your order was cancelled.';

  @override
  String get orderReturn => 'Request a return';

  @override
  String get orderReturnTitle => 'Request a return';

  @override
  String get orderReturnItems => 'Choose items and quantities to return';

  @override
  String get orderReturnReason => 'Reason (optional)';

  @override
  String get orderReturnSent => 'Your return request was sent.';

  @override
  String get payUnpaid => 'Unpaid';

  @override
  String get payPaid => 'Paid';

  @override
  String get payPartial => 'Partially paid';

  @override
  String get payRefunded => 'Refunded';

  @override
  String get proofPending => 'Receipt under review';

  @override
  String get proofApproved => 'Receipt approved';

  @override
  String get proofRejected => 'Receipt rejected';

  @override
  String get paymentWebTitle => 'Online payment';

  @override
  String get paymentConfirming => 'Confirming your payment...';

  @override
  String get paymentSuccess => 'Payment successful';

  @override
  String get paymentPendingTitle => 'Waiting for payment confirmation';

  @override
  String get paymentPendingBody =>
      'Your order will update as soon as the gateway confirms.';

  @override
  String get reasonColorDiffers => 'Colour differs from the photo';

  @override
  String get reasonSizeUnclear => 'Size unclear';

  @override
  String get reasonDamagedPackaging => 'Damaged packaging';

  @override
  String get reasonDeliveryDelay => 'Delivery delay';

  @override
  String get reasonDescriptionInaccurate => 'Description inaccurate';

  @override
  String get reasonQuality => 'Quality';

  @override
  String get reasonWrongItem => 'Wrong item';

  @override
  String get reasonChangedMind => 'Changed my mind';

  @override
  String get reasonOther => 'Other';

  @override
  String get accountOrders => 'My orders';

  @override
  String get accountAddresses => 'My addresses';

  @override
  String get addressesTitle => 'My addresses';

  @override
  String get addressesEmpty => 'No addresses';

  @override
  String get addressesEmptyBody => 'Add an address to check out faster.';

  @override
  String get addressAdd => 'Add address';

  @override
  String get addressEdit => 'Edit address';

  @override
  String get addressLabel => 'Label (e.g. Home)';

  @override
  String get addressCity => 'City';

  @override
  String get addressCityPick => 'Select a city';

  @override
  String get addressCitySearch => 'Search cities';

  @override
  String get addressLine => 'Street address';

  @override
  String get addressDefault => 'Default';

  @override
  String get addressSetDefault => 'Set as default address';

  @override
  String get addressSaved => 'Address saved.';

  @override
  String get addressDeleted => 'Address deleted.';

  @override
  String get addressDeleteTitle => 'Delete address';

  @override
  String get addressDeleteBody => 'Delete this address?';

  @override
  String get profileTitle => 'Edit profile';

  @override
  String get profileSaved => 'Profile updated.';

  @override
  String get profileEmailChangeHint =>
      'To change your e-mail, confirm your current password.';

  @override
  String get currentPassword => 'Current password';

  @override
  String get changePasswordTitle => 'Change password';

  @override
  String get passwordChanged =>
      'Password changed. Other devices were signed out.';

  @override
  String get deleteAccountTitle => 'Delete account';

  @override
  String get deleteAccountWarning =>
      'Deleting your account is permanent. Your personal data, addresses and wishlist are erased and you are signed out everywhere. Past orders are kept anonymously for accounting.';

  @override
  String get deleteAccountNoPassword =>
      'My account uses Google sign-in (no password)';

  @override
  String get deleteAccountConfirmTitle => 'Delete your account?';

  @override
  String get deleteAccountConfirmBody => 'This cannot be undone.';

  @override
  String get deleteAccountAction => 'Delete my account';

  @override
  String get deleteAccountDone => 'Your account has been deleted.';

  @override
  String get themeTitle => 'Appearance';

  @override
  String get themeLight => 'Light';

  @override
  String get themeDark => 'Dark';

  @override
  String get themeSystem => 'Follow system';

  @override
  String get aboutTitle => 'About';

  @override
  String get rateApp => 'Rate the app';

  @override
  String get rateAppUnavailable => 'The store listing is not available yet.';

  @override
  String get contactTitle => 'Contact us';

  @override
  String get contactPhone => 'Phone';

  @override
  String get contactEmpty => 'No contact details are available.';

  @override
  String get blogTitle => 'Blog';

  @override
  String get blogEmpty => 'No posts yet.';

  @override
  String get drawerCollections => 'Collections & offers';

  @override
  String get drawerInfoPages => 'Information';

  @override
  String get trackOrder => 'Track an order';

  @override
  String get trackOrderHint => 'Order link or code';

  @override
  String get trackOrderInvalid => 'This is not a valid order link or code.';

  @override
  String get launchTitle => 'Limited launch';

  @override
  String get launchContact => 'Phone or e-mail';

  @override
  String get launchQuantity => 'Quantity';

  @override
  String get launchConsent => 'I agree to be contacted about this launch.';

  @override
  String get launchConsentRequired => 'Consent is required.';

  @override
  String get launchAcceptTerms => 'I accept the pre-order terms.';

  @override
  String get launchTermsRequired => 'Accept the terms to continue.';

  @override
  String get launchJoin => 'Join';

  @override
  String get launchThanks => 'Thank you, your interest is recorded.';

  @override
  String get launchClosed => 'This launch is closed.';

  @override
  String get linkCopied => 'Link copied.';

  @override
  String get copyLink => 'Copy link';

  @override
  String get affiliateTitle => 'Affiliate program';

  @override
  String get affiliateIntro =>
      'Share products, earn a commission on every order.';

  @override
  String get affiliateApply => 'Apply as an affiliate';

  @override
  String get affiliateApplied => 'Application sent';

  @override
  String get affiliateAppliedBody =>
      'Your account is waiting for approval. You can sign in once it is approved.';

  @override
  String affiliateWelcome(String name) {
    return 'Hello, $name';
  }

  @override
  String get affiliateClicks => 'Clicks';

  @override
  String get affiliateOrders => 'Orders';

  @override
  String get affiliateConversion => 'Conversion';

  @override
  String get affiliateSales => 'Lifetime sales';

  @override
  String affiliateTier(String name, String percent) {
    return 'Tier $name - $percent% commission';
  }

  @override
  String affiliateNextTier(String name, String amount) {
    return 'Next: $name from $amount';
  }

  @override
  String get affiliateCommissions => 'Commissions';

  @override
  String get affiliateRecentCommissions => 'Recent commissions';

  @override
  String get commissionPending => 'Pending';

  @override
  String get commissionApproved => 'Approved';

  @override
  String get commissionPaid => 'Paid';

  @override
  String get commissionAvailable => 'Available for payout';

  @override
  String get affiliateProducts => 'Products & links';

  @override
  String get affiliateQr => 'QR code';

  @override
  String get affiliatePayouts => 'Payouts';

  @override
  String payoutAvailable(String amount) {
    return 'Available: $amount';
  }

  @override
  String get payoutRequest => 'Request payout';

  @override
  String get payoutRequested => 'Payout requested.';

  @override
  String get payoutsEmpty => 'No payouts yet.';

  @override
  String get ambassadorTitle => 'Ambassador program';

  @override
  String get ambassadorUnavailable =>
      'The ambassador program is not available in this store.';

  @override
  String get ambassadorIntro =>
      'Create tracked links per channel and see how your content performs.';

  @override
  String get ambassadorDisclosure => 'Disclosure shown with your posts';

  @override
  String get ambassadorTerms => 'I accept the ambassador terms.';

  @override
  String get ambassadorJoin => 'Join the program';

  @override
  String ambassadorReport(int days) {
    return 'Last $days days';
  }

  @override
  String get ambassadorValid => 'Valid';

  @override
  String get ambassadorPending => 'Pending';

  @override
  String get ambassadorExcluded => 'Excluded';

  @override
  String get ambassadorNewLink => 'New tracked link';

  @override
  String get ambassadorProduct => 'Product';

  @override
  String get ambassadorCreateLink => 'Create link';

  @override
  String get ambassadorLinks => 'My links';

  @override
  String get ambassadorNoLinks => 'No links yet.';

  @override
  String get ambassadorIdeas => 'Content ideas';

  @override
  String get showPassword => 'Show password';

  @override
  String get hidePassword => 'Hide password';
}
