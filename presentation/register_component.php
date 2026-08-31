<?php
//session_start();
ob_start();
require_once 'traitement/user_t.php';
/*
 *
 *
 *
 * CREATE TABLE users (
    id INT(5) UNSIGNED NOT NULL AUTO_INCREMENT,
    email VARCHAR(32) NOT NULL,
    nom VARCHAR(32) NOT NULL,
    prenom VARCHAR(32) NOT NULL,
    numero_telephone VARCHAR(16) NOT NULL,
    password VARCHAR(16) NOT NULL,
    PRIMARY KEY (id)
);
 */



$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    var_dump("totot");;
    $result = AuthManager::handleRegister($_POST);
    if ($result['success']) {
        $message = '<div class="alert alert-success">Compte créé avec succès ! Veuillez vous connecter.</div>';
        header("location: ?page=login");
    } else {
        $message = '<div class="alert alert-danger">' . htmlspecialchars($result['message']) . '</div>';
    }
}
ob_get_clean();
?>
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <h2 class="text-center mb-4">Créer un compte</h2>
            <?php echo $message; ?>
            <form method="POST" id="registerForm" onsubmit="return validateRegisterForm()">
                <div class="mb-3">
                    <label for="clef" class="form-label">Clef d'acces</label>
                    <input type="text" class="form-control" id="clef" name="clef" required>
                    <div class="invalid-feedback">Veuillez entrer une clef valide.</div>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                    <div class="invalid-feedback">Veuillez entrer un email valide.</div>
                </div>
                <div class="mb-3">
                    <label for="nom" class="form-label">Nom</label>
                    <input type="text" class="form-control" id="nom" name="nom" required>
                    <div class="invalid-feedback">Le nom est requis.</div>
                </div>
                <div class="mb-3">
                    <label for="prenom" class="form-label">Prénom</label>
                    <input type="text" class="form-control" id="prenom" name="prenom" required>
                    <div class="invalid-feedback">Le prénom est requis.</div>
                </div>
                <div class="mb-3">
                    <label for="numero_telephone" class="form-label">Numéro de téléphone</label>
                    <input type="text" class="form-control" id="numero_telephone" name="numero_telephone" required>
                    <div class="invalid-feedback">Veuillez entrer un numéro de téléphone valide (chiffres, +, -).</div>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Mot de passe</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                    <div class="invalid-feedback">Le mot de passe doit contenir au moins 6 caractères.</div>
                </div>
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirmer le mot de passe</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    <div class="invalid-feedback">Les mots de passe ne correspondent pas.</div>
                </div>
                <input type="hidden" name="action" value="register">
                <button type="submit" class="btn btn-primary w-100">S'inscrire</button>
            </form>
            <p class="mt-3 text-center">Déjà un compte ? <a href="?page=login">Se connecter</a></p>
        </div>
    </div>
</div>
<script>
    function validateRegisterForm() {
        const form = document.getElementById('registerForm');
        const email = document.getElementById('email');
        const nom = document.getElementById('nom');
        const prenom = document.getElementById('prenom');
        const numeroTelephone = document.getElementById('numero_telephone');
        const password = document.getElementById('password');
        const clef = document.getElementById('clef');
        const confirmPassword = document.getElementById('confirm_password');
        let isValid = true;

        // Réinitialiser les messages d'erreur
        clef.classList.remove('is-invalid');
        email.classList.remove('is-invalid');
        nom.classList.remove('is-invalid');
        prenom.classList.remove('is-invalid');
        numeroTelephone.classList.remove('is-invalid');
        password.classList.remove('is-invalid');
        confirmPassword.classList.remove('is-invalid');

        // Vérifier l'email
        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailPattern.test(email.value)) {
            email.classList.add('is-invalid');
            isValid = false;
        }

        // Vérifier la clef
        if (clef.value.trim().length === 0) {
            clef.classList.add('is-invalid');
            isValid = false;
        }
        // Vérifier le nom
        if (nom.value.trim().length === 0) {
            nom.classList.add('is-invalid');
            isValid = false;
        }

        // Vérifier le prénom
        if (prenom.value.trim().length === 0) {
            prenom.classList.add('is-invalid');
            isValid = false;
        }

        // Vérifier le numéro de téléphone
        const phonePattern = /^[0-9+\-]+$/;
        if (!phonePattern.test(numeroTelephone.value) || numeroTelephone.value.length === 0) {
            numeroTelephone.classList.add('is-invalid');
            isValid = false;
        }

        // Vérifier le mot de passe
        if (password.value.length < 6) {
            password.classList.add('is-invalid');
            isValid = false;
        }

        // Vérifier la correspondance des mots de passe
        if (password.value !== confirmPassword.value) {
            confirmPassword.classList.add('is-invalid');
            isValid = false;
        }

        return isValid;
    }
</script>
