<?php

namespace App\Repositories\Contracts;

use App\Models\AiSetting;

interface AiSettingRepositoryInterface extends RepositoryInterface
{
    /** The single AI settings row (unsaved new instance when none exists yet). */
    public function current(): AiSetting;

    public function save(array $data): AiSetting;
}
