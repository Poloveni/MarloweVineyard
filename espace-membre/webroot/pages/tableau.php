<?php
/* Vue d'ensemble. */
exigerAcces('tableau');

$semaine  = semaineCourante();
$unite    = unite();

$effectif = (int) valeur('SELECT COUNT(*) FROM profils WHERE actif = 1');
$prod     = (int) valeur('SELECT COALESCE(SUM(quantite),0) FROM productions WHERE semaine_id = ?', [$semaine['id']]);
$nbRuns   = (int) valeur('SELECT COUNT(*) FROM productions WHERE semaine_id = ?', [$semaine['id']]);
$ca       = (float) valeur('SELECT COALESCE(SUM(total_ht),0) FROM factures WHERE semaine_id = ? AND statut <> "annulee"', [$semaine['id']]);
$objectif = (int) $semaine['objectif'];
$pct      = $objectif > 0 ? min(100, (int) round($prod / $objectif * 100)) : 0;

$parGrade = lignes(
    'SELECT g.nom, g.couleur, COUNT(DISTINCT p.id) AS n, COALESCE(SUM(pr.quantite),0) AS q
     FROM grades g
     LEFT JOIN profils p     ON p.grade_id = g.id AND p.actif = 1
     LEFT JOIN productions pr ON pr.profil_id = p.id AND pr.semaine_id = ?
     GROUP BY g.id ORDER BY g.rang', [$semaine['id']]
);

$top = lignes(
    'SELECT COALESCE(p.nom_rp, p.pseudo_discord) AS nom, g.nom AS grade, SUM(pr.quantite) AS q
     FROM productions pr
     JOIN profils p ON p.id = pr.profil_id
     LEFT JOIN grades g ON g.id = p.grade_id
     WHERE pr.semaine_id = ?
     GROUP BY p.id ORDER BY q DESC LIMIT 5', [$semaine['id']]
);

$semainesClose = lignes('SELECT * FROM semaines WHERE cloturee = 1 ORDER BY annee DESC, numero DESC LIMIT 6');

$titre     = "Vue d'ensemble";
$sousTitre = 'Semaine ' . $semaine['numero'] . ' · du ' . dateFr($semaine['debut']) . ' au ' . dateFr($semaine['fin']);
require RACINE . '/app/vues/entete.php';
?>

<div class="pile">

  <div class="grille">
    <div class="carte kpi">
      <div class="k">Effectif actif</div>
      <div class="v"><?= nb($effectif) ?></div>
      <div class="d"><?= $effectif === 0 ? 'personne enregistrée pour l\'instant' : 'personnes au domaine' ?></div>
    </div>
    <div class="carte kpi">
      <div class="k">Production</div>
      <div class="v"><?= nb($prod) ?></div>
      <div class="d"><?= e($unite) ?> · <?= nb($nbRuns) ?> saisie<?= $nbRuns > 1 ? 's' : '' ?></div>
    </div>
    <div class="carte kpi">
      <div class="k">Chiffre d'affaires</div>
      <div class="v"><?= nb($ca) ?></div>
      <div class="d">$ hors taxes, factures de la semaine</div>
    </div>
    <div class="carte kpi">
      <div class="k">Objectif collectif</div>
      <div class="v"><?= $pct ?> %</div>
      <div class="d"><?= nb($prod) ?> / <?= nb($objectif) ?> <?= e($unite) ?></div>
      <div class="jauge"><i style="width:<?= $pct ?>%"></i></div>
    </div>
  </div>

  <?php if ($prod === 0 && $effectif <= 1): ?>
    <div class="vide">
      <h2>Le domaine est encore vide</h2>
      <p>Commence par inscrire l'équipe dans <b>Effectif</b>, puis saisis les premières productions.
         Les chiffres de cet écran se rempliront tout seuls.</p>
      <p style="margin-top:1.2rem">
        <a class="btn" href="/index.php?p=effectif">Aller à l'effectif</a>
      </p>
    </div>
  <?php else: ?>

    <div class="grille" style="grid-template-columns:repeat(auto-fit,minmax(320px,1fr))">

      <div class="carte">
        <h2>Production par grade</h2>
        <div class="defile" style="border:0;background:none">
          <table style="min-width:0">
            <thead><tr><th>Grade</th><th class="n">Effectif</th><th class="n">Production</th></tr></thead>
            <tbody>
            <?php foreach ($parGrade as $g): ?>
              <tr>
                <td><span class="badge" style="background:<?= e($g['couleur']) ?>22;color:<?= e($g['couleur']) ?>"><?= e($g['nom']) ?></span></td>
                <td class="n"><?= nb($g['n']) ?></td>
                <td class="n"><?= nb($g['q']) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="carte">
        <h2>Top 5 de la semaine</h2>
        <?php if (!$top): ?>
          <p style="color:var(--doux);margin:0">Aucune production saisie cette semaine.</p>
        <?php else: ?>
          <div class="defile" style="border:0;background:none">
            <table style="min-width:0">
              <thead><tr><th class="n">#</th><th>Nom</th><th>Grade</th><th class="n">Production</th></tr></thead>
              <tbody>
              <?php foreach ($top as $i => $t): ?>
                <tr>
                  <td class="n"><?= $i + 1 ?></td>
                  <td><?= e($t['nom']) ?></td>
                  <td style="color:var(--doux)"><?= e($t['grade'] ?? '—') ?></td>
                  <td class="n"><?= nb($t['q']) ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

    </div>

    <div class="carte">
      <div class="entre"><h2>Semaines précédentes</h2></div>
      <?php if (!$semainesClose): ?>
        <p style="color:var(--doux);margin:0">Aucune semaine clôturée pour l'instant. L'historique se construira à partir de la première clôture du lundi.</p>
      <?php else: ?>
        <div class="defile" style="border:0;background:none">
          <table>
            <thead><tr><th>Semaine</th><th>Période</th><th class="n">Production</th><th class="n">CA</th><th class="n">Effectif</th></tr></thead>
            <tbody>
            <?php foreach ($semainesClose as $s): ?>
              <tr>
                <td>S<?= (int) $s['numero'] ?> · <?= (int) $s['annee'] ?></td>
                <td style="color:var(--doux)"><?= dateFr($s['debut']) ?> → <?= dateFr($s['fin']) ?></td>
                <td class="n"><?= nb($s['total_production']) ?></td>
                <td class="n"><?= argent($s['ca_total']) ?></td>
                <td class="n"><?= nb($s['effectif']) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

  <?php endif; ?>
</div>

<?php require RACINE . '/app/vues/pied.php'; ?>
