<?php

namespace App\Http\Controllers\Admin;

use App\DTOs\ImagePickerImportData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImagePickerImportRequest;
use App\Http\Requests\Admin\ImagePickerSearchRequest;
use App\Models\Product;
use App\Services\ImagePicker\ImagePickerService;
use App\Services\ImagePicker\ImagePickerUnavailableException;
use Illuminate\Http\JsonResponse;

/** Product edit → "Find images": free web image search (tools/image-picker) and import of the picture the admin chose. */
class ImagePickerController extends Controller
{
    public function __construct(private ImagePickerService $picker) {}

    public function search(ImagePickerSearchRequest $request, Product $product): JsonResponse
    {
        try {
            $data = $this->picker->search((string) $request->validated('query'), (int) ($request->validated('page') ?? 1));
        } catch (ImagePickerUnavailableException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 503);
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function import(ImagePickerImportRequest $request, Product $product): JsonResponse
    {
        $d = ImagePickerImportData::fromRequest($request);
        $image = $this->picker->import($product, $d, $request->user('admin')?->id);

        return $this->ok(__($d->as === 'main' ? ($d->variantId ? 'admin.image_picker.imported_variant' : 'admin.image_picker.imported_main') : 'admin.image_picker.imported_gallery'),
            null, ['image' => ['id' => $image->id, 'thumb' => $image->thumb_url]]);
    }
}
