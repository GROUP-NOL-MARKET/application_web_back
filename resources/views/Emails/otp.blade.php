<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Code OTP</title>
</head>

<body>
    <h2>Bonjour {{ $name }},</h2>
    <p>Voici votre code OTP pour modifier votre mot de passe :</p>
    <h1 style="font-size: 24px; letter-spacing: 5px;">{{ $otp }}</h1>
    <p>Ce code est valide pendant <strong>10 minutes</strong>.</p>
    <p>Si vous n’avez pas demandé cette action, ignorez simplement ce message.</p>
    <br>
    <p>Cordialement,<br>L’équipe Nol Market</p>
</body>

</html>
