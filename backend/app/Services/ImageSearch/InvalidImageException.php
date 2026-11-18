<?php

namespace App\Services\ImageSearch;

/** The embedder refused the bytes (not a decodable image, too large): retrying will not help. */
class InvalidImageException extends \RuntimeException {}
