<?php
/* ============================================================
   MARLOWE VINEYARD — Raccordement de l'application Discord

   Trois valeurs à coller, prises sur le portail développeur.
   Le secret n'est jamais réaffiché ensuite.
   ============================================================ */

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '0');

$racine   = dirname(__DIR__);

/* Réservée à la direction dès que l'application a un compte : sans cela,
   n'importe qui pourrait remplacer l'application Discord par la sienne. */
require_once $racine . '/app/garde.php';
exigerDirectionSiInstalle($racine);

$fSecrets = $racine . '/secrets.php';
$config   = require $racine . '/config.php';
$redirect = $config['discord']['redirection'];

$secrets  = is_file($fSecrets) ? require $fSecrets : [];
$erreur   = null;
$succes   = false;

$clientId = (string) ($_POST['client_id'] ?? $secrets['discord_client_id'] ?? '');
$guildId  = (string) ($_POST['guild_id']  ?? $secrets['discord_guild_id']  ?? '');
$aDejaSecret = !empty($secrets['discord_client_secret']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $clientId     = trim((string) ($_POST['client_id'] ?? ''));
    $clientSecret = trim((string) ($_POST['client_secret'] ?? ''));
    $guildId      = trim((string) ($_POST['guild_id'] ?? ''));

    if ($clientSecret === '' && $aDejaSecret) {
        $clientSecret = (string) $secrets['discord_client_secret'];   // on garde l'ancien
    }

    if (!preg_match('/^\d{17,20}$/', $clientId)) {
        $erreur = "L'identifiant de l'application doit être une suite de 17 à 20 chiffres. Copie la valeur « APPLICATION ID » du portail Discord.";
    } elseif (!preg_match('/^\d{17,20}$/', $guildId)) {
        $erreur = "L'identifiant du serveur Discord doit être une suite de 17 à 20 chiffres. Active le mode développeur dans Discord, puis clic droit sur le serveur → « Copier l'identifiant du serveur ».";
    } elseif ($clientSecret === '') {
        $erreur = "Le secret client est vide. Sur le portail, onglet OAuth2, clique sur « Reset Secret » puis copie la valeur affichée.";
    } elseif (strlen($clientSecret) < 20) {
        $erreur = "Ce secret client semble trop court : il fait normalement une trentaine de caractères.";
    } elseif (!is_file($fSecrets)) {
        $erreur = "Le fichier secrets.php n'existe pas encore : termine d'abord installation.php.";
    } else {
        $secrets['discord_client_id']     = $clientId;
        $secrets['discord_client_secret'] = $clientSecret;
        $secrets['discord_guild_id']      = $guildId;

        $contenu = "<?php\n"
                 . "// Secrets de Marlowe Vineyard.\n"
                 . "// Derniere mise a jour le " . date('d/m/Y a H:i') . ".\n"
                 . "// Ne jamais partager ni copier ce fichier ailleurs.\n"
                 . "return " . var_export($secrets, true) . ";\n";

        if (file_put_contents($fSecrets, $contenu, LOCK_EX) === false) {
            $erreur = "Impossible d'écrire dans secrets.php.";
        } else {
            @chmod($fSecrets, 0600);
            $succes = true;
            $aDejaSecret = true;
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
<title>Discord — Marlowe Vineyard</title>
<link rel="icon" href="/assets/favicon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
<style>
  :root{
    --vert-900:#061713; --or:#c9a227; --or-clair:#e6c976;
    --texte:#eae4d5; --doux:#9aaba3; --ligne:#1e3a31;
    --ok:#5fae83; --ko:#d1706c;
  }
  *,*::before,*::after{box-sizing:border-box}
  body{
    margin:0;min-height:100vh;padding:2.6rem 1.2rem;
    background:radial-gradient(1100px 620px at 50% -12%, #14382d 0%, var(--vert-900) 62%);
    color:var(--texte);font-family:'Inter',system-ui,sans-serif;line-height:1.6;
  }
  .carte{
    width:min(620px,100%);margin:0 auto;background:rgba(11,38,32,.86);
    border:1px solid var(--ligne);border-radius:18px;padding:2.4rem 2.2rem;
    box-shadow:0 28px 80px rgba(0,0,0,.55);
  }
  .marque{font-size:.78rem;letter-spacing:.34em;text-transform:uppercase;color:var(--or-clair);margin:0 0 .5rem}
  h1{font-family:'Cormorant Garamond',Georgia,serif;font-weight:700;font-size:2rem;line-height:1.15;margin:0 0 .6rem}
  h2{font-family:'Cormorant Garamond',Georgia,serif;font-weight:600;font-size:1.25rem;margin:2rem 0 .6rem}
  p{margin:0 0 1rem;color:var(--doux);font-size:.95rem}
  ol{margin:0 0 1rem;padding-left:1.3rem;color:var(--doux);font-size:.93rem}
  ol li{margin-bottom:.5rem}
  b{color:var(--texte)}
  label{display:block;font-size:.76rem;letter-spacing:.12em;text-transform:uppercase;color:var(--or-clair);margin:1.6rem 0 .45rem}
  input{
    width:100%;padding:.8rem 1rem;border-radius:10px;
    background:rgba(6,23,19,.75);border:1px solid var(--ligne);
    color:var(--texte);font-family:'JetBrains Mono',monospace;font-size:.9rem;
  }
  input:focus{outline:2px solid var(--or);outline-offset:2px;border-color:transparent}
  .aide{font-size:.82rem;color:var(--doux);margin-top:.45rem}
  button{
    margin-top:1.6rem;width:100%;padding:.9rem 1.4rem;border:0;border-radius:999px;
    background:linear-gradient(135deg,var(--or-clair),var(--or));color:#241a04;
    font:inherit;font-weight:600;cursor:pointer;transition:transform .2s,box-shadow .2s;
  }
  button:hover{transform:translateY(-2px);box-shadow:0 12px 30px rgba(201,162,39,.32)}
  .bandeau{border-radius:12px;padding:1rem 1.15rem;margin:1.4rem 0;font-size:.92rem}
  .bandeau.ko{border:1px solid rgba(209,112,108,.45);background:rgba(209,112,108,.10);color:#f0c9c7}
  .bandeau.ok{border:1px solid rgba(95,174,131,.45);background:rgba(95,174,131,.10);color:#c9e9d8}
  .bandeau b{display:block;margin-bottom:.25rem;color:inherit}
  .copiable{
    display:flex;gap:.6rem;align-items:center;margin:.5rem 0 1rem;
    background:rgba(6,23,19,.75);border:1px solid var(--ligne);border-radius:10px;padding:.7rem .9rem;
  }
  .copiable code{font-family:'JetBrains Mono',monospace;font-size:.82rem;color:var(--or-clair);word-break:break-all;flex:1}
  .copiable button{margin:0;width:auto;padding:.4rem 1rem;font-size:.8rem;border-radius:8px}
</style>
</head>
<body>
<main class="carte">
  <p class="marque">Marlowe Vineyard</p>
  <h1>Connexion Discord</h1>

<?php if ($succes): ?>
  <div class="bandeau ok">
    <b>Application Discord enregistrée.</b>
    Identifiant, secret et serveur sont en place. Préviens Claude : il installe la page de connexion.
  </div>
<?php elseif ($erreur): ?>
  <div class="bandeau ko"><b>Ça n'a pas marché</b><?= $erreur ?></div>
<?php endif; ?>

  <h2>1 · Créer l'application</h2>
  <ol>
    <li>Va sur <b>discord.com/developers/applications</b> et connecte-toi avec ton compte Discord habituel.</li>
    <li>Clique sur <b>New Application</b>, nomme-la <b>Marlowe Vineyard</b>, accepte les conditions, puis <b>Create</b>.</li>
    <li>Tu n'as besoin d'aucun droit sur le serveur Discord pour cette étape : l'application t'appartient.</li>
  </ol>

  <h2>2 · Déclarer l'adresse de retour</h2>
  <p>Dans le menu de gauche, onglet <b>OAuth2</b>. Section <b>Redirects</b> → <b>Add Redirect</b>. Colle exactement ceci, puis <b>Save Changes</b> :</p>
  <div class="copiable">
    <code id="redir"><?= htmlspecialchars($redirect) ?></code>
    <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('redir').textContent);this.textContent='Copié'">Copier</button>
  </div>

  <h2>3 · Récupérer les trois valeurs</h2>
  <ol>
    <li><b>Identifiant de l'application</b> : onglet <b>General Information</b>, ligne <b>Application ID</b>.</li>
    <li><b>Secret client</b> : onglet <b>OAuth2</b>, bouton <b>Reset Secret</b>. Il ne s'affiche qu'une fois — copie-le tout de suite.</li>
    <li><b>Identifiant du serveur</b> : dans Discord, Paramètres → Avancés → active <b>Mode développeur</b>. Puis clic droit sur l'icône du serveur Marlowe Vineyard → <b>Copier l'identifiant du serveur</b>.</li>
  </ol>

  <form method="post" autocomplete="off">
    <label for="cid">Identifiant de l'application</label>
    <input type="text" id="cid" name="client_id" inputmode="numeric" spellcheck="false"
           value="<?= htmlspecialchars($clientId) ?>" placeholder="1234567890123456789">

    <label for="sec">Secret client<?= $aDejaSecret ? ' — déjà enregistré' : '' ?></label>
    <input type="password" id="sec" name="client_secret" spellcheck="false"
           placeholder="<?= $aDejaSecret ? 'laisse vide pour conserver celui déjà enregistré' : 'colle-le ici' ?>">
    <p class="aide">Il n'est jamais réaffiché après enregistrement, et il est stocké hors du dossier public.</p>

    <label for="gid">Identifiant du serveur Discord</label>
    <input type="text" id="gid" name="guild_id" inputmode="numeric" spellcheck="false"
           value="<?= htmlspecialchars($guildId) ?>" placeholder="1234567890123456789">
    <p class="aide">Celui du serveur Marlowe Vineyard : c'est là que les grades seront lus.</p>

    <button type="submit">Enregistrer</button>
  </form>
</main>
</body>
</html>
