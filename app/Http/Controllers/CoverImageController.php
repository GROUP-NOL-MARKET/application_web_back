<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CoverImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class CoverImageController extends Controller
{
    // GET /api/admin/cover-images
    public function index()
    {

         if (CoverImage::count() === 0) {
        $defaults = [
            'carousel_1.webp',
            'carousel_2.avif',
            'carousel_3.avif',
        ];

        foreach ($defaults as $file) {
            CoverImage::create([
                'path' => "defaults/$file",
                'description' => "Image par défaut",
                'active' => true,
            ]);
        }
    }
        
        $images = CoverImage::orderBy('id', 'asc')->get()->map(function ($img) {
            return [
                'id' => $img->id,
                'description' => $img->description,
                'active' => $img->active,
                'url' => asset("storage/{$img->path}"),
            ];
        });

        return response()->json(['data' => $images]);
    }

    // POST /api/admin/cover-images
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|max:5120',
            'description' => 'nullable|string',
            'active' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Invalid data', 'errors' => $validator->errors()], 422);
        }

        if ($request->hasFile('image')) {

            $file = $request->file('image');

            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('cover_images', $filename, 'public');

            $ci = CoverImage::create([
                'path' => $path,
                'description' => $request->description,
                'active' => (bool) $request->active,
            ]);

            return response()->json([
                'message' => 'Uploaded',
                'data' => [
                    'id' => $ci->id,
                    'url' => asset("storage/{$ci->path}"),
                    'description' => $ci->description,
                    'active' => $ci->active,
                ]
            ], 201);
        }

        return response()->json(['message' => 'No file uploaded'], 422);
    }

    // PATCH /api/admin/cover-images/{id}/toggle-active
    public function toggleActive($id)
    {
        $img = CoverImage::findOrFail($id);
        $img->active = !$img->active;
        $img->save();

        return response()->json([
            'message' => 'Updated',
            'data' => [
                'id' => $img->id,
                'active' => $img->active
            ]
        ]);
    }

    // DELETE /api/admin/cover-images/{id}
    public function destroy($id)
    {
        $img = CoverImage::findOrFail($id);

        Storage::disk('public')->delete($img->path);
        $img->delete();

        return response()->json(['message' => 'Deleted']);
    }
}


