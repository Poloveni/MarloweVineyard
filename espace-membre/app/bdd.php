<?php
/* Connexion unique à la base de données. */
declare(strict_types=1);

function bdd(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) { return $pdo; }

    $cfg = configuration();
    $b   = $cfg['bdd'];

    $pdo = new PDO(
        "mysql:host={$b['hote']};port={$b['port']};dbname={$b['base']};charset=utf8mb4",
        $b['utilisateur'],
        $b['mot_de_passe'],
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
    $pdo->exec("SET time_zone = '+02:00'");
    return $pdo;
}

function configuration(): array
{
    static $cfg = null;
    if ($cfg === null) { $cfg = require RACINE . '/config.php'; }
    return $cfg;
}

/** Exécute une requête préparée et renvoie l'instruction. */
function req(string $sql, array $params = []): PDOStatement
{
    $st = bdd()->prepare($sql);
    $st->execute($params);
    return $st;
}

/** Première ligne, ou null. */
function ligne(string $sql, array $params = []): ?array
{
    $r = req($sql, $params)->fetch();
    return $r === false ? null : $r;
}

/** Toutes les lignes. */
function lignes(string $sql, array $params = []): array
{
    return req($sql, $params)->fetchAll();
}

/** Première colonne de la première ligne. */
function valeur(string $sql, array $params = [], $defaut = null)
{
    $v = req($sql, $params)->fetchColumn();
    return $v === false ? $defaut : $v;
}

/* ---------- Réglages de l'entreprise ---------- */

function reglages(bool $recharger = false): array
{
    static $cache = null;
    if ($cache === null || $recharger) {
        $cache = [];
        foreach (lignes('SELECT cle, valeur FROM parametres') as $r) {
            $cache[$r['cle']] = $r['valeur'];
        }
    }
    return $cache;
}

function reglage(string $cle, $defaut = null)
{
    $r = reglages();
    return array_key_exists($cle, $r) ? $r[$cle] : $defaut;
}

function reglageJson(string $cle, $defaut = [])
{
    $v = reglage($cle);
    if ($v === null || $v === '') { return $defaut; }
    $d = json_decode($v, true);
    return $d === null ? $defaut : $d;
}

function definirReglage(string $cle, string $valeur): void
{
    req('INSERT INTO parametres (cle, valeur) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)', [$cle, $valeur]);
    reglages(true);
}

/* ---------- Semaine de travail ---------- */

/** Renvoie la semaine en cours, en la créant au besoin. */
function semaineCourante(): array
{
    $aujourdhui = new DateTimeImmutable('now');
    return semainePour($aujourdhui);
}

function semainePour(DateTimeImmutable $jour): array
{
    $annee  = (int) $jour->format('o');   // année ISO
    $numero = (int) $jour->format('W');

    $s = ligne('SELECT * FROM semaines WHERE annee = ? AND numero = ?', [$annee, $numero]);
    if ($s) { return $s; }

    $lundi    = $jour->setISODate($annee, $numero, 1);
    $dimanche = $jour->setISODate($annee, $numero, 7);

    req('INSERT INTO semaines (annee, numero, debut, fin, objectif) VALUES (?, ?, ?, ?, ?)', [
        $annee,
        $numero,
        $lundi->format('Y-m-d'),
        $dimanche->format('Y-m-d'),
        (int) reglage('objectif_semaine', '0'),
    ]);

    return ligne('SELECT * FROM semaines WHERE annee = ? AND numero = ?', [$annee, $numero]);
}
