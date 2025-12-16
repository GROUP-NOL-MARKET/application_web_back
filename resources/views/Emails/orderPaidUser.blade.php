@component('mail::message')
<!Doctype html>
<html>

<head>

</head>

<body>
    # Nouvelle commande payée

    Vous venez de valider votre nouvelle commande.

    ## Informations personnelles
    Nom: {{ $order->user->name }}
    Téléphone: {{ $order->user->phone || "Aucun" }}
    Adresse: {{ $order->address }}

    ## Produits
    @foreach ($order->items as $item)
        - {{ $item->product->name }}
        Quantité: {{ $item->quantity }}
        Prix unitaire: {{ $item->price }}
        Sous-total: {{ $item->price * $item->quantity }}
    @endforeach

    ## Montant total
    Total: {{ $order->total_amount }}
    Total payé: {{ $order->paid_amount }}

    Group Nol Market vous remercie pour votre achat et vous assure que vos produits vous seront envoyé dans quelques
    instants.
    D'ici là vous pouvez consultez le menu "Mon compte" pour suivre le statut de votre commande..

    Merci et à bientôt...
</body>

</html>

@endcomponent