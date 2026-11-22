<?php

use App\Support\Casting\PosePresetCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('model_pose_presets')) {
            PosePresetCatalog::seed();
        }
    }

    public function down(): void {}
};
