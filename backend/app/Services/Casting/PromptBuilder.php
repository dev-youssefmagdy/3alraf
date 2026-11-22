<?php

namespace App\Services\Casting;

use App\Models\CastingCandidate;
use App\Models\ModelPosePreset;
use App\Models\VirtualModel;

/** Builds prompts. The frozen identity block is reused verbatim for every image of a model (character consistency by prompt + seed). */
class PromptBuilder
{
    private const QUALITY = 'professional advertising photograph, natural skin texture, realistic proportions, sharp focus, soft studio lighting, no text, no watermark, no logo';

    public function identity(array|CastingCandidate|VirtualModel $m): string
    {
        $g = fn ($k) => is_array($m) ? ($m[$k] ?? null) : $m->{$k};
        $gender = $g('gender') === 'male' ? 'man' : 'woman';
        $parts = ['Egyptian '.$gender, $g('age_range') ? 'aged '.$g('age_range') : null, $g('appearance'), $g('hairstyle')];

        return implode(', ', array_filter($parts));
    }

    public function portrait(CastingCandidate $c): string
    {
        $clothing = $c->clothing ? ', wearing '.$c->clothing : '';

        return 'Head and shoulders portrait of '.$this->identity($c).$clothing.', '.$c->personality.' expression, plain soft neutral backdrop, '.self::QUALITY;
    }

    /** @param array{scene?:string,outfit?:string,background?:string,pose?:string,camera?:string,concept?:string,format?:string} $p */
    public function ad(VirtualModel $model, string $productName, string $productDescription, array $p): string
    {
        $pose = ModelPosePreset::query()->where('type', 'pose')->where('key', $p['pose'] ?? '')->value('prompt_fragment');
        $cam = ModelPosePreset::query()->where('type', 'camera')->where('key', $p['camera'] ?? '')->value('prompt_fragment');
        $bits = [
            $model->identity_prompt ?: $this->identity($model),
            ! empty($p['outfit']) ? 'wearing '.$p['outfit'] : ($model->clothing_style ? 'wearing '.$model->clothing_style : null),
            $pose, $cam,
            ! empty($p['scene']) ? 'scene: '.$p['scene'] : null,
            ! empty($p['background']) ? 'background: '.$p['background'] : null,
            ! empty($p['concept']) ? 'advertising concept: '.$p['concept'] : null,
            'advertising the product "'.$productName.'"'.($productDescription !== '' ? ' ('.mb_substr($productDescription, 0, 160).')' : ''),
            'the product must keep its exact real shape, colours, label and packaging; do not invent logos, text or extra features',
            self::QUALITY,
        ];

        return implode(', ', array_filter($bits));
    }
}
