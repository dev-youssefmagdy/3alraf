<?php

namespace App\DTOs;

use App\Http\Requests\Admin\ProductVideoRequest;
use Illuminate\Http\UploadedFile;

final readonly class ProductVideoData
{
    public function __construct(public UploadedFile $video, public ?UploadedFile $poster) {}

    public static function fromRequest(ProductVideoRequest $request): self
    {
        return new self($request->file('video'), $request->file('poster'));
    }
}
