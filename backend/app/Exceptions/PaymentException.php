<?php

namespace App\Exceptions;

use RuntimeException;

/** Base for payment gateway problems. */
class PaymentException extends RuntimeException {}
