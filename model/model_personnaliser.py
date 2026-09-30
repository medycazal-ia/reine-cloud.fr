#!/usr/bin/env python3
"""Personnalise le modèle : remplit les champs {{...}} avec une identité et produit le site prêt à déposer.

Usage :
  1. Copier model-identite.exemple.json en model-identite.json et remplir vos informations.
  2. python3 model_personnaliser.py            (ou : python3 model_personnaliser.py autre-identite.json)
  3. Le résultat est dans model-sortie/<domaine>/ et dans model-sortie/<domaine>.zip
     (à extraire dans le dossier PRINCIPAL du compte d'hébergement, voir MODEL-LISEZ-MOI.md).
"""
import json
import os
import re
import shutil
import sys
import zipfile

ICI = os.path.dirname(os.path.abspath(__file__))
fichier = sys.argv[1] if len(sys.argv) > 1 else os.path.join(ICI, 'model-identite.json')
if not os.path.exists(fichier):
    sys.exit(f"Fichier d'identité introuvable : {fichier}\nCopiez model-identite.exemple.json en model-identite.json et remplissez-le.")
identite = json.load(open(fichier, encoding='utf-8'))

REQUIS = ['DOMAINE', 'EDITEUR_NOM', 'NOM_COMMERCIAL', 'SIRET', 'ADRESSE', 'EMAIL_CONTACT', 'EMAIL_EXPEDITEUR', 'TELEPHONE', 'HEBERGEUR']
manque = [k for k in REQUIS if not str(identite.get(k, '')).strip() or str(identite.get(k)).startswith('A_REMPLIR')]
if manque:
    sys.exit('Champs à remplir dans le fichier d\'identité : ' + ', '.join(manque))

INTERDITS = '"\\$<>{}&'
for k, v in identite.items():
    mauvais = [c for c in str(v) if c in INTERDITS]
    if mauvais:
        sys.exit(f'Le champ {k} contient un caractère interdit ({" ".join(sorted(set(mauvais)))}). '
                 'Écrivez « et » au lieu de « & » et évitez les guillemets doubles.')

# valeurs calculées
v = {k: str(val).replace("'", '’') for k, val in identite.items()}   # apostrophe typographique : sûre partout
chiffres = re.sub(r'\D', '', identite['TELEPHONE'])
v['TELEPHONE_LIEN'] = ('+' if identite['TELEPHONE'].strip().startswith('+') else '') + chiffres
v['WHATSAPP'] = identite.get('WHATSAPP') or chiffres
v['SLUG'] = identite.get('SLUG') or identite['DOMAINE'].split('.')[0]
v.setdefault('COMPTE_CPANEL', identite.get('COMPTE_CPANEL', 'COMPTE'))

sortie = os.path.join(ICI, 'model-sortie', identite['DOMAINE'])
shutil.rmtree(sortie, ignore_errors=True)
TEXTE = ('.html', '.php', '.js', '.md', '.json', '.txt', '.sql')
n = 0
for dossier, _, noms in os.walk(os.path.join(ICI, 'sources')):
    for nom in noms:
        src = os.path.join(dossier, nom)
        dst = os.path.join(sortie, os.path.relpath(src, os.path.join(ICI, 'sources')))
        os.makedirs(os.path.dirname(dst), exist_ok=True)
        if src.endswith(TEXTE):
            t = open(src, encoding='utf-8').read()
            for cle, val in v.items():
                t = t.replace('{{' + cle + '}}', val)
            open(dst, 'w', encoding='utf-8').write(t)
        else:
            shutil.copyfile(src, dst)
        n += 1
restes = []
for dossier, _, noms in os.walk(sortie):
    for nom in noms:
        if nom.endswith(TEXTE):
            for m in re.findall(r'\{\{[A-Z_]+\}\}', open(os.path.join(dossier, nom), encoding='utf-8').read()):
                restes.append(f'{nom}: {m}')
if restes:
    sys.exit('Champs non remplis : ' + ', '.join(sorted(set(restes))))
zip_sortie = sortie + '.zip'
with zipfile.ZipFile(zip_sortie, 'w', zipfile.ZIP_DEFLATED) as z:
    for dossier, _, noms in os.walk(sortie):
        for nom in noms:
            chemin = os.path.join(dossier, nom)
            z.write(chemin, os.path.relpath(chemin, sortie))
print(f'{n} fichiers -> {sortie}\nArchive : {zip_sortie}')
