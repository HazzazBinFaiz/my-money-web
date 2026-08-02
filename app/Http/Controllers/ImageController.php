<?php

namespace App\Http\Controllers;

use App\Enums\ImageType;
use App\Http\Requests\StoreImageRequest;
use App\Models\Image;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImageController extends Controller
{
    /**
     * Images the current user may pick from: their own plus the shared ones.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'integer', Rule::enum(ImageType::class)],
        ]);

        $images = Image::ofType(ImageType::from((int) $validated['type']))
            ->latest('id')
            ->get()
            ->map(fn (Image $image) => [
                'id' => $image->id,
                'url' => $image->url,
                'editable' => $image->isEditableBy($request->user()),
            ]);

        return response()->json(['data' => $images]);
    }

    public function store(StoreImageRequest $request): JsonResponse
    {
        $name = Str::uuid()->toString().'.png';

        $request->file('image')->storeAs('images', $name, 'local');

        $image = Image::create([
            'type' => ImageType::from((int) $request->validated('type')),
            'image_name' => $name,
        ]);

        return response()->json([
            'data' => [
                'id' => $image->id,
                'url' => $image->url,
                'editable' => true,
            ],
        ], 201);
    }

    /**
     * Streams the file. Route model binding already limits this to
     * images the user owns or shared ones.
     */
    public function show(Image $image): BinaryFileResponse
    {
        abort_unless(Storage::disk('local')->exists($image->path()), 404);

        return response()->file(Storage::disk('local')->path($image->path()), [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, max-age=31536000',
        ]);
    }

    public function destroy(Request $request, Image $image)
    {
        abort_unless($image->isEditableBy($request->user()), 403);

        Storage::disk('local')->delete($image->path());
        $image->delete();

        return response()->json(['deleted' => true]);
    }
}
