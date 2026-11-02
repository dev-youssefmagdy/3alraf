import 'package:app/features/tenant/data/tenant_models.dart';
import 'package:app/features/tenant/domain/tenant_decision.dart';
import 'package:flutter_test/flutter_test.dart';

Tenant t(String slug) => Tenant.fromJson({
  'slug': slug,
  'name': slug,
  'country': 'XX',
  'currency': 'EGP',
  'default_locale': 'ar',
  'locales': ['ar'],
});

void main() {
  group('decideTenant', () {
    test('saved + still active -> use saved', () {
      final d = decideTenant(savedSlug: 'sa', tenants: [t('egy'), t('sa')]);
      expect(d, isA<UseSavedTenant>());
      expect((d as UseSavedTenant).tenant.slug, 'sa');
    });

    test('saved + only tenant active -> still use saved (no re-select)', () {
      final d = decideTenant(savedSlug: 'egy', tenants: [t('egy')]);
      expect(d, isA<UseSavedTenant>());
    });

    test('saved but no longer active -> selection with reason', () {
      final d = decideTenant(savedSlug: 'old', tenants: [t('egy'), t('sa')]);
      expect(d, isA<ShowSelection>());
      expect((d as ShowSelection).reason, SelectionReason.savedInactive);
    });

    test('saved inactive and one other tenant -> still asks (message)', () {
      final d = decideTenant(savedSlug: 'old', tenants: [t('egy')]);
      expect(d, isA<ShowSelection>());
    });

    test('nothing saved + exactly one tenant -> auto-select', () {
      final d = decideTenant(savedSlug: null, tenants: [t('egy')]);
      expect(d, isA<AutoSelectTenant>());
      expect((d as AutoSelectTenant).tenant.slug, 'egy');
    });

    test('nothing saved + many tenants -> selection (first run)', () {
      final d = decideTenant(savedSlug: null, tenants: [t('egy'), t('sa')]);
      expect(d, isA<ShowSelection>());
      expect((d as ShowSelection).reason, SelectionReason.firstRun);
    });

    test('nothing saved + empty directory -> selection', () {
      expect(decideTenant(savedSlug: null, tenants: []), isA<ShowSelection>());
    });

    test('unreachable + saved -> offline with saved', () {
      final d = decideTenant(savedSlug: 'egy', tenants: null);
      expect(d, isA<OfflineWithSaved>());
      expect((d as OfflineWithSaved).slug, 'egy');
    });

    test('unreachable + nothing saved -> full-screen error', () {
      expect(
        decideTenant(savedSlug: null, tenants: null),
        isA<UnreachableNoTenant>(),
      );
      expect(
        decideTenant(savedSlug: '', tenants: null),
        isA<UnreachableNoTenant>(),
      );
    });
  });
}
