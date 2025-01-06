


<head>
    <style>
        body>body {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 50vh;
            background-color: #f8f9fa;
        }
        .login-form {
            width: 400px;
            padding: 20px;
            border-radius: 5px;
            background: white;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body >

<div class="login-form mt-4">
    <h2 class="text-center">Connexion</h2>
    <form action="votre_script_php.php" method="POST">
        <div class="form-group">
            <label for="username">Nom d'utilisateur</label>
            <input type="text" class="form-control" id="username" name="username" required>
        </div>
        <div class="form-group mb-3">
            <label for="password">Mot de passe</label>
            <input type="password" class="form-control" id="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block w-100">Se connecter</button>
    </form>
    <p class="text-center mt-3">
        <a href="#">Mot de passe oublié ?</a>
    </p>
</div>
</body>