<?php
//var_dump("ndnndndnn");
@include_once("../donnees/role.php");
@include_once("donnees/role.php");
@include_once("donnees/log.php");
@include_once("../donnees/log.php");
@include_once("donnees/user.php");
//var_dump("bonjour");
@include_once("../donnees/user.php");


class RoleDetailsProcessor2
{
    /**
     * Récupère les utilisateurs ayant un rôle spécifique
     * @param int $roleId ID du rôle
     * @return array Liste des utilisateurs
     */
    public static function getUsersWithRole($roleId)
    {
        $query = Role::prepare_query(
            "SELECT u.* FROM users u JOIN user_roles ur ON u.id = ur.user_id WHERE ur.role_id = ?",
            array($roleId)
        );
        return $query->fetchAll();
    }

    /**
     * Récupère les pages accessibles par un rôle pour un AEP spécifique
     * @param int $roleId ID du rôle
     * @param int $aepId ID de l'AEP
     * @return array Liste des pages
     */
    public static function getPagesWithAccess($roleId, $aepId)
    {
        $query = Role::prepare_query(
            "SELECT p.* FROM pages p JOIN page_role_aep pra ON p.id = pra.page_id WHERE pra.role_id = ? AND pra.aep_id = ?",
            array($roleId, $aepId)
        );
        return $query->fetchAll();
    }

    /**
     * Récupère les pages non accessibles par un rôle pour un AEP spécifique
     * @param int $roleId ID du rôle
     * @param int $aepId ID de l'AEP
     * @return array Liste des pages disponibles
     */
    public static function getAvailablePages($roleId, $aepId)
    {
        $query = Role::prepare_query(
            "SELECT p.* FROM pages p WHERE p.id NOT IN (
                SELECT pra.page_id FROM page_role_aep pra WHERE pra.role_id = ? AND pra.aep_id = ?
            )",
            array($roleId, $aepId)
        );
        return $query->fetchAll();
    }

    /**
     * Récupère les utilisateurs n'ayant pas un rôle spécifique
     * @param int $roleId ID du rôle
     * @return array Liste des utilisateurs disponibles
     */
    public static function getAvailableUsers($roleId)
    {
        $query = Role::prepare_query(
            "SELECT u.* FROM users u WHERE u.id NOT IN (
                SELECT ur.user_id FROM user_roles ur WHERE ur.role_id = ?
            )",
            array($roleId)
        );
        return $query->fetchAll();
    }

    /**
     * Ajoute un accès à une page pour un rôle et un AEP
     * @param array $data Données du formulaire ($_POST)
     * @param int $roleId ID du rôle
     * @param int $aepId ID de l'AEP
     * @return array Résultat avec 'success' (bool) et 'message' (string)
     */
    public static function addPageAccess($data, $roleId, $aepId)
    {
        if (!isset($data['page_id']) || !$data['page_id']) {
            return array('success' => false, 'message' => 'Veuillez sélectionner une page.');
        }

        $pageId = (int)$data['page_id'];

        // Vérifier si la page existe
        if (!Page::getPage($pageId)) {
            return array('success' => false, 'message' => 'Page non trouvée.');
        }

        // Vérifier si le rôle existe
        if (!Role::getRole($roleId)) {
            return array('success' => false, 'message' => 'Rôle non trouvé.');
        }

        // Vérifier si l'accès existe déjà
        $query = Role::prepare_query(
            "SELECT COUNT(*) as count FROM page_role_aep WHERE page_id = ? AND role_id = ? AND aep_id = ?",
            array($pageId, $roleId, $aepId)
        );
        $query = $query->fetch();
        if ($query['count'] > 0) {
            return array('success' => false, 'message' => 'Cet accès existe déjà.');
        }

        // Ajouter l'accès
        $query = Role::prepare_query(
            "INSERT INTO page_role_aep (page_id, role_id, aep_id) VALUES (?, ?, ?)",
            array($pageId, $roleId, $aepId)
        );

        if ($query->rowCount() > 0) {
            $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
            Log::logRequest($userId, 'role_detail', 'add_page_access#'.$roleId.'#'.$aepId, date('Y-m-d H:i:s', time()));
            return array('success' => true, 'message' => 'Accès à la page ajouté avec succès.');
        }

        return array('success' => false, 'message' => 'Erreur lors de l\'ajout de l\'accès.');
    }

    /**
     * Ajoute un utilisateur à un rôle
     * @param array $data Données du formulaire ($_POST)
     * @param int $roleId ID du rôle
     * @return array Résultat avec 'success' (bool) et 'message' (string)
     */
    public static function addUserRole($data, $roleId)
    {
        if (!isset($data['user_id']) || !$data['user_id']) {
            return array('success' => false, 'message' => 'Veuillez sélectionner un utilisateur.');
        }

        $userId = (int)$data['user_id'];

        // Vérifier si l'utilisateur existe
        if (!User::getUser($userId)) {
            return array('success' => false, 'message' => 'Utilisateur non trouvé.');
        }

        // Vérifier si le rôle existe
        if (!Role::getRole($roleId)) {
            return array('success' => false, 'message' => 'Rôle non trouvé.');
        }

        // Vérifier si la relation existe déjà
        $query = Role::prepare_query(
            "SELECT COUNT(*) as count FROM user_roles WHERE user_id = ? AND role_id = ?",
            array($userId, $roleId)
        );
        $query = $query->fetch();
        if ($query['count'] > 0) {
            return array('success' => false, 'message' => 'Cet utilisateur a déjà ce rôle.');
        }

        // Ajouter la relation
        $query = Role::prepare_query(
            "INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)",
            array($userId, $roleId)
        );

        if ($query->rowCount() > 0) {
            $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
            Role::logRequest($userId, 'role_details.php', 'add_user_role');
            return array('success' => true, 'message' => 'Utilisateur ajouté au rôle avec succès.');
        }

        return array('success' => false, 'message' => 'Erreur lors de l\'ajout de l\'utilisateur.');
    }
}



class RoleProcessor
{
    /**
     * Ajoute un nouveau rôle
     * @param array $data Données du formulaire ($_POST)
     * @return array Résultat avec 'success' (bool) et 'message' (string)
     * @throws Exception
     */
    public static function addRole($data)
    {
        if (!isset($data['role_name']) || trim($data['role_name']) === '') {
            return array('success' => false, 'message' => 'Le nom du rôle est requis.');
        }

        $roleName = trim($data['role_name']);

        // Vérifier si le rôle existe déjà
        if (Role::getRoleByName($roleName)) {
            return array('success' => false, 'message' => 'Ce rôle existe déjà.');
        }

        // Créer le rôle
        $role = new Role(0, $roleName);
        $query = $role->ajouter();
        if ($query) {
            $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
            Log::logRequest($userId, 'role', 'add_role#'.$role->id, date("Y-m-d H:i:s", time()));
            return array('success' => true, 'message' => 'Rôle ajouté avec succès.');
        }

        return array('success' => false, 'message' => 'Erreur lors de l\'ajout du rôle.');
    }

    /**
     * Modifie un rôle existant
     * @param array $data Données du formulaire ($_POST)
     * @return array Résultat avec 'success' (bool) et 'message' (string)
     * @throws Exception
     */
    public static function updateRole($data)
    {
        if (!isset($data['role_id'], $data['role_name']) || trim($data['role_name']) === '') {
            return array('success' => false, 'message' => 'Le nom du rôle et l\'ID sont requis.');
        }

        $roleId = (int)$data['role_id'];
        $roleName = trim($data['role_name']);

        // Vérifier si le rôle existe
        $existingRole = Role::getRole($roleId);
        if (!$existingRole) {
            return array('success' => false, 'message' => 'Rôle non trouvé.');
        }

        // Vérifier si le nouveau nom est déjà utilisé par un autre rôle
        $otherRole = Role::getRoleByName($roleName);
        if ($otherRole && $otherRole['id'] != $roleId) {
            return array('success' => false, 'message' => 'Ce nom de rôle est déjà utilisé.');
        }

        // Mettre à jour le rôle
        $query = Role::prepare_query(
            "UPDATE roles SET nom = ? WHERE id = ?",
            array($roleName, $roleId)
        );

        if ($query->rowCount() > 0) {
            $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
            $log = new Log($userId, "page de role", "update_role", date('d-m-Y-H-i-s', time()));
            $log->ajouter();
            return array('success' => true, 'message' => 'Rôle modifié avec succès.');
        }

        return array('success' => false, 'message' => 'Aucune modification effectuée.');
    }

    /**
     * Supprime un rôle
     * @param int $roleId ID du rôle
     * @return array Résultat avec 'success' (bool) et 'message' (string)
     * @throws Exception
     */
    public static function deleteRole($roleId)
    {
        // Vérifier si le rôle existe
        if (!Role::getRole($roleId)) {
            return array('success' => false, 'message' => 'Rôle non trouvé.');
        }

        // Supprimer les relations associées et le rôle
        $success = Role::deleteById($roleId);
        if ($success) {
            $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
            Log::logRequest($userId, 'role', 'delete_role', date('d-m-Y-H-i-s', time()));
            return array('success' => true, 'message' => 'Rôle supprimé avec succès.');
        }

        return array('success' => false, 'message' => 'Erreur lors de la suppression du rôle.');
    }

    /**
     * Récupère un rôle par ID
     * @param int $roleId ID du rôle
     * @return array|null Données du rôle ou null si non trouvé
     */
    public static function getRole($roleId)
    {
        return Role::getRole($roleId);
    }

    /**
     * Récupère tous les rôles
     * @return array Liste des rôles
     * @throws Exception
     */
    public static function getAllRoles()
    {
        return Role::prepare_query("SELECT * FROM roles", array())->fetchAll();
    }

    public static function confirmDeleteRole($data)
    {
        if (!isset($data['role_id'], $data['confirm_role_name']) || trim($data['confirm_role_name']) === '') {
            return array('success' => false, 'message' => 'L\'ID du rôle et le nom de confirmation sont requis.');
        }

        $roleId = (int)$data['role_id'];
        $confirmRoleName = trim($data['confirm_role_name']);

        // Vérifier si le rôle existe
        $role = Role::getRole($roleId);
        if (!$role) {
            return array('success' => false, 'message' => 'Rôle non trouvé.');
        }

        // Vérifier si le nom saisi correspond
        if ($confirmRoleName !== $role['nom']) {
            return array('success' => false, 'message' => 'Le nom du rôle saisi est incorrect.');
        }

        // Supprimer les relations associées et le rôle
        $success = Role::deleteById($roleId);
        if ($success) {
            $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
            Log::logRequest($userId, 'role', 'delete_role#'.$roleId, date('d-m-Y-H-i-s', time()));
            return array('success' => true, 'message' => 'Rôle supprimé avec succès.');
        }

        return array('success' => false, 'message' => 'Erreur lors de la suppression du rôle.');
    }
}


require_once 'donnees/role.php';
require_once 'donnees/log.php';
require_once 'donnees/user.php';
require_once 'donnees/page.php';

class RoleDetailsProcessor
{
    /**
     * Récupère les utilisateurs ayant un rôle spécifique
     * @param int $roleId ID du rôle
     * @return array Liste des utilisateurs
     */
    public static function getUsersWithRole($roleId)
    {
        $query = Role::prepare_query(
            "SELECT u.* FROM users u JOIN user_roles ur ON u.id = ur.user_id WHERE ur.role_id = ?",
            array($roleId)
        );
        return $query->fetchAll();
    }

    /**
     * Récupère les pages accessibles par un rôle pour un AEP spécifique
     * @param int $roleId ID du rôle
     * @param int $aepId ID de l'AEP
     * @return array Liste des pages
     */
    public static function getPagesWithAccess($roleId)
    {
        $query = Role::prepare_query(
            "SELECT p.*, write_access FROM pages p JOIN page_role_aep pra ON p.id = pra.page_id WHERE pra.role_id = ?",
            array($roleId)
        );
        return $query->fetchAll();
    }

    /**
     * Récupère les pages non accessibles par un rôle pour un AEP spécifique
     * @param int $roleId ID du rôle
     * @param int $aepId ID de l'AEP
     * @return array Liste des pages disponibles
     */
    public static function getAvailablePages($roleId)
    {
        $query = Role::prepare_query(
            "SELECT p.*
                FROM pages p WHERE p.id NOT IN (
                SELECT pra.page_id FROM page_role_aep pra WHERE pra.role_id = ?
            )",
            array($roleId)
        );
        return $query->fetchAll();
    }/**
     * Récupère les pages non accessibles par un rôle pour un AEP spécifique
     * @param int $roleId ID du rôle
     * @param int $aepId ID de l'AEP
     * @return array Liste des pages disponibles
     */
    public static function getAvailablePagesWithPage($roleId)
    {
        $query = Role::prepare_query(
            "SELECT p.* , page_id, write_access 
                FROM pages p
                left join page_role_aep pra ON p.id = pra.page_id and pra.role_id = ?
            ",
            array($roleId)
        );
        return $query->fetchAll();
    }

    /**
     * Récupère les utilisateurs n'ayant pas un rôle spécifique
     * @param int $roleId ID du rôle
     * @return array Liste des utilisateurs disponibles
     */
    public static function getAvailableUsers($roleId)
    {
        $query = Role::prepare_query(
            "SELECT u.* FROM users u WHERE u.id NOT IN (
                SELECT ur.user_id FROM user_roles ur WHERE ur.role_id = ?
            )",
            array($roleId)
        );
        return $query->fetchAll();
    }

    /**
     * Ajoute un accès à une page pour un rôle et un AEP
     * @param array $data Données du formulaire ($_POST)
     * @param int $roleId ID du rôle
     * @param int $aepId ID de l'AEP
     * @return array Résultat avec 'success' (bool) et 'message' (string)
     */
    public static function addPageAccess($data)
    {
//        if (!isset($data['page_id']) || !$data['page_id']) {
//            return array('success' => false, 'message' => 'Veuillez sélectionner une page.');
//        }

        $pageId = (int)$data['page_id'];
        $roleId = (int)$data['role_id'];
        $write_access = (int)$data['write_access'];

        // Vérifier si la page existe
        if (!Page::getPage($pageId)) {
            return array('success' => false, 'message' => 'Page non trouvée.');
        }

        // Vérifier si le rôle existe
        var_dump($roleId);
        if (!Role::getRole($roleId)) {
            return array('success' => false, 'message' => 'Rôle non trouvé.');
        }

        // Vérifier si l'accès existe déjà
        $query = Role::prepare_query(
            "SELECT COUNT(*) as count FROM page_role_aep WHERE page_id = ? AND role_id = ?",
            array($pageId, $roleId)
        );
        $query = $query->fetch();
        if ($query['count'] > 0) {
            return array('success' => false, 'message' => 'Cet accès existe déjà.');
        }

        // Ajouter l'accès
        $query = Role::prepare_query(
            "INSERT INTO page_role_aep (page_id, role_id, write_access) VALUES (?, ?, ?)",
            array($pageId, $roleId, $write_access)
        );

        if ($query->rowCount() > 0) {
            $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
            Log::logRequest($userId, 'role_detail', 'add_page_access#' . $roleId . '#' . $_SESSION['id_aep'], date('Y-m-d H:i:s', time()));
            return array('success' => true, 'message' => 'Accès à la page ajouté avec succès.');
        }

        return array('success' => false, 'message' => 'Erreur lors de l\'ajout de l\'accès.');
    }

    /**
     * Ajoute un utilisateur à un rôle
     * @param array $data Données du formulaire ($_POST)
     * @param int $roleId ID du rôle
     * @return array Résultat avec 'success' (bool) et 'message' (string)
     */
    public static function addUserRole($data, $roleId)
    {
        if (!isset($data['user_id']) || !$data['user_id']) {
            return array('success' => false, 'message' => 'Veuillez sélectionner un utilisateur.');
        }

        $userId = (int)$data['user_id'];

        // Vérifier si l'utilisateur existe
        if (!User::getUser($userId)) {
            return array('success' => false, 'message' => 'Utilisateur non trouvé.');
        }

        // Vérifier si le rôle existe
        if (!Role::getRole($roleId)) {
            return array('success' => false, 'message' => 'Rôle non trouvé.');
        }

        // Vérifier si la relation existe déjà
        $query = Role::prepare_query(
            "SELECT COUNT(*) as count FROM user_roles WHERE user_id = ? AND role_id = ?",
            array($userId, $roleId)
        );
        $query = $query->fetch();
        if ($query['count'] > 0) {
            return array('success' => false, 'message' => 'Cet utilisateur a déjà ce rôle.');
        }

        // Ajouter la relation
        $query = Role::prepare_query(
            "INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)",
            array($userId, $roleId)
        );

        if ($query->rowCount() > 0) {
            $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
            Log::logRequest($userId, 'role_detail', 'add_user_role#' . $roleId, date('Y-m-d H:i:s', time()));
            return array('success' => true, 'message' => 'Utilisateur ajouté au rôle avec succès.');
        }

        return array('success' => false, 'message' => 'Erreur lors de l\'ajout de l\'utilisateur.');
    }

    /**
     * Retire un accès à une page pour un rôle et un AEP
     * @param array $data Données du formulaire ($_POST)
     * @param int $roleId ID du rôle
     * @param int $aepId ID de l'AEP
     * @return array Résultat avec 'success' (bool) et 'message' (string)
     */
    public static function removePageAccess($data, $roleId)
    {
        if (!isset($data['page_id'], $data['confirm_page_name']) || !$data['page_id'] || trim($data['confirm_page_name']) === '') {
            return array('success' => false, 'message' => 'L\'ID de la page et le libellé de confirmation sont requis.');
        }

        $pageId = (int)$data['page_id'];
        $confirmPageName = trim($data['confirm_page_name']);

        // Vérifier si la page existe
        $page = Page::getPage($pageId);
        if (!$page) {
            return array('success' => false, 'message' => 'Page non trouvée.');
        }

        // Vérifier si le rôle existe
        if (!Role::getRole($roleId)) {
            return array('success' => false, 'message' => 'Rôle non trouvé.');
        }

        // Vérifier si le libellé saisi correspond
        if ($confirmPageName !== $page['libelle']) {
            return array('success' => false, 'message' => 'Le libellé de la page saisi est incorrect.');
        }

        // Vérifier si l'accès existe
        $query = Role::prepare_query(
            "SELECT COUNT(*) as count FROM page_role_aep WHERE page_id = ? AND role_id = ?",
            array($pageId, $roleId)
        );
        $query = $query->fetch();
        if ($query['count'] == 0) {
            return array('success' => false, 'message' => 'Cet accès n\'existe pas.');
        }

        // Supprimer l'accès
        $query = Role::prepare_query(
            "DELETE FROM page_role_aep WHERE page_id = ? AND role_id = ?",
            array($pageId, $roleId)
        );

        if ($query->rowCount() > 0) {
            $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
            Log::logRequest($userId, 'role_detail', 'remove_page_access#' . $roleId , date('Y-m-d H:i:s', time()));
            return array('success' => true, 'message' => 'Accès à la page retiré avec succès.');
        }

        return array('success' => false, 'message' => 'Erreur lors du retrait de l\'accès.');
    }

    /**
     * Retire un utilisateur d'un rôle
     * @param array $data Données du formulaire ($_POST)
     * @param int $roleId ID du rôle
     * @return array Résultat avec 'success' (bool) et 'message' (string)
     */
    public static function removeUserRole($data, $roleId)
    {
        if (!isset($data['user_id'], $data['confirm_user_name']) || !$data['user_id'] || trim($data['confirm_user_name']) === '') {
            return array('success' => false, 'message' => 'L\'ID de l\'utilisateur et le nom de confirmation sont requis.');
        }

        $userId = (int)$data['user_id'];
        $confirmUserName = trim($data['confirm_user_name']);

        // Vérifier si l'utilisateur existe
        $user = User::getUser($userId);
        if (!$user) {
            return array('success' => false, 'message' => 'Utilisateur non trouvé.');
        }

        // Vérifier si le rôle existe
        if (!Role::getRole($roleId)) {
            return array('success' => false, 'message' => 'Rôle non trouvé.');
        }

        // Vérifier si le nom saisi correspond
        $fullName = $user['nom'] . ' ' . $user['prenom'];
        if ($confirmUserName !== $fullName) {
            return array('success' => false, 'message' => 'Le nom de l\'utilisateur saisi est incorrect.');
        }

        // Vérifier si la relation existe
        $query = Role::prepare_query(
            "SELECT COUNT(*) as count FROM user_roles WHERE user_id = ? AND role_id = ?",
            array($userId, $roleId)
        );
        $query = $query->fetch();
        if ($query['count'] == 0) {
            return array('success' => false, 'message' => 'Cet utilisateur n\'a pas ce rôle.');
        }

        // Supprimer la relation
        $query = Role::prepare_query(
            "DELETE FROM user_roles WHERE user_id = ? AND role_id = ?",
            array($userId, $roleId)
        );

        if ($query->rowCount() > 0) {
            $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
            Log::logRequest($userId, 'role_detail', 'remove_user_role#' . $roleId, date('Y-m-d H:i:s', time()));
            return array('success' => true, 'message' => 'Utilisateur retiré du rôle avec succès.');
        }

        return array('success' => false, 'message' => 'Erreur lors du retrait de l\'utilisateur.');
    }

    public static function updatePageAccess($postData, $roleId) {
//        var_dump($postData);
//        exit();
        try {
            // Supprimer tous les accès existants pour ce rôle
            $query = Manager::prepare_query(
                "DELETE FROM page_role_aep WHERE role_id = ?",
                array($roleId)
            );

            // Ajouter les nouveaux accès pour les pages cochées
            if (isset($postData['pages']) && is_array($postData['pages'])) {
                foreach ($postData['pages'] as $pageId => $pageData) {
                    if (isset($pageData['selected']) && $pageData['selected'] == '1') {
                        $writeAccess = isset($pageData['write_access']) ? (int)$pageData['write_access'] : 0;
                        $query = Manager::prepare_query(
                            "INSERT INTO page_role_aep (role_id, page_id, write_access) VALUES (?, ?, ?)",
                            array($roleId, $pageId, $writeAccess)
                        );
                    }
                }
            }

            return array('success' => true);
        } catch (Exception $e) {
            return array('success' => false, 'message' => 'Erreur lors de la mise à jour des accès : ' . $e->getMessage());
        }
    }


}

