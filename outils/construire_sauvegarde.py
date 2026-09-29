#!/usr/bin/env python3
"""Sauvegarde complète et versionnée du projet reine-cloud.fr.

Chaque exécution produit sauvegardes/reine-cloud-sauvegarde-V<n>-<date>.zip avec :
  - tout le dossier du projet (site, code serveur, documents, outils, tests, livrables, zips d'origine) ;
  - l'historique git complet (reine-cloud-historique.bundle) ;
  - docs/RESTE-A-FAIRE.md et docs/JOURNAL-DES-VERSIONS.md ;
  - un LISEZ-MOI de restauration.
La version s'incrémente à chaque exécution (VERSION.txt = dernière version construite).

Usage : python3 outils/construire_sauvegarde.py            # version suivante
        python3 outils/construire_sauvegarde.py --version 1  # imposer un numéro
"""
import datetime
import os
import subprocess
import sys
import zipfile

RACINE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
os.chdir(RACINE)
EXCLURE_DOSSIERS = {'.git', 'sauvegardes', '__pycache__', 'node_modules'}

# 1) numéro de version
fichier_version = os.path.join(RACINE, 'VERSION.txt')
if '--version' in sys.argv:
    version = int(sys.argv[sys.argv.index('--version') + 1])
else:
    try:
        version = int(open(fichier_version).read().strip()) + 1
    except (FileNotFoundError, ValueError):
        version = 1
date = datetime.date.today().isoformat()

# 2) paquet d'administration à jour
subprocess.run([sys.executable, 'outils/construire_paquet_admin.py'], check=True)

# 3) historique git
bundle = '/tmp/reine-cloud-historique.bundle'
subprocess.run(['git', 'bundle', 'create', bundle, '--all'], check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)

# 4) archive
os.makedirs('sauvegardes', exist_ok=True)
nom = f'sauvegardes/reine-cloud-sauvegarde-V{version}-{date}.zip'
with zipfile.ZipFile(nom, 'w', zipfile.ZIP_DEFLATED) as z:
    for dossier, sous, noms in os.walk(RACINE):
        sous[:] = [d for d in sous if d not in EXCLURE_DOSSIERS]
        for f in noms:
            chemin = os.path.join(dossier, f)
            z.write(chemin, os.path.join('reine-cloud.fr', os.path.relpath(chemin, RACINE)))
    z.write(bundle, 'reine-cloud-historique.bundle')
    z.writestr('LISEZ-MOI-RESTAURATION.txt', f"""SAUVEGARDE COMPLETE reine-cloud.fr — version {version} — {date}

Contenu
  reine-cloud.fr/                  tout le projet (site, code serveur, documents, outils, tests, livrables)
  reine-cloud-historique.bundle    historique git complet
  reine-cloud.fr/docs/RESTE-A-FAIRE.md            ce qu'il reste a faire (a lire en premier)
  reine-cloud.fr/docs/JOURNAL-DES-VERSIONS.md     historique des versions de sauvegarde
  reine-cloud.fr/docs/mode-emploi-lws.md          guide pas a pas (LWS / cPanel)

Restaurer l'historique git :   git clone reine-cloud-historique.bundle reine-cloud-restaure
Deployer l'administration :    reine-cloud.fr/livrables/administration-a-deployer.zip (voir mode d'emploi, etape 13)
Relancer les tests :           bash reine-cloud.fr/outils/tests/lancer-tests.sh   (necessite php, node et playwright)

ATTENTION : cette sauvegarde contient des donnees internes confidentielles (transfert-claude-code.zip)
et aucun mot de passe ni cle. Ne la partagez pas et rangez-la en lieu sur.
""")
open(fichier_version, 'w').write(str(version) + '\n')
print('Sauvegarde :', nom, f'({os.path.getsize(nom) // 1024} Ko) — version {version}')
