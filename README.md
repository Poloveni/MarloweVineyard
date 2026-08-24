# Marlowe Vineyard — site vitrine

Site public du domaine viticole **Marlowe Vineyard**, entreprise fictive du serveur GTA RP **FlashbackFA**.

En ligne : <https://marlowe-vineyard.mauries-inc.com/>

## Ce que contient ce dépôt

| Chemin | Rôle |
|---|---|
| `index.html` | Le site entier — structure, style et scripts dans un seul fichier |
| `assets/` | Logo, favicon, bannière de partage |
| `assets/catalogue/` | Les 48 pages du catalogue, en pleine taille et en vignettes |

Le site est **entièrement statique** : pas de base de données, pas de serveur à faire tourner.
Il suffit de déposer ces fichiers sur n'importe quel hébergeur, ou d'ouvrir `index.html`
directement dans un navigateur.

## Modifier le site

Tout ce qui change au quotidien est regroupé en haut du bloc `<script>`, dans l'objet `CONFIG` :

```js
const CONFIG = {
  discord      : "https://discord.gg/…",   // invitation Discord
  telephone    : "923",                     // standard du domaine
  espaceMembre : "http://…",                // outil de gestion interne
  ...
};
```

Le catalogue des 61 produits vit dans la constante `CATALOGUE`, juste en dessous,
et les 8 gammes dans `GAMMES`.

## L'espace membre

L'outil de gestion interne (effectif, primes, facturation) est un projet séparé,
en PHP : voir le dépôt **MarloweEspaceMembre**. Il ne peut pas être hébergé ici,
car GitHub Pages ne sert que des fichiers statiques.
