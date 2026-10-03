# Journal des versions de sauvegarde

Chaque sauvegarde complète est numérotée (V1, V2…) et rangée dans `sauvegardes/`.
Le numéro augmente à chaque fin de session ou à la demande de Medy :
`python3 outils/construire_sauvegarde.py`

## V1 — 29 septembre 2026
Première sauvegarde complète.
- Site : page d'accueil, pages Commander, Payer, Merci, CGV, Mentions légales, Confidentialité ; polices et QR code hébergés sur le site (aucun service extérieur).
- Mise en ligne sur LWS (cPanel) avec HTTPS ; formulaire de contact PHP et base de données ; rappel de renouvellement de l'hébergement.
- Administration à 7 onglets (tableau de bord, clients, demandes, paiements, comptabilité, site et technique, paramètres) ; abonnements automatiques ou manuels ; factures numérotées ; livre des recettes et exports CSV.
- Confidentialité : e-mails sans nom, reçu de paiement anonyme, facture nominative récupérée par lien et QR code (base de la future GED).
- Paiement : parcours de commande, liens de paiement configurables (lien de test à 1 € en place).
- Outils : scripts de construction du paquet et de la sauvegarde, tests unitaires (59 contrôles) et de bout en bout.
- Textes juridiques rédigés (à faire valider) ; note de conception de la GED.
- Reste à faire : voir `docs/RESTE-A-FAIRE.md`.

## Entre V1 et V2 — 30 septembre 2026
- Ajout du **modèle déployable** (`model/`, `livrables/model-site-deployable.zip`) : copie du site avec champs à remplir et script de personnalisation.
- Sera inclus dans la sauvegarde V2.

## V2 — 2 octobre 2026
- Inclut le **modèle déployable** (`model/`, `livrables/model-site-deployable.zip`, script `outils/model_generer.py`).
- Précisions sur l'utilisation du modèle (modifier le modèle ou un site personnalisé).
- Séparation explicite du projet **Formedy** (dépôt distinct `of-medy`) : aucun partage de code, clés, base ou hébergement.
- Reste à faire : voir `docs/RESTE-A-FAIRE.md` (version 2).

## V3 — 2 octobre 2026
- Les **cinq liens de paiement Revolut** sont en place (socle, agent standard, cadrage, sur mesure, règlement libre) ; le lien de test à 1 € est supprimé.
- Nouveau bouton **« Commander le sur mesure »** et page de remerciement reconnaissant l'offre.
- Le paiement s'ouvre toujours dans un **nouvel onglet**, avec un message qui invite à revenir sur le site.
- L'administration préremplit les liens à partir de `paiement.js` (aucun effacement par erreur) et gère cinq liens.
- Nouveau test des **pages publiques** dans la suite de tests ; modèle « model » à jour (sans lien réel).
- Reste à faire : voir `docs/RESTE-A-FAIRE.md` (version 3).


## Après V3 — Recherche de nom de domaine
- Ajout de `site/domaine.php` (registres officiels, sans clé, rien enregistré, limitation de débit) et de la section `#domaine` sur la page d'accueil ; texte de confidentialité complété ; tests ajoutés (37 unitaires + parcours navigateur). Non testé contre les vrais registres depuis le bac à sable.
