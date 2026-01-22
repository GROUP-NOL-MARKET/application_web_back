<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Admin;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

class AdminController extends Controller
{

    public function login(Request $request)
    {
        $validated = Validator::make($request->all(), [
            "email" => "required|string|max:255|email",
            "password" => "required|string|max:255",
        ]);
        if ($validated->fails()) {
            return response()->json($validated->errors(), 422);
        }

        $admin = Admin::where("email", $request["email"])->first();


        if (!$admin || !Hash::check($request->password, $admin->password)) {
            return response()->json(['error' => 'Identifiants incorrects'], 404);
        }

        $token = JWTAuth::fromUser($admin);

        return response()->json([
            "message" => "Connexion réussie",
            "user" => $admin,
            "token" => $token,
        ], 200);
    }

    public function me()
    {
        try {
            $admin = JWTAuth::parseToken()->authenticate();
            return response()->json($admin, 200);
        } catch (TokenExpiredException $e) {
            return response()->json(['error' => 'Token expiré'], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['error' => 'Token invalide'], 401);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Authentification requise'], 401);
        }
    }

    public function update(Request $request)
    {
        try {
            $admin = JWTAuth::parseToken()->authenticate();

            $validator = Validator::make($request->all(), [
                'lastName' => 'required|string|max:255',
                'firstName' => 'required|string|max:255',
                'country' => 'required|string|max:255',
                'city' => 'required|string|max:255',
                'phone' => 'required|digits_between:8,15',
                'BP' => 'nullable|string|max:10',
                'entreprise_name' => 'required|string|max:255',
                'adresse' => 'required|string|max:255',
                'email' => 'required|email|max:255|unique:admins,email,' . $admin->id,
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'errors' => $validator->errors()
                ], 422);
            }

            // Normalisation téléphone (sécurité backend)
            $phone = preg_replace('/\D/', '', $request->phone);

            $admin->update([
                'lastName' => $request->lastName,
                'firstName' => $request->firstName,
                'country' => $request->country,
                'city' => $request->city,
                'email' => $request->email,
                'phone' => $phone,
                'BP' => $request->BP,
                'entreprise_name' => $request->entreprise_name,
                'adresse' => $request->adresse,
            ]);

            return response()->json([
                'message' => 'Profil mis à jour avec succès',
                'admin' => $admin,
            ], 200);
        } catch (TokenExpiredException $e) {
            return response()->json(['error' => 'Token expiré'], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['error' => 'Token invalide'], 401);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Une erreur interne est survenue',
                'details' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
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
