<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function create(Request $request){

    }
    public function edit(Request $request){

    }
    public function delete(Request $request, $id){
        //
    }
    public function show(Request $request, $id){
        //
    }
    public function index()
    {
        return response()->json(Product::all());
    }
}
