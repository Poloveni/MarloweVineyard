<?php
/* Connexion provisoire par code, en attendant Discord. */
declare(strict_types=1);
define('RACINE', dirname(__DIR__));
require RACINE . '/app/bdd.php';
require RACINE . '/app/auth.php';
require RACINE . '/app/outils.php';
require RACINE . '/app/discord.php';

date_default_timezone_set(configuration()['fuseau'] ?? 'Europe/Paris');

if ((int) valeur('SELECT COUNT(*) FROM profils') === 0) { rediriger('/_premier-compte.php'); }
if (connecte()) { rediriger('/index.php'); }

$erreur = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifierJeton();
    $identifiant = champTexte('identifiant');
    $code        = (string) champ('code', '');

    /* Petite temporisation : rend les essais en masse inconfortables. */
    usleep(400000);

    $profil = ligne(
        'SELECT id, code_hash, actif FROM profils
         WHERE (nom_rp = ? OR pseudo_discord = ?) AND code_hash IS NOT NULL
         LIMIT 1',
        [$identifiant, $identifiant]
    );

    if (!$profil || !password_verify($code, (string) $profil['code_hash'])) {
        $erreur = "Nom ou code incorrect.";
    } elseif ((int) $profil['actif'] !== 1) {
        $erreur = "Ce compte a été désactivé. Rapproche-toi de la direction.";
    } else {
        connecterProfil((int) $profil['id']);
        journaliser('connexion', null, 'code provisoire');
        rediriger('/index.php');
    }
}
$titre = 'Connexion';
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Connexion — <?= e(reglage('nom_entreprise', 'Marlowe Vineyard')) ?></title>
<link rel="icon" href="/assets/favicon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/app.css?v=3">
<style>
  body{display:grid;place-items:center;min-height:100vh;padding:2rem 1.2rem;
       background:radial-gradient(1100px 620px at 50% -12%, #14382d 0%, #061713 62%)}
  .connexion{width:min(430px,100%);background:rgba(11,38,32,.86);border:1px solid var(--ligne);
             border-radius:18px;padding:2.4rem 2.2rem;box-shadow:0 28px 80px rgba(0,0,0,.55)}
  .connexion .blason{width:46px;height:46px;font-size:1.5rem;margin-bottom:1.1rem}
  .connexion h1{font-family:var(--serif);font-size:1.9rem;margin:0 0 .4rem}
  .connexion p.intro{color:var(--doux);font-size:.93rem;margin:0 0 1.4rem}
  .connexion .btn{width:100%;justify-content:center;margin-top:1.5rem}
  .connexion label{margin-top:1.1rem}
  .discord{margin-top:1.8rem;padding-top:1.3rem;border-top:1px solid var(--ligne);
           font-size:.85rem;color:var(--doux)}
  .btn-discord{display:flex;align-items:center;justify-content:center;gap:.6rem;width:100%;
    padding:.85rem 1.2rem;border-radius:999px;text-decoration:none;
    background:#5865f2;color:#fff;font-weight:600;font-size:.95rem;
    transition:transform .2s,box-shadow .2s}
  .btn-discord:hover{transform:translateY(-2px);box-shadow:0 12px 30px rgba(88,101,242,.35)}
  .btn-discord svg{width:22px;height:auto;fill:currentColor;flex:none}
</style>
</head>
<body>
<main class="connexion">
  <span class="blason" aria-hidden="true">M</span>
  <h1>Espace membre</h1>
  <p class="intro"><?= e(reglage('nom_entreprise', 'Marlowe Vineyard')) ?> — accès réservé au personnel du domaine.</p>

  <?php if ($erreur): ?>
    <div class="flash erreur"><?= e($erreur) ?></div>
  <?php endif; ?>

  <form method="post" autocomplete="off">
    <input type="hidden" name="jeton" value="<?= e(jeton()) ?>">

    <label for="id">Nom</label>
    <input type="text" id="id" name="identifiant" required autofocus
           value="<?= e(champTexte('identifiant')) ?>" placeholder="ton nom RP">

    <label for="code">Code d'accès</label>
    <input type="password" id="code" name="code" required placeholder="••••••••">

    <button class="btn" type="submit">Entrer</button>
  </form>

  <?php if (discordPret()): ?>
    <div class="discord">
      <p style="margin:0 0 .9rem">Ou, si ton compte Discord est déjà rattaché à ta fiche :</p>
      <a class="btn-discord" href="/auth/depart.php">
        <svg viewBox="0 0 71 55" aria-hidden="true"><path d="M60.1 4.9A58.5 58.5 0 0 0 45.6.4a.2.2 0 0 0-.2.1c-.6 1.1-1.3 2.6-1.8 3.7a54 54 0 0 0-16.2 0A37 37 0 0 0 25.5.5a.2.2 0 0 0-.2-.1 58.4 58.4 0 0 0-14.5 4.5.2.2 0 0 0-.1.1C1.6 18.7-1 32.1.3 45.4a.2.2 0 0 0 .1.2 58.8 58.8 0 0 0 17.7 9 .2.2 0 0 0 .3-.1c1.4-1.9 2.6-3.9 3.6-6a.2.2 0 0 0-.1-.3 38.7 38.7 0 0 1-5.5-2.6.2.2 0 0 1 0-.4l1.1-.8a.2.2 0 0 1 .2 0 41.9 41.9 0 0 0 35.6 0 .2.2 0 0 1 .2 0l1.1.9a.2.2 0 0 1 0 .3 36.3 36.3 0 0 1-5.5 2.6.2.2 0 0 0-.1.3c1 2.1 2.2 4.1 3.6 6a.2.2 0 0 0 .3.1 58.6 58.6 0 0 0 17.7-9 .2.2 0 0 0 .1-.2c1.5-15.3-2.5-28.6-10.6-40.4a.2.2 0 0 0-.1-.1zM23.7 37.3c-3.5 0-6.4-3.2-6.4-7.2s2.8-7.2 6.4-7.2c3.6 0 6.5 3.3 6.4 7.2 0 4-2.8 7.2-6.4 7.2zm23.6 0c-3.5 0-6.4-3.2-6.4-7.2s2.8-7.2 6.4-7.2c3.6 0 6.5 3.3 6.4 7.2 0 4-2.8 7.2-6.4 7.2z"/></svg>
        Se connecter avec Discord
      </a>
      <p class="aide" style="margin:.9rem 0 0">
        Ton compte doit être membre du serveur Discord du domaine, et déjà rattaché à ta fiche.
        Si ce n'est pas encore le cas, entre d'abord avec ton code : le rattachement se fait ensuite en un clic.
      </p>
    </div>
  <?php else: ?>
    <p class="discord">
      La connexion par Discord n'est pas encore configurée. Le code d'accès reste le seul chemin
      pour l'instant.
    </p>
  <?php endif; ?>
</main>
</body>
</html>
