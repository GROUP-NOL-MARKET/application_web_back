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


    public function me() {}

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
