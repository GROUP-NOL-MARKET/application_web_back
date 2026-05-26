<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CoverImage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Configuration\Configuration;

class CoverImageController extends Controller
{
    // Instance Cloudinary réutilisable
    private function uploadApi(): UploadApi
    {
        Configuration::instance([
            'cloud' => [
                'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
                'api_key'    => env('CLOUDINARY_API_KEY'),
                'api_secret' => env('CLOUDINARY_API_SECRET'),
            ],
            'url' => ['secure' => true]
        ]);

        return new UploadApi();
    }

    // Génère l'URL Cloudinary depuis le public_id
    private function cloudinaryUrl(?string $publicId): string
    {
        if (!$publicId) return '';

        $cloudName = env('CLOUDINARY_CLOUD_NAME');
        return "https://res.cloudinary.com/{$cloudName}/image/upload/f_auto,q_auto/{$publicId}";
    }

    // GET /api/admin/cover-images
    public function index()
    {
        if (CoverImage::count() === 0) {
            $defaults = [
                'couv-1.avif',
                'couv-2.avif',
                'couv-3.avif',
            ];

            foreach ($defaults as $file) {
                $localPath = storage_path('app/public/defaults/' . $file);
                $publicId  = null;

                // Upload l'image par défaut sur Cloudinary si le fichier existe
                if (file_exists($localPath)) {
                    try {
                        $cleanName = pathinfo($file, PATHINFO_FILENAME);
                        $result    = $this->uploadApi()->upload($localPath, [
                            'folder'    => 'cover_images',
                            'public_id' => $cleanName,
                        ]);
                        $publicId = $result['public_id'];
                    } catch (\Exception $e) {
                        Log::error("Cloudinary upload failed for {$file}: " . $e->getMessage());
                    }
                }

                CoverImage::create([
                    'path'        => $publicId ?? "defaults/{$file}", // fallback si upload échoue
                    'description' => "Image par défaut",
                    'link'        => "-",
                    'active'      => true,
                ]);
            }
        }

        $images = CoverImage::orderBy('id', 'asc')->get()->map(function ($img) {
            return [
                'id'          => $img->id,
                'description' => $img->description,
                'link'        => $img->link,
                'active'      => $img->active,
                // URL Cloudinary si public_id, sinon URL locale
                'url'         => str_contains($img->path, 'cover_images/')
                    ? $this->cloudinaryUrl($img->path)
                    : asset("storage/{$img->path}"),
            ];
        });

        return response()->json(['data' => $images]);
    }

    // POST /api/admin/cover-images
    public function store(Request $request)
    {
        Log::info('CoverImages.store request files', $request->allFiles());

        $validator = Validator::make($request->all(), [
            'image'       => 'required|file|mimes:jpg,jpeg,png,webp,avif|max:5120',
            'description' => 'nullable|string',
            'link'        => 'required|string',
            'active'      => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid data',
                'errors'  => $validator->errors()
            ], 422);
        }

        if ($request->hasFile('image')) {
            $file      = $request->file('image');
            $cleanName = strtolower(
                preg_replace('/[^a-zA-Z0-9_\-]/', '_',
                    pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)
                )
            ) . '_' . Str::uuid();

            try {
                //  Upload sur Cloudinary
                $result   = $this->uploadApi()->upload($file->getRealPath(), [
                    'folder'    => 'cover_images',
                    'public_id' => $cleanName,
                ]);
                $publicId = $result['public_id'];
            } catch (\Exception $e) {
                Log::error("Cloudinary upload failed: " . $e->getMessage());
                return response()->json(['message' => 'Upload Cloudinary échoué'], 500);
            }

            $ci = CoverImage::create([
                'path'        => $publicId,
                'description' => $request->description,
                'link'        => $request->link,
                'active'      => (bool) $request->active,
            ]);

            return response()->json([
                'message' => 'Uploaded',
                'data'    => [
                    'id'          => $ci->id,
                    'url'         => $this->cloudinaryUrl($ci->path),
                    'description' => $ci->description,
                    'link'        => $ci->link,
                    'active'      => $ci->active,
                ]
            ], 201);
        }

        return response()->json(['message' => 'No file uploaded'], 422);
    }

    // PATCH /api/admin/cover-images/{id}/toggle-active
    public function toggleActive($id)
    {
        $img         = CoverImage::findOrFail($id);
        $img->active = !$img->active;
        $img->save();

        return response()->json([
            'message' => 'Updated',
            'data'    => ['id' => $img->id, 'active' => $img->active]
        ]);
    }

    // DELETE /api/admin/cover-images/{id}
    public function destroy($id)
    {
        $img = CoverImage::findOrFail($id);

        // Supprime sur Cloudinary si c'est un public_id
        if (str_contains($img->path, 'cover_images/')) {
            try {
                $this->uploadApi()->destroy($img->path);
            } catch (\Exception $e) {
                Log::error("Cloudinary delete failed: " . $e->getMessage());
            }
        }

        $img->delete();
        return response()->json(['message' => 'Deleted']);
    }
}