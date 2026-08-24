<?php
/* Création du tout premier compte : forcément la Direction.
   Cette page se ferme d'elle-même dès qu'un profil existe. */
declare(strict_types=1);
define('RACINE', dirname(__DIR__));
require RACINE . '/app/bdd.php';
require RACINE . '/app/auth.php';
require RACINE . '/app/outils.php';

date_default_timezone_set(configuration()['fuseau'] ?? 'Europe/Paris');

$dejaFait = (int) valeur('SELECT COUNT(*) FROM profils') > 0;
$erreur   = null;

if (!$dejaFait && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom     = champTexte('nom');
    $code    = (string) champ('code', '');
    $code2   = (string) champ('code2', '');

    if (mb_strlen($nom) < 2) {
        $erreur = "Indique ton nom RP, celui sous lequel l'équipe te connaît.";
    } elseif (mb_strlen($code) < 8) {
        $erreur = "Le code doit faire au moins 8 caractères.";
    } elseif ($code !== $code2) {
        $erreur = "Les deux codes ne sont pas identiques.";
    } else {
        req('INSERT INTO profils (discord_id, pseudo_discord, nom_rp, poste, role_site, code_hash, provisoire, actif, date_arrivee)
             VALUES (?, ?, ?, ?, ?, ?, 1, 1, CURDATE())', [
            'provisoire-1',
            $nom,
            $nom,
            'Propriétaire',
            'direction',
            password_hash($code, PASSWORD_DEFAULT),
        ]);
        $id = (int) bdd()->lastInsertId();
        connecterProfil($id);
        req('INSERT INTO journal (profil_id, auteur, action, cible, details, ip) VALUES (?,?,?,?,?,?)',
            [$id, $nom, 'creation_compte', 'profil #' . $id, 'premier compte, role direction', $_SERVER['REMOTE_ADDR'] ?? null]);
        rediriger('/index.php');
    }
}
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Premier compte — Marlowe Vineyard</title>
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
</style>
</head>
<body>
<main class="boite">
  <span class="blason" aria-hidden="true" style="width:46px;height:46px;font-size:1.5rem;margin-bottom:1rem">M</span>

<?php if ($dejaFait): ?>
  <h1>Déjà configuré</h1>
  <p>Un compte existe déjà : cette page est désactivée. Passe par la <a href="/connexion.php" style="color:var(--or-clair)">page de connexion</a>.</p>
<?php else: ?>
  <h1>Premier compte</h1>
  <p>La base est vide. Le compte que tu crées ici sera celui de la <b>Direction</b> : accès à tous les écrans, y compris les paramètres et la gestion des accès.</p>

  <?php if ($erreur): ?><div class="flash erreur" style="margin-top:1.2rem"><?= e($erreur) ?></div><?php endif; ?>

  <form method="post" autocomplete="off">
    <label for="nom">Ton nom RP</label>
    <input type="text" id="nom" name="nom" required autofocus value="<?= e(champTexte('nom')) ?>"
           placeholder="Prénom Nom de ton personnage">

    <label for="code">Code d'accès</label>
    <input type="password" id="code" name="code" required placeholder="au moins 8 caractères">
    <p class="aide">Choisis-le toi-même. Il est enregistré haché : même en lisant la base, personne ne peut le retrouver.</p>

    <label for="code2">Répète le code</label>
    <input type="password" id="code2" name="code2" required>

    <button class="btn" type="submit">Créer mon compte et entrer</button>
  </form>
<?php endif; ?>
</main>
</body>
</html>
