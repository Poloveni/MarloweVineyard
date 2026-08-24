<?php
/** @var string $titre */
require_once RACINE . '/app/discord.php';
$u          = utilisateur();
$discordLie = (bool) preg_match('/^\d{17,20}$/', (string) ($u['discord_id'] ?? ''));
$ecranActif = $ecranActif ?? '';
$sections   = [];
foreach (ecrans() as $code => $def) {
    if (peutVoir($code)) { $sections[$def[1]][$code] = $def[0]; }
}
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($titre ?? 'Espace membre') ?> — <?= e(reglage('nom_entreprise', 'Marlowe Vineyard')) ?></title>
<link rel="icon" href="/assets/favicon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/app.css?v=3">
</head>
<body>
<a class="saut" href="#contenu">Aller au contenu</a>

<div class="appli">

  <aside class="menu" id="menu">
    <a class="menu-marque" href="/index.php">
      <span class="blason" aria-hidden="true">M</span>
      <span>
        <b><?= e(reglage('nom_entreprise', 'Marlowe Vineyard')) ?></b>
        <small>Espace membre</small>
      </span>
    </a>

    <nav>
      <?php foreach ($sections as $nomSection => $liens): ?>
        <p class="menu-section"><?= e($nomSection) ?></p>
        <?php foreach ($liens as $code => $libelle): ?>
          <?php $href = $code === 'sauvegarde' ? '/_sauvegarde.php' : '/index.php?p=' . rawurlencode($code); ?>
          <a class="menu-lien<?= $code === $ecranActif ? ' actif' : '' ?>" href="<?= e($href) ?>">
            <?= e($libelle) ?>
            <?php if (acces($code) === 'ro'): ?><span class="pastille" title="Lecture seule">lecture</span><?php endif; ?>
          </a>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </nav>

    <div class="menu-pied">
      <div class="qui">
        <b><?= e($u['nom_rp'] ?: $u['pseudo_discord']) ?></b>
        <small><?= e(ucfirst($u['role_site'])) ?><?= $u['grade_nom'] ? ' · ' . e($u['grade_nom']) : '' ?></small>
      </div>
      <?php if (discordPret() && !$discordLie): ?>
        <a class="lier-discord" href="/auth/depart.php?lier=1" title="Pour te connecter en un clic la prochaine fois">
          Rattacher mon Discord
        </a>
      <?php endif; ?>
      <a class="deco" href="/deconnexion.php">Se déconnecter</a>
    </div>
  </aside>

  <main class="contenu" id="contenu">
    <header class="barre">
      <button class="burger" type="button" onclick="document.getElementById('menu').classList.toggle('ouvert')" aria-label="Menu">☰</button>
      <h1><?= e($titre ?? '') ?></h1>
      <?php if (!empty($sousTitre)): ?><p class="sous"><?= e($sousTitre) ?></p><?php endif; ?>
    </header>

    <?php foreach (messages() as $m): ?>
      <div class="flash <?= e($m['type']) ?>"><?= e($m['texte']) ?></div>
    <?php endforeach; ?>
