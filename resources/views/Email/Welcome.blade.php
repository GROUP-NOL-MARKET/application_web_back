<!DOCTYPE html>
<html>

<head>
    <title>Bienvenue</title>
    <style>
        /* Optionnel : styles inline ou simples */
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #0d6efd;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }

        .image {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
        }
    </style>
</head>

<body>
    <div class="image">
        <img src="{{ asset('image/Logo_entreprise-removebg-preview.png') }}" alt="entreprise" width="150" />
    </div>

    <hr />
    <h1>Bienvenue sur notre plateforme!</h1>
    <h2>Bonjour, {{ $user->name ?? '' }}</h2>
    <h4>Merci de vous être inscrit sur notre plateforme.</h4>
    <p>
        Nous sommes ravis de vous compter parmi nous. Votre inscription marque le début d’une belle aventure, et nous
        mettons tout en œuvre pour vous offrir une expérience fluide, sécurisée et enrichissante.
    </p>
    <p>
        N’hésitez pas à explorer, poser vos questions et profiter pleinement des fonctionnalités à votre disposition.
    </p>
    <p>
        À très bientôt... L’équipe <b>Group Nol Market</b>.
    </p>

    <a href="{{ url('/') }}" class="btn">
        Achetez maintenant
    </a>

    <hr />
</body>

</html>
