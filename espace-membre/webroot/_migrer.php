<?php
/* ============================================================
   MARLOWE VINEYARD — Installation / mise à jour de la base

   Applique les étapes de schema.php qui n'ont pas encore été
   exécutées. Relançable sans danger : ce qui est déjà fait est
   ignoré. Aucune donnée n'est jamais supprimée.
   ============================================================ */

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '0');
set_time_limit(120);

$racine = dirname(__DIR__);

/* Tant qu'aucun compte n'existe, cette page reste ouverte : c'est elle
   qui construit la base. Dès le premier compte créé, elle se referme. */
require_once $racine . '/app/garde.php';
exigerDirectionSiInstalle($racine);

$config = require $racine . '/config.php';
$schema = require $racine . '/schema.php';
$b      = $config['bdd'];

$pdo        = null;
$erreurBase = null;
$dejaFaites = [];
$resultats  = [];
$lance      = ($_SERVER['REQUEST_METHOD'] === 'POST');

try {
    $pdo = new PDO(
        "mysql:host={$b['hote']};port={$b['port']};dbname={$b['base']};charset=utf8mb4",
        $b['utilisateur'],
        $b['mot_de_passe'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );

    $pdo->exec("CREATE TABLE IF NOT EXISTS migrations (
        etape    VARCHAR(80) NOT NULL PRIMARY KEY,
        applique TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $dejaFaites = $pdo->query('SELECT etape FROM migrations')->fetchAll(PDO::FETCH_COLUMN);

    if ($lance) {
        $marquer = $pdo->prepare('INSERT INTO migrations (etape) VALUES (?)');

        foreach ($schema as $etape => $sql) {
            if (in_array($etape, $dejaFaites, true)) {
                $resultats[] = ['etape' => $etape, 'etat' => 'saut', 'msg' => 'déjà appliquée'];
                continue;
            }
            try {
                $pdo->exec($sql);
                $marquer->execute([$etape]);
                $resultats[] = ['etape' => $etape, 'etat' => 'ok', 'msg' => 'appliquée'];
            } catch (Throwable $e) {
                $resultats[] = ['etape' => $etape, 'etat' => 'ko', 'msg' => $e->getMessage()];
                break; // on s'arrête à la première erreur, rien n'est laissé à moitié
            }
        }
        $dejaFaites = $pdo->query('SELECT etape FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
    }

} catch (Throwable $e) {
    $erreurBase = $e->getMessage();
}

$restantes = $pdo ? array_values(array_diff(array_keys($schema), $dejaFaites)) : array_keys($schema);
$tables    = [];
if ($pdo && !$erreurBase) {
    try { $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN); } catch (Throwable $e) {}
}
$echec = false;
foreach ($resultats as $r) { if ($r['etat'] === 'ko') { $echec = true; } }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Base de données — Marlowe Vineyard</title>
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
    margin:0;min-height:100vh;padding:3rem 1.2rem;
    background:radial-gradient(1100px 620px at 50% -12%, #14382d 0%, var(--vert-900) 62%);
    color:var(--texte);font-family:'Inter',system-ui,sans-serif;line-height:1.6;
  }
  .carte{
    width:min(760px,100%);margin:0 auto;background:rgba(11,38,32,.86);
    border:1px solid var(--ligne);border-radius:18px;padding:2.4rem 2.2rem;
    box-shadow:0 28px 80px rgba(0,0,0,.55);
  }
  .marque{font-size:.78rem;letter-spacing:.34em;text-transform:uppercase;color:var(--or-clair);margin:0 0 .5rem}
  h1{font-family:'Cormorant Garamond',Georgia,serif;font-weight:700;font-size:2rem;line-height:1.15;margin:0 0 .6rem}
  p{margin:0 0 1rem;color:var(--doux);font-size:.95rem}
  .bandeau{border-radius:12px;padding:1rem 1.15rem;margin:1.4rem 0;font-size:.92rem}
  .bandeau.ko{border:1px solid rgba(209,112,108,.45);background:rgba(209,112,108,.10);color:#f0c9c7}
  .bandeau.ok{border:1px solid rgba(95,174,131,.45);background:rgba(95,174,131,.10);color:#c9e9d8}
  .bandeau b{display:block;margin-bottom:.25rem}
  button{
    margin-top:1.2rem;padding:.9rem 2rem;border:0;border-radius:999px;
    background:linear-gradient(135deg,var(--or-clair),var(--or));color:#241a04;
    font:inherit;font-weight:600;cursor:pointer;transition:transform .2s,box-shadow .2s;
  }
  button:hover{transform:translateY(-2px);box-shadow:0 12px 30px rgba(201,162,39,.32)}
  ul.etapes{list-style:none;padding:0;margin:1.4rem 0 0;font-family:'JetBrains Mono',monospace;font-size:.82rem}
  ul.etapes li{display:flex;gap:.8rem;padding:.32rem 0;border-bottom:1px solid rgba(30,58,49,.6)}
  ul.etapes li:last-child{border-bottom:0}
  .p-ok{color:var(--ok)}
  .p-ko{color:var(--ko)}
  .p-saut{color:var(--doux);opacity:.6}
  .puce{width:1.4rem;flex:none;text-align:center}
  .nom{flex:none;width:14rem;color:var(--texte)}
  .msg{color:var(--doux);word-break:break-word}
  .tables{display:flex;flex-wrap:wrap;gap:.4rem;margin-top:1rem}
  .tables span{
    font-family:'JetBrains Mono',monospace;font-size:.74rem;
    border:1px solid var(--ligne);border-radius:99px;padding:.2rem .7rem;color:var(--doux);
  }
  .suite{margin-top:1.8rem;padding-top:1.4rem;border-top:1px solid var(--ligne);font-size:.9rem}
  .suite b{color:var(--or-clair)}
</style>
</head>
<body>
<main class="carte">
  <p class="marque">Marlowe Vineyard</p>
  <h1>Base de données</h1>

<?php if ($erreurBase): ?>

  <div class="bandeau ko">
    <b>Connexion impossible</b>
    <?= htmlspecialchars($erreurBase) ?>
  </div>
  <p>Vérifie que l'installation s'est bien terminée sur <code>installation.php</code>.</p>

<?php else: ?>

  <?php if ($lance && !$echec): ?>
    <div class="bandeau ok">
      <b>Base à jour.</b>
      <?= count(array_filter($resultats, fn($r) => $r['etat'] === 'ok')) ?> étape(s) appliquée(s),
      <?= count($tables) ?> tables présentes.
    </div>
  <?php elseif ($lance && $echec): ?>
    <div class="bandeau ko">
      <b>Une étape a échoué.</b>
      Les étapes précédentes sont conservées. Envoie le message ci-dessous à Claude.
    </div>
  <?php elseif (count($restantes) === 0): ?>
    <div class="bandeau ok">
      <b>Rien à faire.</b> Toutes les étapes sont déjà appliquées.
    </div>
  <?php else: ?>
    <p><?= count($restantes) ?> étape(s) à appliquer. Aucune donnée existante ne sera touchée : on ne fait qu'ajouter.</p>
  <?php endif; ?>

  <?php if ($resultats): ?>
    <ul class="etapes">
      <?php foreach ($resultats as $r): ?>
        <li class="p-<?= $r['etat'] ?>">
          <span class="puce"><?= $r['etat'] === 'ok' ? '✓' : ($r['etat'] === 'ko' ? '✕' : '·') ?></span>
          <span class="nom"><?= htmlspecialchars($r['etape']) ?></span>
          <span class="msg"><?= htmlspecialchars($r['msg']) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <?php if ($tables): ?>
    <p style="margin-top:1.6rem">Tables présentes dans <b><?= htmlspecialchars($b['base']) ?></b> :</p>
    <div class="tables">
      <?php foreach ($tables as $t): ?><span><?= htmlspecialchars($t) ?></span><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (count($restantes) > 0): ?>
    <form method="post">
      <button type="submit">Appliquer les <?= count($restantes) ?> étape(s)</button>
    </form>
  <?php else: ?>
    <p class="suite"><b>C'est fait.</b> Préviens Claude : il enchaîne avec la connexion Discord.</p>
  <?php endif; ?>

<?php endif; ?>
</main>
</body>
</html>
