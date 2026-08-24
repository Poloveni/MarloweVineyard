<?php
/* Journal d'activité du site. */
exigerAcces('journal');

$page   = max(1, champEntier('page', 1));
$parPage= 60;
$total  = (int) valeur('SELECT COUNT(*) FROM journal');
$pages  = max(1, (int) ceil($total / $parPage));
$page   = min($page, $pages);

$entrees = lignes(
    'SELECT j.*, COALESCE(p.nom_rp, j.auteur) AS nom
     FROM journal j LEFT JOIN profils p ON p.id = j.profil_id
     ORDER BY j.id DESC LIMIT ' . $parPage . ' OFFSET ' . (($page - 1) * $parPage)
);

$titre     = 'Journal';
$sousTitre = nb($total) . ' entrée' . ($total > 1 ? 's' : '') . ' · qui a fait quoi, et quand';
require RACINE . '/app/vues/entete.php';
?>
<div class="carte">
<?php if (!$entrees): ?>
  <div class="vide" style="border:0;padding:2rem 0"><h2>Journal vide</h2><p>Les actions importantes s'inscriront ici automatiquement.</p></div>
<?php else: ?>
  <div class="defile" style="border:0;background:none">
    <table>
      <thead><tr><th>Quand</th><th>Qui</th><th>Action</th><th>Cible</th><th>Détail</th></tr></thead>
      <tbody>
      <?php foreach ($entrees as $j): ?>
        <tr>
          <td style="color:var(--doux);white-space:nowrap"><?= dateFr($j['cree_le'], true) ?></td>
          <td><?= e($j['nom'] ?: '—') ?></td>
          <td><span class="badge gris"><?= e($j['action']) ?></span></td>
          <td style="color:var(--doux)"><?= e($j['cible'] ?: '—') ?></td>
          <td style="color:var(--doux)"><?= e($j['details'] ?: '') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1): ?>
    <div class="rangee" style="margin-top:1rem">
      <?php if ($page > 1): ?><a class="btn fantome petit" href="/index.php?p=journal&amp;page=<?= $page - 1 ?>">← Précédent</a><?php endif; ?>
      <span style="color:var(--doux);font-size:.85rem">Page <?= $page ?> / <?= $pages ?></span>
      <?php if ($page < $pages): ?><a class="btn fantome petit" href="/index.php?p=journal&amp;page=<?= $page + 1 ?>">Suivant →</a><?php endif; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>
</div>
<?php require RACINE . '/app/vues/pied.php'; ?>
