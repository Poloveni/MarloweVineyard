<?php
/* ============================================================
   MARLOWE VINEYARD — Configuration

   Ce fichier est HORS du dossier "webroot" : aucun navigateur ne
   peut le lire. Il ne contient AUCUN secret en clair : les secrets
   vivent dans secrets.php, généré automatiquement par la page
   d'installation.

   Tu n'as normalement jamais besoin de modifier ce fichier.
   ============================================================ */

$secrets = is_file(__DIR__ . '/secrets.php') ? require __DIR__ . '/secrets.php' : [];

/* Adresse publique du site. Déterminée automatiquement à partir de
   l'adresse consultée : le jour où l'application déménage, il n'y a
   rien à modifier ici. On peut la figer dans Paramètres > Adresse
   publique une fois le vrai nom de domaine en place. */
$schema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$urlBase = rtrim((string) ($secrets['url_publique'] ?? ($schema . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'))), '/');

return [

    /* ---- Base de données MySQL (onglet "Databases" du panneau) ---- */
    'bdd' => [
        'hote'         => $secrets['bdd_hote'] ?? '172.17.0.1',
        'port'         => (int) ($secrets['bdd_port'] ?? 3306),
        'base'         => $secrets['bdd_base'] ?? 's52_marlowe',
        'utilisateur'  => $secrets['bdd_utilisateur'] ?? 'u52_gvjTQfWDrI',
        'mot_de_passe' => $secrets['bdd_mot_de_passe'] ?? '',
    ],

    /* ---- Application Discord (créée à l'étape suivante) ---- */
    'discord' => [
        'client_id'     => $secrets['discord_client_id']     ?? '',
        'client_secret' => $secrets['discord_client_secret'] ?? '',
        'guild_id'      => $secrets['discord_guild_id']      ?? '',
        'redirection'   => $urlBase . '/auth/retour.php',
    ],

    /* ---- Clé de chiffrement interne, générée automatiquement ---- */
    'cle_secrete' => $secrets['cle_secrete'] ?? '',

    /* ---- Divers ---- */
    'url_base' => $urlBase,
    'fuseau'  => 'Europe/Paris',
    'version' => '0.1',
];
