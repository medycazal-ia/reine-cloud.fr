# Mode d'emploi : mettre reine-cloud.fr en ligne chez LWS

Guide pas à pas, pensé pour être transmis à quelqu'un qui débute.
Ce document ne contient jamais de mot de passe ni de clé : ceux-ci se
rangent uniquement dans un gestionnaire de mots de passe.

## Règles d'or
- Un mot de passe différent par usage (base de données, FTP, e-mail).
- Ne jamais écrire un mot de passe dans un e-mail, un chat ou un fichier
  du dépôt.
- Pour en fabriquer un : `openssl rand -base64 24` dans un terminal.

## Étape 1 : entrer dans cPanel
1. Ouvrir https://panel.lws.fr et se connecter avec son identifiant LWS.
2. Repérer l'hébergement de reine-cloud.fr dans la liste.
3. Cliquer sur l'accès à cPanel.
4. On est au bon endroit quand on voit des icônes rangées par sections
   (Fichiers, Bases de données, Sécurité, Courrier…).

## Étape 2 : envoyer la page d'accueil
1. Dans cPanel, section « Fichiers », ouvrir « Gestionnaire de fichiers ».
2. Cliquer sur le dossier `public_html` (à gauche) : c'est le dossier du site.
3. Noter ce qu'il contient (un `index.html` par défaut peut exister).
4. Télécharger `index.html` et `favicon.ico` depuis le dossier `site/` du
   dépôt GitHub.
5. Cliquer sur « Charger » et les envoyer dans `public_html`.
6. Ouvrir http://reine-cloud.fr dans le navigateur pour vérifier.
Remarque : un avertissement « certificat auto-signé » est normal avant
l'étape 4.
Aide LWS : https://tutoriels.lws.fr/site-web/mettre-un-site-en-ligne

## Étape 3 : vérifier le domaine (à venir)
## Étape 4 : activer le HTTPS gratuit (à venir)
Aide LWS : https://aide.lws.fr/base/Hebergement-web-mutualise/Outils-web/activer-redirection-web-https-SSL-automatique

## Étape 5 : adresse e-mail et formulaire de contact (à venir)
## Étape 6 : base de données, seulement si utile (à venir)
