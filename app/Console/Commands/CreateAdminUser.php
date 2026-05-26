<?php
namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdminUser extends Command
{
    protected $signature   = 'admin:create';
    protected $description = 'Créer le compte administrateur';

    public function handle()
    {
        $this->info(" Création du compte administrateur...");

        // Vérifie si un admin existe déjà
        if (Admin::exists()) {
            $this->warn(" Un administrateur existe déjà.");

            if (!$this->confirm("Voulez-vous en créer un autre ?")) {
                return;
            }
        }

        //  Champs obligatoires
        $name     = $this->ask("Nom complet");
        $email    = $this->ask("Email");

        //  Vérifie que l'email n'est pas déjà pris
        if (Admin::where('email', $email)->exists()) {
            $this->error("Cet email est déjà utilisé.");
            return;
        }

        $password = $this->secret("Mot de passe (caché)");
        $confirm  = $this->secret("Confirmez le mot de passe");

        if ($password !== $confirm) {
            $this->error(" Les mots de passe ne correspondent pas.");
            return;
        }

        // Champs optionnels
        $this->info(" Informations optionnelles (Entrée pour ignorer)");

        $firstName      = $this->ask("Prénom", null);
        $lastName       = $this->ask("Nom de famille", null);
        $phone          = $this->ask("Téléphone", null);
        $country        = $this->ask("Pays", null);
        $city           = $this->ask("Ville", null);
        $bp             = $this->ask("Boîte Postale", null);
        $entrepriseName = $this->ask("Nom de l'entreprise", null);
        $adresse        = $this->ask("Adresse", null);

        // Création dans la table admins
        Admin::create([
            'name'            => $name,
            'email'           => $email,
            'password'        => Hash::make($password),
            'firstName'       => $firstName,
            'lastName'        => $lastName,
            'phone'           => $phone,
            'Country'         => $country,
            'city'            => $city,
            'BP'              => $bp ? intval($bp) : null,
            'entreprise_name' => $entrepriseName,
            'adresse'         => $adresse,
        ]);

        $this->info(" Administrateur créé avec succès !");
        $this->table(
            ['Champ', 'Valeur'],
            [
                ['Nom',       $name],
                ['Email',     $email],
                ['Téléphone', $phone  ?? 'Non renseigné'],
                ['Ville',     $city   ?? 'Non renseigné'],
                ['Pays',      $country ?? 'Non renseigné'],
            ]
        );
    }
}
