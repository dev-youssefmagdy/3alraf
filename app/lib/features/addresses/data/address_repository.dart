import 'package:dio/dio.dart';

import '../../../core/error/api_exception.dart';
import '../../../core/network/json.dart';
import 'address_models.dart';

class AddressRepository {
  const AddressRepository(this._dio);

  final Dio _dio;

  Future<T> _guard<T>(Future<T> Function() run) async {
    try {
      return await run();
    } on DioException catch (e) {
      throw ApiException.from(e);
    }
  }

  Future<List<City>> cities() => _guard(() async {
    final r = await _dio.get<Object?>('/checkout/cities');
    return asMapList(r.data).map(City.fromJson).toList();
  });

  Future<List<Address>> list() => _guard(() async {
    final r = await _dio.get<Object?>('/addresses');
    return asMapList(r.data).map(Address.fromJson).toList();
  });

  Future<Address> create(AddressInput input) => _guard(() async {
    final r = await _dio.post<Object?>('/addresses', data: input.toJson());
    return Address.fromJson(asMap(r.data));
  });

  Future<Address> update(int id, AddressInput input) => _guard(() async {
    final r = await _dio.put<Object?>('/addresses/$id', data: input.toJson());
    return Address.fromJson(asMap(r.data));
  });

  Future<void> delete(int id) =>
      _guard(() async => _dio.delete<Object?>('/addresses/$id'));
}
