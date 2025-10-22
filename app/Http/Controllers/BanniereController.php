<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Banniere;

class BanniereController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'video' => 'nullable|mimetypes:video/mp4,video/avi,video/mpeg|max:20480',
            'subTitle' => 'required|string|max:255',
            'percent' => 'required|numeric',
            'link' => 'required|string',
            'phone' => 'required|string|max:20',
        ]);

        $imagesPaths = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('bannieres/images', 'public');
                $imagesPaths[] = $path;
            }
        }

        $videoPath = null;
        if ($request->hasFile('video')) {
            $videoPath = $request->file('video')->store('bannieres/videos', 'public');
        }

        $banniere = Banniere::create([
            'images' => json_encode($imagesPaths),
            'video' => $videoPath,
            'subTitle' => $request->subTitle,
            'percent' => $request->percent,
            'link' => $request->link,
            'phone' => $request->phone,
        ]);

        return response()->json([
            'message' => 'Bannière créée avec succès',
            'banniere' => $banniere,
        ], 201);
    }

    public function index()
    {
        $bannieres = Banniere::latest()->get();

        return response()->json([
            'bannieres' => $bannieres->map(function ($b) {
                return [
                    'id' => $b->id,
                    'images' => json_decode($b->images),
                    'video' => $b->video,
                    'subTitle' => $b->subTitle,
                    'percent' => $b->percent,
                    'link' => $b->link,
                    'phone' => $b->phone,
                ];
            }),
        ]);
    }

    public function show($id)
    {
        $banniere = Banniere::findOrFail($id);
        return response()->json([
            'banniere' => [
                'id' => $banniere->id,
                'images' => json_decode($banniere->images),
                'video' => $banniere->video,
                'subTitle' => $banniere->subTitle,
                'percent' => $banniere->percent,
                'link' => $banniere->link,
                'phone' => $banniere->phone,
            ]
        ]);
    }
}