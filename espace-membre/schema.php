<?php
/* ============================================================
   MARLOWE VINEYARD — Schéma de la base de données

   Chaque entrée est une étape numérotée, appliquée une seule fois.
   La page _migrer.php note ce qui a déjà été fait dans la table
   "migrations" : on peut donc la relancer sans rien casser, et
   ajouter de nouvelles étapes plus tard sans toucher aux anciennes.

   Ne jamais modifier une étape déjà appliquée : en ajouter une
   nouvelle à la suite.
   ============================================================ */

return [

/* ---------- 1. Réglages ---------- */

'001_parametres' => "
CREATE TABLE parametres (
  cle          VARCHAR(64)  NOT NULL PRIMARY KEY,
  valeur       TEXT         NULL,
  description  VARCHAR(255) NULL,
  maj          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

'002_grades' => "
CREATE TABLE grades (
  id             TINYINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nom            VARCHAR(60)  NOT NULL,
  rang           TINYINT UNSIGNED NOT NULL,
  quota          INT UNSIGNED NOT NULL DEFAULT 0,
  multiplicateur DECIMAL(4,2) NOT NULL DEFAULT 1.00,
  seuil_montee   INT UNSIGNED NULL,
  couleur        CHAR(7)      NOT NULL DEFAULT '#c9a227',
  role_discord   VARCHAR(32)  NULL,
  UNIQUE KEY u_rang (rang),
  UNIQUE KEY u_role_discord (role_discord)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

/* ---------- 2. Personnes ---------- */

'003_profils' => "
CREATE TABLE profils (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  discord_id        VARCHAR(32)  NOT NULL,
  pseudo_discord    VARCHAR(80)  NOT NULL,
  avatar            VARCHAR(120) NULL,
  nom_rp            VARCHAR(80)  NULL,
  telephone         VARCHAR(20)  NULL,
  rib               VARCHAR(40)  NULL,
  poste             VARCHAR(50)  NULL,
  grade_id          TINYINT UNSIGNED NULL,
  role_site         ENUM('direction','rh','comptable','commercial','membre') NOT NULL DEFAULT 'membre',
  actif             TINYINT(1)   NOT NULL DEFAULT 1,
  motif_desactivation VARCHAR(120) NULL,
  date_arrivee      DATE         NULL,
  recruteur_id      INT UNSIGNED NULL,
  stagiaire         TINYINT(1)   NOT NULL DEFAULT 0,
  derniere_connexion DATETIME    NULL,
  derniere_activite  DATE        NULL,
  notes             TEXT         NULL,
  cree_le           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY u_discord (discord_id),
  KEY k_role (role_site),
  KEY k_actif (actif),
  CONSTRAINT fk_profil_grade     FOREIGN KEY (grade_id)     REFERENCES grades(id)  ON DELETE SET NULL,
  CONSTRAINT fk_profil_recruteur FOREIGN KEY (recruteur_id) REFERENCES profils(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

'004_sessions' => "
CREATE TABLE sessions (
  id         CHAR(64)     NOT NULL PRIMARY KEY,
  profil_id  INT UNSIGNED NOT NULL,
  ip         VARCHAR(45)  NULL,
  navigateur VARCHAR(200) NULL,
  cree_le    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  vu_le      DATETIME     NULL,
  expire_le  DATETIME     NOT NULL,
  KEY k_profil (profil_id),
  KEY k_expire (expire_le),
  CONSTRAINT fk_session_profil FOREIGN KEY (profil_id) REFERENCES profils(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

'005_discord_jetons' => "
CREATE TABLE discord_jetons (
  profil_id       INT UNSIGNED NOT NULL PRIMARY KEY,
  refresh_chiffre TEXT         NOT NULL,
  expire_le       DATETIME     NULL,
  dernier_controle DATETIME    NULL,
  dernier_statut  VARCHAR(120) NULL,
  CONSTRAINT fk_jeton_profil FOREIGN KEY (profil_id) REFERENCES profils(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

/* ---------- 3. Production ---------- */

'006_semaines' => "
CREATE TABLE semaines (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  annee          SMALLINT UNSIGNED NOT NULL,
  numero         TINYINT UNSIGNED  NOT NULL,
  debut          DATE         NOT NULL,
  fin            DATE         NOT NULL,
  objectif       INT UNSIGNED NOT NULL DEFAULT 0,
  total_production INT UNSIGNED NOT NULL DEFAULT 0,
  ca_total       DECIMAL(12,2) NOT NULL DEFAULT 0,
  effectif       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  cloturee       TINYINT(1)   NOT NULL DEFAULT 0,
  cloturee_le    DATETIME     NULL,
  cloturee_par   INT UNSIGNED NULL,
  UNIQUE KEY u_semaine (annee, numero),
  CONSTRAINT fk_semaine_par FOREIGN KEY (cloturee_par) REFERENCES profils(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

'007_productions' => "
CREATE TABLE productions (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  profil_id   INT UNSIGNED NOT NULL,
  semaine_id  INT UNSIGNED NOT NULL,
  quantite    INT NOT NULL,
  commentaire VARCHAR(200) NULL,
  saisi_par   INT UNSIGNED NULL,
  cree_le     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY k_profil_semaine (profil_id, semaine_id),
  KEY k_semaine (semaine_id),
  CONSTRAINT fk_prod_profil  FOREIGN KEY (profil_id)  REFERENCES profils(id)  ON DELETE CASCADE,
  CONSTRAINT fk_prod_semaine FOREIGN KEY (semaine_id) REFERENCES semaines(id) ON DELETE CASCADE,
  CONSTRAINT fk_prod_par     FOREIGN KEY (saisi_par)  REFERENCES profils(id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

'008_semaines_employes' => "
CREATE TABLE semaines_employes (
  semaine_id  INT UNSIGNED NOT NULL,
  profil_id   INT UNSIGNED NOT NULL,
  grade_id    TINYINT UNSIGNED NULL,
  grade_nom   VARCHAR(60)  NULL,
  poste       VARCHAR(50)  NULL,
  quantite    INT UNSIGNED NOT NULL DEFAULT 0,
  quota       INT UNSIGNED NOT NULL DEFAULT 0,
  quota_atteint TINYINT(1) NOT NULL DEFAULT 0,
  salaire     DECIMAL(10,2) NOT NULL DEFAULT 0,
  prime       DECIMAL(10,2) NOT NULL DEFAULT 0,
  prime_podium DECIMAL(10,2) NOT NULL DEFAULT 0,
  prime_extra DECIMAL(10,2) NOT NULL DEFAULT 0,
  recrues     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  absent      TINYINT(1)   NOT NULL DEFAULT 0,
  rang        SMALLINT UNSIGNED NULL,
  PRIMARY KEY (semaine_id, profil_id),
  KEY k_profil (profil_id),
  CONSTRAINT fk_se_semaine FOREIGN KEY (semaine_id) REFERENCES semaines(id) ON DELETE CASCADE,
  CONSTRAINT fk_se_profil  FOREIGN KEY (profil_id)  REFERENCES profils(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

/* ---------- 4. Ressources humaines ---------- */

'009_mouvements_grade' => "
CREATE TABLE mouvements_grade (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  profil_id     INT UNSIGNED NOT NULL,
  ancien_grade  VARCHAR(60) NULL,
  nouveau_grade VARCHAR(60) NULL,
  sens          ENUM('montee','descente','arrivee','depart') NOT NULL,
  motif         VARCHAR(200) NULL,
  par           INT UNSIGNED NULL,
  cree_le       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY k_profil (profil_id),
  CONSTRAINT fk_mvt_profil FOREIGN KEY (profil_id) REFERENCES profils(id) ON DELETE CASCADE,
  CONSTRAINT fk_mvt_par    FOREIGN KEY (par)       REFERENCES profils(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

'010_avertissements' => "
CREATE TABLE avertissements (
  id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  profil_id INT UNSIGNED NOT NULL,
  niveau    ENUM('rappel','avertissement','grave') NOT NULL DEFAULT 'avertissement',
  motif     VARCHAR(255) NOT NULL,
  actif     TINYINT(1)   NOT NULL DEFAULT 1,
  par       INT UNSIGNED NULL,
  cree_le   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY k_profil (profil_id),
  CONSTRAINT fk_avert_profil FOREIGN KEY (profil_id) REFERENCES profils(id) ON DELETE CASCADE,
  CONSTRAINT fk_avert_par    FOREIGN KEY (par)       REFERENCES profils(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

'011_absences' => "
CREATE TABLE absences (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  profil_id  INT UNSIGNED NOT NULL,
  du         DATE NOT NULL,
  au         DATE NOT NULL,
  motif      VARCHAR(255) NULL,
  statut     ENUM('attente','acceptee','refusee') NOT NULL DEFAULT 'attente',
  traite_par INT UNSIGNED NULL,
  cree_le    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY k_profil (profil_id),
  KEY k_dates (du, au),
  CONSTRAINT fk_abs_profil FOREIGN KEY (profil_id)  REFERENCES profils(id) ON DELETE CASCADE,
  CONSTRAINT fk_abs_par    FOREIGN KEY (traite_par) REFERENCES profils(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

'012_recrutements' => "
CREATE TABLE recrutements (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  profil_id    INT UNSIGNED NULL,
  nom          VARCHAR(80)  NOT NULL,
  recruteur_id INT UNSIGNED NULL,
  statut       ENUM('candidature','entretien','accepte','refuse','parti') NOT NULL DEFAULT 'candidature',
  commentaire  VARCHAR(255) NULL,
  date_event   DATE NOT NULL,
  cree_le      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY k_date (date_event),
  CONSTRAINT fk_recrut_profil    FOREIGN KEY (profil_id)    REFERENCES profils(id) ON DELETE SET NULL,
  CONSTRAINT fk_recrut_recruteur FOREIGN KEY (recruteur_id) REFERENCES profils(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

'013_blacklist' => "
CREATE TABLE blacklist (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nom        VARCHAR(80)  NOT NULL,
  discord_id VARCHAR(32)  NULL,
  motif      VARCHAR(255) NOT NULL,
  par        INT UNSIGNED NULL,
  cree_le    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY k_nom (nom),
  KEY k_discord (discord_id),
  CONSTRAINT fk_bl_par FOREIGN KEY (par) REFERENCES profils(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

/* ---------- 5. Commerce ---------- */

'014_clients' => "
CREATE TABLE clients (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nom              VARCHAR(120) NOT NULL,
  type             VARCHAR(50)  NULL,
  telephone        VARCHAR(20)  NULL,
  rib              VARCHAR(40)  NULL,
  lieu_livraison   VARCHAR(200) NULL,
  remise           DECIMAL(5,2) NOT NULL DEFAULT 0,
  derniere_commande DATE        NULL,
  relance_en_cours TINYINT(1)   NOT NULL DEFAULT 0,
  notes            TEXT         NULL,
  cree_le          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY u_nom (nom),
  KEY k_derniere (derniere_commande)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

'015_articles' => "
CREATE TABLE articles (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  reference VARCHAR(40)  NULL,
  nom       VARCHAR(120) NOT NULL,
  gamme     VARCHAR(50)  NULL,
  prix      DECIMAL(10,2) NOT NULL DEFAULT 0,
  prix_collector DECIMAL(10,2) NULL,
  tva       DECIMAL(5,2) NOT NULL DEFAULT 0,
  actif     TINYINT(1)   NOT NULL DEFAULT 1,
  UNIQUE KEY u_reference (reference),
  KEY k_gamme (gamme)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

'016_factures' => "
CREATE TABLE factures (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  numero     VARCHAR(20)  NOT NULL,
  client_id  INT UNSIGNED NULL,
  profil_id  INT UNSIGNED NULL,
  semaine_id INT UNSIGNED NULL,
  date_facture DATE       NOT NULL,
  statut     ENUM('attente','payee','annulee') NOT NULL DEFAULT 'attente',
  remise     DECIMAL(5,2) NOT NULL DEFAULT 0,
  total_ht   DECIMAL(12,2) NOT NULL DEFAULT 0,
  total_tva  DECIMAL(12,2) NOT NULL DEFAULT 0,
  total_ttc  DECIMAL(12,2) NOT NULL DEFAULT 0,
  note       VARCHAR(255) NULL,
  cree_le    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY u_numero (numero),
  KEY k_client (client_id),
  KEY k_date (date_facture),
  KEY k_statut (statut),
  CONSTRAINT fk_fact_client  FOREIGN KEY (client_id)  REFERENCES clients(id)  ON DELETE SET NULL,
  CONSTRAINT fk_fact_profil  FOREIGN KEY (profil_id)  REFERENCES profils(id)  ON DELETE SET NULL,
  CONSTRAINT fk_fact_semaine FOREIGN KEY (semaine_id) REFERENCES semaines(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

'017_facture_lignes' => "
CREATE TABLE facture_lignes (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  facture_id    INT UNSIGNED NOT NULL,
  article_id    INT UNSIGNED NULL,
  libelle       VARCHAR(160) NOT NULL,
  quantite      INT NOT NULL DEFAULT 1,
  prix_unitaire DECIMAL(10,2) NOT NULL DEFAULT 0,
  tva           DECIMAL(5,2)  NOT NULL DEFAULT 0,
  KEY k_facture (facture_id),
  CONSTRAINT fk_ligne_facture FOREIGN KEY (facture_id) REFERENCES factures(id) ON DELETE CASCADE,
  CONSTRAINT fk_ligne_article FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

/* ---------- 6. Comptabilité ---------- */

'018_depenses' => "
CREATE TABLE depenses (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  semaine_id INT UNSIGNED NULL,
  libelle    VARCHAR(160) NOT NULL,
  montant    DECIMAL(12,2) NOT NULL DEFAULT 0,
  deductible TINYINT(1)   NOT NULL DEFAULT 1,
  date_depense DATE       NOT NULL,
  par        INT UNSIGNED NULL,
  cree_le    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY k_semaine (semaine_id),
  CONSTRAINT fk_dep_semaine FOREIGN KEY (semaine_id) REFERENCES semaines(id) ON DELETE SET NULL,
  CONSTRAINT fk_dep_par     FOREIGN KEY (par)        REFERENCES profils(id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

'019_factures_recues' => "
CREATE TABLE factures_recues (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  fournisseur VARCHAR(120) NOT NULL,
  montant     DECIMAL(12,2) NOT NULL DEFAULT 0,
  date_facture DATE        NOT NULL,
  payee       TINYINT(1)   NOT NULL DEFAULT 0,
  image       VARCHAR(200) NULL,
  note        VARCHAR(255) NULL,
  par         INT UNSIGNED NULL,
  cree_le     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY k_date (date_facture),
  CONSTRAINT fk_fr_par FOREIGN KEY (par) REFERENCES profils(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

'020_bilans' => "
CREATE TABLE bilans (
  semaine_id     INT UNSIGNED NOT NULL PRIMARY KEY,
  ca_brut        DECIMAL(12,2) NOT NULL DEFAULT 0,
  depenses       DECIMAL(12,2) NOT NULL DEFAULT 0,
  masse_salariale DECIMAL(12,2) NOT NULL DEFAULT 0,
  primes         DECIMAL(12,2) NOT NULL DEFAULT 0,
  benefice       DECIMAL(12,2) NOT NULL DEFAULT 0,
  taux_impot     DECIMAL(5,2)  NOT NULL DEFAULT 0,
  impot          DECIMAL(12,2) NOT NULL DEFAULT 0,
  net            DECIMAL(12,2) NOT NULL DEFAULT 0,
  fige           TINYINT(1)    NOT NULL DEFAULT 0,
  calcule_le     DATETIME      NULL,
  CONSTRAINT fk_bilan_semaine FOREIGN KEY (semaine_id) REFERENCES semaines(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

/* ---------- 7. Organisation ---------- */

'021_agenda' => "
CREATE TABLE agenda (
  id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  profil_id INT UNSIGNED NOT NULL,
  titre     VARCHAR(160) NOT NULL,
  type      ENUM('rdv','commande','entretien','evenement','salle','autre') NOT NULL DEFAULT 'autre',
  debut     DATETIME NOT NULL,
  fin       DATETIME NULL,
  partage   TINYINT(1) NOT NULL DEFAULT 0,
  salle     VARCHAR(60) NULL,
  statut    ENUM('prevu','confirme','annule') NOT NULL DEFAULT 'prevu',
  note      VARCHAR(255) NULL,
  cree_le   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY k_profil (profil_id),
  KEY k_debut (debut),
  CONSTRAINT fk_agenda_profil FOREIGN KEY (profil_id) REFERENCES profils(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

'022_journal' => "
CREATE TABLE journal (
  id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  profil_id INT UNSIGNED NULL,
  auteur    VARCHAR(80)  NULL,
  action    VARCHAR(80)  NOT NULL,
  cible     VARCHAR(120) NULL,
  details   TEXT         NULL,
  ip        VARCHAR(45)  NULL,
  cree_le   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY k_date (cree_le),
  KEY k_action (action),
  CONSTRAINT fk_journal_profil FOREIGN KEY (profil_id) REFERENCES profils(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

/* ---------- 8. Données de départ ---------- */

'023_grades_defaut' => "
INSERT INTO grades (nom, rang, quota, multiplicateur, seuil_montee, couleur) VALUES
  ('Vendangeur',       1, 1500, 1.00, 2000, '#8ea63a'),
  ('Caviste',          2, 2500, 1.50, 3500, '#c9a227'),
  ('Caviste confirme', 3, 4000, 2.00, 5500, '#d97a2b'),
  ('Maitre de chai',   4, 5500, 2.50, NULL, '#d4697a')",

'024_parametres_defaut' => "
INSERT INTO parametres (cle, valeur, description) VALUES
  ('unite_production', 'bouteilles',  'Ce que l''on compte : bouteilles, caisses, livraisons...'),
  ('prix_unite',       '8',           'Gain en \$ par unite produite, avant multiplicateur de grade'),
  ('plafond_prime',    '42500',       'Prime maximale par semaine et par personne'),
  ('prime_recrue',     '5000',        'Prime versee au recruteur par nouvelle recrue'),
  ('podium',           '[15000,10000,5000]', 'Primes du podium hebdomadaire'),
  ('objectif_semaine', '200000',      'Objectif collectif de production'),
  ('salaires_postes',  '{}',          'Salaire fixe par poste, au format nom:montant'),
  ('bareme_impot',     '[[0,0],[100000,5],[300000,10],[600000,15]]', 'Paliers : [benefice, taux %]'),
  ('jour_cloture',     '1',           'Jour de la cloture hebdomadaire, 1 = lundi'),
  ('nom_entreprise',   'Marlowe Vineyard', 'Nom affiche partout dans l''application')",

/* ---------- 9. Accès provisoire, en attendant Discord ---------- */

'025_code_provisoire' => "
ALTER TABLE profils
  ADD COLUMN code_hash VARCHAR(255) NULL AFTER role_site,
  ADD COLUMN provisoire TINYINT(1) NOT NULL DEFAULT 0 AFTER code_hash",

/* ---------- 10. Invitations : chacun choisit son propre code ---------- */

'026_invitations' => "
CREATE TABLE invitations (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  profil_id  INT UNSIGNED NOT NULL,
  jeton_hash CHAR(64)     NOT NULL,
  cree_par   INT UNSIGNED NULL,
  cree_le    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expire_le  DATETIME     NOT NULL,
  utilise_le DATETIME     NULL,
  UNIQUE KEY u_jeton (jeton_hash),
  KEY k_profil (profil_id),
  CONSTRAINT fk_invit_profil FOREIGN KEY (profil_id) REFERENCES profils(id) ON DELETE CASCADE,
  CONSTRAINT fk_invit_par    FOREIGN KEY (cree_par)  REFERENCES profils(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

/* ---------- 11. Adresse publique, pour préparer le déménagement ---------- */

'027_url_publique' => "
INSERT INTO parametres (cle, valeur, description) VALUES
  ('url_publique', '', 'Adresse publique du site, ex. https://marlowe.flashbackfa.fr. Laisser vide pour deduire automatiquement.')",

/* ---------- 12. Connexion Discord ----------
   Discord ne communique que des NUMEROS de roles, jamais leurs
   noms. Cette table memorise chaque numero apercu et permet a la
   direction de lui donner un sens : tel numero = tel grade. */

'028_discord_roles' => "
CREATE TABLE discord_roles (
  role_id         VARCHAR(20)  NOT NULL PRIMARY KEY,
  libelle         VARCHAR(80)  NULL,
  grade_id        TINYINT UNSIGNED NULL,
  role_site       ENUM('direction','rh','comptable','commercial','membre') NULL,
  masque          TINYINT(1)   NOT NULL DEFAULT 0,
  vu_le           DATETIME     NULL,
  dernier_porteur INT UNSIGNED NULL,
  CONSTRAINT fk_drole_grade   FOREIGN KEY (grade_id)        REFERENCES grades(id)  ON DELETE SET NULL,
  CONSTRAINT fk_drole_porteur FOREIGN KEY (dernier_porteur) REFERENCES profils(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

'029_reglages_discord' => "
INSERT INTO parametres (cle, valeur, description) VALUES
  ('discord_role_requis',  '', 'Numero du role Discord exige pour entrer. Vide = etre membre du serveur suffit.'),
  ('discord_auto_grade',   '0', 'Attribuer automatiquement le grade du site depuis le role Discord.'),
  ('discord_auto_retrait', '0', 'Desactiver le compte si la personne quitte le serveur ou perd le role exige.')",

/* Le profil se souvient du dernier controle Discord. */
'030_profils_discord' => "
ALTER TABLE profils
  ADD COLUMN discord_verifie_le DATETIME NULL AFTER derniere_connexion",

];
