<?php
/* ============================================================
   MARLOWE VINEYARD — Retour de Discord

   Discord renvoie la personne ici avec un code à usage unique.
   On l'échange contre un laissez-passer, on demande à Discord
   qui elle est et si elle est bien sur le serveur du domaine,
   puis on ouvre la session.

   Règle de sécurité tenue de bout en bout : un compte Discord
   inconnu n'ouvre JAMAIS un profil tout seul. Il faut soit un
   profil déjà rattaché à ce compte, soit une invitation, soit
   une demande de liaison faite depuis une session ouverte.
   ============================================================ */
declare(strict_types=1);
define('RACINE', dirname(__DIR__, 2));
require RACINE . '/app/bdd.php';
require RACINE . '/app/auth.php';
require RACINE . '/app/outils.php';
require RACINE . '/app/discord.php';

date_default_timezone_set(configuration()['fuseau'] ?? 'Europe/Paris');
demarrerSession();

/* ------------------------------------------------------------
   Affichage d'une fin de parcours, réussie ou non
   ------------------------------------------------------------ */
function sortie(string $titre, string $texte, string $ton = 'ko', ?string $lien = null): void
{
    $lien = $lien ?? '/connexion.php';
    http_response_code($ton === 'ko' ? 400 : 200);
    ?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= htmlspecialchars($titre) ?> — Marlowe Vineyard</title>
<link rel="icon" href="/assets/favicon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  *,*::before,*::after{box-sizing:border-box}
  body{margin:0;min-height:100vh;display:grid;place-items:center;padding:2rem 1.2rem;
       background:radial-gradient(1100px 620px at 50% -12%, #14382d 0%, #061713 62%);
       color:#eae4d5;font-family:'Inter',system-ui,sans-serif;line-height:1.6}
  main{width:min(480px,100%);background:rgba(11,38,32,.86);border:1px solid #1e3a31;
       border-radius:18px;padding:2.4rem 2.2rem;box-shadow:0 28px 80px rgba(0,0,0,.55)}
  .marque{font-size:.74rem;letter-spacing:.34em;text-transform:uppercase;color:#e6c976;margin:0 0 .6rem}
  h1{font-family:'Cormorant Garamond',Georgia,serif;font-weight:700;font-size:1.85rem;margin:0 0 .7rem}
  p{margin:0 0 1.2rem;color:#9aaba3;font-size:.95rem}
  .pastille{display:inline-block;width:10px;height:10px;border-radius:50%;margin-right:.5rem;
            background:<?= $ton === 'ok' ? '#5fae83' : '#d1706c' ?>}
  a.btn{display:inline-block;padding:.8rem 1.6rem;border-radius:999px;text-decoration:none;
        background:linear-gradient(135deg,#e6c976,#c9a227);color:#241a04;font-weight:600}
</style>
</head>
<body>
<main>
  <p class="marque">Marlowe Vineyard</p>
  <h1><span class="pastille"></span><?= htmlspecialchars($titre) ?></h1>
  <p><?= $texte ?></p>
  <a class="btn" href="<?= htmlspecialchars($lien) ?>">Continuer</a>
</main>
</body>
</html><?php
    exit;
}

/* ------------------------------------------------------------
   1. Vérifications d'entrée
   ------------------------------------------------------------ */

if (!discordPret()) {
    sortie('Discord n\'est pas configuré', 'Les identifiants de l\'application Discord n\'ont pas encore été enregistrés.');
}

$depart = $_SESSION['discord_depart'] ?? null;
unset($_SESSION['discord_depart']);          // à usage strictement unique

if (!empty($_GET['error'])) {
    sortie('Connexion annulée',
        'Tu as refusé l\'autorisation sur Discord, ou Discord l\'a refusée. Rien n\'a été enregistré : tu peux réessayer quand tu veux.');
}

if (!is_array($depart) || $depart['expire'] < time()) {
    sortie('Demande expirée',
        'Cette demande de connexion a plus de dix minutes, ou elle n\'a pas commencé sur ce site. Repars de la page de connexion.');
}

$etatRecu = (string) ($_GET['state'] ?? '');
if ($etatRecu === '' || !hash_equals((string) $depart['etat'], $etatRecu)) {
    sortie('Demande non reconnue',
        'Le jeton de sécurité ne correspond pas. C\'est la protection qui empêche quelqu\'un de te faire ouvrir une session à ton insu. Recommence depuis la page de connexion.');
}

$code = (string) ($_GET['code'] ?? '');
if ($code === '') {
    sortie('Réponse incomplète', 'Discord n\'a pas renvoyé de code d\'autorisation.');
}

/* ------------------------------------------------------------
   2. Échange du code, puis identité
   ------------------------------------------------------------ */

$jetons = discordEchangerCode($code);
if ($jetons['code'] !== 200 || empty($jetons['corps']['access_token'])) {
    $detail = htmlspecialchars((string) ($jetons['corps']['error_description'] ?? $jetons['erreur'] ?? 'réponse inattendue'));
    sortie('Discord a refusé l\'échange',
        'Le plus souvent, c\'est que l\'adresse de retour déclarée sur le portail Discord ne correspond pas, au caractère près, à celle du site.<br><br><small>Détail technique : ' . $detail . '</small>');
}

$acces   = (string) $jetons['corps']['access_token'];
$refresh = (string) ($jetons['corps']['refresh_token'] ?? '');
$duree   = (int) ($jetons['corps']['expires_in'] ?? 604800);

$rMoi = discordMoi($acces);
if ($rMoi['code'] !== 200 || empty($rMoi['corps']['id'])) {
    sortie('Identité illisible', 'Discord n\'a pas voulu nous dire qui tu es. Réessaie dans un instant.');
}

$discordId = (string) $rMoi['corps']['id'];
$pseudo    = discordNom($rMoi['corps']);
$avatar    = discordAvatar($rMoi['corps']);

/* ------------------------------------------------------------
   3. Est-elle sur le serveur du domaine ?
   ------------------------------------------------------------ */

$guildId = discordConf()['guild_id'];
$rMembre = discordMembre($acces, $guildId);

if ($rMembre['code'] === 404) {
    sortie('Tu n\'es pas sur le serveur du domaine',
        'Ce compte Discord n\'est pas membre du serveur Marlowe Vineyard. L\'espace membre est réservé au personnel.');
}
if ($rMembre['code'] !== 200) {
    $detail = htmlspecialchars((string) ($rMembre['corps']['message'] ?? $rMembre['erreur'] ?? 'code ' . $rMembre['code']));
    sortie('Vérification impossible',
        'Discord n\'a pas pu confirmer que tu es sur le serveur du domaine.<br><br><small>Détail technique : ' . $detail . '</small>');
}

$rolesDiscord = array_values(array_filter(
    (array) ($rMembre['corps']['roles'] ?? []),
    static fn ($r) => preg_match('/^\d{17,20}$/', (string) $r) === 1
));

$roleRequis = trim((string) reglage('discord_role_requis', ''));
if ($roleRequis !== '' && !in_array($roleRequis, $rolesDiscord, true)) {
    sortie('Rôle manquant',
        'Tu es bien sur le serveur, mais tu n\'as pas le rôle Discord exigé pour entrer dans l\'espace membre. Rapproche-toi des RH du domaine.');
}

/* ------------------------------------------------------------
   4. À quel profil rattacher ce compte ?
   ------------------------------------------------------------ */

$profil = ligne('SELECT * FROM profils WHERE discord_id = ? LIMIT 1', [$discordId]);

if (!$profil) {

    if ($depart['intention'] === 'liaison' || $depart['intention'] === 'invitation') {
        $cible = ligne('SELECT * FROM profils WHERE id = ?', [(int) $depart['profil_id']]);
        if (!$cible) {
            sortie('Profil introuvable', 'La fiche à laquelle rattacher ce compte n\'existe plus.');
        }
        if ((int) $cible['actif'] !== 1) {
            sortie('Compte désactivé', 'Cette fiche a été désactivée. Rapproche-toi de la direction.');
        }

        req('UPDATE profils SET discord_id = ?, pseudo_discord = ?, avatar = ?, provisoire = 0 WHERE id = ?',
            [$discordId, $pseudo, $avatar, (int) $cible['id']]);

        if ($depart['intention'] === 'invitation' && $depart['invitation']) {
            req('UPDATE invitations SET utilise_le = NOW() WHERE id = ? AND utilise_le IS NULL', [(int) $depart['invitation']]);
        }

        $profil = ligne('SELECT * FROM profils WHERE id = ?', [(int) $cible['id']]);

    } else {
        sortie('Compte Discord inconnu',
            'Ton compte Discord n\'est rattaché à aucune fiche du domaine. Demande à la direction ou aux RH de te créer une fiche et de t\'envoyer un lien d\'invitation — c\'est ce lien qui rattachera ton Discord à ta fiche.');
    }
}

if ((int) $profil['actif'] !== 1) {
    sortie('Compte désactivé',
        'Ta fiche a été désactivée' . ($profil['motif_desactivation'] ? ' (' . htmlspecialchars((string) $profil['motif_desactivation']) . ')' : '') . '. Rapproche-toi de la direction.');
}

$profilId = (int) $profil['id'];

/* ------------------------------------------------------------
   5. Mise à jour de la fiche depuis Discord
   ------------------------------------------------------------ */

req('UPDATE profils SET pseudo_discord = ?, avatar = ?, discord_verifie_le = NOW() WHERE id = ?',
    [$pseudo, $avatar, $profilId]);

discordMemoriserRoles($rolesDiscord, $profilId);

if ($refresh !== '') {
    discordEnregistrerJeton($profilId, $refresh, $duree, 'connexion');
}

/* Attribution automatique du grade et du rôle du site, si la direction l'a activée. */
$changements = [];

if (reglage('discord_auto_grade', '0') === '1' && $rolesDiscord) {
    $marqueurs = implode(',', array_fill(0, count($rolesDiscord), '?'));

    $nouveauGrade = ligne(
        'SELECT g.id, g.nom FROM discord_roles d
         JOIN grades g ON g.id = d.grade_id
         WHERE d.role_id IN (' . $marqueurs . ') AND d.masque = 0
         ORDER BY g.rang DESC LIMIT 1',
        $rolesDiscord
    );
    if ($nouveauGrade && (int) $nouveauGrade['id'] !== (int) $profil['grade_id']) {
        $ancien = $profil['grade_id']
            ? ligne('SELECT nom, rang FROM grades WHERE id = ?', [(int) $profil['grade_id']])
            : null;
        $rangNeuf = (int) valeur('SELECT rang FROM grades WHERE id = ?', [(int) $nouveauGrade['id']]);
        $sens = ($ancien === null) ? 'arrivee' : ($rangNeuf >= (int) $ancien['rang'] ? 'montee' : 'descente');

        req('UPDATE profils SET grade_id = ? WHERE id = ?', [(int) $nouveauGrade['id'], $profilId]);
        req('INSERT INTO mouvements_grade (profil_id, ancien_grade, nouveau_grade, sens, motif, par)
             VALUES (?,?,?,?,?,NULL)',
            [$profilId, $ancien['nom'] ?? null, (string) $nouveauGrade['nom'], $sens, 'rôle Discord']);
        $changements[] = 'grade → ' . $nouveauGrade['nom'];
    }

    $nouveauRole = valeur(
        'SELECT role_site FROM discord_roles
         WHERE role_id IN (' . $marqueurs . ') AND role_site IS NOT NULL AND masque = 0
         ORDER BY FIELD(role_site, \'direction\',\'rh\',\'comptable\',\'commercial\',\'membre\') LIMIT 1',
        $rolesDiscord
    );
    /* Garde-fou : on ne rétrograde jamais automatiquement une direction.
       Sans cela, un rôle Discord mal renseigné suffirait à fermer à clé
       la porte de l'application, direction comprise. */
    if ((string) $profil['role_site'] === 'direction' && $nouveauRole !== 'direction') {
        $nouveauRole = null;
    }

    if ($nouveauRole && (string) $nouveauRole !== (string) $profil['role_site']) {
        req('UPDATE profils SET role_site = ? WHERE id = ?', [(string) $nouveauRole, $profilId]);
        $changements[] = 'accès → ' . $nouveauRole;
    }
}

/* ------------------------------------------------------------
   6. Ouverture de la session
   ------------------------------------------------------------ */

connecterProfil($profilId);

$details = 'Discord' . ($changements ? ' · ' . implode(' · ', $changements) : '');
journaliser($depart['intention'] === 'connexion' ? 'connexion' : 'discord_liaison', $pseudo, $details);

if ($depart['intention'] === 'connexion') {
    rediriger('/index.php');
}

message($depart['intention'] === 'invitation'
    ? 'Bienvenue. Ton compte Discord est maintenant rattaché à ta fiche : la prochaine fois, un seul clic suffira.'
    : 'Ton compte Discord est maintenant rattaché à ta fiche.');
rediriger('/index.php');
