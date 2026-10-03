#!/usr/bin/env python3
"""Génère le dossier model/ (modèle déployable) à partir du site actuel.

Les informations propres à reine-cloud.fr (nom, SIRET, adresse, e-mails, domaine…) sont remplacées
par des champs {{...}}. Le script model/model_personnaliser.py les remplit avec une autre identité.
Usage : python3 outils/model_generer.py
"""
import os
import shutil
import zipfile

RACINE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
os.chdir(RACINE)
MODEL = 'model'
TEXTE = ('.html', '.php', '.js', '.md', '.json', '.txt', '.sql')

# Ordre important : du plus long / plus précis au plus court.
REMPLACEMENTS = [
    ('LWS (Ligne Web Services), 4 rue Galvani, 75017 Paris, RCS Paris 450 453 881, téléphone : 01 77 62 30 03', '{{HEBERGEUR}}'),
    ('231 rue du Faubourg Saint-Honoré, 75001 Paris, France', '{{ADRESSE}}'),
    ('231 rue du Faubourg Saint-Honoré, 75001 Paris', '{{ADRESSE}}'),
    ('415&nbsp;030&nbsp;550&nbsp;00139', '{{SIRET}}'),
    ('415 030 550 00139', '{{SIRET}}'),
    ('Medy Harry CAZAL', '{{EDITEUR_NOM}}'),
    ('La Maison du CREL', '{{NOM_COMMERCIAL}}'),
    ('contact@reine-cloud.fr', '{{EMAIL_EXPEDITEUR}}'),
    ('cazal@medy.site', '{{EMAIL_CONTACT}}'),
    ('https://wa.me/33674201662', 'https://wa.me/{{WHATSAPP}}'),
    ('tel:+33674201662', 'tel:{{TELEPHONE_LIEN}}'),
    ('+33 6 74 20 16 62', '{{TELEPHONE}}'),
    ('config-reine-cloud.php', 'config-{{SLUG}}.php'),
    ('reine-cloud.fr', '{{DOMAINE}}'),
    ('rs2875111', '{{COMPTE_CPANEL}}'),
    ('COMPTE_reine', 'COMPTE_base'),
    ('`reine` (elle devient', '`base` (elle devient'),
    ('illustrations (dont la prune couronnée)', 'illustrations'),
    ('Medy', '{{EDITEUR_NOM}}'),
]

SITE_FICHIERS = ['index.html', 'commander.html', 'payer.html', 'merci.html', 'cgv.html', 'mentions-legales.html',
                 'confidentialite.html', 'contact.php', 'domaine.php', 'code.php', 'paiement.js', 'facture.php', 'recu.php', 'favicon.ico']
COPIES = []   # (source, destination sous model/sources/)
for f in SITE_FICHIERS:
    COPIES.append((f'site/{f}', f'public_html/{f}'))
COPIES += [('site/fonts', 'public_html/fonts'), ('site/js', 'public_html/js'), ('site/gestion', 'public_html/gestion'),
           ('serveur/abonnements', 'abonnements'),
           ('docs/juridique', 'docs/juridique'), ('docs/base-de-donnees', 'docs/base-de-donnees'),
           ('docs/mode-emploi-lws.md', 'docs/mode-emploi-lws.md'), ('docs/ged-conception.md', 'docs/ged-conception.md')]


def tokeniser(texte):
    for ancien, nouveau in REMPLACEMENTS:
        texte = texte.replace(ancien, nouveau)
    return texte


def copier(src, dst):
    if os.path.isdir(src):
        for dossier, _, noms in os.walk(src):
            for nom in noms:
                s = os.path.join(dossier, nom)
                copier(s, os.path.join(dst, os.path.relpath(s, src)))
        return
    os.makedirs(os.path.dirname(dst), exist_ok=True)
    if src.endswith(TEXTE):
        with open(src, encoding='utf-8') as f:
            contenu = tokeniser(f.read())
        if src.endswith('mode-emploi-lws.md') and '## Sauvegarde versionnée' in contenu:
            contenu = contenu[:contenu.index('## Sauvegarde versionnée')].rstrip() + '\n'
        with open(dst, 'w', encoding='utf-8') as f:
            f.write(contenu)
    else:
        shutil.copyfile(src, dst)


shutil.rmtree(MODEL + '/sources', ignore_errors=True)
for src, dst in COPIES:
    copier(src, f'{MODEL}/sources/{dst}')
# le modèle ne doit contenir aucun lien de paiement réel
with open(f'{MODEL}/sources/public_html/paiement.js', 'w', encoding='utf-8') as f:
    f.write('// Liens de paiement du site. Ne contient aucun secret : ces adresses sont publiques.\n'
            '// Collez entre les guillemets l\'adresse (https://…) du lien de paiement créé chez le prestataire.\n'
            '// Laissez "" pour désactiver un bouton.\n'
            'window.LIENS_PAIEMENT = {\n  socle:   "",\n  agent:   "",\n  cadrage: "",\n  surmesure: "",\n  libre:   ""\n};\n')
shutil.copyfile('outils/model_personnaliser.py', f'{MODEL}/model_personnaliser.py')
print('model/ généré :', sum(len(fs) for _, _, fs in os.walk(MODEL + '/sources')), 'fichiers')

# archive livrable
os.makedirs('livrables', exist_ok=True)
sortie = 'livrables/model-site-deployable.zip'
with zipfile.ZipFile(sortie, 'w', zipfile.ZIP_DEFLATED) as z:
    for dossier, _, noms in os.walk(MODEL):
        for nom in noms:
            chemin = os.path.join(dossier, nom)
            z.write(chemin, chemin)
print('->', sortie, os.path.getsize(sortie) // 1024, 'Ko')
