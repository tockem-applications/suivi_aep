<?php
//session_start();
ob_start();
require_once 'traitement/user_t.php';
echo hash('sha256', 'ae8604e73a48e7022190406c201ca358'."ooooooooooo").'<br/>';
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $result = AuthManager::handleLogin($_POST);
    if ($result['success']) {
        header("Location: ?page=home"); // Remplacer par votre page de destination
        exit;
    } else {
        $message = '<div class="alert alert-danger">' . htmlspecialchars($result['message']) . '</div>';
    }
}

// Afficher un message d'erreur si accès refusé
if (isset($_GET['error']) && $_GET['error'] === 'access_denied') {
    $message = '<div class="alert alert-danger">Accès refusé. Veuillez vous connecter.</div>';
}
ob_get_clean();
?>
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <h2 class="text-center mb-4">Connexion</h2>
            <?php echo $message; ?>
            <form method="POST" id="loginForm" onsubmit="return validateLoginForm()">
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                    <div class="invalid-feedback">Veuillez entrer un email valide.</div>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Mot de passe</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                    <div class="invalid-feedback">Le mot de passe est requis.</div>
                </div>
                <input type="hidden" name="action" value="login">
                <button type="submit" class="btn btn-primary w-100">Se connecter</button>
            </form>
            <p class="mt-3 text-center">Pas de compte ? <a href="?page=register">Créer un compte</a></p>
        </div>
    </div>
</div>
<script>
    function validateLoginForm() {
        const form = document.getElementById('loginForm');
        const email = document.getElementById('email');
        const password = document.getElementById('password');
        let isValid = true;

        // Réinitialiser les messages d'erreur
        email.classList.remove('is-invalid');
        password.classList.remove('is-invalid');

        // Vérifier l'email
        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailPattern.test(email.value)) {
            email.classList.add('is-invalid');
            isValid = false;
        }

        // Vérifier le mot de passe
        if (password.value.length === 0) {
            password.classList.add('is-invalid');
            isValid = false;
        }

        return isValid;
    }
</script>
