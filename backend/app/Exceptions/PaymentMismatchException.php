<?php

namespace App\Exceptions;

/** A verified webhook disagrees with OUR order (amount, currency, status or reference): reject, settle nothing. */
class PaymentMismatchException extends PaymentException {}
