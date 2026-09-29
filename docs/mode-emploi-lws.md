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
l'étape 4 (Paramètres avancés > Continuer, sur son propre site seulement).
Si l'ancien `index.html` de LWS existe, le renommer en `index-ancien.html`
avant l'envoi (ne pas le supprimer).
Fait le 2026-09-29 : la page s'affiche.
Aide LWS : https://tutoriels.lws.fr/site-web/mettre-un-site-en-ligne

## Étape 3 : vérifier le domaine
Si l'adresse reine-cloud.fr répond (même avec un avertissement de
certificat), le domaine pointe bien vers l'hébergement : rien d'autre à faire.
## Étape 4 : activer le HTTPS gratuit
1. cPanel > section « Sécurité » > « SSL/TLS Certificates » > onglet « État ».
2. Cliquer sur « Run AutoSSL » (ou « Exécuter AutoSSL »).
3. Attendre 5 à 10 minutes, recharger : cadenas vert = réussi.
Piège rencontré : juste après l'achat d'un domaine, AutoSSL répond
« unmanaged » (domaine non géré). C'est la propagation DNS : attendre
quelques heures, puis relancer. Le certificat Let's Encrypt est gratuit et
se renouvelle tout seul.
Aide LWS : https://aide.lws.fr/base/Hebergement-web-mutualise/Outils-web/activer-redirection-web-https-SSL-automatique

## Étape 5 : forcer l'ouverture en HTTPS
Sur la formule cPanel (CloudCP), la page « Domaines » n'a pas d'interrupteur
de redirection et l'espace LWS n'a pas de rubrique « SSL » : on passe par le
fichier `.htaccess`.
1. cPanel > « Gestionnaire de fichiers » > dossier `public_html`.
2. Sélectionner `.htaccess` > « Copier » > destination
   `/public_html/.htaccess-sauvegarde` (copie de secours).
3. Sélectionner `.htaccess` > « Modifier ». Ne pas toucher au bloc
   « DÉBUT… FIN » généré par cPanel.
4. Ajouter à la fin ces 5 lignes, puis « Enregistrer les modifications » :
```
# Redirection vers HTTPS (reine-cloud.fr)
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteCond %{REQUEST_URI} !^/\.well-known/
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]
```
5. Test : ouvrir `reine-cloud.fr` sans `https://` (fenêtre privée) : le
   cadenas doit apparaître.
Contrôle : le fichier passe de 614 à 805 octets.
Retour arrière : recopier `.htaccess-sauvegarde` sur `.htaccess`.
Pièges rencontrés :
- La traduction automatique de Chrome déforme l'affichage de l'éditeur
  (« On » devient « activé »…). Elle ne change pas le fichier enregistré,
  mais il vaut mieux la désactiver avant de modifier.
- Si Chrome affiche encore « Non sécurisé » alors que la fenêtre privée est
  bonne : `chrome://restart` (il garde en mémoire le « continuer malgré
  l'avertissement »).
- Sur Chromebook : Ctrl + Maj + R pour recharger sans la mémoire.

## Rappel
La formule d'hébergement affiche une date d'expiration (27-10-2026) :
vérifier le renouvellement chez LWS pour ne pas perdre le site.

## Étape 6 : adresse e-mail et formulaire de contact (à venir)
## Étape 7 : base de données, seulement si utile (à venir)
