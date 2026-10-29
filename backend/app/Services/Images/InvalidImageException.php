<?php

namespace App\Services\Images;

use RuntimeException;

/** The input is not a safe, supported raster image (corrupt, wrong type, animated, too large). */
class InvalidImageException extends RuntimeException {}
