import '../../../core/network/json.dart';

class City {
  const City({required this.id, required this.name});

  factory City.fromJson(Map<String, Object?> j) =>
      City(id: intOf(j['id']), name: str(j['name']));

  final int id;
  final String name;
}

class Address {
  const Address({
    required this.id,
    required this.label,
    required this.name,
    required this.phone,
    required this.cityId,
    required this.city,
    required this.address,
    required this.notes,
    required this.isDefault,
  });

  factory Address.fromJson(Map<String, Object?> j) => Address(
    id: intOf(j['id']),
    label: strOrNull(j['label']),
    name: str(j['name']),
    phone: str(j['phone_full'] ?? j['phone']),
    cityId: intOf(j['city_id']),
    city: str(j['city']),
    address: str(j['address']),
    notes: strOrNull(j['notes']),
    isDefault: boolOf(j['is_default']),
  );

  final int id;
  final String? label;
  final String name;
  final String phone;
  final int cityId;
  final String city;
  final String address;
  final String? notes;
  final bool isDefault;
}

/// Form payload for create / update.
class AddressInput {
  const AddressInput({
    required this.name,
    required this.phone,
    required this.cityId,
    required this.address,
    this.label,
    this.notes,
    this.isDefault = false,
  });

  final String name;
  final String phone;
  final int cityId;
  final String address;
  final String? label;
  final String? notes;
  final bool isDefault;

  Map<String, Object?> toJson() => {
    'name': name,
    'phone': phone,
    'city_id': cityId,
    'address': address,
    if (label != null && label!.isNotEmpty) 'label': label,
    if (notes != null && notes!.isNotEmpty) 'notes': notes,
    'is_default': isDefault,
  };
}
