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
