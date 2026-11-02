import 'dart:async';
import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';

typedef Responder = ResponseBody Function(RequestOptions options);

/// Scripted [HttpClientAdapter]: records requests, answers via [responder].
class FakeAdapter implements HttpClientAdapter {
  FakeAdapter(this.responder);

  final Responder responder;
  final List<RequestOptions> requests = [];

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    requests.add(options);
    return responder(options);
  }

  @override
  void close({bool force = false}) {}

  static ResponseBody json(
    Object body, {
    int status = 200,
    Map<String, List<String>> headers = const {},
  }) => ResponseBody.fromString(
    jsonEncode(body),
    status,
    headers: {
      Headers.contentTypeHeader: [Headers.jsonContentType],
      ...headers,
    },
  );
}
