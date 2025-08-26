<?php
@include_once("../donnees/user.php");
@include_once("donnees/user.php");
@include_once("../donnees/role.php");
@require_once("donnees/role.php");
//require_once 'User.php';
//require_once 'Role.php';

class AuthManager
{
    public static function logout(){
        var_dump("ototo");
        session_start();
        // Supprimer toutes les variables de session
        $_SESSION = array();
        // Si un cookie de session existe, le supprimer
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        // Détruire la session
        session_destroy();
        // Rediriger vers la page de connexion
        header('Location: ?page=home');
        exit;
    }
    private static function generateSalt()
    {
        return md5(uniqid(mt_rand(), true));
    }

    public static function handleLogin($data)
    {
        if (!isset($data['email']) || !isset($data['password'])) {
            return array('success' => false, 'message' => 'Veuillez remplir tous les champs.');
        }

        $email = trim($data['email']);
        $password = trim($data['password']);

        $user = User::getUserByEmail($email);
        if (!$user) {
            return array('success' => false, 'message' => 'Email ou mot de passe incorrect.11');
        }

        // Vérifier si c'est un ancien hachage SHA-256 sans sel
        $isOldHash = ($user['salt'] === '' && strlen($user['password']) === 64 && ctype_xdigit($user['password']));
        if ($isOldHash && hash('sha256', $password) === $user['password']) {
            // Ancien hachage correct, générer un nouveau sel et rehacher
            $newSalt = self::generateSalt();
            $newHashedPassword = hash('sha256', $password . $newSalt);
            User::prepare_query(
                "UPDATE users SET password = ?, salt = ? WHERE id = ?",
                array($newHashedPassword, $newSalt, $user['id'])
            );
        } elseif (hash('sha256', $password . $user['salt']) !== $user['password']) {
//            echo  '<br><br><br>'.hash('sha256', $password . $user['salt']).'<br>';
//            echo $user['password'].'<br>';
            return array('success' => false, 'message' => 'Email ou mot de passe incorrect.2');
        }

        $_SESSION['user_id'] = $user['id'];
//        $_SESSION['id_aep'] = 1;
        header('Location: ?page=home');
        return array('success' => true, 'message' => 'Connexion réussie.');
    }

    public static function checkPageAccess($user_id, $page)
    {
//        var_dump($page);
        // Validation des entrées
        $user_id = intval($user_id);
        $page = trim($page);
//        var_dump($user_id, $page);
        if ($user_id <= 0 || empty($page)) {
            return -1;
        }

        // Récupérer le niveau d'accès le plus permissif pour les rôles de l'utilisateur
        $access_level = -1;
        $query = Manager::prepare_query(
            "SELECT write_access FROM page_role_aep pra 
            inner join pages as p on p.id = pra.page_id
              inner join user_roles ur on ur.role_id = pra.role_id
              inner join users u on u.id = ur.user_id
              WHERE u.id = ? and p.libelle = ?",
            array($user_id, $page)
        );
        $permissions = $query->fetchAll();
        foreach ($permissions as $permission) {
            $access_level = max($access_level, (int)($permission['write_access'] + 0.0000001));
        }
        var_dump($access_level);
        return $access_level;
    }

    public static function handleRegister($data)
    {
        if (!isset($data['email'], $data['nom'], $data['prenom'], $data['numero_telephone'], $data['password'], $data['confirm_password'])) {
            return array('success' => false, 'message' => 'Veuillez remplir tous les champs.');
        }

        $email = trim($data['email']);
        $nom = trim($data['nom']);
        $prenom = trim($data['prenom']);
        $numero_telephone = trim($data['numero_telephone']);
        $password = trim($data['password']);
        $clef = trim($data['clef']);
        $confirm_password = trim($data['confirm_password']);

        if ($password !== $confirm_password) {
            return array('success' => false, 'message' => 'Les mots de passe ne correspondent pas.');
        }

        if (strlen($password) < 6) {
            return array('success' => false, 'message' => 'Le mot de passe doit contenir au moins 6 caractères.');
        }
        if (strlen($clef) < 3) {
            return array('success' => false, 'message' => 'Le mot de passe doit contenir au moins 6 caractères.');
        }


        if (User::getUserByEmail($email)) {
            return array('success' => false, 'message' => 'Cet email est déjà utilisé.');
        }

        $salt = self::generateSalt();
        $hashedPassword = hash('sha256', $password . $salt);

        $user = new User(0, $email, $nom, $prenom, $numero_telephone);
        $userData = $user->getDonnee();
        $userData['password'] = $hashedPassword;
        $userData['salt'] = $salt;

        $clef_id = 0;
        if (!$clef_id = User::verifier_clef($clef)){
            return array('success' => false, 'message' => 'Cette clef est invalide.');
        }

        $user = new User(0, $userData['email'], $userData['nom'], $userData['prenom'], $userData['numero_telephone'], $userData['password'], $userData['salt']);
        $query = $user->ajouter();

        if (!$query) {
            return array('success' => false, 'message' => 'Erreur lors de la création du compte.');
        }

        $userId = $user->id;

        $role = Role::getRoleByName('Visiteur');
        if ($role) {
            User::prepare_query(
                "INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)",
                array($userId, $role['id'])
            );
            User::connectUserToKey($user->id, $clef_id);
        }

        return array('success' => true, 'message' => 'Compte créé avec succès.');
    }
}