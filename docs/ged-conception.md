# Espace GED (gestion électronique de documents) — note de conception

Statut : idée validée par Medy le 2026-09-29, à construire plus tard. Rien de cette note n'est encore en ligne.

## Pourquoi
Certains documents (factures, justificatifs) peuvent être des pièces de santé transmises à une mutuelle.
Le principe retenu : **le prestataire n'envoie plus de documents nominatifs ; le client va les chercher lui-même.**
Medy veut être précurseur de cette façon de travailler, même sans besoin HDS, pour les sites et les échanges privés.

## Déjà en place (version 1)
- E-mails de paiement **sans nom** : référence de commande seulement.
- **Reçu de paiement anonyme** (`recu.php`) : référence, date, montant, vendeur ; aucun nom.
- **Facture nominative** (`facture.php`) : accessible seulement avec un lien secret de 128 bits, jamais envoyée par e-mail.
- **Lien et QR code** (générés sur le site, sans service extérieur) sur le reçu et dans l'administration.

## Principes de la GED (phase suivante)
1. Un espace par client, un coffre de documents (factures, reçus, contrats, comptes rendus).
2. Accès **sans e-mail de document** : lien personnel + QR code, avec en plus un code de retrait (PIN) remis séparément.
3. Liens à **durée limitée** et révocables ; journal de chaque consultation (qui, quand).
4. Références pseudonymes dans les noms de fichiers et les objets ; jamais de donnée de santé dans le système de facturation.
5. Stockage en France, chiffré au repos, sauvegardé ; suppression selon des durées de conservation définies.
6. Séparation stricte : ce système administratif reste distinct de tout futur environnement HDS.

## Limites à garder en tête
- L'hébergement actuel (mutualisé) n'est **pas certifié HDS** : aucune donnée de santé ne doit y être stockée.
- Le lien du reçu donne accès à la facture nominative : à traiter comme un document personnel.
- Une vraie GED pour des données de santé demandera un hébergeur certifié HDS, à étudier avec un juriste.

## Questions à trancher avant de construire
1. Qui dépose les documents (Medy seul, ou aussi les clients) ?
2. Durée de validité des liens (30 jours, 1 an) et code de retrait obligatoire ou non ?
3. Types de documents à gérer en premier ?
4. Où l'héberger (LWS pour le non-santé ; hébergeur HDS pour la santé) ?
