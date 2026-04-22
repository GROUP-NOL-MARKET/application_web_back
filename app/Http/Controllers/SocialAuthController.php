<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Tymon\JWTAuth\Facades\JWTAuth;

class SocialAuthController extends Controller
{
    private array $allowedProviders = ['google', 'facebook', 'github'];

    /**
     * Retourne l'URL de redirection OAuth
     */
    public function redirect(string $provider)
    {
        if (!in_array($provider, $this->allowedProviders)) {
            return response()->json(['message' => 'Provider non supporté.'], 422);
        }

        $url = Socialite::driver($provider)
            ->stateless()
            ->redirect()
            ->getTargetUrl();

        return response()->json(['url' => $url]);
    }

    /**
     * Callback OAuth — crée/met à jour l'user et génère un JWT
     */
    public function callback(string $provider)
    {
        if (!in_array($provider, $this->allowedProviders)) {
            return redirect(env('FRONTEND_URL') . '/login?error=provider_invalide');
        }

        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();
        } catch (\Exception $e) {
            Log::error("OAuth $provider error: " . $e->getMessage());
            return redirect(env('FRONTEND_URL') . '/login?error=oauth_echec');
        }

        $nameParts = explode(' ', $socialUser->getName() ?? '', 2);

        $user = User::updateOrCreate(
            [
                'provider'    => $provider,
                'provider_id' => $socialUser->getId(),
            ],
            [
                'name'      => $socialUser->getName(),
                'firstName' => $nameParts[0] ?? null,
                'lastName'  => $nameParts[1] ?? null,
                'email'     => $socialUser->getEmail(),
                'avatar'    => $socialUser->getAvatar(),
                'password'  => null,
            ]
        );

        // Génération du token JWT
        $token = JWTAuth::fromUser($user);

        $userData = urlencode(json_encode([
            'id'        => $user->id,
            'name'      => $user->name,
            'firstName' => $user->firstName,
            'lastName'  => $user->lastName,
            'email'     => $user->email,
            'avatar'    => $user->avatar,
        ]));

        return redirect(
            env('FRONTEND_URL') . '/auth/callback?token=' . $token . '&user=' . $userData
        );
    }
}
