<?php
require_once __DIR__ . '/_guard.php';
traitement_guard();

@include_once("../donnees/Abones.php");
@include_once("donnees/Abones.php");

if (!isset($_GET['file_name'])) {
    header('Location: ../index.php');
    exit;
}

$fileBase = basename((string) $_GET['file_name']);
if ($fileBase === '' || $fileBase !== (string) $_GET['file_name']) {
    header('Location: ../index.php');
    exit;
}

$userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
if ($userId <= 0) {
    header('Location: ../index.php?page=login&error=access_denied');
    exit;
}

$dir = realpath(__DIR__ . '/../tmp/' . $userId);
if ($dir === false || !is_dir($dir)) {
    header('Location: ../index.php');
    exit;
}

$filePath = $dir . DIRECTORY_SEPARATOR . $fileBase;
$resolved = realpath($filePath);
if ($resolved === false || !is_file($resolved) || strpos($resolved, $dir) !== 0) {
    header('Location: ../index.php');
    exit;
}

Abones::telecharger($resolved);
