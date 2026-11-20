<?php

namespace App\Services\ImagePicker;

use RuntimeException;

/** The picker script could not run or gave no usable answer (not installed, timed out, the engines refused). */
class ImagePickerUnavailableException extends RuntimeException {}
