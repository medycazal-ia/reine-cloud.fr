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

## Étape 6 : adresse d'expédition et formulaire de contact
6a. cPanel > « E-mail » > « Comptes de messagerie » > « Créer » :
    `contact@reine-cloud.fr` (mot de passe généré et rangé dans un
    gestionnaire, jamais écrit ici). Cette adresse sert d'expéditeur.
6b. Télécharger depuis le dossier `site/` du dépôt `contact.php` et
    `index.html`. Dans `public_html` : renommer l'ancien `index.html` en copie
    de secours, téléverser les deux fichiers, puis renommer un éventuel
    `index (1).html` en `index.html` (nom exact).
6c. Test dans une fenêtre privée : remplir le formulaire, envoyer, vérifier
    le message « Merci… » et la réception sur `cazal@medy.site` (regarder
    aussi les indésirables la première fois).
6d. Quand tout marche, supprimer les copies de secours de `index.html`.
Fait le 2026-09-29 : formulaire testé, messages reçus.
Fonctionnement : le formulaire envoie ses données à `contact.php`, qui
envoie un e-mail à la boîte de réception et renvoie le visiteur sur la page
avec un message de confirmation. Un champ caché sert de piège à robots.

## Étape 7 : base de données (demandes du formulaire)
7a. cPanel > « Bases de données » > « Manage My Databases » : créer la base
    `reine` (elle devient `COMPTE_reine`, le préfixe est ajouté par cPanel).
7b. Créer l'utilisateur `rcweb` (mot de passe généré et rangé dans un
    gestionnaire), puis l'ajouter à la base avec « Tous les privilèges ».
7c. phpMyAdmin > base `COMPTE_reine` > onglet « SQL » : coller le contenu de
    `docs/base-de-donnees/schema.sql`, « Exécuter ». Le tableau `demandes`
    apparaît.
7d. Gestionnaire de fichiers, dossier PRINCIPAL du compte (celui qui contient
    `public_html`, pas dedans) : créer `config-reine-cloud.php` avec le modèle
    `docs/base-de-donnees/config-exemple.php`, en y mettant le vrai nom de base,
    d'utilisateur et le mot de passe. Ce fichier n'est jamais mis dans le dépôt.
Pourquoi hors de `public_html` : ce qui s'y trouve est lisible depuis
internet, le reste du compte ne l'est pas.

## Étape 8 : polices hébergées sur le site
Le site n'appelle plus Google Fonts : les polices sont dans le dossier `fonts`
de `public_html` (3 fichiers `.woff2` du dossier `site/fonts` du dépôt).
Déploiement : renommer l'ancien `index.html` en copie de secours, créer le
dossier `fonts`, y téléverser les 3 polices, puis téléverser `contact.php` et
`index.html` dans `public_html`.
Test : page identique en fenêtre privée, message de test envoyé, e-mail reçu et
ligne présente dans phpMyAdmin (`demandes` > « Afficher »).
Fait le 2026-09-29.

## Entretien
- Purge des demandes de plus de 3 ans : requête en commentaire à la fin de
  `schema.sql`, à lancer de temps en temps (ou tâche Cron).
- Sauvegardes : cPanel > « Sauvegardes » avant toute grosse modification.
- Renouvellement de l'hébergement : voir l'encadré « Rappel » plus haut.

## Étape 9 : rappels automatiques de renouvellement
Un petit script (`serveur/rappels/rappel.php` dans le dépôt) envoie un e-mail à
cazal@medy.site : 1 semaine, 2 jours, 1 jour, 6 heures et 1 heure avant
l'échéance de l'hébergement (27-10-2026 par défaut).
9a. Gestionnaire de fichiers, dossier PRINCIPAL du compte (pas `public_html`) :
    créer un dossier `rappels`.
9b. Y téléverser `rappel.php` (téléchargé depuis le dossier `serveur/rappels/`
    du dépôt GitHub).
9c. Essai : cPanel > « Avancé » > « Terminal », taper
    `php /home/COMPTE/rappels/rappel.php test` : un e-mail de test arrive.
9d. cPanel > « Avancé » > « Tâches Cron » > ajouter : minute `*/10`, les autres
    champs `*`, commande `php /home/COMPTE/rappels/rappel.php`.
Vérification du cron : au premier passage, le tableau `rappels` apparaît dans
phpMyAdmin (aucun e-mail n'est envoyé tant qu'aucun délai n'est atteint).
Cycle mensuel (essai) : pas de rappel à 1 mois ni à 15 jours.
Après chaque renouvellement : ouvrir `rappel.php` (Modifier) et changer la date
de la ligne ECHEANCE. Les rappels de l'ancienne échéance ne sont pas renvoyés.

## Étape 10 : pages « Mentions légales » et « Confidentialité »
Deux pages sont prêtes dans le dossier `site/` du dépôt : `mentions-legales.html`
et `confidentialite.html` (textes sources dans `docs/juridique/`). Le pied de la
page d'accueil y renvoie.
Mise en ligne : téléverser dans `public_html` les deux pages ET le nouvel
`index.html` (copie de secours de l'ancien avant remplacement). Vérifier que les
deux liens du pied de page s'ouvrent.
À faire relire par un juriste ; en attendant, les pages contiennent les
informations légales minimales (éditeur, hébergeur, données personnelles).

## Étape 11 : page « CGV »
Ajouter aux fichiers de l'étape 10 la page `cgv.html` (dossier `site/` du dépôt) :
téléverser dans `public_html` `cgv.html`, `mentions-legales.html`,
`confidentialite.html` et le nouvel `index.html`. Le pied de page de chaque page
renvoie aux trois textes. Texte source : `docs/juridique/cgv.md`.

## Étape 12 : commande et paiement en ligne
Parcours : bouton « Commander » sur chaque offre (page d'accueil) > page
`commander.html` (récapitulatif, case « je suis un professionnel et j'accepte les
CGV ») > bouton de paiement. La page `payer.html` sert à régler une facture ou un
devis (cadrage 199 € ou montant convenu).
Tant qu'aucun lien de paiement n'est renseigné, le bouton s'appelle « Recevoir mon
lien de paiement » et ouvre un e-mail prérempli vers cazal@medy.site.
Pour activer le vrai paiement : ouvrir `paiement.js` dans `public_html`
(Gestionnaire de fichiers > Modifier, traduction Chrome désactivée) et coller entre
les guillemets les adresses `https://…` des liens créés chez le prestataire :
`socle`, `agent`, `cadrage` (199 €), `libre` (montant saisi par le client).
Enregistrer, puis rafraîchir avec Ctrl + Maj + R : le bouton devient « Payer en ligne ».
Aucun secret dans ce fichier : les liens sont publics. Effacer un lien le désactive.
Fichiers à téléverser dans `public_html` : `index.html`, `commander.html`,
`payer.html`, `paiement.js`, `merci.html`, `cgv.html`, `confidentialite.html`,
`mentions-legales.html` (copie de secours de l'ancien `index.html` avant).

## Étape 13 : espace d'administration (code maison sur LWS)
Adresse : `reine-cloud.fr/gestion/` (protégée par mot de passe). Sept onglets :
- Tableau de bord : revenu mensuel, encaissé de l'année, à encaisser, échéances en retard
  ou proches, demandes à traiter, échéance de l'hébergement, état de la tâche automatique.
- Clients : fiches (contact, entreprise, adresse, statut prospect/client/ancien, notes),
  avec abonnements, factures et messages reçus de chaque client.
- Demandes : messages du formulaire de contact (nouvelle/traitée/archivée), répondre,
  transformer une demande en fiche client.
- Paiements : abonnements (mode automatique ou manuel, modification à tout moment du montant,
  de la date, du lien personnel), « Payé + facture par e-mail », lien de paiement ponctuel,
  et liens de paiement du site : on les colle et « Enregistrer et publier » met à jour les
  boutons du site sans toucher aux fichiers.
- Comptabilité : factures numérotées (F-2026-0001…) avec facture acquittée en ligne (lien secret
  envoyé au client, imprimable en PDF), création de facture manuelle, livre des recettes par
  année et par mois, export CSV (Excel), chiffre d'affaires et seuil d'alerte facultatif.
- Site et technique : état des composants (HTTPS, base, e-mails, tâche automatique, fichiers du site),
  e-mail de test, sauvegarde des tableaux en CSV.
- Paramètres : identité de l'éditeur (SIRET, adresse…), mention de TVA, conditions de paiement,
  préfixe des factures, échéance de l'hébergement.
Envoi automatique (mode automatique) : le lien de paiement part 7 jours avant l'échéance (il reste
valable), un rappel la veille, une relance 3 jours après ; copie à cazal@medy.site.
Les factures ne se suppriment pas (numérotation continue obligatoire).
13a. Paquet : `livrables/administration-a-deployer.zip` (dépôt GitHub). CPanel > Gestionnaire de
     fichiers > dossier PRINCIPAL du compte (pas `public_html`) > « Téléverser » le zip > clic droit
     sur le zip > « Extraire » (ou « Extract »). Il crée `abonnements/`, `public_html/gestion/` et
     `public_html/facture.php`. Les tableaux de la base se créent tout seuls à la première ouverture.
13b. Protéger la page : cPanel > « Fichiers » > « Confidentialité du répertoire » > dossier
     `public_html/gestion` > activer la protection, créer identifiant et mot de passe (rangés dans un
     gestionnaire). Sans cette protection, la page refuse de s'ouvrir.
13c. Réglages : `config-reine-cloud.php` (dossier principal) reste le même ; le bloc `'liens'` devient
     facultatif (les liens se règlent dans Paiements). Aller ensuite dans Paramètres pour vérifier
     l'identité et l'échéance de l'hébergement.
13d. Essai : dans le Terminal cPanel, `php /home/COMPTE/abonnements/rappels.php simulation`
     (n'envoie rien, affiche ce qui partirait).
13e. Tâche Cron quotidienne : cPanel > « Tâches Cron » : minute `7`, heure `8`, autres champs `*`,
     commande `/usr/local/bin/php /home/COMPTE/abonnements/rappels.php`.
Limite : le script ne voit pas les paiements Revolut tout seul ; « Payé » reste un clic manuel
(une connexion automatique demanderait l'offre Revolut Business avec interface de programmation).
Règle HDS : ne jamais écrire de donnée de santé dans les notes, intitulés, motifs, factures ou e-mails.
Mise à jour ultérieure : reprendre le zip le plus récent et l'extraire de nouveau (il remplace les fichiers).
