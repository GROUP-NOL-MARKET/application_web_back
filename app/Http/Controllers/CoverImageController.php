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
    // GET /api/cover-images
    public function index()
    {
        // retourne toutes images (ou uniquement actives si query ?active=1)
        $images = CoverImage::orderBy('order', 'asc')->get()->map(function ($img) {
            return [
                'id' => $img->id,
                'description' => $img->description,
                'active' => $img->active,
                'order' => $img->order,
                'url' => asset("storage/{$img->path}"),
            ];
        });

        return response()->json(['data' => $images]);
    }

    // POST /api/cover-images
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'images.*' => 'required|image|max:5120', // max 5MB
            'description' => 'nullable|string',
            'active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Invalid data', 'errors' => $validator->errors()], 422);
        }

        $uploaded = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $filename = (string) Str::uuid() . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('cover_images', $filename, 'public'); // storage/app/public/cover_images/...
                $ci = CoverImage::create([
                    'path' => $path,
                    'description' => $request->input('description'),
                    'active' => (bool) $request->input('active', false),
                ]);

                $uploaded[] = [
                    'id' => $ci->id,
                    'url' => asset("storage/{$ci->path}"),
                    'description' => $ci->description,
                    'active' => $ci->active,
                ];
            }
        }

        return response()->json(['message' => 'Uploaded', 'data' => $uploaded], 201);
    }

    // PATCH /api/cover-images/{id}/toggle-active
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

    // DELETE /api/cover-images/{id}
    public function destroy($id)
    {
        $img = CoverImage::findOrFail($id);
        // delete file
        Storage::disk('public')->delete($img->path);
        $img->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
