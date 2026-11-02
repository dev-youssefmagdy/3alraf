Map<String, Object?> _map(Object? v) =>
    v is Map ? v.map((k, v) => MapEntry('$k', v)) : const {};

class Customer {
  const Customer({
    required this.id,
    required this.name,
    required this.email,
    this.phone,
    this.phoneLocal,
    this.phoneCountryCode,
    this.emailVerified = false,
  });

  factory Customer.fromJson(Map<String, Object?> j) => Customer(
    id: j['id'] is int ? j['id'] as int : 0,
    name: j['name'] is String ? j['name'] as String : '',
    email: j['email'] is String ? j['email'] as String : '',
    phone: j['phone_full'] is String ? j['phone_full'] as String : null,
    phoneLocal: j['phone'] is String ? j['phone'] as String : null,
    phoneCountryCode: j['phone_country_code'] is String
        ? j['phone_country_code'] as String
        : null,
    emailVerified: j['email_verified'] == true,
  );

  final int id;
  final String name;
  final String email;
  final String? phone;

  /// Number without the country code (profile form).
  final String? phoneLocal;
  final String? phoneCountryCode;
  final bool emailVerified;
}

/// `data` of login / register / google.
class AuthResult {
  const AuthResult({required this.token, required this.customer});

  factory AuthResult.fromJson(Map<String, Object?> j) => AuthResult(
    token: j['token'] is String ? j['token'] as String : '',
    customer: Customer.fromJson(_map(j['customer'])),
  );

  final String token;
  final Customer customer;
}
