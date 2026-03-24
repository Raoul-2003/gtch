<?php
session_start();
require 'configuration.php';

if (!empty($_SESSION['utilisateur'])) {
    journaliser($pdo, 'DECONNEXION', 'utilisateurs', 'Déconnexion volontaire');
}

session_destroy();
header('Location: connexion.php');
exit;
