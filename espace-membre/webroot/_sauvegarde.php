<?php
/* ============================================================
   MARLOWE VINEYARD — Sauvegarde de la base de données

   Deux usages :
   · à la main, en étant connecté en Direction : bouton de
     téléchargement d'un fichier .sql complet ;
   · automatiquement, en appelant l'adresse avec la clé
     ?cle=... : le fichier est écrit dans /sauvegardes,
     hors du dossier public, et seules les 14 dernières
     sauvegardes sont conservées.

   Le fichier produit se réimporte tel quel dans n'importe
   quel MySQL ou MariaDB.
   ============================================================ */
declare(strict_types=1);
define('RACINE', dirname(__DIR__));
require RACINE . '/app/bdd.php';
require RACINE . '/app/auth.php';
require RACINE . '/app/outils.php';

date_default_timezone_set(configuration()['fuseau'] ?? 'Europe/Paris');
set_time_limit(300);

/* ------------------------------------------------------------
   Fabrique le contenu du fichier .sql
   ------------------------------------------------------------ */
function fabriquerDump(): string
{
    $pdo    = bdd();
    $base   = configuration()['bdd']['base'];
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

    $sql  = "-- Sauvegarde Marlowe Vineyard\n";
    $sql .= "-- Base : {$base}\n";
    $sql .= '-- Date : ' . date('d/m/Y \a H:i:s') . "\n";
    $sql .= '-- Serveur : ' . $pdo->query('SELECT VERSION()')->fetchColumn() . "\n\n";
    $sql .= "SET NAMES utf8mb4;\n";
    $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

    foreach ($tables as $table) {
        $creation = $pdo->query('SHOW CREATE TABLE `' . $table . '`')->fetch(PDO::FETCH_NUM)[1];

        $sql .= "-- ------------------------------------------------------\n";
        $sql .= "-- Table {$table}\n";
        $sql .= "-- ------------------------------------------------------\n";
        $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
        $sql .= $creation . ";\n\n";

        $lignes = $pdo->query('SELECT * FROM `' . $table . '`');
        $paquet = [];
        $nb     = 0;

        while ($ligne = $lignes->fetch(PDO::FETCH_ASSOC)) {
            $valeurs = [];
            foreach ($ligne as $v) {
                $valeurs[] = $v === null ? 'NULL' : $pdo->quote((string) $v);
            }
            $paquet[] = '(' . implode(',', $valeurs) . ')';
            $nb++;

            if (count($paquet) >= 200) {
                $sql .= 'INSERT INTO `' . $table . '` VALUES ' . implode(",\n  ", $paquet) . ";\n";
                $paquet = [];
            }
        }
        if ($paquet) {
            $sql .= 'INSERT INTO `' . $table . '` VALUES ' . implode(",\n  ", $paquet) . ";\n";
        }
        $sql .= "-- {$nb} ligne(s)\n\n";
    }

    $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    return $sql;
}

/* ------------------------------------------------------------
   Mode automatique : ?cle=<cle_secrete>
   ------------------------------------------------------------ */
$cleFournie = (string) ($_GET['cle'] ?? '');
$cleAttendue = (string) (configuration()['cle_secrete'] ?? '');

if ($cleFournie !== '') {
    header('Content-Type: text/plain; charset=utf-8');

    if ($cleAttendue === '' || !hash_equals($cleAttendue, $cleFournie)) {
        http_response_code(403);
        exit("cle invalide\n");
    }

    $dossier = RACINE . '/sauvegardes';
    if (!is_dir($dossier) && !@mkdir($dossier, 0750, true)) {
        http_response_code(500);
        exit("impossible de creer le dossier de sauvegarde\n");
    }

    $nom     = 'marlowe-' . date('Y-m-d_His') . '.sql';
    $chemin  = $dossier . '/' . $nom;
    $contenu = fabriquerDump();

    if (file_put_contents($chemin, $contenu, LOCK_EX) === false) {
        http_response_code(500);
        exit("ecriture impossible\n");
    }
    @chmod($chemin, 0640);

    /* On ne garde que les 14 plus récentes. */
    $fichiers = glob($dossier . '/marlowe-*.sql') ?: [];
    rsort($fichiers);
    foreach (array_slice($fichiers, 14) as $vieux) { @unlink($vieux); }

    req('INSERT INTO journal (auteur, action, cible, details) VALUES (?,?,?,?)',
        ['sauvegarde automatique', 'sauvegarde', $nom, nb(strlen($contenu)) . ' octets']);

    exit("ok {$nom} " . strlen($contenu) . " octets\n");
}

/* ------------------------------------------------------------
   Mode manuel : réservé à la Direction
   ------------------------------------------------------------ */
if (!connecte()) { rediriger('/connexion.php'); }
if (role() !== 'direction') {
    http_response_code(403);
    exit('Réservé à la direction.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifierJeton();
    $contenu = fabriquerDump();
    $nom     = 'marlowe-' . date('Y-m-d_His') . '.sql';

    journaliser('sauvegarde', $nom, 'téléchargement manuel');

    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nom . '"');
    header('Content-Length: ' . strlen($contenu));
    echo $contenu;
    exit;
}

$dossier   = RACINE . '/sauvegardes';
$fichiers  = is_dir($dossier) ? (glob($dossier . '/marlowe-*.sql') ?: []) : [];
rsort($fichiers);
$tables    = lignes('SHOW TABLES');
$lignesTot = 0;
foreach ($tables as $t) {
    $nomTable = reset($t);
    $lignesTot += (int) valeur('SELECT COUNT(*) FROM `' . $nomTable . '`');
}
$urlAuto = urlBase() . '/_sauvegarde.php?cle=VOTRE_CLE';

$titre     = 'Sauvegarde';
$sousTitre = 'Exporter toute la base dans un fichier réimportable ailleurs';
$ecranActif = '';
require RACINE . '/app/vues/entete.php';
?>

<div class="pile">

  <div class="carte">
    <div class="entre"><h2>Télécharger maintenant</h2></div>
    <p style="color:var(--doux);margin-top:0">
      Un seul fichier <code>.sql</code> contenant la structure et la totalité des données :
      <b><?= nb(count($tables)) ?> tables</b>, <b><?= nb($lignesTot) ?> lignes</b>.
      Il se réimporte tel quel dans n'importe quel MySQL ou MariaDB — c'est ce fichier
      qui accompagnera le site le jour du transfert.
    </p>
    <form method="post">
      <input type="hidden" name="jeton" value="<?= e(jeton()) ?>">
      <button class="btn" type="submit">Télécharger la sauvegarde</button>
    </form>
  </div>

  <div class="carte">
    <div class="entre"><h2>Sauvegardes enregistrées sur le serveur</h2></div>
    <?php if (!$fichiers): ?>
      <p style="color:var(--doux);margin:0">
        Aucune pour l'instant. Elles apparaîtront ici dès que la sauvegarde automatique sera branchée.
      </p>
    <?php else: ?>
      <div class="defile" style="border:0;background:none">
        <table style="min-width:0">
          <thead><tr><th>Fichier</th><th class="n">Taille</th><th class="n">Date</th></tr></thead>
          <tbody>
          <?php foreach ($fichiers as $f): ?>
            <tr>
              <td style="font-family:var(--mono);font-size:.82rem"><?= e(basename($f)) ?></td>
              <td class="n"><?= nb(round(filesize($f) / 1024)) ?> Ko</td>
              <td class="n" style="color:var(--doux)"><?= dateFr(date('Y-m-d H:i:s', filemtime($f)), true) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p class="aide">Elles sont dans <code>/home/container/sauvegardes</code>, hors du dossier public. Les 14 plus récentes sont conservées.</p>
    <?php endif; ?>
  </div>

  <div class="carte">
    <h2>Automatiser</h2>
    <p style="color:var(--doux)">
      La sauvegarde peut se déclencher toute seule : il suffit d'appeler cette adresse une fois par jour,
      depuis n'importe quel service capable d'ouvrir une page à heure fixe.
    </p>
    <div class="rangee" style="background:rgba(6,23,19,.7);border:1px solid var(--ligne);border-radius:9px;padding:.7rem .9rem">
      <code style="flex:1;font-family:var(--mono);font-size:.8rem;color:var(--or-clair);word-break:break-all"><?= e($urlAuto) ?></code>
    </div>
    <p class="aide">
      Remplace <code>VOTRE_CLE</code> par la clé de chiffrement générée à l'installation :
      elle se trouve dans <code>secrets.php</code>, à la racine du serveur, sous le nom <code>cle_secrete</code>.
      Sans cette clé, l'adresse renvoie une erreur : personne ne peut déclencher de sauvegarde à ta place.
      <b>Traite cette adresse complète comme un mot de passe.</b>
    </p>
  </div>

</div>

<?php require RACINE . '/app/vues/pied.php'; ?>
