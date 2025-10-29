<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function uploadProfile(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'profil' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // Supprimer l'ancienne image si elle existe
        if ($user->profil && Storage::exists('public/profils/' . $user->profil)) {
            Storage::delete('public/profils/' . $user->profil);
        }

        // Enregistrer la nouvelle image
        $imageName = time() . '.' . $request->profil->extension();
        $request->profil->storeAs('public/profils', $imageName);

        // Mettre à jour le champ dans la base
        $user->profil = $imageName;
        $user->save();

        return response()->json([
            'message' => 'Profil mis à jour avec succès',
            'profil_url' => asset('storage/profils/' . $imageName),
        ]);
    }
}
