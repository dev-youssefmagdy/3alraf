import '../data/order_models.dart';
import '../data/orders_repository.dart';

/// Asks `payments/return/{token}` until the order is paid (the signed
/// webhook is what marks it - the answer is never authoritative), up to
/// [attempts] times. Returns the last answer; `paid == false` means "still
/// waiting for the gateway confirmation".
Future<PaymentReturn> pollPayment(
  OrdersRepository repo,
  String token, {
  int attempts = 6,
  Duration delay = const Duration(seconds: 2),
  Future<void> Function(Duration)? wait,
}) async {
  final sleep = wait ?? Future<void>.delayed;
  late PaymentReturn last;
  for (var i = 0; i < attempts; i++) {
    last = await repo.paymentReturn(token);
    if (last.paid) return last;
    if (i < attempts - 1) await sleep(delay);
  }
  return last;
}
