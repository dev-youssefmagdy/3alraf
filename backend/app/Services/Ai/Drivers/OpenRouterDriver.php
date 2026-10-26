<?php

namespace App\Services\Ai\Drivers;

use App\Exceptions\AiException;
use App\Services\Ai\Support\AiResponse;

/**
 * OpenRouter: OpenAI-compatible chat + tools, a dedicated /images API, and async /videos jobs.
 * Model ids are vendor-prefixed slugs (e.g. "google/gemma-4-31b-it:free").
 */
class OpenRouterDriver extends OpenAiDriver
{
    protected function defaultBaseUrl(): string
    {
        return 'https://openrouter.ai/api/v1';
    }

    protected function headers(): array
    {
        return parent::headers() + ['HTTP-Referer' => (string) config('app.url'), 'X-Title' => (string) config('app.name')];
    }

    /** /models is public, so probe the authenticated /key endpoint to actually validate the credential. */
    public function test(): bool
    {
        return $this->call(fn () => $this->http()->get($this->base().'/key'))->successful();
    }

    public function image(string $model, string $prompt, array $options = []): AiResponse
    {
        $body = ['model' => $model, 'prompt' => $prompt, 'n' => (int) ($options['n'] ?? 1)]
            + array_filter(['size' => $options['size'] ?? null, 'aspect_ratio' => $options['aspect_ratio'] ?? null, 'quality' => $options['quality'] ?? null]);
        $res = $this->call(fn () => $this->http()->post($this->base().'/images', $body));

        // models that only emit images through chat completions are not served by /images
        if (in_array($res->status(), [400, 404, 405], true)) {
            return $this->imageViaChat($model, $prompt);
        }

        $d = $this->check($res);
        $media = array_map(fn ($i) => isset($i['b64_json']) ? ['b64' => $i['b64_json']] : ['url' => $i['url'] ?? null], $d['data'] ?? []);

        return new AiResponse(media: $media, images: count($media), tokensIn: (int) ($d['usage']['prompt_tokens'] ?? 0), tokensOut: (int) ($d['usage']['completion_tokens'] ?? 0));
    }

    private function imageViaChat(string $model, string $prompt): AiResponse
    {
        $d = $this->check($this->call(fn () => $this->http()->post($this->base().'/chat/completions', [
            'model' => $model, 'modalities' => ['image', 'text'], 'messages' => [['role' => 'user', 'content' => $prompt]],
        ])));
        $media = [];
        foreach ($d['choices'][0]['message']['images'] ?? [] as $img) {
            $url = (string) ($img['image_url']['url'] ?? '');
            $media[] = str_starts_with($url, 'data:') ? ['b64' => substr($url, (int) strpos($url, ',') + 1)] : ['url' => $url];
        }

        return new AiResponse(media: $media, images: count($media), tokensIn: (int) ($d['usage']['prompt_tokens'] ?? 0), tokensOut: (int) ($d['usage']['completion_tokens'] ?? 0));
    }

    /** Submits a job and blocks (bounded) until it completes; prefers the signed URL, else downloads the bytes. */
    public function video(string $model, string $prompt, array $options = []): AiResponse
    {
        $body = ['model' => $model, 'prompt' => $prompt]
            + array_filter(['duration' => $options['duration'] ?? null, 'resolution' => $options['resolution'] ?? null, 'aspect_ratio' => $options['aspect_ratio'] ?? null], fn ($v) => $v !== null);
        $job = $this->check($this->call(fn () => $this->http()->post($this->base().'/videos', $body)));
        $id = $job['id'] ?? null;
        if (! $id) {
            throw AiException::provider();
        }

        $deadline = time() + (int) config('ai.video_poll_timeout', 280);
        while (true) {
            $s = $this->check($this->call(fn () => $this->http()->get($this->base().'/videos/'.$id)));
            $status = $s['status'] ?? 'pending';
            if ($status === 'completed') {
                break;
            }
            if ($status === 'failed' || time() >= $deadline) {
                \Illuminate\Support\Facades\Log::warning('OpenRouter video job not completed', ['provider' => $this->provider->getKey(), 'job' => $id, 'status' => $status, 'error' => $s['error'] ?? null]);
                throw AiException::provider();
            }
            sleep((int) config('ai.video_poll_interval', 5));
        }

        if ($signed = ($s['urls'][0] ?? null)) {
            $media = [['url' => $signed]];
        } else {
            $file = $this->call(fn () => $this->http()->get($this->base().'/videos/'.$id.'/content', ['index' => 0]));
            if ($file->failed()) {
                throw AiException::provider();
            }
            $media = [['b64' => base64_encode($file->body())]];
        }

        return new AiResponse(media: $media, videoSecs: (int) ($options['duration'] ?? 0));
    }

    public function supports(string $capability): bool
    {
        return in_array($capability, ['chat', 'tools', 'image', 'video', 'json_mode'], true);
    }
}
