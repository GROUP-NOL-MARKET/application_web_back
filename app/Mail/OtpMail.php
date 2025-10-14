<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $otp;

    public function __construct($user, $otp)
    {
        $this->user = $user;
        $this->otp = $otp;
    }

    public function build()
    {
        return $this->subject('Votre code OTP pour réinitialiser votre mot de passe')
            ->view('emails.otp')
            ->with([
                'name' => $this->user->firstName ?? 'Utilisateur',
                'otp' => $this->otp,
            ]);
    }
}