<?php

namespace App\Listeners;

use App\Events\OrderPaid;
use App\Mail\OrderPaidAdmin;
use Illuminate\Support\Facades\Mail;

class SendOrderPaidEmail
{
    public function handle(OrderPaid $event)
    {
        $adminEmail = env('ADMIN_EMAIL', 'admin@example.com');
        Mail::to($adminEmail)->send(new OrderPaidAdmin($event->order));
    }
}