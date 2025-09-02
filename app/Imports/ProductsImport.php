<?php

namespace App\Imports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\ToModel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class ProductsImport implements ToModel, WithHeadingRow, WithChunkReading
{
    public function model(array $row)
    {
        $price = $row['price'] ?? null;
        if ($price !== null) {
            $price = preg_replace('/\s+/u', '', $row['price']); // supprime tous les espaces
            $price = str_replace(',', '.', $price); // virgule → point
        }
        return new Product([
            'reference' => $row['reference'] ?? null, // Colonne A
            'name' => $row['name'] ?? null, // Colonne B
            'family' => $row['family'] ?? null, // Colonne C
            'price' => $price, // Colonne D
            'category' => $row['category'] ?? null, // Colonne E
            'description' => $row['description'] ?? null, //Colonne F
            'disponibility' => $row['disponibility'] ?? null, //Colonne G
            'image' => $row['image'] ?? null, //Colonne H
            // 'A' => $row[8], //Colonne I
            // 'B' => $row[9], //Colonne J
            'sous_category' => $row['sous_category'] ?? "Autre" //Colonne K
        ]);
    }

    public function chunkSize(): int
    {
        return 1000;
    }
}
