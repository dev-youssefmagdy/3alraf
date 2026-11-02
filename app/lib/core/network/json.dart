/// Typed accessors for decoded JSON so no `dynamic` leaks out of the data
/// layer. All are total: a missing/mistyped field yields a safe default.
Map<String, Object?> asMap(Object? v) =>
    v is Map ? v.map((k, v) => MapEntry('$k', v)) : const {};

List<Object?> asList(Object? v) => v is List ? v : const [];

List<Map<String, Object?>> asMapList(Object? v) =>
    asList(v).whereType<Map<Object?, Object?>>().map(asMap).toList();

String str(Object? v, [String fallback = '']) => v == null ? fallback : '$v';

String? strOrNull(Object? v) {
  if (v == null) return null;
  final s = '$v';
  return s.isEmpty ? null : s;
}

int intOf(Object? v, [int fallback = 0]) =>
    v is int ? v : (v is num ? v.toInt() : int.tryParse('$v') ?? fallback);

int? intOrNull(Object? v) =>
    v == null ? null : (v is num ? v.toInt() : int.tryParse('$v'));

double? doubleOrNull(Object? v) =>
    v == null ? null : (v is num ? v.toDouble() : double.tryParse('$v'));

bool boolOf(Object? v) => v == true || v == 1 || v == '1' || v == 'true';

DateTime? dateOrNull(Object? v) => v is String ? DateTime.tryParse(v) : null;
