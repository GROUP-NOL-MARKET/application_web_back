<?php

namespace App\Http\Controllers;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MessageController extends Controller
{
    /**
     * Récupérer tous les messages de l'utilisateur connecté
     * avec pagination et tri dynamique.
     */
    public function index(Request $request)
    {
        $sort = $request->query('sort', 'recent'); // par défaut: 'recent'

        $query = Message::where('user_id', Auth::id());

        // Tri dynamique
        if ($sort === 'anciens') {
            $query->orderBy('created_at', 'asc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $messages = $query->paginate(5); // 5 messages par page

        return response()->json($messages);
    }

    /**
     * Afficher un message spécifique
     */
    public function show($id)
    {
        $message = Message::where('user_id', Auth::id())->findOrFail($id);
        return response()->json($message);
    }

    /**
     * Créer un nouveau message (optionnel)
     */
    public function store(Request $request)
    {
        $request->validate([
            'sender' => 'nullable|string|max:255',
            'title' => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        $message = Message::create([
            'user_id' => Auth::id(),
            'sender' => $request->sender ?? 'Group Nol Market',
            'content' => $request->content,
            'title' => $request->title,

        ]);

        return response()->json($message, 201);
    }

    /**
     * Supprimer un message
     */
    public function destroy($id)
    {
        $message = Message::where('user_id', Auth::id())->findOrFail($id);
        $message->delete();

        return response()->json(['message' => 'Message supprimé avec succès']);
    }
}
