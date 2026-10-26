<?php

namespace App\Services\Ai;

use App\Models\AiModel;
use App\Services\Ai\Contracts\AiDriver;
use App\Services\Ai\Contracts\AiTool;
use App\Services\Ai\Support\AiRequest;
use App\Services\Ai\Support\AiResponse;

/** Normalised tool loop: model -> tools -> model ... (max rounds, token + time budget). Tool output is capped and labelled as data. */
class AiRunner
{
    public const MAX_ROUNDS = 6;

    /**
     * @param  array<int,AiTool>  $tools
     * @param  array<int,array>  $trace  filled with executed tool names (UI chips)
     */
    public function run(AiDriver $driver, AiModel $model, AiRequest $req, array $tools, AiContext $ctx, ?array &$trace = null, int $tokenBudget = 60000, int $timeBudget = 90): AiResponse
    {
        $trace = [];
        $byName = [];
        foreach ($tools as $t) {
            $byName[$t->name()] = $t;
        }
        $req->tools = array_map(fn (AiTool $t) => ['name' => $t->name(), 'description' => $t->description(), 'schema' => $t->schema()], array_values($byName));
        $total = new AiResponse;
        $start = microtime(true);

        for ($round = 0; $round <= self::MAX_ROUNDS; $round++) {
            $res = $driver->chat($req);
            $total->tokensIn += $res->tokensIn;
            $total->tokensOut += $res->tokensOut;
            $total->text = $res->text;
            $total->stopReason = $res->stopReason;
            if (! $res->wantsTools()) {
                return $total;
            }
            if ($round === self::MAX_ROUNDS || $total->tokensIn + $total->tokensOut > $tokenBudget || microtime(true) - $start > $timeBudget) {
                $total->stopReason = 'budget';

                return $total;
            }
            $req->messages[] = ['role' => 'assistant', 'content' => $res->text, 'tool_calls' => $res->toolCalls];
            foreach ($res->toolCalls as $c) {
                $trace[] = $c['name'];
                $req->messages[] = ['role' => 'tool', 'tool_call_id' => $c['id'], 'content' => $this->exec($byName[$c['name']] ?? null, $c['arguments'], $ctx)];
            }
        }

        return $total;
    }

    private function exec(?AiTool $tool, array $args, AiContext $ctx): string
    {
        if (! $tool) {
            return json_encode(['error' => 'unknown_tool']);
        }
        try {
            $out = json_encode(['data' => $tool->execute($args, $ctx)], JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        } catch (\Throwable $e) {
            return json_encode(['error' => 'tool_failed']);
        }
        $max = (int) config('ai.tool_result_chars', 12000);

        return mb_strlen($out) > $max ? json_encode(['truncated' => true, 'data' => mb_substr($out, 0, $max)]) : $out;
    }
}
