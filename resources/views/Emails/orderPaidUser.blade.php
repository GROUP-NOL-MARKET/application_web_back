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
    <div class="image">
        <img src="{{ asset('image/Logo_entreprise-removebg-preview.png') }}" alt="entreprise" width="150" />
    </div>

    <hr/>

    <div class="container">
        <h1>Nouvelle commande payée</h1>

        <p>Vous avez validé une nouvelle commande.</p>

        <h2>Produits</h2>

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
            <p>
                Group Nol Market vous remercie pour votre achat et vous assure que vos produits vous seront envoyé dans quelques
                instants.
                D'ici là vous pouvez consultez le menu "Mon compte" pour suivre le statut de votre commande..

                Merci et à bientôt...</p>
        </div>
    </div>

</body>

</html>