# Espace membre

Outil de gestion interne du domaine : effectif, production, primes,
facturation, comptabilité, agenda.

Application **PHP 8 + MySQL**, sans framework et sans étape de compilation.
Elle a besoin d'un serveur PHP et d'une base de données : elle ne peut pas
être hébergée sur GitHub Pages.

## Arborescence

La règle qui protège tout : **seul `webroot/` est exposé au web.**
Le reste est un cran au-dessus, hors d'atteinte d'un navigateur.

| Chemin | Rôle | Exposé au web |
|---|---|---|
| `config.php` | Réglages de connexion — aucun secret | non |
| `secrets.php` | Mots de passe et clés — **généré sur place, jamais versionné** | non |
| `schema.php` | Structure de la base, en étapes numérotées | non |
| `sauvegardes/` | Exports automatiques de la base | non |
| `app/` | Le moteur : base, sessions, permissions, Discord, gabarits | non |
| `webroot/` | Racine du site — le serveur web pointe ici | **oui** |
| `webroot/pages/` | Un fichier par écran de l'application | oui |

## Installer sur un serveur

1. Créer une base MySQL ou MariaDB vide.
2. Déposer le contenu de ce dossier en respectant l'arborescence ci-dessus.
3. Faire pointer le serveur web sur `webroot/`.
4. Ouvrir `/installation.php` : saisir l'utilisateur et le mot de passe de la base.
   La page teste la connexion **avant** d'écrire quoi que ce soit, génère
   `secrets.php`, puis se désactive d'elle-même.
5. Ouvrir `/_migrer.php` et appliquer les étapes : la base se construit.
   Aucune donnée existante n'est touchée.
6. Ouvrir la racine du site : la page de création du premier compte s'affiche.
   Ce premier compte est la Direction.
7. Renseigner *Paramètres → Adresse publique* une fois le nom de domaine en place.
8. Pour la connexion Discord, ouvrir `/_discord.php`.

## Pages d'administration

| Page | Usage | Qui peut y accéder |
|---|---|---|
| `/installation.php` | Une seule fois, à l'installation | tout le monde, puis se désactive |
| `/_migrer.php` | Après chaque mise à jour du code | direction (ouvert tant qu'aucun compte n'existe) |
| `/_discord.php` | Raccordement de l'application Discord | direction |
| `/_sauvegarde.php` | Export complet de la base | direction |
| `/_controle-discord.php` | Revérification des accès Discord | direction |

Les deux dernières acceptent aussi `?cle=<cle_secrete>` pour être déclenchées
automatiquement par un service extérieur.
**Traiter ces adresses complètes comme des mots de passe.**

## Ce qui ne doit jamais entrer dans ce dépôt

`secrets.php`, le mot de passe de la base, le secret client Discord,
toute sauvegarde `.sql`, et toute adresse contenant `?cle=`.
