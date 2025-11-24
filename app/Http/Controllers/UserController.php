<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Mail\WelcomeMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Twilio\Rest\Client;

class UserController extends Controller
{
    public function sendWelcomeSms($phone)
    {
        $sid = env('TWILIO_SID');
        $token = env('TWILIO_AUTH_TOKEN');
        $from = env('TWILIO_PHONE');

        $twilio = new Client($sid, $token);

        $twilio->messages->create(
            $phone,
            [
                "from" => $from,
                "body" => "Bienvenue sur Nol Market 🛒 ! Votre inscription est confirmée."
            ]
        );
    }

    public function register(Request $request)
    {


        // Détection automatique du mode
        $isEmail = $request->filled("email");
        $isPhone = $request->filled("phone");

        if (!$isEmail && !$isPhone) {
            return response()->json([
                "message" => "Veuillez fournir un email ou un numéro de téléphone."
            ], 422);
        }

        // VALIDATION DYNAMIQUE
        $rules = [
            "password" => "required|min:8|confirmed"
        ];

        if ($isEmail) {
            $rules["email"] = "required|email|unique:users,email";
        }

        if ($isPhone) {
            $rules["phone"] = "required|digits:10|unique:users,phone";
        }

        $validated = $request->validate($rules);

        // if ($validated->fails()) {
        //     return response()->json($validated->errors(), 422);
        // }
        $phone = null;

        if ($isPhone) {
            $rawPhone = $validated["phone"];

            // Ajout automatique de l'indicatif
            if (!str_starts_with($rawPhone, "229")) {
                $phone = "+229" . $rawPhone;
            } else {
                $phone = $rawPhone;
            }

            // Validation unicité APRES formatage
            if (User::where("phone", $phone)->exists()) {
                return response()->json([
                    "phone" => ["Ce numéro de téléphone est déjà utilisé."]
                ], 422);
            }
        }

        // Création utilisateur
        $user = User::create([
            "email" => $validated["email"] ?? null,
            "phone" => $phone ?? null,
            "password" => bcrypt($validated["password"]),
        ]);

        // Génération du token

        $token = JWTAuth::fromUser($user);

        // if ($user->email) {
        //     // L'utilisateur s'est inscrit avec un email
        //     Mail::to($user->email)->send(new WelcomeMail($user));
        // }

        // if ($user->phone) {
        //     // L'utilisateur s'est inscrit avec son numéro
        //     $this->sendWelcomeSms($user->phone);
        // }


        return response()->json([
            "message" => "Inscription réussie",
            "token" => $token,
            "user" => $user
        ]);
    }
    public function login(Request $request)
    {
        $isEmail = $request->filled("email");
        $isPhone = $request->filled("phone");

        if (!$isEmail && !$isPhone) {
            return response()->json([
                "message" => "Veuillez fournir un email ou un numéro de téléphone."
            ], 422);
        }
        $rules = [
            "password" => "required|string|max:255"
        ];

        if ($isEmail) {
            $rules["email"] = "required|email|unique:users,email";
        }

        if ($isPhone) {
            $rules["phone"] = "required|digits:10|unique:users,phone";
        }

        $validated = $request->validate($rules);
        $phone = null;
        if ($isPhone) {
            $rawPhone = $validated["phone"];
            if (!str_starts_with($rawPhone, "229")) {
                $phone = "+229" . $rawPhone;
            } else {
                $phone=$rawPhone;
            }
        }

        if ($isEmail) {
            $user = User::where("email", $request["email"])->first();
        }
        if ($isPhone) {
            $user = User::where("phone", $phone)->first();
        }

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['error' => 'Identifiants incorrects'], 404);
        }

        $token = JWTAuth::fromUser($user);

        return response()->json([
            "message" => "Connexion réussie",
            "user" => $user,
            "token" => $token,
        ], 200);
    }

    public function show(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            return response()->json($user, 200);
        } catch (TokenExpiredException $e) {
            return response()->json(['error' => 'Token expiré'], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['error' => 'Token invalide'], 401);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Token non trouvé'], 401);
        }
    }

    public function deleteUser(Request $request)
    {
        try {
            // Authentifier via JWT (lève si token invalide/absent)
            $user = JWTAuth::parseToken()->authenticate();

            // Valider la présence du mot de passe de confirmation
            $request->validate([
                'password' => 'required|string',
            ]);

            // Vérifier le mot de passe
            if (!Hash::check($request->password, $user->password)) {
                return response()->json(['message' => 'Mot de passe incorrect.'], 422);
            }

            // Transaction pour sécurité : supprime et annule si erreur
            DB::beginTransaction();

            // — ici : place toute suppression ou nettoyage additionnel (fichiers, images, ...)
            // Par exemple : supprimer des fichiers stockés, révoquer tokens tiers, etc.
            // Ex:


            if ($user->profil) {
                Storage::delete($user->profil);
            }

            // Supprimer l'utilisateur (soft delete si Model utilise SoftDeletes sinon suppression réelle)
            $userId = $user->id;

            // Si tu veux *vraiment* supprimer :
            // $user->delete();

            // Si tu veux garder un historique, utiliser softDeletes:
            // $user->delete();

            // Ici on supprime réellement :
            $user->delete();

            // Invalider le token JWT actuel (sécurité)
            $token = JWTAuth::getToken();
            if ($token) {
                try {
                    JWTAuth::invalidate($token);
                } catch (\Exception $e) {
                    // pas bloquant : juste loguer
                    Log::warning("Impossible d'invalider le token après suppression user {$userId}: " . $e->getMessage());
                }
            }

            DB::commit();

            return response()->json(['message' => 'Compte supprimé avec succès.'], 200);
        } catch (TokenExpiredException $e) {
            return response()->json(['error' => 'Token expiré'], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['error' => 'Token invalide'], 401);
        } catch (\Illuminate\Validation\ValidationException $ve) {
            // Renvoie des erreurs de validation (ex: mot de passe manquant)
            return response()->json(['message' => 'Données invalides', 'errors' => $ve->errors()], 422);
        } catch (\Exception $e) {
            // Log détaillé pour debug serveur
            Log::error("Erreur suppression utilisateur: " . $e->getMessage());
            DB::rollBack();
            return response()->json(['message' => "Erreur interne lors de la suppression du compte."], 500);
        }
    }

    public function update(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            $validated = Validator::make($request->all(), [
                'lastName' => 'required|string|max:255',
                'firstName' => 'required|string|max:255',
                'secondName' => 'required|string|max:255',
                'email' => 'required|email|max:255|unique:users,email,' . $user->id,
                'genre' => 'required|in:Masculin,Féminin',
                'dateNaissance' => 'required|date',
            ]);

            if ($validated->fails()) {
                return response()->json($validated->errors(), 422);
            }

            $user->update([
                'lastName' => $request->lastName,
                'firstName' => $request->firstName,
                'secondName' => $request->secondName,
                'email' => $request->email,
                'genre' => $request->genre,
                'dateNaissance' => $request->dateNaissance,
            ]);

            return response()->json([
                'message' => 'Profil mis à jour avec succès',
                'user' => $user,
            ], 200);
        } catch (TokenExpiredException $e) {
            return response()->json(['error' => 'Token expiré'], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['error' => 'Token invalide'], 401);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Token non trouvé'], 401);
        }
    }

    // ✅ Mise à jour de l'adresse
    public function updateAddress(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            $validated = Validator::make($request->all(), [
                'addresse' => [
                    'required',
                    'string',
                    'min:5',
                    'max:200',
                    'regex:/^[A-Za-zÀ-ÖØ-öø-ÿ0-9\s,\'-]+$/',
                ],
            ]);

            if ($validated->fails()) {
                return response()->json($validated->errors(), 422);
            }

            $user->update([
                'addresse' => $request->addresse,
            ]);

            return response()->json([
                'message' => 'Adresse mise à jour avec succès.',
                'user' => $user,
            ], 200);
        } catch (TokenExpiredException $e) {
            return response()->json(['error' => 'Token expiré'], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['error' => 'Token invalide'], 401);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Token non trouvé'], 401);
        }
    }

    // mise à jour du numéro de téléphone
    public function updatePhone(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            // Validation stricte : 8 chiffres uniquement
            $validated = Validator::make($request->all(), [
                'phone' => [
                    'required',
                    'regex:/^[0-9]{10}$/',
                    'unique:users,phone,' . $user->id,
                ],
            ]);

            if ($validated->fails()) {
                return response()->json($validated->errors(), 422);
            }

            // Supprime les espaces au cas où
            $cleanPhone = preg_replace('/\s+/', '', $request->phone);

            $user->update([
                'phone' => $cleanPhone,
            ]);

            return response()->json([
                'message' => 'Numéro de téléphone mis à jour avec succès.',
                'user' => $user,
            ], 200);
        } catch (TokenExpiredException $e) {
            return response()->json(['error' => 'Token expiré'], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['error' => 'Token invalide'], 401);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Token non trouvé'], 401);
        }
    }


    // Code otp pour mise à jour du password


    public function requestOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $request->email)->first();

        $otp = mt_rand(1000, 9999);

        // Enregistrer dans le cache pour 10 minutes
        cache()->put('otp_' . $user->id, $otp, now()->addMinutes(1));

        // Envoyer le mail
        Mail::to($user->email)->send(new \App\Mail\OtpMail($user, $otp));

        return response()->json([
            'message' => 'Un code OTP a été envoyé à votre adresse email.',
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|digits:4',
        ]);

        $user = User::where('email', $request->email)->first();
        $cachedOtp = Cache::get('otp_' . $user->id);

        if (!$cachedOtp || $cachedOtp != $request->otp) {
            return response()->json(['message' => 'Code OTP invalide ou expiré.'], 422);
        }

        // OTP correct générer un token temporaire pour reset password
        $resetToken = Hash::make($user->email . now());

        Cache::put('reset_token_' . $user->id, $resetToken, now()->addMinutes(10));

        return response()->json([
            'message' => 'OTP vérifié avec succès.',
            'reset_token' => $resetToken,
        ]);
    }

    // Réinitialisation du mot de passe

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'reset_token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::where('email', $request->email)->first();

        // On récupère le token en cache
        $cachedToken = Cache::get('reset_token_' . $user->id);

        if (!$cachedToken) {
            return response()->json(['message' => 'Token expiré ou inexistant.'], 422);
        }

        // Vérifie si le token envoyé correspond
        if ($cachedToken !== $request->reset_token) {
            return response()->json(['message' => 'Token de réinitialisation invalide.'], 422);
        }

        // Mise à jour du mot de passe
        $user->password = Hash::make($request->password);
        $user->save();

        // On supprime le token après usage
        Cache::forget('reset_token_' . $user->id);

        return response()->json([
            'message' => 'Mot de passe réinitialisé avec succès.',
        ], 200);
    }



    public function logout()
    {
        try {
            $token = JWTAuth::getToken();
            if (!$token) {
                return response()->json([
                    "message" => "Token non fourni",
                ], 401);
            }
            JWTAuth::invalidate($token);
            return response()->json([
                "message" => "Déconnexion réussie",
            ], 200);
        } catch (TokenInvalidException) {
            return response()->json([
                "message" => "Token invalide"
            ], 401);
        } catch (TokenExpiredException) {
            return response()->json([
                "message" => "Token expiré"
            ], 401);
        }
    }
}