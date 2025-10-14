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
            ->paginate(10);

        return response()->json($reviews);
    }

    public function store(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'comment' => 'required|string',
            'rating' => 'required|integer|min:1|max:5',
        ]);

        $review = Review::create([
            'user_id' => Auth::id(),
            'order_id' => $request->order_id,
            'comment' => $request->comment,
            'rating' => $request->rating,
        ]);

        return response()->json($review, 201);
    }
}
