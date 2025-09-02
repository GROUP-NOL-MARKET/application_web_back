<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Exceptions;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

class UserController extends Controller
{
    public function register(Request $request){
        $validated = Validator::make($request->all(),[
            "firstName"=> "required|string|max:255",
            "lastName"=> "required|string|max:255",
            "phone"=> "required|digits:10",
            "email"=>"required|string|max:255|email|unique:users,email",
            "password"=>"required|string|min:8|confirmed",
        ]);
        if($validated->fails()){
            return response()->json($validated->errors(), 422);
        }

        $user = User::create( [
            "firstName"=> $request->firstName,
            "lastName"=>$request->lastName,
            "phone"=>$request->phone,
            "email"=>$request->email,
            "password"=>bcrypt($request->password),
        ]);

        $token = JWTAuth::fromUser($user);

        return (
            response()->json([
                "message"=>"Inscription réussie",
                "user"=>$user,
                "token"=>$token,
            ])
            );
    }
    public function login(Request $request){
        $validated = Validator::make($request->all(), [
            "email"=>"required|string|max:255|email",
            "password"=>"required|string|max:255",
        ]);
        if($validated->fails()){
            return response()->json($validated->errors(), 422);
        }

        $user = User::where("email", $request["email"])->first();
        $token = JWTAuth::fromUser($user);

        return response()->json([
            "message"=>"Connexion réussie",
            "user"=>$user,
            "token"=>$token,
        ], 201);
    }
    public function dashbord(Request $request){

    }
    public function logout(){

        try {
            $token = JWTAuth::getToken();
            if(!$token){
                return response()->json([
                    "message"=>"Token non fourni",
                ], 401);
            }
            JWTAuth::invalidate($token);
            return response()->json([
                "message"=>"Déconnexion réussie",

            ], 200);
        }catch(TokenInvalidException){
            return response()->json([
                "message"=>"Token invalide"
            ], 401);
        } catch(TokenExpiredException){
            return response()->json([
                "message"=>"Token expiré"
            ], 401);

        };
    }
}
