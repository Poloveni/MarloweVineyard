<?php
/* ============================================================
   MARLOWE VINEYARD — Page d'installation

   Un seul champ à remplir : le mot de passe de la base de données.
   Cette page teste la connexion AVANT d'enregistrer quoi que ce soit,
   génère elle-même la clé de chiffrement, puis se désactive.

   À supprimer une fois l'installation terminée.
   ============================================================ */

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '0');

$racine      = dirname(__DIR__);
$fSecrets    = $racine . '/secrets.php';
$config      = require $racine . '/config.php';
$b           = $config['bdd'];

$deja        = is_file($fSecrets);
$erreur      = null;
$succes      = null;
$infos       = [];

if (!$deja && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $motDePasse   = (string) ($_POST['mot_de_passe'] ?? '');
    $utilisateur  = trim((string) ($_POST['utilisateur'] ?? $b['utilisateur']));

    if ($utilisateur === '') {
        $erreur = "Le nom d'utilisateur est vide : recopie-le depuis le champ USERNAME du panneau.";
    } elseif ($motDePasse === '') {
        $erreur = "Le champ est vide : colle le mot de passe affiché derrière l'icône œil du panneau.";
    } elseif (!is_writable($racine)) {
        $erreur = "Le serveur n'a pas le droit d'écrire dans " . htmlspecialchars($racine) . ".";
    } else {
        // 1. On teste la connexion avant d'enregistrer quoi que ce soit.
        try {
            $pdo = new PDO(
                "mysql:host={$b['hote']};port={$b['port']};dbname={$b['base']};charset=utf8mb4",
                $utilisateur,
                $motDePasse,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
            );

            $infos['Version de MySQL'] = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
            $infos['Encodage']         = (string) $pdo->query('SELECT @@character_set_database')->fetchColumn();
            $tables                    = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
            $infos['Tables existantes'] = count($tables) ? implode(', ', $tables) : 'aucune — base vierge';

            // 2. La connexion marche : on enregistre.
            $donnees = [
                'bdd_utilisateur'  => $utilisateur,
                'bdd_mot_de_passe' => $motDePasse,
                'cle_secrete'      => bin2hex(random_bytes(32)),
            ];

            $contenu = "<?php\n"
                     . "// Secrets de Marlowe Vineyard.\n"
                     . "// Genere automatiquement le " . date('d/m/Y a H:i') . ".\n"
                     . "// Ne jamais partager ni copier ce fichier ailleurs.\n"
                     . "return " . var_export($donnees, true) . ";\n";

            if (file_put_contents($fSecrets, $contenu, LOCK_EX) === false) {
                $erreur = "La connexion fonctionne, mais l'enregistrement du fichier a échoué.";
            } else {
                @chmod($fSecrets, 0600);
                $succes = true;
                $infos['Utilisateur'] = $utilisateur;
                $infos['Clé de chiffrement'] = 'générée (64 caractères) et enregistrée';
                $deja = true;
            }

        } catch (Throwable $e) {
            $msg = $e->getMessage();
            if (stripos($msg, 'Access denied') !== false) {
                $erreur = "MySQL a refusé ce couple utilisateur / mot de passe. Vérifie les deux champs caractère par caractère : ils se copient depuis USERNAME et PASSWORD dans le panneau. Attention aux confusions g/q et I/l.";
            } elseif (stripos($msg, 'Unknown database') !== false) {
                $erreur = "La base « {$b['base']} » n'existe pas ou n'est pas accessible avec cet utilisateur.";
            } else {
                $erreur = "Connexion impossible : " . htmlspecialchars($msg);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Installation — Marlowe Vineyard</title>
<link rel="icon" href="/assets/favicon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
<style>
  :root{
    --vert-900:#061713; --vert-800:#0b2620; --vert-700:#10332a;
    --or:#c9a227; --or-clair:#e6c976;
    --texte:#eae4d5; --doux:#9aaba3; --ligne:#1e3a31;
    --ok:#5fae83; --ko:#d1706c;
  }
  *,*::before,*::after{box-sizing:border-box}
  body{
    margin:0;min-height:100vh;display:grid;place-items:center;padding:2rem 1.2rem;
    background:radial-gradient(1100px 620px at 50% -12%, #14382d 0%, var(--vert-900) 62%);
    color:var(--texte);font-family:'Inter',system-ui,sans-serif;line-height:1.6;
  }
  .carte{
    width:min(560px,100%);background:rgba(11,38,32,.86);
    border:1px solid var(--ligne);border-radius:18px;padding:2.4rem 2.2rem;
    box-shadow:0 28px 80px rgba(0,0,0,.55);
  }
  .marque{
    font-family:'Cormorant+Garamond','Cormorant Garamond',Georgia,serif;
    font-size:.78rem;letter-spacing:.34em;text-transform:uppercase;
    color:var(--or-clair);margin:0 0 .5rem;
  }
  h1{
    font-family:'Cormorant Garamond',Georgia,serif;font-weight:700;
    font-size:2rem;line-height:1.15;margin:0 0 .6rem;
  }
  p{margin:0 0 1rem;color:var(--doux);font-size:.95rem}
  p:last-of-type{margin-bottom:0}
  label{display:block;font-size:.78rem;letter-spacing:.12em;text-transform:uppercase;color:var(--or-clair);margin:1.8rem 0 .5rem}
  input[type=password],input[type=text]{
    width:100%;padding:.85rem 1rem;border-radius:10px;
    background:rgba(6,23,19,.75);border:1px solid var(--ligne);
    color:var(--texte);font:inherit;font-family:'JetBrains Mono',monospace;font-size:.92rem;
  }
  input:focus{outline:2px solid var(--or);outline-offset:2px;border-color:transparent}
  .aide{font-size:.83rem;color:var(--doux);margin-top:.5rem}
  button{
    margin-top:1.6rem;width:100%;padding:.9rem 1.4rem;border:0;border-radius:999px;
    background:linear-gradient(135deg,var(--or-clair),var(--or));color:#241a04;
    font:inherit;font-weight:600;cursor:pointer;transition:transform .2s,box-shadow .2s;
  }
  button:hover{transform:translateY(-2px);box-shadow:0 12px 30px rgba(201,162,39,.32)}
  .bandeau{border-radius:12px;padding:1rem 1.15rem;margin-bottom:1.6rem;font-size:.92rem}
  .bandeau.ko{border:1px solid rgba(209,112,108,.45);background:rgba(209,112,108,.10);color:#f0c9c7}
  .bandeau.ok{border:1px solid rgba(95,174,131,.45);background:rgba(95,174,131,.10);color:#c9e9d8}
  .bandeau b{display:block;margin-bottom:.25rem;color:inherit}
  dl{margin:1.4rem 0 0;display:grid;grid-template-columns:auto 1fr;gap:.45rem 1.2rem;font-size:.88rem}
  dt{color:var(--doux)}
  dd{margin:0;font-family:'JetBrains Mono',monospace;word-break:break-word}
  .suite{margin-top:1.8rem;padding-top:1.4rem;border-top:1px solid var(--ligne);font-size:.9rem}
  .suite b{color:var(--or-clair)}
  code{font-family:'JetBrains Mono',monospace;font-size:.86em;background:rgba(6,23,19,.7);padding:.1em .4em;border-radius:5px}
</style>
</head>
<body>
<main class="carte">
  <p class="marque">Marlowe Vineyard</p>

<?php if ($succes): ?>

  <h1>Installation terminée</h1>
  <div class="bandeau ok">
    <b>Connexion à la base réussie.</b>
    Le mot de passe et la clé de chiffrement sont enregistrés hors du dossier public.
  </div>
  <dl>
    <?php foreach ($infos as $k => $v): ?>
      <dt><?= htmlspecialchars($k) ?></dt><dd><?= htmlspecialchars($v) ?></dd>
    <?php endforeach; ?>
  </dl>
  <p class="suite">
    <b>C'est fini pour toi.</b> Préviens Claude : il installera les tables et la page de connexion Discord.
    Cette page ne sert plus à rien et refusera désormais toute modification.
  </p>

<?php elseif ($deja): ?>

  <h1>Déjà configuré</h1>
  <div class="bandeau ok">
    <b>Le fichier de secrets existe déjà.</b>
    Cette page est donc désactivée : elle ne peut plus rien écrire.
  </div>
  <p class="suite">
    Pour recommencer à zéro, il faudrait supprimer le fichier <code>secrets.php</code>
    à la racine du serveur — à ne faire que si on te le demande.
  </p>

<?php else: ?>

  <h1>Raccorder la base de données</h1>
  <p>Une seule chose à faire. Le mot de passe reste sur ton serveur : il est enregistré dans un fichier situé en dehors du dossier public, que personne ne peut ouvrir depuis Internet.</p>

  <?php if ($erreur): ?>
    <div class="bandeau ko"><b>Ça n'a pas marché</b><?= $erreur ?></div>
  <?php endif; ?>

  <form method="post" autocomplete="off">
    <label for="usr">Nom d'utilisateur</label>
    <input type="text" id="usr" name="utilisateur" required spellcheck="false"
           value="<?= htmlspecialchars($_POST['utilisateur'] ?? $b['utilisateur']) ?>">
    <p class="aide">Déjà prérempli. Vérifie qu'il correspond exactement au champ <b>USERNAME</b> du panneau, sinon remplace-le par un copier-coller.</p>

    <label for="mdp">Mot de passe de la base <?= htmlspecialchars($b['base']) ?></label>
    <input type="password" id="mdp" name="mot_de_passe" required autofocus
           placeholder="colle-le ici" spellcheck="false">
    <p class="aide">
      Où le trouver : panneau → <b>WEB PHP 1</b> → onglet <b>Databases</b> → petite icône œil
      sur la ligne <?= htmlspecialchars($b['base']) ?>. Copie la valeur entière.
    </p>
    <button type="submit">Vérifier et enregistrer</button>
  </form>

  <p class="suite">
    Rien n'est enregistré tant que la connexion n'a pas été testée avec succès.
    Si le mot de passe est mauvais, la page te le dira et tu pourras réessayer.
    La clé de chiffrement interne, elle, est générée automatiquement : tu n'as pas à t'en occuper.
  </p>

<?php endif; ?>
</main>
</body>
</html>
