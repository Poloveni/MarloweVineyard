# Marlowe Vineyard

Tout ce qui fait tourner le domaine viticole **Marlowe Vineyard**,
entreprise fictive du serveur GTA RP **FlashbackFA**.

Ce dépôt contient deux projets distincts, qui ne s'hébergent pas au même endroit.

| Dossier | Ce que c'est | Technologie | Où ça tourne |
|---|---|---|---|
| [`site/`](site/) | Le site public : présentation, catalogue, recrutement | HTML, CSS, JS — un seul fichier | Hébergement statique |
| [`espace-membre/`](espace-membre/) | L'outil de gestion interne : effectif, primes, facturation | PHP 8 + MySQL | Serveur PHP |

En ligne : <https://marlowe-vineyard.mauries-inc.com/>

## Pourquoi deux projets dans un seul dépôt

Ils parlent de la même entreprise, évoluent ensemble, et le site public
renvoie vers l'espace membre. Les garder côte à côte évite d'avoir à
synchroniser deux historiques.

En revanche ils ne se déploient pas de la même façon :

- **`site/`** ne demande aucun serveur. Déposer les fichiers quelque part suffit.
- **`espace-membre/`** a besoin de PHP et d'une base MySQL. Il ne peut pas être
  hébergé sur GitHub Pages, qui ne sert que des fichiers statiques.

## Règle de sécurité

Le fichier `espace-membre/secrets.php` — mot de passe de la base, clé de
chiffrement, secret Discord — **n'est jamais versionné**. Il est créé sur le
serveur par la page d'installation et n'existe qu'à cet endroit.
Le `.gitignore` l'écarte, ainsi que toutes les sauvegardes `.sql`.
Ne pas contourner cette règle.

## Par où commencer

- Modifier le site public → [`site/README.md`](site/README.md)
- Installer ou reprendre l'outil de gestion → [`espace-membre/README.md`](espace-membre/README.md)
