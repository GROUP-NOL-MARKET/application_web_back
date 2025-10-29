<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class ReviewController extends Controller
{
    public function index()
    {
        $reviews = Review::where('user_id', Auth::id())
            ->with('order')
            ->latest()
            ->paginate(5);

        return response()->json($reviews);
    }


    public function store(Request $request)
    {
        $request->validate([
            'notation' => 'required|integer|min:1,max:5',
            'appreciation' => 'required|string',
            'order_id' => 'required|exists:orders,id',
        ]);

        $review = Review::create([
            'user_id' => Auth::id(),
            'notation' => $request->notation,
            'appreciation' => $request->appreciation,
            'order_id' => $request->order_id,
        ]);

        return response()->json([
            'message' => 'Avis enregistré avec succès',
            'data' => $review,
        ], 201);
    }

    public function show()
    {
        $avis = Review::with('user')->orderBy('created_at', 'desc')->get();
        return response()->json($avis);
    }
}