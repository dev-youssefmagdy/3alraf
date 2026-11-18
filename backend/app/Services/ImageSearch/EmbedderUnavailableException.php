<?php

namespace App\Services\ImageSearch;

/** The embedder service did not answer (down, timeout, 5xx, wrong model): retry later, never lose data. */
class EmbedderUnavailableException extends \RuntimeException {}
