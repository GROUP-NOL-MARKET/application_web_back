<?php
namespace App\Console\Commands;

use App\Models\CoverImage;
use Illuminate\Console\Command;
use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Configuration\Configuration;

class MigrateCoverImagesToCloudinary extends Command
{
    protected $signature   = 'cover-images:migrate';
    protected $description = 'Migre les cover images locales vers Cloudinary';

    public function handle()
    {
        Configuration::instance([
            'cloud' => [
                'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
                'api_key'    => env('CLOUDINARY_API_KEY'),
                'api_secret' => env('CLOUDINARY_API_SECRET'),
            ],
            'url' => ['secure' => true]
        ]);

        $uploadApi = new UploadApi();

        // Récupère les images pas encore sur Cloudinary
        $images = CoverImage::where('path', 'not like', 'cover_images/%')->get();

        $this->info(" {$images->count()} cover images à migrer...");

        $success = 0;
        $failed  = 0;

        foreach ($images as $img) {
            $filename  = basename($img->path);
            $localPath = storage_path('app/public/' . $img->path);

            $this->line("────────────────────────────────────");
            $this->line(" ID      : {$img->id}");
            $this->line(" Fichier : {$filename}");
            $this->line(" Existe ?: " . (file_exists($localPath) ? 'OUI' : ' NON'));

            if (!file_exists($localPath)) {
                $this->warn("  Introuvable, on passe.");
                $failed++;
                continue;
            }

            try {
                $cleanName = strtolower(
                    preg_replace('/[^a-zA-Z0-9_\-]/', '_',
                        pathinfo($filename, PATHINFO_FILENAME)
                    )
                );

                $result   = $uploadApi->upload($localPath, [
                    'folder'    => 'cover_images',
                    'public_id' => $cleanName,
                ]);

                $publicId = $result['public_id'] ?? null;

                if (!$publicId) {
                    throw new \Exception("public_id absent");
                }

                $img->path = $publicId;
                $img->save();

                $this->info(" Migré → {$publicId}");
                $success++;

            } catch (\Exception $e) {
                $this->error(" Erreur : " . $e->getMessage());
                $failed++;
            }
        }

        $this->line("════════════════════════════════════");
        $this->info("Succès : {$success}");
        $this->warn("Échecs : {$failed}");
        $this->info("Migration terminée !");
    }
}