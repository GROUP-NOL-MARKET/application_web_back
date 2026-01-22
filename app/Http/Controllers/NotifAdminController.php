<?php

namespace App\Http\Controllers;

use App\Mail\OrderDiscount;
use App\Models\NotifAdmin;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use App\Models\Message;
use Illuminate\Support\Facades\Mail;
use App\Services\FasterMessageService;
use App\Mail\OrderPaidUser;

class NotifAdminController extends Controller
{
    /**
     * Notifications de l'admin connecté
     */
    public function index()
    {
        return response()->json(
            NotifAdmin::where('admin_id', Auth::id())
                ->latest()
                ->take(10)
                ->get()
        );
    }

    /**
     * Accepter une demande de remboursement
     */
    public function acceptRefund(NotifAdmin $message)
    {
        // Sécurité : la notification appartient-elle à l'admin ?
        if ($message->admin_id !== Auth::id()) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        if (!$message->can_act) {
            return response()->json(['message' => 'Action déjà traitée'], 400);
        }

        $order = Order::find($message->related_id);

        if (!$order) {
            return response()->json(['message' => 'Commande introuvable'], 404);
        }

        $order->update([
            'status' => 'remboursee'
        ]);

        // Créer le message utilisateur
        Message::create([
            'user_id' => $order->user_id,
            'title' => "Remboursement effectué",
            'sender' => 'Système',
            'content' => "Demande de remboursement pour la commande n°{$order->reference} effectuée. Votre avis compte pour nous et vous pouvez le donner dans l'onglet commandes. Merci à vous!!",
        ]);

        $user = $order->user;
        if (!empty($user->email)) {
            Mail::to($user->email)->send(new OrderDiscount($order));
        }
        // SMS
        elseif (!empty($user->phone)) {

            app(FasterMessageService::class)->send(
                $user->phone,
                "Votre commande {$order->reference} a été remboursée. Vous pouvez vérifier dans votre compte. Puisque votre avis compte, donnez votre avis par rapport à la commande dans le menu Commandes de votre espace compte sur la plateforme. Info: 0165002800"
            );
        }

        $message->update([
            'can_act' => false
        ]);

        return response()->json([
            'message' => 'Remboursement accepté'
        ]);
    }
}
