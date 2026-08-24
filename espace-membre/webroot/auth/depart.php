<?php
/* ============================================================
   MARLOWE VINEYARD — Départ vers Discord

   Cette page n'affiche rien : elle prépare un « état » (un
   numéro aléatoire à usage unique qui prouve, au retour, que
   c'est bien nous qui avons lancé la demande) puis envoie la
   personne sur Discord.

   Trois raisons d'arriver ici :
   · se connecter, tout simplement ;
   · lier son Discord à son profil, quand on est déjà entré
     avec son code d'accès ;
   · ouvrir son accès depuis un lien d'invitation.
   ============================================================ */
declare(strict_types=1);
define('RACINE', dirname(__DIR__, 2));
require RACINE . '/app/bdd.php';
require RACINE . '/app/auth.php';
require RACINE . '/app/outils.php';
require RACINE . '/app/discord.php';

date_default_timezone_set(configuration()['fuseau'] ?? 'Europe/Paris');
demarrerSession();

if (!discordPret()) {
    http_response_code(503);
    exit('La connexion Discord n\'est pas encore configurée. Voir la page _discord.php.');
}

$intention  = 'connexion';
$profilId   = null;
$invitation = null;

/* Cas 1 : la personne est déjà entrée avec son code et veut rattacher son Discord. */
if (!empty($_GET['lier']) && connecte()) {
    $intention = 'liaison';
    $profilId  = (int) utilisateur()['id'];
}

/* Cas 2 : elle arrive d'un lien d'invitation. On revérifie ici que ce lien vaut
   encore quelque chose, pour ne pas envoyer quelqu'un sur Discord pour rien. */
if (!empty($_GET['invitation'])) {
    $jetonBrut = (string) $_GET['invitation'];
    if (!preg_match('/^[a-f0-9]{64}$/', $jetonBrut)) {
        exit('Lien d\'invitation invalide.');
    }
    $inv = ligne(
        'SELECT id, profil_id FROM invitations
         WHERE jeton_hash = ? AND utilise_le IS NULL AND expire_le > NOW()',
        [hash('sha256', $jetonBrut)]
    );
    if (!$inv) {
        exit('Ce lien d\'invitation a expiré ou a déjà servi. Demande-en un nouveau à la direction.');
    }
    $intention  = 'invitation';
    $profilId   = (int) $inv['profil_id'];
    $invitation = (int) $inv['id'];
}

$etat = bin2hex(random_bytes(24));

$_SESSION['discord_depart'] = [
    'etat'       => $etat,
    'intention'  => $intention,
    'profil_id'  => $profilId,
    'invitation' => $invitation,
    'expire'     => time() + 600,   // dix minutes, largement suffisant
];

rediriger(discordUrlAutorisation($etat));
