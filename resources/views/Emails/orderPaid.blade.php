<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Nouvelle commande payée</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 600px;
            margin: auto;
            background-color: #ffffff;
            padding: 24px;
            border-radius: 6px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
        }

        h1 {
            color: #0d6efd;
            font-size: 22px;
            margin-bottom: 10px;
        }

        h2 {
            font-size: 16px;
            margin-top: 24px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 6px;
            color: #333;
        }

        p {
            color: #555;
            font-size: 14px;
            line-height: 1.6;
            margin: 6px 0;
        }

        .product {
            margin-bottom: 12px;
            padding-left: 10px;
            border-left: 3px solid #0d6efd;
        }

        .total {
            font-size: 16px;
            font-weight: bold;
            color: #000;
            margin-top: 16px;
        }

        .footer {
            margin-top: 30px;
            font-size: 13px;
            color: #777;
        }
    </style>
</head>

<body>

    <div class="container">
        <div class="image">
            <img src="{{ asset('image/Logo_entreprise-removebg-preview.png') }}" alt="entreprise" width="150" />
        </div>

        <hr />
        <h1>Nouvelle commande payée</h1>

        <p>Une nouvelle commande vient d'être validée.</p>

        <h3>Détails de la commande :</h3>
        <ul>
            <li><strong>Référence :</strong> {{ $order->reference }}</li>
            <li><strong>Client :</strong> {{ $order->user->firstName }} {{ $order->user->lastName }}</li>
            <li><strong>Montant :</strong> {{ number_format($order->total, 0, ',', ' ') }} FCFA</li>
            <li><strong>Mode de paiement :</strong>
                @if ($order->payment_method === 'livraison')
                    <span style="color: orange; font-weight: bold;">PAIEMENT À LA LIVRAISON ⚠️</span>
                @else
                    {{ $order->payment->method ?? 'Non spécifié' }}
                @endif
            </li>
            <li><strong>Statut paiement :</strong> {{ $order->payment_status }}</li>
            @if ($order->delivery_address)
                <li><strong>Adresse de livraison :</strong> {{ $order->delivery_address }}</li>
            @endif
            @if ($order->delivery_phone)
                <li><strong>Téléphone livraison :</strong> {{ $order->delivery_phone }}</li>
            @endif

            @foreach ($order->produits as $item)
                <div class="product">
                    <p><strong>{{ $item['name'] }}</strong></p>
                    <p>Quantité : {{ $item['quantity'] }}</p>
                    <p>Prix unitaire : {{ $item['price'] }} FCFA</p>
                    <p>Sous-total : {{ $item['price'] * $item['quantity'] }} FCFA</p>
                </div>
            @endforeach

            <h2>Montant total</h2>
            <p class="total">Total payé : {{ $order->total }} FCFA</p>

            <div class="footer">
                <p>Merci,<br>L’équipe système</p>
            </div>
        </ul>


    </div>

</body>

</html>
