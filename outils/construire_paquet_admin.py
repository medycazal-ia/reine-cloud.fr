#!/usr/bin/env python3
"""Construit livrables/administration-a-deployer.zip (à extraire dans le dossier principal du compte LWS).
Usage : python3 outils/construire_paquet_admin.py
"""
import os
import zipfile

RACINE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
fichiers = []
for f in ('lib.php', 'lib_gestion.php', 'lib_domaines.php', 'lib_remises.php', 'rappels.php'):
    fichiers.append((f'serveur/abonnements/{f}', f'abonnements/{f}'))
for dossier, _, noms in os.walk(f'{RACINE}/site/gestion'):
    for nom in noms:
        if nom.endswith('.php'):
            rel = os.path.relpath(os.path.join(dossier, nom), f'{RACINE}/site')
            fichiers.append((f'site/{rel}', f'public_html/{rel}'))
fichiers += [('site/facture.php', 'public_html/facture.php'),
             ('site/recu.php', 'public_html/recu.php'),
             ('site/code.php', 'public_html/code.php'),
             ('site/js/qrcode.js', 'public_html/js/qrcode.js')]
os.makedirs(f'{RACINE}/livrables', exist_ok=True)
sortie = f'{RACINE}/livrables/administration-a-deployer.zip'
with zipfile.ZipFile(sortie, 'w', zipfile.ZIP_DEFLATED) as z:
    for src, dst in sorted(fichiers):
        z.write(f'{RACINE}/{src}', dst)
    z.writestr('LISEZ-MOI.txt', """Paquet de l'administration de reine-cloud.fr

A extraire dans le dossier PRINCIPAL du compte (celui qui contient public_html), pas dans public_html.
Il cree / met a jour :
  abonnements/               (code des rappels, factures, recus, comptabilite)
  public_html/gestion/       (espace d'administration, a proteger par mot de passe)
  public_html/facture.php    (facture nominative, par lien secret)
  public_html/recu.php       (recu de paiement sans nom, avec lien et QR code vers la facture)
  public_html/js/qrcode.js   (generateur de QR code, licence MIT, heberge sur le site)
Aucun mot de passe ni cle dans ce paquet.
""")
print(len(fichiers), 'fichiers ->', sortie)
