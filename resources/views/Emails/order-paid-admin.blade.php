@component('mail::message')
# Une nouvelle commande a été payée !

Référence : **{{ $order->reference }}**  
Montant : **{{ $order->total }} {{ $order->currency }}**  
Client : **{{ $order->user->name }}**  
Date : **{{ $order->created_at->format('d/m/Y H:i') }}**

@component('mail::button', ['url' => url('/admin/orders/'.$order->id)])
Voir la commande
@endcomponent

Merci,<br>
{{ config('app.name') }}
@endcomponent
