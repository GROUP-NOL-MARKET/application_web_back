<?php


namespace App\Imports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class ProductsImport implements ToModel, WithHeadingRow, WithChunkReading
{
    public function model(array $row)
    {
        $price = $row['price'] ?? null;
        if ($price !== null) {
            $price = preg_replace('/\s+/u', '', $row['price']);
            $price = str_replace(',', '.', $price);
        }

        // Construction du chemin de l'image locale
        $imageName = $row['image'] ?? null;
        $imagePath = null;

        if ($imageName) {
            $localPath = storage_path('app/public/products/' . $imageName);
            if (file_exists($localPath)) {
                // URL publique via asset()
                $imagePath = asset('storage/products/' . $imageName);
            }
        }

        return new Product([
            'reference' => $row['reference'] ?? null,
            'name' => $row['name'] ?? null,
            'family' => $row['family'] ?? null,
            'price' => $price,
            'category' => $row['category'] ?? null,
            'description' => $row['description'] ?? null,
            'disponibility' => $row['disponibility'] ?? null,
            'image' => $imagePath,
            'sous_category' => $row['sous_category'] ?? "Autre"
        ]);
    }

    public function chunkSize(): int
    {
        return 1000;
    }
}