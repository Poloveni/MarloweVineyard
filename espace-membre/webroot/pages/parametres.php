<?php
/* Paramètres : réglages de l'entreprise et grille des grades. */
exigerAcces('parametres', $_SERVER['REQUEST_METHOD'] === 'POST');

$champsReglages = [
    'nom_entreprise'   => ["Nom de l'entreprise", 'text',   "Affiché partout dans l'application."],
    'unite_production' => ['Unité de production', 'text',   "Ce que l'on compte : bouteilles, caisses, livraisons…"],
    'prix_unite'       => ["Gain par unité ($)",  'number', "Avant multiplicateur de grade."],
    'objectif_semaine' => ['Objectif hebdomadaire', 'number', "Objectif collectif de production."],
    'plafond_prime'    => ['Plafond de prime ($)', 'number', "Maximum par semaine et par personne."],
    'prime_recrue'     => ['Prime par recrue ($)', 'number', "Versée au recruteur."],
    'url_publique'     => ['Adresse publique', 'text', "Laisse vide tant que le site n'a pas de nom de domaine : l'adresse est alors déduite toute seule. À renseigner le jour du transfert."],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifierJeton();
    $action = champTexte('action');

    if ($action === 'reglages') {
        foreach (array_keys($champsReglages) as $cle) {
            if (isset($_POST[$cle])) { definirReglage($cle, trim((string) $_POST[$cle])); }
        }
        $podium = [champEntier('podium1'), champEntier('podium2'), champEntier('podium3')];
        definirReglage('podium', json_encode($podium));
        journaliser('parametres_modif', 'reglages');
        message('Réglages enregistrés.');
        rediriger('/index.php?p=parametres');
    }

    if ($action === 'discord') {
        definirReglage('discord_role_requis',  preg_match('/^\d{17,20}$/', champTexte('discord_role_requis')) ? champTexte('discord_role_requis') : '');
        definirReglage('discord_auto_grade',   isset($_POST['discord_auto_grade'])   ? '1' : '0');
        definirReglage('discord_auto_retrait', isset($_POST['discord_auto_retrait']) ? '1' : '0');

        foreach (lignes('SELECT role_id FROM discord_roles') as $r) {
            $rid = (string) $r['role_id'];
            $grade = (int) ($_POST['drole_grade'][$rid] ?? 0);
            $siteR = (string) ($_POST['drole_site'][$rid] ?? '');
            req('UPDATE discord_roles SET libelle = ?, grade_id = ?, role_site = ?, masque = ? WHERE role_id = ?', [
                trim((string) ($_POST['drole_libelle'][$rid] ?? '')) ?: null,
                $grade > 0 ? $grade : null,
                in_array($siteR, ['direction','rh','comptable','commercial','membre'], true) ? $siteR : null,
                isset($_POST['drole_masque'][$rid]) ? 1 : 0,
                $rid,
            ]);
        }
        journaliser('parametres_modif', 'discord');
        message('Correspondance Discord enregistrée.');
        rediriger('/index.php?p=parametres');
    }

    if ($action === 'grades') {
        foreach (lignes('SELECT id FROM grades') as $g) {
            $id = (int) $g['id'];
            req('UPDATE grades SET nom=?, quota=?, multiplicateur=?, seuil_montee=? WHERE id=?', [
                trim((string) ($_POST['nom'][$id]   ?? '')) ?: 'Sans nom',
                max(0, (int) ($_POST['quota'][$id]  ?? 0)),
                max(0, (float) str_replace(',', '.', (string) ($_POST['mult'][$id] ?? 1))),
                ($_POST['seuil'][$id] ?? '') === '' ? null : max(0, (int) $_POST['seuil'][$id]),
                $id,
            ]);
        }
        journaliser('parametres_modif', 'grades');
        message('Grille des grades enregistrée.');
        rediriger('/index.php?p=parametres');
    }
}

$reg    = reglages(true);
$podium = reglageJson('podium', [0, 0, 0]) + [0, 0, 0];
$grades = lignes('SELECT * FROM grades ORDER BY rang');

$rolesDiscord = lignes(
    'SELECT d.*, p.nom_rp, p.pseudo_discord,
            COALESCE(p.nom_rp, p.pseudo_discord) AS porteur
     FROM discord_roles d
     LEFT JOIN profils p ON p.id = d.dernier_porteur
     ORDER BY d.masque, d.vu_le DESC'
);

$titre     = 'Paramètres';
$sousTitre = "Les règles du domaine. Tout se change ici, jamais dans le code.";
require RACINE . '/app/vues/entete.php';
?>

<div class="pile">

  <div class="carte">
    <div class="entre"><h2>Réglages généraux</h2></div>
    <form method="post" class="bloc">
      <input type="hidden" name="jeton" value="<?= e(jeton()) ?>">
      <input type="hidden" name="action" value="reglages">

      <?php foreach ($champsReglages as $cle => [$libelle, $type, $aide]): ?>
        <div>
          <label for="<?= e($cle) ?>"><?= e($libelle) ?></label>
          <input type="<?= e($type) ?>" id="<?= e($cle) ?>" name="<?= e($cle) ?>"
                 value="<?= e($reg[$cle] ?? '') ?>"<?= $type === 'number' ? ' min="0" step="1"' : '' ?>>
          <p class="aide"><?= e($aide) ?></p>
        </div>
      <?php endforeach; ?>

      <div>
        <label>Podium hebdomadaire ($)</label>
        <div class="rangee" style="gap:.4rem">
          <input type="number" name="podium1" min="0" value="<?= (int) $podium[0] ?>" style="width:33%" title="1er">
          <input type="number" name="podium2" min="0" value="<?= (int) $podium[1] ?>" style="width:33%" title="2e">
          <input type="number" name="podium3" min="0" value="<?= (int) $podium[2] ?>" style="width:33%" title="3e">
        </div>
        <p class="aide">Primes du 1<sup>er</sup>, 2<sup>e</sup> et 3<sup>e</sup> producteur de la semaine.</p>
      </div>

      <div><button class="btn" type="submit">Enregistrer</button></div>
    </form>
  </div>

  <div class="carte">
    <div class="entre">
      <h2>Grille des grades</h2>
      <span style="color:var(--doux);font-size:.85rem">Quota exprimé en <?= e(unite()) ?></span>
    </div>

    <form method="post">
      <input type="hidden" name="jeton" value="<?= e(jeton()) ?>">
      <input type="hidden" name="action" value="grades">
      <div class="defile" style="border:0;background:none">
        <table>
          <thead>
            <tr><th class="n">Rang</th><th>Nom du grade</th><th class="n">Quota</th><th class="n">Multiplicateur</th><th class="n">Seuil de montée</th></tr>
          </thead>
          <tbody>
          <?php foreach ($grades as $g): $id = (int) $g['id']; ?>
            <tr>
              <td class="n"><span class="badge" style="background:<?= e($g['couleur']) ?>22;color:<?= e($g['couleur']) ?>"><?= (int) $g['rang'] ?></span></td>
              <td><input type="text" name="nom[<?= $id ?>]" value="<?= e($g['nom']) ?>"></td>
              <td><input type="number" min="0" name="quota[<?= $id ?>]" value="<?= (int) $g['quota'] ?>"></td>
              <td><input type="text" name="mult[<?= $id ?>]" value="<?= e(rtrim(rtrim(number_format((float) $g['multiplicateur'], 2, ',', ''), '0'), ',')) ?>"></td>
              <td><input type="number" min="0" name="seuil[<?= $id ?>]" value="<?= $g['seuil_montee'] === null ? '' : (int) $g['seuil_montee'] ?>" placeholder="grade max"></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div style="margin-top:1.2rem"><button class="btn" type="submit">Enregistrer la grille</button></div>
    </form>

    <p class="aide" style="margin-top:1rem">
      Le <b>multiplicateur</b> s'applique au gain par unité pour calculer la prime.
      Le <b>seuil de montée</b> est la production à partir de laquelle une montée de grade devient envisageable ; laisse vide pour le grade le plus haut.
    </p>
  </div>

  <div class="carte">
    <div class="entre">
      <h2>Connexion Discord</h2>
      <span style="color:var(--doux);font-size:.85rem">
        <?= discordPret() ? 'application raccordée' : 'application non configurée' ?>
      </span>
    </div>

    <?php if (!discordPret()): ?>
      <p style="color:var(--doux);margin:0">
        Les identifiants de l'application Discord n'ont pas encore été enregistrés.
        Cela se fait une seule fois, sur la page <code>/_discord.php</code>.
      </p>
    <?php else: ?>
      <p style="color:var(--doux);margin-top:0">
        Discord ne nous communique jamais le <em>nom</em> des rôles, seulement leur numéro.
        Chaque numéro aperçu lors d'une connexion s'ajoute au tableau ci-dessous : donne-lui un nom,
        puis dis à quel grade et à quel niveau d'accès il correspond.
      </p>

      <form method="post">
        <input type="hidden" name="jeton" value="<?= e(jeton()) ?>">
        <input type="hidden" name="action" value="discord">

        <?php if (!$rolesDiscord): ?>
          <div class="vide" style="margin:1rem 0">
            <p style="margin:0">Aucun rôle observé pour l'instant. Le tableau se remplira tout seul
            au fur et à mesure que les membres se connecteront avec Discord.</p>
          </div>
        <?php else: ?>
          <div class="defile" style="border:0;background:none">
            <table>
              <thead>
                <tr><th>Numéro du rôle</th><th>Nom que tu lui donnes</th><th>Grade</th><th>Accès au site</th><th class="n">Vu chez</th><th class="n">Ignorer</th></tr>
              </thead>
              <tbody>
              <?php foreach ($rolesDiscord as $r): $rid = e((string) $r['role_id']); ?>
                <tr>
                  <td style="font-family:var(--mono);font-size:.78rem;color:var(--doux)"><?= $rid ?></td>
                  <td><input type="text" name="drole_libelle[<?= $rid ?>]" value="<?= e((string) ($r['libelle'] ?? '')) ?>" placeholder="ex. Caviste"></td>
                  <td>
                    <select name="drole_grade[<?= $rid ?>]">
                      <option value="0">—</option>
                      <?php foreach ($grades as $g): ?>
                        <option value="<?= (int) $g['id'] ?>"<?= (int) $g['id'] === (int) $r['grade_id'] ? ' selected' : '' ?>><?= e($g['nom']) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </td>
                  <td>
                    <select name="drole_site[<?= $rid ?>]">
                      <option value="">—</option>
                      <?php foreach (['direction'=>'Direction','rh'=>'RH','comptable'=>'Comptable','commercial'=>'Commercial','membre'=>'Membre'] as $k => $lib): ?>
                        <option value="<?= $k ?>"<?= $k === (string) $r['role_site'] ? ' selected' : '' ?>><?= $lib ?></option>
                      <?php endforeach; ?>
                    </select>
                  </td>
                  <td class="n" style="color:var(--doux);font-size:.85rem"><?= e((string) ($r['porteur'] ?? '—')) ?></td>
                  <td class="n"><input type="checkbox" name="drole_masque[<?= $rid ?>]" value="1"<?= (int) $r['masque'] === 1 ? ' checked' : '' ?>></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>

        <div class="bloc" style="margin-top:1.4rem">
          <div>
            <label for="discord_role_requis">Rôle Discord exigé pour entrer</label>
            <input type="text" id="discord_role_requis" name="discord_role_requis"
                   value="<?= e($reg['discord_role_requis'] ?? '') ?>" placeholder="laisse vide : être sur le serveur suffit">
            <p class="aide">Colle ici le numéro d'un rôle du tableau. Sans ce rôle, la connexion sera refusée même à un membre du serveur.</p>
          </div>

          <div>
            <label style="display:flex;align-items:center;gap:.55rem;text-transform:none;letter-spacing:0;color:var(--texte);font-size:.95rem">
              <input type="checkbox" name="discord_auto_grade" value="1"<?= reglage('discord_auto_grade','0') === '1' ? ' checked' : '' ?>>
              Attribuer le grade automatiquement à chaque connexion
            </label>
            <p class="aide">Le grade du site suit alors le rôle Discord. Toute modification manuelle sera écrasée à la connexion suivante. Une direction n'est jamais rétrogradée automatiquement : c'est ce qui empêche de se retrouver enfermé dehors.</p>
          </div>

          <div>
            <label style="display:flex;align-items:center;gap:.55rem;text-transform:none;letter-spacing:0;color:var(--texte);font-size:.95rem">
              <input type="checkbox" name="discord_auto_retrait" value="1"<?= reglage('discord_auto_retrait','0') === '1' ? ' checked' : '' ?>>
              Désactiver la fiche de qui quitte le serveur ou perd le rôle exigé
            </label>
            <p class="aide">Le contrôle a lieu lors du passage quotidien. La fiche n'est jamais supprimée : elle est seulement désactivée, et l'historique reste.</p>
          </div>

          <div class="rangee" style="gap:.8rem;flex-wrap:wrap">
            <button class="btn" type="submit">Enregistrer</button>
            <a class="btn fantome" href="/_controle-discord.php">Contrôler les accès maintenant</a>
          </div>
        </div>
      </form>
    <?php endif; ?>
  </div>

  <div class="carte">
    <h2>État de l'installation</h2>
    <div class="defile" style="border:0;background:none">
      <table style="min-width:0">
        <tbody>
          <tr><td>Adresse utilisée</td><td class="n"><?= e(urlBase()) ?></td></tr>
          <tr><td>Base de données</td><td class="n"><?= e(configuration()['bdd']['base']) ?></td></tr>
          <tr><td>Version du serveur</td><td class="n"><?= e((string) valeur('SELECT VERSION()')) ?></td></tr>
          <tr><td>Tables installées</td><td class="n"><?= nb(count(lignes('SHOW TABLES'))) ?></td></tr>
          <tr><td>Étapes de migration appliquées</td><td class="n"><?= nb((int) valeur('SELECT COUNT(*) FROM migrations')) ?></td></tr>
          <tr>
            <td>Connexion Discord</td>
            <td class="n">
              <?php if (configuration()['discord']['client_id'] !== ''): ?>
                <span class="badge vert">configurée</span>
              <?php else: ?>
                <span class="badge gris">en attente</span>
              <?php endif; ?>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

</div>

<?php require RACINE . '/app/vues/pied.php'; ?>
