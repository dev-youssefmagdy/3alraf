<?php

namespace App\Support\Casting;

use App\Models\ModelPosePreset;

/** System pose / camera presets (idempotent seed: existing rows, including marketer edits, are never overwritten). */
final class PosePresetCatalog
{
    /** @return array<string,list<array{0:string,1:string,2:string,3:string}>> type => [key, ar, en, prompt fragment] */
    public static function all(): array
    {
        return [
            'pose' => [
                ['front_portrait', 'بورتريه أمامي', 'Front portrait', 'front-facing head and shoulders portrait, looking straight at the camera'],
                ['three_quarter_portrait', 'بورتريه ٣/٤', 'Three-quarter portrait', 'three-quarter view head and shoulders portrait'],
                ['side_portrait', 'بورتريه جانبي', 'Side portrait', 'side profile portrait'],
                ['full_body', 'لقطة كاملة للجسم', 'Full body', 'full body standing pose, head to toe visible'],
                ['sitting', 'جالس', 'Sitting', 'sitting relaxed on a chair'],
                ['holding_product', 'ماسك المنتج', 'Holding product', 'holding the product naturally in both hands at chest height'],
                ['using_product', 'يستخدم المنتج', 'Using product', 'naturally using the product in a real-life moment'],
                ['looking_at_camera', 'ينظر للكاميرا', 'Looking at camera', 'looking directly at the camera with a warm natural expression'],
                ['looking_at_product', 'ينظر للمنتج', 'Looking at product', 'looking down at the product with interest'],
                ['walking', 'يمشي', 'Walking', 'walking naturally, candid mid-step'],
                ['close_up', 'كلوز أب', 'Close-up', 'tight close-up of the face'],
                ['beauty_close_up', 'كلوز أب بيوتي', 'Beauty close-up', 'beauty close-up, flawless natural skin, soft glow'],
            ],
            'camera' => [
                ['close_up', 'كلوز أب', 'Close-up', 'close-up shot'],
                ['medium', 'لقطة متوسطة', 'Medium shot', 'medium shot, waist up'],
                ['full_body', 'لقطة كاملة', 'Full-body shot', 'full-body wide shot'],
                ['eye_level', 'مستوى العين', 'Eye level', 'eye-level camera'],
                ['low_angle', 'زاوية منخفضة', 'Low angle', 'low-angle camera looking slightly up'],
                ['high_angle', 'زاوية عالية', 'High angle', 'high-angle camera looking slightly down'],
                ['three_quarter', 'زاوية ٣/٤', 'Three-quarter angle', 'three-quarter camera angle'],
                ['product_focused', 'تركيز على المنتج', 'Product focused', 'camera focused on the product with the person softly blurred'],
            ],
            // Video Studio: camera movement, product movement, scene animation (prompt fragments for image-to-video)
            'motion' => [
                ['static_hold', 'كاميرا ثابتة', 'Static camera', 'locked-off static camera, no camera movement'],
                ['slow_push_in', 'تقريب بطيء', 'Slow push-in', 'slow smooth camera push-in toward the subject'],
                ['slow_pull_out', 'ابتعاد بطيء', 'Slow pull-out', 'slow smooth camera pull-out revealing more of the scene'],
                ['orbit_left', 'دوران لليسار', 'Orbit left', 'smooth slow orbiting camera arc to the left around the subject'],
                ['orbit_right', 'دوران لليمين', 'Orbit right', 'smooth slow orbiting camera arc to the right around the subject'],
                ['pan_left', 'بان لليسار', 'Pan left', 'gentle camera pan to the left'],
                ['pan_right', 'بان لليمين', 'Pan right', 'gentle camera pan to the right'],
                ['tilt_up', 'ميل لأعلى', 'Tilt up', 'gentle camera tilt upward'],
                ['handheld_subtle', 'كاميرا محمولة خفيفة', 'Subtle handheld', 'very subtle natural handheld camera sway'],
            ],
            'pmove' => [
                ['product_still', 'المنتج ثابت', 'Product stays still', 'the product stays perfectly still and unchanged'],
                ['gentle_rotate', 'دوران بسيط للمنتج', 'Gentle product turn', 'the product turns very slowly to show its form, shape and colour never change'],
                ['hold_and_present', 'عرض المنتج', 'Present the product', 'the person lifts the product slightly toward the camera and presents it'],
                ['hero_reveal', 'ظهور بطولي', 'Hero reveal', 'light sweeps across the product to reveal it, the product itself does not change'],
            ],
            'scene' => [
                ['ambient_light', 'ضوء متحرك', 'Shifting light', 'soft light slowly shifting across the scene'],
                ['soft_breeze', 'نسيم خفيف', 'Soft breeze', 'a soft breeze gently moves hair and fabric'],
                ['sparkle', 'لمعان', 'Light sparkle', 'subtle sparkles of light in the air'],
                ['bokeh_drift', 'بوكيه متحرك', 'Drifting bokeh', 'soft bokeh lights drifting slowly in the background'],
            ],
        ];
    }

    public static function seed(): void
    {
        foreach (self::all() as $type => $rows) {
            foreach ($rows as $i => [$key, $ar, $en, $fragment]) {
                ModelPosePreset::query()->firstOrCreate(['type' => $type, 'key' => $key],
                    ['label' => ['ar' => $ar, 'en' => $en], 'prompt_fragment' => $fragment, 'is_system' => true, 'sort_order' => $i]);
            }
        }
    }
}
