<?php

namespace App\Http\Controllers;

use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VoucherController extends Controller
{
    // Récupérer tous les bons d’achat de l’utilisateur connecté
    public function index(Request $request)
    {
        // Nombre d'éléments par page (par défaut 5)
        $perPage = $request->get('per_page', 5);
        $status = $request->get('status'); // "actif" ou "inactif"

        $query = Auth::user()->vouchers()->orderByDesc('created_at');

        if ($status) {
            $query->where('status', $status === 'actif' ? 'actif' : 'inactif');
        }

        $vouchers = $query->paginate($perPage);

        return response()->json([
            'data' => $vouchers->items(),
            'current_page' => $vouchers->currentPage(),
            'last_page' => $vouchers->lastPage(),
            'total' => $vouchers->total(),
        ]);
    }

    // Créer un bon d’achat
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'sub_title' => 'nullable|string|max:255',
            'code' => 'required|string|unique:vouchers,code',
            'valeur' => 'required|string',
            'date' => 'required|date',
            'until' => 'required|date|after_or_equal:date_debut',
            'status' => 'string|required',
        ]);

        $voucher = Voucher::create([
            'user_id' => Auth::id(),
            'title' => $request->title,
            'sub_title' => $request->sub_title,
            'code' => $request->code,
            'valeur' => $request->valeur,
            'date' => $request->date,
            'until' => $request->until,
            'status' => $request->status,
        ]);

        return response()->json($voucher, 201);
    }

    // Afficher un bon spécifique
    public function show($id)
    {
        $voucher = Auth::user()->vouchers()->findOrFail($id);
        return response()->json($voucher);
    }

    // Supprimer un bon
    public function destroy($id)
    {
        $voucher = Auth::user()->vouchers()->findOrFail($id);
        $voucher->delete();

        return response()->json(['message' => 'Bon supprimé avec succès']);
    }
}
