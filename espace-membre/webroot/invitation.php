<?php
/* ============================================================
   Ouverture d'accès par invitation.

   La direction génère un lien à usage unique ; la personne
   invitée choisit elle-même son code. Aucun code ne transite
   jamais par quelqu'un d'autre.
   ============================================================ */
declare(strict_types=1);
define('RACINE', dirname(__DIR__));
require RACINE . '/app/bdd.php';
require RACINE . '/app/auth.php';
require RACINE . '/app/outils.php';
require RACINE . '/app/discord.php';

date_default_timezone_set(configuration()['fuseau'] ?? 'Europe/Paris');

$jetonBrut = (string) ($_GET['j'] ?? $_POST['j'] ?? '');
$erreur    = null;
$profil    = null;
$invit     = null;

if (preg_match('/^[a-f0-9]{64}$/', $jetonBrut)) {
    $invit = ligne(
        'SELECT i.*, p.nom_rp, p.pseudo_discord, p.role_site, p.actif, p.code_hash
         FROM invitations i JOIN profils p ON p.id = i.profil_id
         WHERE i.jeton_hash = ?',
        [hash('sha256', $jetonBrut)]
    );
}

if (!$invit) {
    $erreur = "Ce lien n'est pas valable. Il a peut-être été remplacé par un plus récent.";
} elseif ($invit['utilise_le'] !== null) {
    $erreur = "Ce lien a déjà servi. Un lien d'invitation ne fonctionne qu'une seule fois.";
} elseif (strtotime((string) $invit['expire_le']) < time()) {
    $erreur = "Ce lien a expiré. Demande à la direction d'en générer un nouveau.";
} elseif ((int) $invit['actif'] !== 1) {
    $erreur = "Ce compte est désactivé.";
} else {
    $profil = $invit;
}

$fini = false;

if ($profil && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $code  = (string) ($_POST['code'] ?? '');
    $code2 = (string) ($_POST['code2'] ?? '');

    if (mb_strlen($code) < 8) {
        $erreur = "Le code doit faire au moins 8 caractères.";
    } elseif ($code !== $code2) {
        $erreur = "Les deux codes ne sont pas identiques.";
    } else {
        $pdo = bdd();
        $pdo->beginTransaction();
        req('UPDATE profils SET code_hash = ? WHERE id = ?', [password_hash($code, PASSWORD_DEFAULT), $profil['profil_id']]);
        req('UPDATE invitations SET utilise_le = NOW() WHERE id = ?', [$profil['id']]);
        req('INSERT INTO journal (profil_id, auteur, action, cible, details, ip) VALUES (?,?,?,?,?,?)', [
            $profil['profil_id'],
            $profil['nom_rp'] ?: $profil['pseudo_discord'],
            'acces_ouvert',
            'profil #' . $profil['profil_id'],
            'code défini via invitation',
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
        $pdo->commit();

        connecterProfil((int) $profil['profil_id']);
        $fini = true;
    }
}

$nom = $profil ? ($profil['nom_rp'] ?: $profil['pseudo_discord']) : '';
$roles = ['direction' => 'Direction', 'rh' => 'Ressources humaines', 'comptable' => 'Comptable',
          'commercial' => 'Commercial', 'membre' => 'Membre'];
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Ouvrir mon accès — <?= e(reglage('nom_entreprise', 'Marlowe Vineyard')) ?></title>
<link rel="icon" href="/assets/favicon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/app.css?v=3">
<style>
  body{display:grid;place-items:center;min-height:100vh;padding:2rem 1.2rem;
       background:radial-gradient(1100px 620px at 50% -12%, #14382d 0%, #061713 62%)}
  .boite{width:min(460px,100%);background:rgba(11,38,32,.86);border:1px solid var(--ligne);
         border-radius:18px;padding:2.4rem 2.2rem;box-shadow:0 28px 80px rgba(0,0,0,.55)}
  .boite h1{font-family:var(--serif);font-size:1.9rem;margin:0 0 .4rem}
  .boite p{color:var(--doux);font-size:.93rem}
  .boite .btn{width:100%;justify-content:center;margin-top:1.6rem}
  .boite label{margin-top:1.2rem}
  .blason{width:46px;height:46px;font-size:1.5rem;margin-bottom:1rem}
  .btn-discord{display:flex;align-items:center;justify-content:center;gap:.6rem;width:100%;
    padding:.85rem 1.2rem;border-radius:999px;text-decoration:none;
    background:#5865f2;color:#fff;font-weight:600;font-size:.95rem;
    transition:transform .2s,box-shadow .2s}
  .btn-discord:hover{transform:translateY(-2px);box-shadow:0 12px 30px rgba(88,101,242,.35)}
  .btn-discord svg{width:22px;height:auto;fill:currentColor;flex:none}
</style>
</head>
<body>
<main class="boite">
  <span class="blason" aria-hidden="true">M</span>

<?php if ($fini): ?>
  <h1>Bienvenue, <?= e($nom) ?></h1>
  <div class="flash ok" style="margin-top:1.2rem">Ton accès est ouvert et tu es déjà connecté.</div>
  <p>Retiens bien ton code : personne, pas même la direction, ne peut le relire. En cas d'oubli, il faudra demander un nouveau lien.</p>
  <a class="btn" href="/index.php">Entrer dans l'espace membre</a>

<?php elseif (!$profil): ?>
  <h1>Lien inutilisable</h1>
  <div class="flash erreur" style="margin-top:1.2rem"><?= e($erreur) ?></div>
  <p>Rapproche-toi de la direction du domaine pour obtenir un nouveau lien.</p>

<?php else: ?>
  <h1>Ouvre ton accès</h1>
  <p>
    Bonjour <b style="color:var(--texte)"><?= e($nom) ?></b>. La direction t'a ouvert un accès à l'espace membre
    avec le rôle <b style="color:var(--or-clair)"><?= e($roles[$profil['role_site']] ?? $profil['role_site']) ?></b>.
    Choisis ton code : toi seul le connaîtras.
  </p>

  <?php if ($erreur): ?><div class="flash erreur" style="margin-top:1.2rem"><?= e($erreur) ?></div><?php endif; ?>

  <form method="post" autocomplete="off">
    <input type="hidden" name="j" value="<?= e($jetonBrut) ?>">

    <label for="code">Ton code d'accès</label>
    <input type="password" id="code" name="code" required autofocus placeholder="au moins 8 caractères">
    <p class="aide">Il est enregistré haché : même en ouvrant la base de données, personne ne peut le retrouver.</p>

    <label for="code2">Répète le code</label>
    <input type="password" id="code2" name="code2" required>

    <button class="btn" type="submit">Ouvrir mon accès</button>
  </form>

  <?php if (discordPret()): ?>
    <div style="margin-top:1.8rem;padding-top:1.3rem;border-top:1px solid var(--ligne)">
      <p style="margin:0 0 .9rem">Ou rattache directement ton compte Discord — plus rien à retenir :</p>
      <a class="btn-discord" href="/auth/depart.php?invitation=<?= e($jetonBrut) ?>">
        <svg viewBox="0 0 71 55" aria-hidden="true"><path d="M60.1 4.9A58.5 58.5 0 0 0 45.6.4a.2.2 0 0 0-.2.1c-.6 1.1-1.3 2.6-1.8 3.7a54 54 0 0 0-16.2 0A37 37 0 0 0 25.5.5a.2.2 0 0 0-.2-.1 58.4 58.4 0 0 0-14.5 4.5.2.2 0 0 0-.1.1C1.6 18.7-1 32.1.3 45.4a.2.2 0 0 0 .1.2 58.8 58.8 0 0 0 17.7 9 .2.2 0 0 0 .3-.1c1.4-1.9 2.6-3.9 3.6-6a.2.2 0 0 0-.1-.3 38.7 38.7 0 0 1-5.5-2.6.2.2 0 0 1 0-.4l1.1-.8a.2.2 0 0 1 .2 0 41.9 41.9 0 0 0 35.6 0 .2.2 0 0 1 .2 0l1.1.9a.2.2 0 0 1 0 .3 36.3 36.3 0 0 1-5.5 2.6.2.2 0 0 0-.1.3c1 2.1 2.2 4.1 3.6 6a.2.2 0 0 0 .3.1 58.6 58.6 0 0 0 17.7-9 .2.2 0 0 0 .1-.2c1.5-15.3-2.5-28.6-10.6-40.4a.2.2 0 0 0-.1-.1zM23.7 37.3c-3.5 0-6.4-3.2-6.4-7.2s2.8-7.2 6.4-7.2c3.6 0 6.5 3.3 6.4 7.2 0 4-2.8 7.2-6.4 7.2zm23.6 0c-3.5 0-6.4-3.2-6.4-7.2s2.8-7.2 6.4-7.2c3.6 0 6.5 3.3 6.4 7.2 0 4-2.8 7.2-6.4 7.2z"/></svg>
        Rattacher mon Discord
      </a>
      <p class="aide" style="margin:.9rem 0 0">Tu dois être membre du serveur Discord du domaine.</p>
    </div>
  <?php endif; ?>
<?php endif; ?>
</main>
</body>
</html>
