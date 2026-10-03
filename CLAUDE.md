# reine-cloud.fr — consignes pour les sessions Claude

Projet indépendant : il ne partage ni code, ni hébergement, ni clés, ni
réglages avec aucun autre projet de Medy. Langue : français, vouvoiement,
sans jargon. Ne jamais committer de clé, mot de passe, lien de
session cPanel, coût, marge ou nom de fournisseur interne.

## Concept (voulu par Medy)
- Marque « reine-cloud.fr » : jeu de mots **reine-claude** (la prune, que
  tous les Français connaissent) / **reine = souveraineté** / **cloud**.
  Sert à donner du sens à l'hébergement web et à l'IA.
- Signature : « Hébergement · Cloud · IA · Souveraineté ».
- Ton : haut de gamme mais décalé par rapport au secteur, en gardant
  l'image de la prune couronnée. Insister sur des **serveurs en France**.
- Projets futurs : solutions compatibles **HDS** (santé), chez des clients
  ou en interne. **Aucune certification HDS n'est revendiquée** tant qu'elle
  n'existe pas.
- Contact : La Maison du CREL — cazal@medy.site — +33 6 74 20 16 62
  (WhatsApp https://wa.me/33674201662). Prix nets, « TVA non applicable,
  art. 293 B du CGI ».

## Contenu du dépôt
- `reine_cloud_branding_pack_reine-cloud-fr.zip` : pack d'images (scène 3D).
- `transfert-claude-code.zip` : historique des offres et logo à plat
  (`livrables/logo/…`, palette dans son LISEZ-MOI). Contient des données
  internes confidentielles : ne jamais les publier.
- `site/` : page d'accueil (`template.html` + logos SVG inlinés → `index.html`,
  `favicon.ico`). Palette : violet #491E65 / #6B3878, nuit #22103A,
  or #D9A93A, émeraude #1E9A5C, ivoire #FAF7F0. Polices : Cormorant
  Garamond (titres), Montserrat (texte).

## Offres affichées (ne rien modifier sans l'accord de Medy)
Socle 19,99 €/mois ; agent standard 99 €/mois par agent (remises dès 3
agents) ; sur mesure dès 299 €/mois, mise en place dès 1 500 €, cadrage
199 €, engagement 24 mois ; heure à 49 €. « Hébergé en France » est confirmé par
Medy (2026-09-29).

## Hébergement (décision du 2026-09-29)
Tout sur **LWS** (cPanel, serveurs en France) : `index.html` et
`favicon.ico` dans `public_html`, SSL gratuit, e-mails LWS. **Pas Render**
(pas de région France). Medy a créé un hébergement LWS et une clé API : ne
jamais les écrire dans le dépôt ni dans le chat ; usage de la clé API
encore à préciser.

## À faire / questions ouvertes
- Formulaire de contact en PHP : fait et testé (`site/contact.php`, envoi vers
  cazal@medy.site depuis contact@reine-cloud.fr). Site en ligne en HTTPS.
- Guide pas à pas pour Medy : `docs/mode-emploi-lws.md`.
- Hébergement LWS : date d'expiration affichée 27-10-2026, à vérifier.
- Base de données LWS en place (table `demandes`, copie des messages du
  formulaire). Polices hébergées sur le site (aucun appel à Google).
- Mentions légales et confidentialité : pages prêtes (`site/mentions-legales.html`,
  `site/confidentialite.html`, textes dans `docs/juridique/`), à mettre en ligne
  et à faire relire par un juriste. Éditeur : entrepreneur individuel ; hébergeur
  LWS cité dans les mentions (accord de Medy). CGV : `site/cgv.html` (texte dans `docs/juridique/cgv.md`, confirmé par Medy le
  2026-09-29, clients professionnels uniquement), à mettre en ligne et à relire.
- Paiement en ligne : parcours de commande (`site/commander.html`, `site/payer.html`), liens
  dans `site/paiement.js` (aucun secret). Prestataire envisagé : Revolut Pro (liens
  de paiement, pas d'API). État au 2026-10-02 : les cinq liens Revolut (socle, agent, cadrage,
  sur mesure, règlement libre) sont en place et s'ouvrent dans un nouvel onglet ; adresse de retour
  après paiement à régler dans Revolut (`merci.html?offre=…`). Abonnements mensuels : l'administration
  envoie les liens (mode automatique ou manuel), « Payé » reste manuel. Examen du site par Revolut en cours.
- Administration maison sur LWS : `site/gestion/` (7 onglets : tableau de bord, clients, demandes,
  paiements, comptabilité, site et technique, paramètres), `site/facture.php`, code métier dans
  `serveur/abonnements/` (cron quotidien `rappels.php`). Paquet à déployer :
  `livrables/administration-a-deployer.zip` (à régénérer après toute modification du code).
  Page protégée par « Confidentialité du répertoire » de cPanel. RÈGLE HDS : ce système de
  facturation reste séparé de tout environnement de données de santé ; n'y jamais stocker de
  donnée de santé (notes, intitulés, motifs, factures, e-mails).
- Confidentialité des échanges : e-mails et reçus SANS nom (référence de commande ou d'abonnement seulement) ;
  la facture nominative n'est jamais envoyée, le client la récupère avec un lien secret et un QR code (`recu.php`,
  `facture.php`). Base de la future GED (`docs/ged-conception.md`).
- Suivi : `docs/RESTE-A-FAIRE.md` (à mettre à jour à chaque fin de session), `docs/JOURNAL-DES-VERSIONS.md`.
  Sauvegarde complète versionnée : `python3 outils/construire_sauvegarde.py` (V1, V2…, incrémentée à chaque fin de
  session ou à la demande de Medy) ; tests : `bash outils/tests/lancer-tests.sh`.
- Modèle déployable « model » : `model/` (sources avec champs `{{...}}`, `model_personnaliser.py`, `MODEL-LISEZ-MOI.md`) et
  `livrables/model-site-deployable.zip`, généré par `python3 outils/model_generer.py` (à relancer après toute évolution du site).
- Recherche de nom de domaine : `site/domaine.php` (registres officiels RDAP, sans clé, rien enregistré) + section `#domaine` de la page d'accueil. Non testé contre les vrais registres depuis le bac à sable (réseau bloqué) : à essayer en ligne. API LWS (noms de domaine, prix) : possible plus tard, clé hors dépôt dans `config-reine-cloud.php`.
- Rappels de renouvellement de l'hébergement : `serveur/rappels/rappel.php` (cron LWS).
- Dépôt en lecture seule pour Claude tant que l'app GitHub Claude n'est pas
  installée sur medycazal-ia/reine-cloud.fr.
