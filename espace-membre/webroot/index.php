<?php
/* ============================================================
   MARLOWE VINEYARD — point d'entrée de l'espace membre
   Toutes les pages passent par ici : /index.php?p=effectif
   ============================================================ */
declare(strict_types=1);
define('RACINE', dirname(__DIR__));

require RACINE . '/app/bdd.php';
require RACINE . '/app/auth.php';
require RACINE . '/app/outils.php';
require RACINE . '/app/discord.php';

date_default_timezone_set(configuration()['fuseau'] ?? 'Europe/Paris');

/* Personne en base ? On envoie vers la création du premier compte. */
if ((int) valeur('SELECT COUNT(*) FROM profils') === 0) {
    rediriger('/_premier-compte.php');
}

if (!connecte()) { rediriger('/connexion.php'); }

$p = (string) ($_GET['p'] ?? 'tableau');
if (!array_key_exists($p, ecrans())) { $p = 'tableau'; }
if (!peutVoir($p)) {
    foreach (array_keys(ecrans()) as $c) { if (peutVoir($c)) { $p = $c; break; } }
}

$ecranActif = $p;
$fichier    = __DIR__ . '/pages/' . $p . '.php';

if (is_file($fichier)) {
    require $fichier;
} else {
    $titre     = ecrans()[$p][0];
    $sousTitre = 'Écran prévu, pas encore construit';
    require RACINE . '/app/vues/entete.php';
    echo '<div class="vide"><h2>Bientôt ici</h2><p>Cet écran fait partie du plan mais n\'a pas encore été développé. '
       . 'Il arrive dans une prochaine étape.</p></div>';
    require RACINE . '/app/vues/pied.php';
}
