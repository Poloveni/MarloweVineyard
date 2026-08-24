<?php
/* Effectif : liste, ajout, modification du grade et du rôle. */
exigerAcces('effectif');
$modifiable = peutModifier('effectif');

$semaine = semaineCourante();
$grades  = lignes('SELECT * FROM grades ORDER BY rang');
$roles   = ['direction' => 'Direction', 'rh' => 'Ressources humaines', 'comptable' => 'Comptable',
            'commercial' => 'Commercial', 'membre' => 'Membre'];

/* ---------- actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $modifiable) {
    verifierJeton();
    $action = champTexte('action');

    if ($action === 'ajouter') {
        $nom = champTexte('nom_rp');
        if (mb_strlen($nom) < 2) {
            message("Il faut au moins un nom pour créer une fiche.", 'erreur');
        } elseif (valeur('SELECT COUNT(*) FROM profils WHERE nom_rp = ?', [$nom]) > 0) {
            message("Quelqu'un porte déjà ce nom dans l'effectif.", 'erreur');
        } else {
            $gradeId = champEntier('grade_id') ?: null;
            req('INSERT INTO profils (discord_id, pseudo_discord, nom_rp, poste, grade_id, role_site, date_arrivee, actif)
                 VALUES (?,?,?,?,?,?,?,1)', [
                'manuel-' . bin2hex(random_bytes(6)),
                $nom,
                $nom,
                champTexte('poste') ?: null,
                $gradeId,
                array_key_exists(champTexte('role_site'), $roles) ? champTexte('role_site') : 'membre',
                champTexte('date_arrivee') ?: date('Y-m-d'),
            ]);
            $id = (int) bdd()->lastInsertId();
            req('INSERT INTO mouvements_grade (profil_id, nouveau_grade, sens, motif, par) VALUES (?,?,?,?,?)', [
                $id,
                $gradeId ? (string) valeur('SELECT nom FROM grades WHERE id = ?', [$gradeId]) : null,
                'arrivee', 'inscription manuelle', utilisateur()['id'],
            ]);
            journaliser('effectif_ajout', $nom);
            message("$nom a rejoint l'effectif.");
        }
        rediriger('/index.php?p=effectif');
    }

    if ($action === 'inviter') {
        $id     = champEntier('id');
        $profil = ligne('SELECT * FROM profils WHERE id = ? AND actif = 1', [$id]);
        if (!$profil) {
            message("Fiche introuvable ou compte désactivé.", 'erreur');
        } elseif (role() !== 'direction') {
            message("Seule la direction peut ouvrir un accès.", 'erreur');
        } else {
            /* Un seul lien valable à la fois : on annule les précédents. */
            req('UPDATE invitations SET utilise_le = NOW() WHERE profil_id = ? AND utilise_le IS NULL', [$id]);

            $jeton = bin2hex(random_bytes(32));
            req('INSERT INTO invitations (profil_id, jeton_hash, cree_par, expire_le)
                 VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY))',
                [$id, hash('sha256', $jeton), utilisateur()['id']]);

            demarrerSession();
            $_SESSION['lien_invitation'] = [
                'nom'  => $profil['nom_rp'] ?: $profil['pseudo_discord'],
                'lien' => urlBase() . '/invitation.php?j=' . $jeton,
            ];
            journaliser('acces_invitation', $profil['nom_rp'], 'lien généré, valable 7 jours');
            message("Lien d'accès généré pour " . ($profil['nom_rp'] ?: $profil['pseudo_discord']) . ".");
        }
        rediriger('/index.php?p=effectif');
    }

    if ($action === 'modifier') {
        $id      = champEntier('id');
        $profil  = ligne('SELECT * FROM profils WHERE id = ?', [$id]);
        if ($profil) {
            $nouveauGrade = champEntier('grade_id') ?: null;
            $nouveauRole  = array_key_exists(champTexte('role_site'), $roles) ? champTexte('role_site') : $profil['role_site'];

            /* Sécurité : on ne peut pas se retirer soi-même la direction. */
            if ((int) $profil['id'] === (int) utilisateur()['id'] && $nouveauRole !== 'direction') {
                message("Tu ne peux pas retirer ton propre accès Direction. Demande à quelqu'un d'autre de le faire.", 'erreur');
                rediriger('/index.php?p=effectif');
            }

            if ((int) ($profil['grade_id'] ?? 0) !== (int) ($nouveauGrade ?? 0)) {
                $ancien = $profil['grade_id'] ? (string) valeur('SELECT nom FROM grades WHERE id = ?', [$profil['grade_id']]) : null;
                $neuf   = $nouveauGrade ? (string) valeur('SELECT nom FROM grades WHERE id = ?', [$nouveauGrade]) : null;
                $rangA  = $profil['grade_id'] ? (int) valeur('SELECT rang FROM grades WHERE id = ?', [$profil['grade_id']]) : 0;
                $rangB  = $nouveauGrade ? (int) valeur('SELECT rang FROM grades WHERE id = ?', [$nouveauGrade]) : 0;
                req('INSERT INTO mouvements_grade (profil_id, ancien_grade, nouveau_grade, sens, motif, par) VALUES (?,?,?,?,?,?)', [
                    $id, $ancien, $neuf, $rangB >= $rangA ? 'montee' : 'descente', 'modification manuelle', utilisateur()['id'],
                ]);
            }

            req('UPDATE profils SET nom_rp=?, poste=?, grade_id=?, role_site=?, telephone=?, actif=? WHERE id=?', [
                champTexte('nom_rp') ?: $profil['nom_rp'],
                champTexte('poste') ?: null,
                $nouveauGrade,
                $nouveauRole,
                champTexte('telephone') ?: null,
                champEntier('actif', 1) === 1 ? 1 : 0,
                $id,
            ]);
            journaliser('effectif_modif', $profil['nom_rp']);
            message('Fiche mise à jour.');
        }
        rediriger('/index.php?p=effectif');
    }
}

/* ---------- lecture ---------- */
$membres = lignes(
    'SELECT p.*, g.nom AS grade_nom, g.quota AS quota, g.couleur AS couleur,
            COALESCE((SELECT SUM(quantite) FROM productions pr WHERE pr.profil_id = p.id AND pr.semaine_id = ?),0) AS prod,
            (SELECT MAX(expire_le) FROM invitations i WHERE i.profil_id = p.id AND i.utilise_le IS NULL AND i.expire_le > NOW()) AS invit_expire
     FROM profils p
     LEFT JOIN grades g ON g.id = p.grade_id
     ORDER BY p.actif DESC, g.rang DESC, p.nom_rp', [$semaine['id']]
);

$edite = champEntier('edit');

$titre     = 'Effectif';
$sousTitre = count($membres) . ' fiche' . (count($membres) > 1 ? 's' : '') . ' · quotas de la semaine ' . $semaine['numero'];
require RACINE . '/app/vues/entete.php';
?>

<div class="pile">

<?php
demarrerSession();
$lienInvit = $_SESSION['lien_invitation'] ?? null;
unset($_SESSION['lien_invitation']);
?>
<?php if ($lienInvit): ?>
  <div class="carte" style="border-color:rgba(230,201,118,.5)">
    <div class="entre"><h2>Lien d'accès pour <?= e($lienInvit['nom']) ?></h2></div>
    <p style="color:var(--doux);margin-top:0">
      Envoie ce lien à la personne, en message privé sur Discord. Elle choisira son propre code —
      tu ne le connaîtras jamais, et c'est voulu.
    </p>
    <div class="rangee" style="background:rgba(6,23,19,.7);border:1px solid var(--ligne);border-radius:9px;padding:.7rem .9rem">
      <code id="lienInvit" style="flex:1;font-family:var(--mono);font-size:.8rem;color:var(--or-clair);word-break:break-all"><?= e($lienInvit['lien']) ?></code>
      <button class="btn petit" type="button"
              onclick="navigator.clipboard.writeText(document.getElementById('lienInvit').textContent.trim());this.textContent='Copié'">Copier</button>
    </div>
    <p class="aide">
      Valable <b>7 jours</b>, utilisable <b>une seule fois</b>. Il n'est affiché qu'ici et maintenant :
      si tu quittes cette page sans le copier, il faudra en générer un nouveau.
    </p>
  </div>
<?php endif; ?>

<?php if ($modifiable): ?>
  <div class="carte">
    <div class="entre"><h2>Inscrire quelqu'un</h2></div>
    <form method="post" class="bloc">
      <input type="hidden" name="jeton" value="<?= e(jeton()) ?>">
      <input type="hidden" name="action" value="ajouter">
      <div>
        <label for="n">Nom RP</label>
        <input type="text" id="n" name="nom_rp" required placeholder="Prénom Nom">
      </div>
      <div>
        <label for="po">Poste</label>
        <input type="text" id="po" name="poste" placeholder="Vendangeur, Régisseur…">
      </div>
      <div>
        <label for="gr">Grade</label>
        <select id="gr" name="grade_id">
          <option value="">— aucun —</option>
          <?php foreach ($grades as $g): ?>
            <option value="<?= (int) $g['id'] ?>"><?= e($g['nom']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="ro">Accès au site</label>
        <select id="ro" name="role_site">
          <?php foreach ($roles as $k => $v): ?>
            <option value="<?= e($k) ?>"<?= $k === 'membre' ? ' selected' : '' ?>><?= e($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="da">Arrivée</label>
        <input type="date" id="da" name="date_arrivee" value="<?= date('Y-m-d') ?>">
      </div>
      <div><button class="btn" type="submit">Ajouter</button></div>
    </form>
    <p class="aide">En attendant Discord, les fiches se créent à la main. Quand la connexion Discord sera branchée, elles se rempliront toutes seules et se rattacheront à ces fiches par le nom.</p>
  </div>
<?php endif; ?>

  <div class="carte">
    <div class="entre"><h2>L'équipe</h2></div>

    <?php if (!$membres): ?>
      <div class="vide" style="border:0;padding:2rem 0">
        <h2>Personne pour l'instant</h2>
        <p>Ajoute la première fiche avec le formulaire ci-dessus.</p>
      </div>
    <?php else: ?>
      <div class="defile" style="border:0;background:none">
        <table>
          <thead>
            <tr>
              <th>Nom</th><th>Poste</th><th>Grade</th><th>Accès</th>
              <th class="n">Production</th><th class="n">Quota</th><th>État</th><th>Connexion</th>
              <?php if ($modifiable): ?><th></th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($membres as $m):
              $quota = (int) $m['quota'];
              $pct   = $quota > 0 ? (int) round($m['prod'] / $quota * 100) : 0;
          ?>
            <?php if ($edite === (int) $m['id'] && $modifiable): ?>
              <tr>
                <td colspan="9">
                  <form method="post" class="bloc" style="margin:.4rem 0">
                    <input type="hidden" name="jeton" value="<?= e(jeton()) ?>">
                    <input type="hidden" name="action" value="modifier">
                    <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                    <div><label>Nom RP</label><input type="text" name="nom_rp" value="<?= e($m['nom_rp']) ?>"></div>
                    <div><label>Poste</label><input type="text" name="poste" value="<?= e($m['poste']) ?>"></div>
                    <div><label>Grade</label>
                      <select name="grade_id">
                        <option value="">— aucun —</option>
                        <?php foreach ($grades as $g): ?>
                          <option value="<?= (int) $g['id'] ?>"<?= (int) $m['grade_id'] === (int) $g['id'] ? ' selected' : '' ?>><?= e($g['nom']) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div><label>Accès</label>
                      <select name="role_site">
                        <?php foreach ($roles as $k => $v): ?>
                          <option value="<?= e($k) ?>"<?= $m['role_site'] === $k ? ' selected' : '' ?>><?= e($v) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div><label>Téléphone</label><input type="text" name="telephone" value="<?= e($m['telephone']) ?>"></div>
                    <div><label>État</label>
                      <select name="actif">
                        <option value="1"<?= (int) $m['actif'] === 1 ? ' selected' : '' ?>>Actif</option>
                        <option value="0"<?= (int) $m['actif'] === 0 ? ' selected' : '' ?>>Parti</option>
                      </select>
                    </div>
                    <div class="rangee">
                      <button class="btn" type="submit">Enregistrer</button>
                      <a class="btn fantome petit" href="/index.php?p=effectif">Annuler</a>
                    </div>
                  </form>
                </td>
              </tr>
            <?php else: ?>
              <tr<?= (int) $m['actif'] === 0 ? ' style="opacity:.45"' : '' ?>>
                <td><b><?= e($m['nom_rp'] ?: $m['pseudo_discord']) ?></b></td>
                <td style="color:var(--doux)"><?= e($m['poste'] ?: '—') ?></td>
                <td>
                  <?php if ($m['grade_nom']): ?>
                    <span class="badge" style="background:<?= e($m['couleur']) ?>22;color:<?= e($m['couleur']) ?>"><?= e($m['grade_nom']) ?></span>
                  <?php else: ?><span style="color:var(--doux)">—</span><?php endif; ?>
                </td>
                <td style="color:var(--doux)"><?= e($roles[$m['role_site']] ?? $m['role_site']) ?></td>
                <td class="n"><?= nb($m['prod']) ?></td>
                <td class="n"><?= $quota ? nb($quota) : '—' ?></td>
                <td>
                  <?php if ((int) $m['actif'] === 0): ?>
                    <span class="badge gris">parti</span>
                  <?php elseif (!$quota): ?>
                    <span class="badge gris">sans quota</span>
                  <?php elseif ($pct >= 100): ?>
                    <span class="badge vert"><?= $pct ?> %</span>
                  <?php elseif ($pct >= 60): ?>
                    <span class="badge or"><?= $pct ?> %</span>
                  <?php else: ?>
                    <span class="badge rouge"><?= $pct ?> %</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($m['code_hash']): ?>
                    <span class="badge vert">accès ouvert</span>
                  <?php elseif ($m['invit_expire']): ?>
                    <span class="badge or" title="Expire le <?= e(dateFr($m['invit_expire'], true)) ?>">invitation envoyée</span>
                  <?php else: ?>
                    <span class="badge gris">pas d'accès</span>
                  <?php endif; ?>
                </td>
                <?php if ($modifiable): ?>
                  <td>
                    <div class="rangee" style="gap:.35rem;flex-wrap:nowrap">
                      <a class="btn fantome petit" href="/index.php?p=effectif&amp;edit=<?= (int) $m['id'] ?>">Modifier</a>
                      <?php if (role() === 'direction' && (int) $m['actif'] === 1): ?>
                        <form method="post" style="margin:0">
                          <input type="hidden" name="jeton" value="<?= e(jeton()) ?>">
                          <input type="hidden" name="action" value="inviter">
                          <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                          <button class="btn fantome petit" type="submit"><?= $m['code_hash'] ? 'Nouveau lien' : 'Lien d\'accès' ?></button>
                        </form>
                      <?php endif; ?>
                    </div>
                  </td>
                <?php endif; ?>
              </tr>
            <?php endif; ?>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require RACINE . '/app/vues/pied.php'; ?>
