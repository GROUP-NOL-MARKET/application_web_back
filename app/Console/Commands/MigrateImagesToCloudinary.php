<?php
namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Configuration\Configuration;

class MigrateImagesToCloudinary extends Command
{
    protected $signature   = 'images:migrate';
    protected $description = 'Migre les images locales vers Cloudinary';

    public function handle()
    {
        // Vérifier la config Cloudinary avant tout
        $this->info("🔧 Vérification config Cloudinary...");

        $cloudName = env('CLOUDINARY_CLOUD_NAME');
        $apiKey    = env('CLOUDINARY_API_KEY');
        $apiSecret = env('CLOUDINARY_API_SECRET');

        $this->info("   CLOUD_NAME : " . ($cloudName  ?: ' NON DÉFINI'));
        $this->info("   API_KEY    : " . ($apiKey     ? ' OK' : ' NON DÉFINI'));
        $this->info("   API_SECRET : " . ($apiSecret  ? ' OK' : ' NON DÉFINI'));

        if (!$cloudName || !$apiKey || !$apiSecret) {
            $this->error(" Configuration Cloudinary incomplète. Arrêt.");
            return;
        }

        //  Initialisation correcte du SDK officiel
        Configuration::instance([
            'cloud' => [
                'cloud_name' => $cloudName,
                'api_key'    => $apiKey,
                'api_secret' => $apiSecret,
            ],
            'url' => ['secure' => true]
        ]);

        $uploadApi = new UploadApi();

        $this->newLine();

        $products = Product::whereNotNull('image')
            ->where('image', 'not like', 'products/%')
            ->get();

        $this->info(" {$products->count()} produits à migrer...");
        $this->newLine();

        $success = 0;
        $failed  = 0;

        foreach ($products as $product) {

            //  Extraire le nom du fichier depuis l'URL complète
            $filename  = basename(urldecode($product->image));
            $localPath = storage_path('app/public/products/' . $filename);

            $this->line("────────────────────────────────────────────");
            $this->line(" Produit   : {$product->name}");
            $this->line(" Image BDD : {$product->image}");
            $this->line(" Fichier   : {$filename}");
            $this->line(" Chemin    : {$localPath}");
            $this->line(" Existe ?  : " . (file_exists($localPath) ? ' OUI' : ' NON'));

            if (!file_exists($localPath)) {
                $this->warn("  Fichier introuvable, on passe au suivant.");
                $failed++;
                continue;
            }

            try {
                //  Nettoyer le nom : espaces → underscores, minuscules, sans caractères spéciaux
                $cleanName = strtolower(
                    preg_replace('/[^a-zA-Z0-9_\-]/', '_',
                        preg_replace('/\s+/', '_',
                            pathinfo($filename, PATHINFO_FILENAME)
                        )
                    )
                );

                $this->line(" Public ID cible : products/{$cleanName}");
                $this->line(" Upload en cours...");

                //  Upload via UploadApi (SDK officiel)
                $result = $uploadApi->upload($localPath, [
                    'folder'    => 'products',
                    'public_id' => $cleanName,
                ]);

                //  La réponse est un array, on accède directement à public_id
                $this->line(" Réponse reçue, clés : " . implode(', ', array_keys((array) $result)));

                $publicId = $result['public_id'] ?? null;

                if (!$publicId) {
                    throw new \Exception("public_id absent dans la réponse Cloudinary");
                }

                //  Mettre à jour la BDD avec le public_id Cloudinary
                $product->update(['image' => $publicId]);
                $this->info(" Migré avec succès → {$publicId}");
                $success++;

            } catch (\Exception $e) {
                $this->error(" Erreur   : " . $e->getMessage());
                $this->error("   Ligne    : " . $e->getLine());
                $this->error("   Fichier  : " . $e->getFile());
                $failed++;
            }

            $this->newLine();
        }

        $this->line("════════════════════════════════════════════");
        $this->info(" Succès  : {$success}");
        $this->warn(" Échecs  : {$failed}");
        $this->info(" Migration terminée !");
    }
}