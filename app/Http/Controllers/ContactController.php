<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ContactController extends Controller
{
    //  Enregistrement d'un message depuis le formulaire public
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nom' => 'required|string|min:2|max:50',
            'email' => 'required|email',
            'message' => 'required|string|min:5|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $contact = Contact::create($validator->validated());

        //  émettre un événement pour le temps réel
        // event(new \App\Events\NewContactMessage($contact));

        return response()->json([
            'message' => 'Message envoyé avec succès.',
            'contact' => $contact
        ], 201);
    }

    //  Liste des messages pour l'admin
    public function index()
    {
        $messages = Contact::orderBy('created_at', 'desc')->get();

        return response()->json($messages);
    }
}