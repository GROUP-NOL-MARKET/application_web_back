@component('mail::message')
    # Nouvelle commande payée

    Une nouvelle commande vient d'être validée.

    ## Informations Client
    Nom: {{ $order->user->name }}
    Téléphone: {{ $order->user->phone }}
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

    Merci,
    L’équipe système
@endcomponent
