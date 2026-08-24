<?php
/* ============================================================
   MARLOWE VINEYARD — Contrôle périodique des accès Discord

   Passe en revue toutes les personnes qui se sont connectées
   avec Discord, redemande discrètement à Discord si elles sont
   toujours sur le serveur et avec quels rôles, puis met à jour
   grades, accès et activation selon les réglages.

   Deux usages, comme pour la sauvegarde :
   · à la main, bouton dans Paramètres (direction uniquement) ;
   · automatiquement, en appelant cette adresse avec ?cle=…

   Rien n'est jamais supprimé : au pire une fiche est désactivée,
   et tout son historique reste intact.
   ============================================================ */
declare(strict_types=1);
define('RACINE', dirname(__DIR__));
require RACINE . '/app/bdd.php';
require RACINE . '/app/auth.php';
require RACINE . '/app/outils.php';
require RACINE . '/app/discord.php';

date_default_timezone_set(configuration()['fuseau'] ?? 'Europe/Paris');
set_time_limit(300);

/**
 * Le contrôle lui-même. Renvoie un compte rendu ligne par ligne.
 */
function controlerAccesDiscord(): array
{
    $rapport  = [];
    $guildId  = discordConf()['guild_id'];
    $autoGrade   = reglage('discord_auto_grade', '0') === '1';
    $autoRetrait = reglage('discord_auto_retrait', '0') === '1';
    $roleRequis  = trim((string) reglage('discord_role_requis', ''));

    $gens = lignes(
        'SELECT p.id, p.nom_rp, p.pseudo_discord, p.grade_id, p.role_site, p.actif,
                j.refresh_chiffre
         FROM discord_jetons j
         JOIN profils p ON p.id = j.profil_id
         ORDER BY p.nom_rp'
    );

    foreach ($gens as $g) {
        $qui = $g['nom_rp'] ?: $g['pseudo_discord'];

        $refresh = coffreDechiffrer((string) $g['refresh_chiffre']);
        if ($refresh === null) {
            $rapport[] = [$qui, 'illisible', 'Le jeton enregistré est illisible. La personne devra se reconnecter.'];
            req('UPDATE discord_jetons SET dernier_controle = NOW(), dernier_statut = ? WHERE profil_id = ?',
                ['jeton illisible', (int) $g['id']]);
            continue;
        }

        $neuf = discordRafraichir($refresh);
        if ($neuf['code'] !== 200 || empty($neuf['corps']['access_token'])) {
            $rapport[] = [$qui, 'autorisation retirée', 'Discord refuse de renouveler l\'autorisation : la personne l\'a révoquée.'];
            req('UPDATE discord_jetons SET dernier_controle = NOW(), dernier_statut = ? WHERE profil_id = ?',
                ['autorisation revoquee', (int) $g['id']]);
            continue;
        }

        $acces = (string) $neuf['corps']['access_token'];
        if (!empty($neuf['corps']['refresh_token'])) {
            discordEnregistrerJeton((int) $g['id'], (string) $neuf['corps']['refresh_token'],
                (int) ($neuf['corps']['expires_in'] ?? 604800), 'controle');
        }

        $m = discordMembre($acces, $guildId);

        /* A quitté le serveur */
        if ($m['code'] === 404) {
            if ($autoRetrait && (int) $g['actif'] === 1) {
                req('UPDATE profils SET actif = 0, motif_desactivation = ? WHERE id = ?',
                    ['a quitté le serveur Discord', (int) $g['id']]);
                req('INSERT INTO mouvements_grade (profil_id, ancien_grade, sens, motif) VALUES (?,?,?,?)',
                    [(int) $g['id'],
                     $g['grade_id'] ? (string) valeur('SELECT nom FROM grades WHERE id = ?', [(int) $g['grade_id']]) : null,
                     'depart', 'départ du serveur Discord']);
                $rapport[] = [$qui, 'désactivé', 'N\'est plus sur le serveur Discord.'];
            } else {
                $rapport[] = [$qui, 'absent du serveur', 'N\'est plus sur le serveur Discord (retrait automatique désactivé).'];
            }
            req('UPDATE discord_jetons SET dernier_controle = NOW(), dernier_statut = ? WHERE profil_id = ?',
                ['hors serveur', (int) $g['id']]);
            continue;
        }

        if ($m['code'] !== 200) {
            $rapport[] = [$qui, 'indéterminé', 'Discord n\'a pas répondu correctement (code ' . $m['code'] . ').'];
            req('UPDATE discord_jetons SET dernier_controle = NOW(), dernier_statut = ? WHERE profil_id = ?',
                ['erreur ' . $m['code'], (int) $g['id']]);
            continue;
        }

        $roles = array_values(array_filter((array) ($m['corps']['roles'] ?? []),
            static fn ($r) => preg_match('/^\d{17,20}$/', (string) $r) === 1));
        discordMemoriserRoles($roles, (int) $g['id']);
        req('UPDATE profils SET discord_verifie_le = NOW() WHERE id = ?', [(int) $g['id']]);

        /* A perdu le rôle exigé */
        if ($roleRequis !== '' && !in_array($roleRequis, $roles, true)) {
            if ($autoRetrait && (int) $g['actif'] === 1) {
                req('UPDATE profils SET actif = 0, motif_desactivation = ? WHERE id = ?',
                    ['rôle Discord retiré', (int) $g['id']]);
                $rapport[] = [$qui, 'désactivé', 'A perdu le rôle Discord exigé.'];
            } else {
                $rapport[] = [$qui, 'rôle manquant', 'N\'a plus le rôle exigé (retrait automatique désactivé).'];
            }
            req('UPDATE discord_jetons SET dernier_controle = NOW(), dernier_statut = ? WHERE profil_id = ?',
                ['role manquant', (int) $g['id']]);
            continue;
        }

        /* Retour de quelqu'un désactivé automatiquement */
        $changements = [];
        if ((int) $g['actif'] === 0 && str_contains((string) valeur('SELECT motif_desactivation FROM profils WHERE id = ?', [(int) $g['id']]), 'Discord')) {
            req('UPDATE profils SET actif = 1, motif_desactivation = NULL WHERE id = ?', [(int) $g['id']]);
            $changements[] = 'réactivé';
        }

        if ($autoGrade && $roles) {
            $marqueurs = implode(',', array_fill(0, count($roles), '?'));

            $nouveauGrade = ligne(
                'SELECT g.id, g.nom, g.rang FROM discord_roles d
                 JOIN grades g ON g.id = d.grade_id
                 WHERE d.role_id IN (' . $marqueurs . ') AND d.masque = 0
                 ORDER BY g.rang DESC LIMIT 1', $roles);

            if ($nouveauGrade && (int) $nouveauGrade['id'] !== (int) $g['grade_id']) {
                $ancien = $g['grade_id'] ? ligne('SELECT nom, rang FROM grades WHERE id = ?', [(int) $g['grade_id']]) : null;
                req('UPDATE profils SET grade_id = ? WHERE id = ?', [(int) $nouveauGrade['id'], (int) $g['id']]);
                req('INSERT INTO mouvements_grade (profil_id, ancien_grade, nouveau_grade, sens, motif) VALUES (?,?,?,?,?)',
                    [(int) $g['id'], $ancien['nom'] ?? null, (string) $nouveauGrade['nom'],
                     $ancien === null ? 'arrivee' : ((int) $nouveauGrade['rang'] >= (int) $ancien['rang'] ? 'montee' : 'descente'),
                     'contrôle Discord']);
                $changements[] = 'grade → ' . $nouveauGrade['nom'];
            }

            $nouveauRole = valeur(
                'SELECT role_site FROM discord_roles
                 WHERE role_id IN (' . $marqueurs . ') AND role_site IS NOT NULL AND masque = 0
                 ORDER BY FIELD(role_site, \'direction\',\'rh\',\'comptable\',\'commercial\',\'membre\') LIMIT 1', $roles);

            /* Même garde-fou qu'à la connexion : une direction n'est jamais
               rétrogradée automatiquement. */
            if ((string) $g['role_site'] === 'direction' && $nouveauRole !== 'direction') {
                $nouveauRole = null;
            }

            if ($nouveauRole && (string) $nouveauRole !== (string) $g['role_site']) {
                req('UPDATE profils SET role_site = ? WHERE id = ?', [(string) $nouveauRole, (int) $g['id']]);
                $changements[] = 'accès → ' . $nouveauRole;
            }
        }

        req('UPDATE discord_jetons SET dernier_controle = NOW(), dernier_statut = ? WHERE profil_id = ?',
            ['ok', (int) $g['id']]);

        $rapport[] = [$qui, 'à jour', $changements ? implode(' · ', $changements) : 'Rien à changer.'];
    }

    req('INSERT INTO journal (auteur, action, cible, details) VALUES (?,?,?,?)',
        ['contrôle Discord', 'discord_controle', null, count($rapport) . ' compte(s) vérifié(s)']);

    return $rapport;
}

/* ------------------------------------------------------------
   Mode automatique : ?cle=<cle_secrete>
   ------------------------------------------------------------ */
$cleFournie  = (string) ($_GET['cle'] ?? '');
$cleAttendue = (string) (configuration()['cle_secrete'] ?? '');

if ($cleFournie !== '') {
    header('Content-Type: text/plain; charset=utf-8');
    if ($cleAttendue === '' || !hash_equals($cleAttendue, $cleFournie)) {
        http_response_code(403);
        exit("cle invalide\n");
    }
    if (!discordPret()) { exit("discord non configure\n"); }

    foreach (controlerAccesDiscord() as [$qui, $etat, $detail]) {
        echo $qui . ' | ' . $etat . ' | ' . $detail . "\n";
    }
    exit;
}

/* ------------------------------------------------------------
   Mode manuel : réservé à la direction
   ------------------------------------------------------------ */
if (!connecte()) { rediriger('/connexion.php'); }
if (role() !== 'direction') { http_response_code(403); exit('Réservé à la direction.'); }
if (!discordPret()) { rediriger('/index.php?p=parametres'); }

$rapport = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifierJeton();
    $rapport = controlerAccesDiscord();
}

$titre      = 'Contrôle Discord';
$sousTitre  = 'Revérifier qui est encore sur le serveur, et avec quels rôles';
$ecranActif = 'parametres';
require RACINE . '/app/vues/entete.php';
?>

<div class="pile">
  <div class="carte">
    <div class="entre"><h2>Lancer un contrôle</h2></div>
    <p style="color:var(--doux);margin-top:0">
      Chaque personne ayant déjà utilisé la connexion Discord est revérifiée auprès de Discord :
      est-elle toujours sur le serveur, a-t-elle toujours ses rôles ? Les réglages de la page
      Paramètres décident de ce qui est appliqué automatiquement.
    </p>
    <form method="post">
      <input type="hidden" name="jeton" value="<?= e(jeton()) ?>">
      <button class="btn" type="submit">Contrôler maintenant</button>
    </form>
  </div>

  <?php if ($rapport !== null): ?>
    <div class="carte">
      <div class="entre"><h2>Compte rendu</h2><span style="color:var(--doux);font-size:.85rem"><?= nb(count($rapport)) ?> compte(s)</span></div>
      <?php if (!$rapport): ?>
        <p style="color:var(--doux);margin:0">Personne ne s'est encore connecté avec Discord : il n'y a rien à contrôler.</p>
      <?php else: ?>
        <div class="defile" style="border:0;background:none">
          <table>
            <thead><tr><th>Personne</th><th>État</th><th>Détail</th></tr></thead>
            <tbody>
            <?php foreach ($rapport as [$qui, $etat, $detail]): ?>
              <tr>
                <td><?= e($qui) ?></td>
                <td><span class="badge <?= $etat === 'à jour' ? 'vert' : ($etat === 'désactivé' ? 'rouge' : 'gris') ?>"><?= e($etat) ?></span></td>
                <td style="color:var(--doux)"><?= e($detail) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="carte">
    <h2>Automatiser</h2>
    <p style="color:var(--doux)">
      Le contrôle peut tourner tout seul une fois par jour, en appelant cette adresse
      depuis n'importe quel service capable d'ouvrir une page à heure fixe.
    </p>
    <div class="rangee" style="background:rgba(6,23,19,.7);border:1px solid var(--ligne);border-radius:9px;padding:.7rem .9rem">
      <code style="flex:1;font-family:var(--mono);font-size:.8rem;color:var(--or-clair);word-break:break-all"><?= e(urlBase() . '/_controle-discord.php?cle=VOTRE_CLE') ?></code>
    </div>
    <p class="aide">
      Même clé que la sauvegarde : celle de <code>secrets.php</code>, sous le nom <code>cle_secrete</code>.
      <b>Traite cette adresse complète comme un mot de passe.</b>
    </p>
  </div>
</div>

<?php require RACINE . '/app/vues/pied.php'; ?>
