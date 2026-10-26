<?php

namespace App\Services\Ai;

use App\DTOs\Ai\AiProviderData;
use App\Exceptions\AiException;
use App\Models\AiProvider;
use App\Repositories\Contracts\AiProviderRepositoryInterface;

class AiProviderService
{
    public function __construct(private AiProviderRepositoryInterface $providers, private AiManager $manager) {}

    public function create(AiProviderData $d): AiProvider
    {
        return $this->providers->create($this->payload($d));
    }

    public function update(AiProvider $p, AiProviderData $d): AiProvider
    {
        return $this->providers->update($p, $this->payload($d));   // blank key keeps the stored one
    }

    public function delete(AiProvider $p): void
    {
        $p->models()->update(['is_active' => false, 'is_default' => false]);
        $this->providers->delete($p);
    }

    /** Probes the vendor; stores only ok/not-ok + timestamp. */
    public function test(AiProvider $p): bool
    {
        try {
            $ok = $this->manager->forProvider($p)->test();
        } catch (AiException) {
            $ok = false;
        }
        $p->forceFill(['last_tested_at' => now(), 'last_test_ok' => $ok])->saveQuietly();

        return $ok;
    }

    private function payload(AiProviderData $d): array
    {
        $p = ['driver' => $d->driver, 'name' => $d->name, 'base_url' => $d->baseUrl, 'is_active' => $d->isActive];
        if ($d->apiKey !== null) {
            $p['api_key'] = $d->apiKey;
        }

        return $p;
    }
}
