# MODEL — modèle de site déployable

Ce dossier est une copie **déployable** du site reine-cloud.fr et de tout ce qui s'y rapporte, avec les informations
propres à l'entreprise remplacées par des champs `{{...}}`. Il sert à monter **un site identique pour une autre identité**
(autre entreprise, autre client, autre marque), rapidement.

## Ce que contient le modèle (`model/sources/`)
- `public_html/` : page d'accueil, commande, règlement, remerciement, CGV, mentions légales, confidentialité,
  formulaire de contact, liens de paiement, polices et QR code hébergés sur le site, reçu (`recu.php`), facture (`facture.php`),
  et l'**administration** (`gestion/` : tableau de bord, clients, demandes, paiements, comptabilité, technique, paramètres).
- `abonnements/` : code des abonnements, rappels automatiques, factures, reçus, comptabilité (à placer hors du dossier public).
- `docs/` : mode d'emploi pas à pas (cPanel/LWS), textes juridiques, base de données, note sur la future GED.

## Utilisation en 4 étapes
1. Copier `model-identite.exemple.json` en `model-identite.json` et remplir les informations (nom, SIRET, adresse, e-mails,
   téléphone, hébergeur). Les caractères `"`, `\`, `$`, `<`, `>`, `{`, `}` et `&` sont refusés (écrire « et » au lieu de « & »).
2. Lancer : `python3 model_personnaliser.py`
3. Le site prêt à déposer est créé dans `model-sortie/<domaine>/` et en zip `model-sortie/<domaine>.zip`.
4. Suivre `docs/mode-emploi-lws.md` (dans la sortie) : dépôt du zip dans le dossier principal du compte, protection de
   `public_html/gestion`, création de la base et du fichier de réglages, tâche automatique.

## À adapter à la main avant de mettre en ligne (le script ne peut pas le faire)
- **Textes de la page d'accueil** (`public_html/index.html`) : l'histoire de la marque (reine-claude, prune…), les offres, les prix
  et les logos sont ceux de reine-cloud.fr. À réécrire pour la nouvelle marque.
- **Offres et prix** : `commander.html`, `payer.html`, `cgv.html`, administration (Paramètres et Paiements).
- **Textes juridiques** (`cgv.html`, `mentions-legales.html`, `confidentialite.html`) : écrits pour un entrepreneur individuel vendant à des
  professionnels ; à faire relire et adapter par un juriste (forme juridique, durées, conditions).
- **Palette et polices** : variables de couleur en haut de `index.html` ; polices dans `public_html/fonts/`.
- **Liens de paiement** : à créer chez le prestataire du nouveau client, puis à coller dans l'onglet Paiements (aucun n'est fourni).

## Règles à garder
- Aucun mot de passe ni clé dans ce dossier (le fichier de réglages de la base se crée sur le serveur, hors du dossier public).
- Aucune donnée de santé dans le système de facturation ; e-mails et reçus sans nom (voir `docs/ged-conception.md`).
- L'hébergement mutualisé n'est pas certifié HDS.

## Régénérer le modèle
Si le site d'origine évolue : `python3 outils/model_generer.py` (depuis la racine du projet) reconstruit `model/` et
`livrables/model-site-deployable.zip`.
