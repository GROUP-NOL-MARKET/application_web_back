<?php
namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Api\Admin\AdminApi;
use Cloudinary\Configuration\Configuration;

class MigrateImagesToCloudinary extends Command
{
    protected $signature   = 'images:migrate';
    protected $description = 'Migre les images locales vers Cloudinary';

    public function handle()
    {
        $this->info("Initialisation Cloudinary...");

        $cloudName = env('CLOUDINARY_CLOUD_NAME');
        $apiKey    = env('CLOUDINARY_API_KEY');
        $apiSecret = env('CLOUDINARY_API_SECRET');

        $this->info("   CLOUD_NAME : " . ($cloudName  ?: 'NON DÉFINI'));
        $this->info("   API_KEY    : " . ($apiKey     ? 'OK' : 'NON DÉFINI'));
        $this->info("   API_SECRET : " . ($apiSecret  ? 'OK' : 'NON DÉFINI'));

        if (!$cloudName || !$apiKey || !$apiSecret) {
            $this->error("Configuration incomplète. Arrêt.");
            return;
        }

        Configuration::instance([
            'cloud' => [
                'cloud_name' => $cloudName,
                'api_key'    => $apiKey,
                'api_secret' => $apiSecret,
            ],
            'url' => ['secure' => true]
        ]);

        $uploadApi = new UploadApi();
        $adminApi  = new AdminApi();

        $this->newLine();

        $products = Product::whereNotNull('image')
            ->where('image', 'not like', 'products/%')
            ->get();

        $this->info("{$products->count()} produits à traiter...");
        $this->newLine();

        $success  = 0;
        $skipped  = 0;
        $failed   = 0;

        foreach ($products as $product) {
            $filename  = basename(urldecode($product->image));
            $localPath = storage_path('app/public/products/' . $filename);

            $cleanName = strtolower(
                preg_replace('/[^a-zA-Z0-9_\-]/', '_',
                    preg_replace('/\s+/', '_',
                        pathinfo($filename, PATHINFO_FILENAME)
                    )
                )
            );

            $publicId = 'products/' . $cleanName;

            $this->line("────────────────────────────────────────────");
            $this->line("Produit   : {$product->name}");
            $this->line("Public ID : {$publicId}");

            //Vérifie si l'image existe déjà sur Cloudinary
            $existsOnCloudinary = false;
            try {
                $adminApi->asset($publicId);
                $existsOnCloudinary = true;
            } catch (\Exception $e) {
                $existsOnCloudinary = false;
            }

            if ($existsOnCloudinary) {
                // Image déjà sur Cloudinary — on met juste à jour la BDD
                $this->warn("Déjà sur Cloudinary, mise à jour BDD uniquement");
                $product->update(['image' => $publicId]);
                $skipped++;
                continue;
            }

            //  Image absente sur Cloudinary — on vérifie le fichier local
            $this->line("Fichier local existe ? : " . (file_exists($localPath) ? 'OUI' : '❌ NON'));

            if (!file_exists($localPath)) {
                $this->warn("Fichier introuvable localement, on passe.");
                $failed++;
                continue;
            }

            //  Upload uniquement si absent
            try {
                $this->line(" Upload en cours...");

                $result = $uploadApi->upload($localPath, [
                    'folder'    => 'products',
                    'public_id' => $cleanName,
                ]);

                $publicId = $result['public_id'] ?? null;

                if (!$publicId) {
                    throw new \Exception("public_id absent dans la réponse");
                }

                $product->save(['image' => $publicId]);
                $this->info("Uploadé → {$publicId}");
                $success++;

            } catch (\Exception $e) {
                $this->error("Erreur   : " . $e->getMessage());
                $this->error("Ligne   : " . $e->getLine());
                $failed++;
            }

            $this->newLine();
        }

        $this->line("════════════════════════════════════════════");
        $this->info("Uploadés  : {$success}");
        $this->warn("Ignorés   : {$skipped} (déjà sur Cloudinary)");
        $this->error("Échoués   : {$failed}");
        $this->info("Migration terminée !");
    }
}