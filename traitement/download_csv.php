<?php

@include_once("../donnees/Abones.php");
@include_once("donnees/Abones.php");

if(!isset($_GET['file_name']))
    header("Location: ../index.php");
$fileName = $_GET['file_name'];
$repertoire = '../tmp/'.$_SESSION['user_id'];
$fileName = $repertoire.'/'.$fileName;
//var_dump($fileName);
Abones::telecharger($fileName);
