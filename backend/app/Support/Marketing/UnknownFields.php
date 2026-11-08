<?php

namespace App\Support\Marketing;

use Illuminate\Support\Arr;

/**
 * Strict input: every submitted key must match a validation-rule key (numeric list indexes generalised to `*`). Used by the suggestion write API
 * so a client can never smuggle fields (raw SQL, table names, paths, URLs) through keys the schema does not declare.
 * $free = rule keys whose whole subtree is accepted (size-limited elsewhere), e.g. `source_data`.
 */
final class UnknownFields
{
    /** @return list<string> offending keys (dot notation as sent) */
    public static function find(array $input, array $ruleKeys, array $free = []): array
    {
        $allowed = array_flip($ruleKeys);
        $bad = [];
        foreach (Arr::dot($input) as $key => $v) {
            $key = (string) $key;
            $general = implode('.', array_map(fn ($seg) => ctype_digit((string) $seg) ? '*' : $seg, explode('.', $key)));
            if (isset($allowed[$general])) {
                continue;
            }
            $ok = false;
            foreach ($free as $f) {
                if ($general === $f || str_starts_with($general, $f.'.')) {
                    $ok = true;
                    break;
                }
            }
            $ok || $bad[] = $key;
        }

        return $bad;
    }
}
