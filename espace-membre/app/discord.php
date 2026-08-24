<?php
/* ============================================================
   MARLOWE VINEYARD — Dialogue avec Discord

   Tout ce qui parle à Discord passe par ici, et nulle part
   ailleurs. Aucune de ces fonctions n'affiche quoi que ce soit :
   elles renvoient des données, les pages décident quoi en faire.

   Vocabulaire, une fois pour toutes :
   · « jeton d'accès »  : laissez-passer valable une semaine, il
     sert à poser une question à Discord au nom de la personne ;
   · « jeton de rafraîchissement » : sert uniquement à obtenir un
     nouveau laissez-passer quand l'ancien expire. C'est le seul
     que l'on conserve, et on le conserve chiffré ;
   · « guilde » : le mot employé par Discord pour dire serveur.
   ============================================================ */
declare(strict_types=1);

const DISCORD_API    = 'https://discord.com/api/v10';
const DISCORD_SCOPES = 'identify guilds.members.read';

/* ------------------------------------------------------------
   Configuration
   ------------------------------------------------------------ */

function discordConf(): array
{
    return configuration()['discord'];
}

/** Vrai si les trois valeurs ont été renseignées dans _discord.php. */
function discordPret(): bool
{
    $d = discordConf();
    return $d['client_id'] !== '' && $d['client_secret'] !== '' && $d['guild_id'] !== '';
}

/* ------------------------------------------------------------
   Coffre : chiffrement du jeton de rafraîchissement

   On ne stocke jamais un jeton en clair dans la base. Si
   quelqu'un mettait la main sur une sauvegarde SQL, il ne
   pourrait rien en faire sans la clé, qui vit dans secrets.php.
   ------------------------------------------------------------ */

function coffreCle(): string
{
    $cle = (string) (configuration()['cle_secrete'] ?? '');
    if ($cle === '') {
        throw new RuntimeException("Clé de chiffrement absente : relance installation.php.");
    }
    return hash('sha256', 'coffre|' . $cle, true);
}

function coffreChiffrer(string $clair): string
{
    $vecteur = random_bytes(12);
    $marque  = '';
    $chiffre = openssl_encrypt($clair, 'aes-256-gcm', coffreCle(), OPENSSL_RAW_DATA, $vecteur, $marque);
    if ($chiffre === false) {
        throw new RuntimeException('Chiffrement impossible.');
    }
    return base64_encode($vecteur . $marque . $chiffre);
}

function coffreDechiffrer(string $stocke): ?string
{
    $brut = base64_decode($stocke, true);
    if ($brut === false || strlen($brut) < 29) { return null; }

    $vecteur = substr($brut, 0, 12);
    $marque  = substr($brut, 12, 16);
    $chiffre = substr($brut, 28);

    $clair = openssl_decrypt($chiffre, 'aes-256-gcm', coffreCle(), OPENSSL_RAW_DATA, $vecteur, $marque);
    return $clair === false ? null : $clair;
}

/* ------------------------------------------------------------
   Appels réseau
   ------------------------------------------------------------ */

/**
 * Un appel à l'API Discord.
 * Renvoie ['code' => entier HTTP, 'corps' => tableau décodé, 'erreur' => texte|null].
 */
function discordAppel(string $methode, string $chemin, array $options = []): array
{
    $url     = str_starts_with($chemin, 'http') ? $chemin : DISCORD_API . $chemin;
    $entetes = ['Accept: application/json', 'User-Agent: MarloweVineyard (espace membre, 1.0)'];

    if (!empty($options['jeton'])) {
        $entetes[] = 'Authorization: Bearer ' . $options['jeton'];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $methode,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    if (!empty($options['formulaire'])) {
        $entetes[] = 'Content-Type: application/x-www-form-urlencoded';
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($options['formulaire']));
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $entetes);

    $reponse = curl_exec($ch);
    $code    = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $souci   = curl_error($ch);
    curl_close($ch);

    if ($reponse === false) {
        return ['code' => 0, 'corps' => [], 'erreur' => 'Discord est injoignable (' . $souci . ').'];
    }

    $corps = json_decode((string) $reponse, true);
    if (!is_array($corps)) { $corps = []; }

    return ['code' => $code, 'corps' => $corps, 'erreur' => null];
}

/* ------------------------------------------------------------
   Les quatre étapes du parcours de connexion
   ------------------------------------------------------------ */

/** 1. L'adresse vers laquelle on envoie la personne. */
function discordUrlAutorisation(string $etat): string
{
    $d = discordConf();
    return 'https://discord.com/oauth2/authorize?' . http_build_query([
        'client_id'     => $d['client_id'],
        'redirect_uri'  => $d['redirection'],
        'response_type' => 'code',
        'scope'         => DISCORD_SCOPES,
        'state'         => $etat,
        'prompt'        => 'consent',
    ]);
}

/** 2. Discord nous renvoie un code : on l'échange contre des jetons. */
function discordEchangerCode(string $code): array
{
    $d = discordConf();
    return discordAppel('POST', '/oauth2/token', ['formulaire' => [
        'client_id'     => $d['client_id'],
        'client_secret' => $d['client_secret'],
        'grant_type'    => 'authorization_code',
        'code'          => $code,
        'redirect_uri'  => $d['redirection'],
    ]]);
}

/** 2 bis. Plus tard : obtenir un nouveau laissez-passer sans déranger la personne. */
function discordRafraichir(string $refresh): array
{
    $d = discordConf();
    return discordAppel('POST', '/oauth2/token', ['formulaire' => [
        'client_id'     => $d['client_id'],
        'client_secret' => $d['client_secret'],
        'grant_type'    => 'refresh_token',
        'refresh_token' => $refresh,
    ]]);
}

/** 3. Qui est cette personne ? */
function discordMoi(string $acces): array
{
    return discordAppel('GET', '/users/@me', ['jeton' => $acces]);
}

/**
 * 4. Est-elle sur le serveur du domaine, et avec quels rôles ?
 *    Renvoie l'objet « membre », qui contient la liste des
 *    identifiants de rôles. Code 404 = elle n'est pas sur le serveur.
 */
function discordMembre(string $acces, string $guildId): array
{
    return discordAppel('GET', '/users/@me/guilds/' . rawurlencode($guildId) . '/member', ['jeton' => $acces]);
}

/* ------------------------------------------------------------
   Petits utilitaires
   ------------------------------------------------------------ */

/** Le nom affichable d'un compte Discord, quelle que soit son ancienneté. */
function discordNom(array $u): string
{
    $pseudo = (string) ($u['global_name'] ?? '');
    if ($pseudo === '') { $pseudo = (string) ($u['username'] ?? ''); }
    $discriminant = (string) ($u['discriminator'] ?? '0');
    if ($discriminant !== '0' && $discriminant !== '') {
        $pseudo .= '#' . $discriminant;
    }
    return $pseudo !== '' ? $pseudo : 'Compte Discord';
}

/** L'adresse de l'avatar, ou null s'il n'en a pas. */
function discordAvatar(array $u): ?string
{
    $id  = (string) ($u['id'] ?? '');
    $ava = (string) ($u['avatar'] ?? '');
    if ($id === '' || $ava === '') { return null; }
    $ext = str_starts_with($ava, 'a_') ? 'gif' : 'png';
    return 'https://cdn.discordapp.com/avatars/' . $id . '/' . $ava . '.' . $ext . '?size=128';
}

/**
 * Mémorise les rôles Discord aperçus.
 * Discord ne nous donne que des identifiants, jamais les noms :
 * cette table permettra à la direction de dire « ce numéro-là,
 * c'est le grade Caviste » en voyant qui le porte.
 */
function discordMemoriserRoles(array $roles, int $profilId): void
{
    foreach ($roles as $roleId) {
        $roleId = (string) $roleId;
        if (!preg_match('/^\d{17,20}$/', $roleId)) { continue; }
        req('INSERT INTO discord_roles (role_id, vu_le, dernier_porteur)
             VALUES (?, NOW(), ?)
             ON DUPLICATE KEY UPDATE vu_le = NOW(), dernier_porteur = VALUES(dernier_porteur)',
            [$roleId, $profilId]);
    }
}

/**
 * Sauvegarde le jeton de rafraîchissement d'une personne, chiffré.
 * $duree est le nombre de secondes annoncé par Discord.
 */
function discordEnregistrerJeton(int $profilId, string $refresh, int $duree, string $statut = 'ok'): void
{
    req('INSERT INTO discord_jetons (profil_id, refresh_chiffre, expire_le, dernier_controle, dernier_statut)
         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), NOW(), ?)
         ON DUPLICATE KEY UPDATE
           refresh_chiffre = VALUES(refresh_chiffre),
           expire_le       = VALUES(expire_le),
           dernier_controle = NOW(),
           dernier_statut  = VALUES(dernier_statut)',
        [$profilId, coffreChiffrer($refresh), max(60, $duree), $statut]);
}
