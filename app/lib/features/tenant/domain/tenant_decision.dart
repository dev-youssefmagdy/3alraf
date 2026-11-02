import '../data/tenant_models.dart';

/// Why the selection screen is shown.
enum SelectionReason { firstRun, savedInactive }

/// Outcome of the startup tenant decision table (PLAN 3.1).
sealed class TenantDecision {
  const TenantDecision();
}

/// Saved tenant is still active -> Home.
class UseSavedTenant extends TenantDecision {
  const UseSavedTenant(this.tenant);
  final Tenant tenant;
}

/// No saved tenant and exactly one active -> select it silently.
class AutoSelectTenant extends TenantDecision {
  const AutoSelectTenant(this.tenant);
  final Tenant tenant;
}

/// Country selection screen.
class ShowSelection extends TenantDecision {
  const ShowSelection(this.reason);
  final SelectionReason reason;
}

/// `/tenants` unreachable but a tenant is saved -> offline-tolerant Home.
class OfflineWithSaved extends TenantDecision {
  const OfflineWithSaved(this.slug);
  final String slug;
}

/// `/tenants` unreachable and nothing saved -> full-screen error + retry.
class UnreachableNoTenant extends TenantDecision {
  const UnreachableNoTenant();
}

/// Pure decision function. [tenants] is `null` when `/tenants` could not be
/// fetched (offline / server down).
TenantDecision decideTenant({
  required String? savedSlug,
  required List<Tenant>? tenants,
}) {
  final hasSaved = savedSlug != null && savedSlug.isNotEmpty;
  if (tenants == null) {
    return hasSaved ? OfflineWithSaved(savedSlug) : const UnreachableNoTenant();
  }
  if (hasSaved) {
    for (final t in tenants) {
      if (t.slug == savedSlug) return UseSavedTenant(t);
    }
    return const ShowSelection(SelectionReason.savedInactive);
  }
  if (tenants.length == 1) return AutoSelectTenant(tenants.first);
  return const ShowSelection(SelectionReason.firstRun);
}
