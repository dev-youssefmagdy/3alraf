<?php

namespace App\DTOs;

use App\Http\Requests\Admin\ImageSearchRequest;

/** One image-search query. $bytes = the uploaded photo, read from PHP's temp upload and never written anywhere else. */
final readonly class ImageSearchQueryData
{
    public function __construct(public string $bytes, public string $filename, public string $type = 'all', public int $limit = 24) {}

    public static function fromRequest(ImageSearchRequest $request): self
    {
        $file = $request->file('image');

        return new self((string) file_get_contents($file->getRealPath()), 'query.'.($file->guessExtension() ?: 'img'),
            (string) $request->validated('type', 'all'), (int) config('image_search.results', 24));
    }
}
