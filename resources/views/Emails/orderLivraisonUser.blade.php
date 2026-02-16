<!DOCTYPE html>
<html>
<head>
    <title>Commande enregistrée</title>
</head>
<body>
    <h2>Bonjour {{ $order->user->firstName }},</h2>
    
    <p>Votre commande a été enregistrée avec succès.</p>
    
    <h3>Détails de la commande :</h3>
    <ul>
        <li><strong>Référence :</strong> {{ $order->reference }}</li>
        <li><strong>Montant total :</strong> {{ number_format($order->total, 0, ',', ' ') }} FCFA</li>
        <li><strong>Mode de paiement :</strong> Paiement à la livraison</li>
        <li><strong>Adresse de livraison :</strong> {{ $order->delivery_address }}</li>
    </ul>
    
    <p><strong>Important :</strong> Veuillez préparer le montant exact de {{ number_format($order->total, 0, ',', ' ') }} FCFA pour le paiement lors de la livraison.</p>
    
    <p>Merci pour votre confiance !</p>
    <p>L'équipe Nol Market</p>
</body>
</html>