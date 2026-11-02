import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../data/tenant_models.dart';
import 'tenant_controller.dart';

/// Active stores for the country picker (selection page and switcher).
final tenantListProvider = FutureProvider.autoDispose<List<Tenant>>(
  (ref) => ref.watch(tenantRepositoryProvider).fetchTenants(),
);
