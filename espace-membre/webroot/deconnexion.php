<?php
declare(strict_types=1);
define('RACINE', dirname(__DIR__));
require RACINE . '/app/bdd.php';
require RACINE . '/app/auth.php';
require RACINE . '/app/outils.php';

if (connecte()) { journaliser('deconnexion'); }
deconnecter();
rediriger('/connexion.php');
