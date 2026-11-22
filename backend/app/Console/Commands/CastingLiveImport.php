<?php

namespace App\Console\Commands;

use App\Services\Casting\LiveProductImporter;
use Illuminate\Console\Command;

class CastingLiveImport extends Command
{
    protected $signature = 'casting:live-import {file : JSON file holding the store MCP get_product_details result} {--image=0 : index of the photo to use (0 = first)}';

    protected $description = 'Import a read-only snapshot of a live 3alraf.com product (from the store MCP) for the AI Casting Studio';

    public function handle(LiveProductImporter $importer): int
    {
        $raw = (string) @file_get_contents((string) $this->argument('file'));
        $json = json_decode($raw, true);
        if (! is_array($json) && ($a = strpos($raw, '{')) !== false && ($b = strrpos($raw, '}')) !== false) {
            $json = json_decode(substr($raw, $a, $b - $a + 1), true);   // the MCP envelope may be wrapped in a security notice
        }
        if (! is_array($json)) {
            $this->error('The file does not contain JSON.');

            return self::FAILURE;
        }
        try {
            $p = $importer->import($json, (int) $this->option('image'));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info("Imported live product #{$p->live_id} \"{$p->displayName('en')}\" as casting live product {$p->id}".($p->image_path ? ' (photo saved).' : ' (no photo).'));

        return self::SUCCESS;
    }
}
