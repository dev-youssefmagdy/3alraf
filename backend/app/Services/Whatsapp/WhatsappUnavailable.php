<?php

namespace App\Services\Whatsapp;

/** The WhatsApp session cannot send right now (disabled, not connected, or the Node service is down). */
class WhatsappUnavailable extends \RuntimeException {}
