<?php
/* Sessions, rôles et permissions. */
declare(strict_types=1);

/* ------------------------------------------------------------
   La carte des écrans. Chaque entrée :
     titre, section, et les rôles qui y ont accès.
   'ro' = lecture seule.
   ------------------------------------------------------------ */
function ecrans(): array
{
    return [
        'tableau'    => ['Vue d\'ensemble', 'Pilotage',    ['direction' => 'x', 'rh' => 'x', 'comptable' => 'x', 'commercial' => 'ro']],
        'semaine'    => ['Semaine & primes', 'Pilotage',   ['direction' => 'x', 'rh' => 'x', 'comptable' => 'x']],
        'historique' => ['Historique',      'Pilotage',    ['direction' => 'x', 'rh' => 'ro', 'comptable' => 'ro']],

        'effectif'   => ['Effectif',        'Gestion',     ['direction' => 'x', 'rh' => 'x', 'comptable' => 'ro']],
        'production' => ['Productions',     'Gestion',     ['direction' => 'x', 'rh' => 'x']],
        'rh'         => ['Feuille RH',      'Gestion',     ['direction' => 'x', 'rh' => 'x']],
        'eligibilite'=> ['Éligibilité',     'Gestion',     ['direction' => 'x', 'rh' => 'x', 'comptable' => 'ro', 'commercial' => 'ro']],
        'blacklist'  => ['Blacklist',       'Gestion',     ['direction' => 'x', 'rh' => 'x']],

        'factures'   => ['Facturation',     'Commerce',    ['direction' => 'x', 'commercial' => 'x', 'comptable' => 'ro']],
        'clients'    => ['Clients',         'Commerce',    ['direction' => 'x', 'commercial' => 'x', 'comptable' => 'ro']],
        'bilan'      => ['Bilan comptable', 'Commerce',    ['direction' => 'x', 'comptable' => 'x']],
        'frecues'    => ['Factures reçues', 'Commerce',    ['direction' => 'x', 'comptable' => 'x']],

        'moi'        => ['Ma semaine',      'Personnel',   ['direction' => 'x', 'rh' => 'x', 'comptable' => 'x', 'commercial' => 'x', 'membre' => 'x']],
        'agenda'     => ['Agenda',          'Personnel',   ['direction' => 'x', 'rh' => 'x', 'comptable' => 'x', 'commercial' => 'x', 'membre' => 'ro']],

        'journal'    => ['Journal',         'Système',     ['direction' => 'x']],
        'sauvegarde' => ['Sauvegarde',      'Système',     ['direction' => 'x']],
        'parametres' => ['Paramètres',      'Système',     ['direction' => 'x']],
    ];
}

function demarrerSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) { return; }
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('marlowe');
    session_start();
}

function utilisateur(): ?array
{
    static $u = null;
    static $cherche = false;

    if ($cherche) { return $u; }
    $cherche = true;

    demarrerSession();
    if (empty($_SESSION['profil_id'])) { return null; }

    $u = ligne('SELECT p.*, g.nom AS grade_nom, g.quota AS grade_quota, g.couleur AS grade_couleur
                FROM profils p LEFT JOIN grades g ON g.id = p.grade_id
                WHERE p.id = ? AND p.actif = 1', [(int) $_SESSION['profil_id']]);

    if (!$u) { $_SESSION = []; }
    return $u;
}

function connecte(): bool { return utilisateur() !== null; }

function role(): string
{
    $u = utilisateur();
    return $u['role_site'] ?? '';
}

/** 'x' accès complet, 'ro' lecture seule, '' aucun accès. */
function acces(string $ecran): string
{
    $u = utilisateur();
    if (!$u) { return ''; }
    $def = ecrans()[$ecran] ?? null;
    if (!$def) { return ''; }
    return $def[2][$u['role_site']] ?? '';
}

function peutVoir(string $ecran): bool   { return acces($ecran) !== ''; }
function peutModifier(string $ecran): bool { return acces($ecran) === 'x'; }

/** Bloque l'accès à un écran, ou à une action de modification. */
function exigerAcces(string $ecran, bool $modification = false): void
{
    if (!connecte()) { rediriger('/connexion.php'); }
    $a = acces($ecran);
    if ($a === '' || ($modification && $a !== 'x')) {
        http_response_code(403);
        page403();
        exit;
    }
}

function connecterProfil(int $profilId): void
{
    demarrerSession();
    session_regenerate_id(true);
    $_SESSION['profil_id'] = $profilId;
    req('UPDATE profils SET derniere_connexion = NOW() WHERE id = ?', [$profilId]);
}

function deconnecter(): void
{
    demarrerSession();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        setcookie(session_name(), '', time() - 42000, '/');
    }
    session_destroy();
}

/* ---------- Jeton anti-piégeage des formulaires ---------- */

function jeton(): string
{
    demarrerSession();
    if (empty($_SESSION['jeton'])) {
        $_SESSION['jeton'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['jeton'];
}

function verifierJeton(): void
{
    demarrerSession();
    $envoye = (string) ($_POST['jeton'] ?? '');
    if (!hash_equals((string) ($_SESSION['jeton'] ?? ''), $envoye)) {
        http_response_code(400);
        exit('Formulaire expiré. Reviens en arrière et recommence.');
    }
}
