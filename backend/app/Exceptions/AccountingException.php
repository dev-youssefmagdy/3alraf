<?php

namespace App\Exceptions;

use RuntimeException;

/** Business-rule violation in the accounting engine (unbalanced entry, closed period, archived account, ...). */
class AccountingException extends RuntimeException {}
