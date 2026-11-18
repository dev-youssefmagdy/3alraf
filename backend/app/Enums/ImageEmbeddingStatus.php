<?php

namespace App\Enums;

/** pending = queued (a job will embed it), ready = vector stored, failed = the last job gave up (error says why). */
enum ImageEmbeddingStatus: string
{
    case PENDING = 'pending';
    case READY = 'ready';
    case FAILED = 'failed';
}
