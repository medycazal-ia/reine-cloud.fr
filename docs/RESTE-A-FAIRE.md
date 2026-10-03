# Reste à faire — reine-cloud.fr

**Version 3 — 2 octobre 2026** (mise à jour à chaque fin de session ou à la demande de Medy)

## A. À faire par Medy (déploiement et vérifications)
Cochez au fur et à mesure. Le guide pas à pas est `docs/mode-emploi-lws.md`.

### Mise en ligne du site (dossier `site/` de GitHub, à téléverser dans `public_html`)
- [ ] Renommer l'ancien `index.html` en copie de secours, puis téléverser : `index.html`, `commander.html`, `payer.html`, `merci.html`, `paiement.js`, `cgv.html`, `confidentialite.html`, `mentions-legales.html`, et le nouveau **`domaine.php`** (recherche de nom de domaine). *(À vérifier : je ne sais pas lesquels sont déjà en ligne.)*
- [ ] Tester en fenêtre privée : les liens du pied de page (Mentions légales, Confidentialité, CGV, Payer) s'ouvrent ; « Commander le socle » ouvre la page de commande.
- [ ] Tester en ligne la recherche de nom de domaine (section « Votre nom, disponible ? ») avec un nom libre et un nom pris (ex. google) : je n'ai pas pu l'essayer contre les vrais registres depuis mon environnement.
- [ ] Supprimer les copies de secours de `public_html` (`index-ancien.html`, `index-avant-*.html`) ; garder `.htaccess-sauvegarde`.

### Administration (étape 13 du mode d'emploi)
- [ ] Téléverser `livrables/administration-a-deployer.zip` dans le dossier PRINCIPAL du compte et l'extraire (crée `abonnements/`, `public_html/gestion/`, `facture.php`, `recu.php`, `js/qrcode.js`).
- [ ] cPanel > Confidentialité du répertoire : protéger `public_html/gestion` (identifiant + mot de passe rangés dans un gestionnaire).
- [ ] Ouvrir `reine-cloud.fr/gestion/`, vérifier les Paramètres (identité, échéance de l'hébergement).
- [ ] Terminal cPanel : `php /home/rs2875111/abonnements/rappels.php simulation`, puis créer la tâche Cron quotidienne (minute 7, heure 8, `/usr/local/bin/php /home/rs2875111/abonnements/rappels.php`).
- [ ] Vérifier dans l'onglet « Site et technique » que tout est au vert.

### Paiement
- [x] Les **cinq liens Revolut** sont renseignés (2026-10-02) : socle, agent standard, cadrage, sur mesure et règlement libre. **Plus aucun lien de test à 1 €.**
- [x] Tous les boutons de paiement s'ouvrent dans un **nouvel onglet** : le client peut revenir sur le site à tout moment (message affiché sur la page de commande).
- [ ] Dans Revolut, régler l'**adresse de retour après paiement** de chaque lien :
  - socle → `https://reine-cloud.fr/merci.html?offre=socle`
  - agent standard → `https://reine-cloud.fr/merci.html?offre=agent`
  - cadrage → `https://reine-cloud.fr/merci.html?offre=cadrage`
  - sur mesure → `https://reine-cloud.fr/merci.html?offre=surmesure`
- [ ] Vérifier le **montant** de chaque lien (socle 19,99 €, agent 99 €, cadrage 199 €, libre = montant saisi) et ce que couvre le lien « sur mesure » (le site indique : montant exact selon devis).
- [ ] Mettre en ligne les fichiers modifiés : `paiement.js`, `index.html`, `commander.html`, `payer.html`, `merci.html`.
- [ ] Vérifier que le lien du socle est réutilisable (plusieurs clients, plusieurs mois).
- [ ] Attendre le retour de l'examen du site par Revolut ; noter ce qu'ils proposent (encaissement, récurrent).

### Hébergement et sécurité
- [ ] **Renouveler l'hébergement LWS avant le 27-10-2026** (essai mensuel). Le premier rappel automatique de l'ancien script arrive vers le 20 octobre ; vérifier aussi l'option de renouvellement automatique dans l'espace LWS.
- [ ] Faire une sauvegarde cPanel (Fichiers > Sauvegardes) et conserver la sauvegarde V1 (zip du projet).
- [ ] Ne jamais partager `config-reine-cloud.php` (mot de passe de la base).

### Juridique et comptable
- [ ] Faire relire par un juriste : mentions légales, confidentialité, CGV.
- [ ] Faire relire par un comptable : modèle de facture, mentions, numérotation, livre des recettes.
- [ ] Vérifier la forme juridique de LWS (https://www.lws.fr/a_propos_infos.php) et décider de l'ajouter aux mentions.

## B. À faire par Claude (prochaines sessions)
- [ ] **API LWS** (noms de domaine : prix, commande) : Medy en a une ; à brancher sur la recherche de domaine quand Medy m'enverra le lien de la documentation (jamais la clé : elle ira dans `config-reine-cloud.php`, hors de `public_html`).
- [ ] **GED** : espace de documents par client, accès par lien + QR code + code de retrait, liens à durée limitée, journal de consultation (note de conception : `docs/ged-conception.md`). Questions à trancher listées dans la note.
- [ ] Adapter la page d'accueil et les CGV si Medy change la formule d'abonnement ou de paiement.
- [ ] Améliorations de l'administration : conserver la saisie après une erreur de formulaire, pagination et recherche des factures, avoirs, suivi des heures facturées à 49 €/h, export comptable dédié, authentification renforcée.
- [ ] Contrat de sous-traitance de données (si un client le demande) et modèle de devis cohérent avec les CGV.
- [ ] Paiement automatique : brancher l'interface de programmation Revolut Business si Medy passe à cette offre (aujourd'hui « Payé » est un clic manuel).
- [ ] Étudier un hébergement certifié HDS pour les futurs projets santé (rien à promettre avant d'avoir la certification).

## B bis. Modèle déployable (« model »)
- [x] Modèle créé le 2026-09-30 : `model/` et `livrables/model-site-deployable.zip` (guide : `model/MODEL-LISEZ-MOI.md`).
- [ ] Pour un nouveau site : remplir `model-identite.json`, lancer `python3 model_personnaliser.py`, puis réécrire la page d'accueil, les offres et faire relire les textes juridiques.
- [ ] Après toute évolution du site d'origine : `python3 outils/model_generer.py` pour remettre le modèle à jour.

## B ter. Projet Formedy (séparé de reine-cloud.fr)
- Formedy est un projet **distinct** (dépôt `of-medy`), construit à partir du modèle. Aucun code, aucune clé, aucune base ni aucun hébergement n'est partagé avec reine-cloud.fr.
- Sa sauvegarde et son guide de déploiement sont livrés à part (pas dans ce dépôt).
- **Constat du 2026-10-02** : le dépôt `medycazal-ia/of-medy` ne contient que `model-site-deployable.zip` (le modèle de reine-cloud.fr). Les 46 fichiers de Formedy décrits dans un résumé n'y sont pas : à envoyer (zip du dossier `ofmedy_model/`) pour relecture, sauvegarde et guide de déploiement définitif.
- Un guide provisoire a été remis à Medy (écrit d'après le résumé, code non vu). Points critiques notés : webhook Stripe à sortir du dossier protégé, scripts cron hors du dossier public, clés hors de `public_html`.

## B quater. Entretien du dépôt
- [ ] L'historique git contient encore deux anciens fichiers d'historique (`sauvegardes/*.bundle`, ~58 Mo) enregistrés par erreur lors des versions V1 et V2 ; ils ne sont plus suivis. Les retirer de l'historique demande de le réécrire (à décider avec Medy, sans urgence).

## C. Décisions en attente
- Formule des abonnements : l'administration envoie déjà le lien de paiement 7 jours avant l'échéance (mode automatique) ou à la demande (mode manuel) ; « Payé » reste manuel. À confirmer avec ce que propose Revolut.
- Prestataire de paiement définitif (Revolut Pro envisagé ; Stripe et Stancer étudiés).
- Durée de conservation des données : 3 ans après le dernier échange (proposée, à valider par le juriste).

## D. Règles à garder en tête
- **Aucune donnée de santé** dans ce système (notes, intitulés, motifs, factures, e-mails) : l'hébergement actuel n'est pas certifié HDS.
- **E-mails sans nom** : référence de commande ou d'abonnement seulement. Reçu de paiement anonyme ; la facture nominative n'est jamais envoyée, le client la récupère avec un lien et un QR code.
- Ne jamais écrire de clé, mot de passe ou lien de session cPanel dans le dépôt ou dans le chat.
- Offres et prix : ne rien modifier sans l'accord de Medy.
