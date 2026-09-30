#!/usr/bin/env python3
"""Sauvegarde complète et versionnée du projet reine-cloud.fr.

Chaque exécution produit dans sauvegardes/ :
  - reine-cloud-sauvegarde-V<n>-<date>.zip           version légère (tout le travail, sans les gros zips d'origine) ;
  - reine-cloud-historique-V<n>-<date>.bundle        historique git complet ;
  - reine-cloud-sauvegarde-V<n>-<date>-COMPLETE.zip  tout, y compris les gros zips d'origine et l'historique ;
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

# 2) paquets à jour : administration et modèle déployable (model)
subprocess.run([sys.executable, 'outils/construire_paquet_admin.py'], check=True)
subprocess.run([sys.executable, 'outils/model_generer.py'], check=True)

# 3) historique git
bundle = '/tmp/reine-cloud-historique.bundle'
subprocess.run(['git', 'bundle', 'create', bundle, '--all'], check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)

# 4) archives : légère (tout le travail), historique git seul, et complète (tout, y compris les gros zips d'origine)
os.makedirs('sauvegardes', exist_ok=True)
base = f'sauvegardes/reine-cloud-sauvegarde-V{version}-{date}'
nom_leger, nom_bundle, nom_complet = base + '.zip', f'sauvegardes/reine-cloud-historique-V{version}-{date}.bundle', base + '-COMPLETE.zip'
GROS = 5 * 1024 * 1024   # au-delà de 5 Mo : fichiers d'origine (packs d'images...) exclus de la version légère


def lisez_moi(kind):
    exclus = ("\nVersion LEGERE : les gros fichiers d'origine (reine_cloud_branding_pack_reine-cloud-fr.zip, transfert-claude-code.zip)\n"
              "et l'historique git ne sont pas dedans : ils sont dans la version COMPLETE et sur GitHub.\n") if kind == 'leger' else ''
    return f"""SAUVEGARDE reine-cloud.fr — version {version} — {date} ({'legere' if kind == 'leger' else 'complete'})

Contenu
  reine-cloud.fr/                  le projet (site, code serveur, documents, outils, tests, livrables)
  reine-cloud.fr/docs/RESTE-A-FAIRE.md            ce qu'il reste a faire (a lire en premier)
  reine-cloud.fr/docs/JOURNAL-DES-VERSIONS.md     historique des versions de sauvegarde
  reine-cloud.fr/docs/mode-emploi-lws.md          guide pas a pas (LWS / cPanel)
{exclus}
Restaurer l'historique git :   git clone reine-cloud-historique-V{version}-{date}.bundle reine-cloud-restaure
Deployer l'administration :    reine-cloud.fr/livrables/administration-a-deployer.zip (voir mode d'emploi, etape 13)
Relancer les tests :           bash reine-cloud.fr/outils/tests/lancer-tests.sh   (necessite php, node et playwright)
Nouvelle sauvegarde :          python3 reine-cloud.fr/outils/construire_sauvegarde.py   (version suivante)

ATTENTION : ces sauvegardes peuvent contenir des donnees internes confidentielles. Aucun mot de passe ni cle.
Ne les partagez pas et rangez-les en lieu sur.
"""


def fichiers_projet(leger):
    for dossier, sous, noms in os.walk(RACINE):
        sous[:] = [d for d in sous if d not in EXCLURE_DOSSIERS]
        for f in noms:
            chemin = os.path.join(dossier, f)
            if leger and os.path.getsize(chemin) > GROS:
                continue
            yield chemin


for nom, leger in ((nom_leger, True), (nom_complet, False)):
    with zipfile.ZipFile(nom, 'w', zipfile.ZIP_DEFLATED) as z:
        for chemin in fichiers_projet(leger):
            z.write(chemin, os.path.join('reine-cloud.fr', os.path.relpath(chemin, RACINE)))
        if not leger:
            z.write(bundle, f'reine-cloud-historique-V{version}-{date}.bundle')
        z.writestr('LISEZ-MOI-RESTAURATION.txt', lisez_moi('leger' if leger else 'complete'))
subprocess.run(['cp', bundle, nom_bundle], check=True)
for n in (nom_leger, nom_bundle, nom_complet):
    print(f'{n} ({os.path.getsize(n) // 1024} Ko)')
open(fichier_version, 'w').write(str(version) + '\n')
print('Sauvegarde :', nom, f'({os.path.getsize(nom) // 1024} Ko) — version {version}')
