<?php

namespace App\Http\Controllers;

use App\Models\RecentView;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class RecentViewController extends Controller
{
    public function index()
    {
        $recentViews = Auth::user()
            ->recentViews()
            ->with('product')
            ->latest()
            ->paginate(6); // 6 par page

        return response()->json($recentViews);
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);

        $recentView = RecentView::create([
            'user_id' => Auth::id(),
            'product_id' => $request->product_id,
        ]);

        return response()->json($recentView, 201);
    }
}
