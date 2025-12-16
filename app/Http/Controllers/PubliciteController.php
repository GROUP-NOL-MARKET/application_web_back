<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Publicite;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class PubliciteController extends Controller
{
    // GET /api/admin/cover-images
    public function index()
    {

        if (Publicite::count() === 0) {
            $defaults = [
                'Couv-1.avif',
                'Couv-2.avif',
                'Couv-3.avif',
            ];

            foreach ($defaults as $file) {
                Publicite::create([
                    'path' => "defaults/$file",
                    'active' => true,
                ]);
            }
        }

        $images = Publicite::orderBy('id', 'asc')->get()->map(function ($img) {
            return [
                'id' => $img->id,
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
            'image' => 'required|file|mimes:jpg,jpeg,png,webp,avif|max:5120',
            'active' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Invalid data', 'errors' => $validator->errors()], 422);
        }

        if ($request->hasFile('image')) {
            $file = $request->file('image');

            $filename = (string) Str::uuid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('publicite', $filename, 'public');

            $ci = Publicite::create([
                'path' => $path,
                'active' => (bool) $request->active,
            ]);

            return response()->json([
                'message' => 'Uploaded',
                'data' => [
                    'id' => $ci->id,
                    'url' => asset("storage/{$ci->path}"),
                    'active' => $ci->active,
                ]
            ], 201);
        }

        return response()->json(['message' => 'No file uploaded'], 422);
    }
    // PATCH /api/admin/cover-images/{id}/toggle-active
    public function toggleActive($id)
    {
        $img = Publicite::findOrFail($id);
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
        $img = Publicite::findOrFail($id);

        Storage::disk('public')->delete($img->path);
        $img->delete();

        return response()->json(['message' => 'Deleted']);
    }
}


