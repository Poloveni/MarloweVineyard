<?php
/* Petits outils partagés par toutes les pages. */
declare(strict_types=1);

/** Échappe pour affichage HTML. À utiliser sur TOUTE donnée venant de la base. */
function e($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** 12345 -> "12 345" */
function nb($v, int $decimales = 0): string
{
    return number_format((float) $v, $decimales, ',', ' ');
}

/** 12345 -> "12 345 $" */
function argent($v): string
{
    return nb($v) . ' $';
}

function dateFr(?string $d, bool $avecHeure = false): string
{
    if (!$d) { return '—'; }
    try { $t = new DateTimeImmutable($d); } catch (Throwable $e) { return '—'; }
    return $t->format($avecHeure ? 'd/m/Y à H:i' : 'd/m/Y');
}

function rediriger(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/** Message affiché une seule fois après une redirection. */
function message(string $texte, string $type = 'ok'): void
{
    demarrerSession();
    $_SESSION['flash'][] = ['texte' => $texte, 'type' => $type];
}

function messages(): array
{
    demarrerSession();
    $m = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $m;
}

/** Écrit une ligne dans le journal d'activité. */
function journaliser(string $action, ?string $cible = null, ?string $details = null): void
{
    $u = utilisateur();
    req('INSERT INTO journal (profil_id, auteur, action, cible, details, ip) VALUES (?,?,?,?,?,?)', [
        $u['id'] ?? null,
        $u['nom_rp'] ?? ($u['pseudo_discord'] ?? 'système'),
        $action,
        $cible,
        $details,
        $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}

function champ(string $nom, $defaut = '')
{
    return $_POST[$nom] ?? $_GET[$nom] ?? $defaut;
}

function champTexte(string $nom, string $defaut = ''): string
{
    return trim((string) champ($nom, $defaut));
}

function champEntier(string $nom, int $defaut = 0): int
{
    $v = champ($nom, $defaut);
    return is_numeric($v) ? (int) $v : $defaut;
}

/** Adresse publique du site, sans barre finale.
    Priorité au réglage saisi dans Paramètres, sinon l'adresse consultée. */
function urlBase(): string
{
    $fige = trim((string) reglage('url_publique', ''));
    if ($fige !== '') { return rtrim($fige, '/'); }
    return rtrim(configuration()['url_base'], '/');
}

/** Unité de production paramétrée : bouteilles, caisses… */
function unite(bool $pluriel = true): string
{
    $u = (string) reglage('unite_production', 'unités');
    return $pluriel ? $u : rtrim($u, 's');
}

function page403(): void
{
    $titre = 'Accès refusé';
    include RACINE . '/app/vues/entete.php';
    echo '<div class="vide"><h2>Cet écran ne t\'est pas ouvert</h2>'
       . '<p>Ton rôle actuel est <b>' . e(role()) . '</b>. Si c\'est une erreur, demande à la direction de revoir tes accès.</p></div>';
    include RACINE . '/app/vues/pied.php';
}
