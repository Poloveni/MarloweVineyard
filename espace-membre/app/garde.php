<?php
/* ============================================================
   MARLOWE VINEYARD — Garde des pages d'administration

   Certaines pages (_migrer.php, _discord.php) doivent rester
   ouvertes tant que l'application n'existe pas encore : au tout
   premier démarrage, aucun compte n'a été créé, donc personne
   ne peut se connecter pour y accéder.

   Dès qu'un compte existe, elles se referment et deviennent
   réservées à la direction. Sans cette garde, n'importe qui
   connaissant leur adresse pourrait reconfigurer l'application.

   Principe de prudence : en cas de doute, on REFUSE. La porte ne
   s'ouvre que dans deux situations parfaitement identifiées —
   la base n'existe pas encore, ou elle ne contient aucun compte.
   ============================================================ */
declare(strict_types=1);

/** Refuse l'accès et arrête tout. */
function gardeRefuser(string $raison): void
{
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    exit('<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<meta name="robots" content="noindex, nofollow">'
       . '<title>Accès refusé</title>'
       . '<style>body{margin:0;min-height:100vh;display:grid;place-items:center;'
       . 'background:#061713;color:#eae4d5;font-family:system-ui,sans-serif;'
       . 'text-align:center;padding:2rem;line-height:1.6}'
       . 'a{color:#e6c976}p{color:#9aaba3}</style></head><body><div>'
       . '<h1 style="font-size:1.4rem;margin:0 0 .6rem">Page réservée à la direction</h1>'
       . '<p style="margin:0 0 1.2rem">' . htmlspecialchars($raison) . '</p>'
       . '<a href="/connexion.php">Se connecter</a>'
       . '</div></body></html>');
}

/**
 * Laisse passer si l'application n'est pas encore installée,
 * sinon exige une session de direction.
 */
function exigerDirectionSiInstalle(string $racine): void
{
    if (!defined('RACINE')) { define('RACINE', $racine); }

    require_once $racine . '/app/bdd.php';
    require_once $racine . '/app/auth.php';
    require_once $racine . '/app/outils.php';

    try {
        $nombreDeComptes = (int) valeur('SELECT COUNT(*) FROM profils');

    } catch (PDOException $e) {
        /* Seules deux erreurs signifient « pas encore installé » :
           la table profils n'existe pas (42S02), ou la base elle-même
           est absente / injoignable (08006, 42000, 1049...).
           Toute autre erreur est suspecte : on refuse. */
        $etat = (string) $e->getCode();
        $bootstrap = in_array($etat, ['42S02', '42000', '1049', '2002', '08006', 'HY000'], true);
        if ($bootstrap) { return; }
        gardeRefuser("La base de données a renvoyé une erreur inattendue.");

    } catch (Throwable $e) {
        gardeRefuser("Impossible de vérifier les droits d'accès.");
    }

    if ($nombreDeComptes === 0) { return; }

    if (!connecte()) {
        gardeRefuser("Cette page d'administration ne s'ouvre qu'à une session de direction.");
    }
    if (role() !== 'direction') {
        gardeRefuser("Ton rôle actuel ne donne pas accès aux pages d'administration.");
    }
}
