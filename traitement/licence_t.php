<?php

@include_once(__DIR__ . '/../donnees/app_licence.php');

class LicenceT
{
    /**
     * @return array success, message
     */
    public static function handleImportUpload()
    {
        if (!isset($_FILES['licence_file']) || !is_array($_FILES['licence_file'])) {
            return array('success' => false, 'message' => 'Aucun fichier sélectionné.');
        }

        $file = $_FILES['licence_file'];
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return array('success' => false, 'message' => 'Erreur lors du téléversement du fichier.');
        }

        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return array('success' => false, 'message' => 'Fichier upload invalide.');
        }

        $name = isset($file['name']) ? strtolower((string) $file['name']) : '';
        if ($name !== '' && substr($name, -4) !== '.lic') {
            return array('success' => false, 'message' => 'Le fichier doit avoir l\'extension .lic');
        }

        return app_licence()->importFromPath($file['tmp_name']);
    }
}
