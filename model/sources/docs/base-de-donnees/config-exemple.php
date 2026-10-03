<?php
// MODÈLE — ne contient aucun secret.
// À copier sur le serveur sous le nom « config-{{SLUG}}.php », dans le dossier
// PRINCIPAL du compte (le dossier qui contient public_html, PAS dans public_html),
// puis à remplir avec les vrais identifiants dans le Gestionnaire de fichiers.
// Ne jamais mettre le fichier rempli dans le dépôt.
return [
    'hote'  => 'localhost',
    'base'  => 'COMPTE_base',   // nom complet de la base, préfixe cPanel compris
    'user'  => 'COMPTE_rcweb',   // nom complet de l'utilisateur, préfixe compris
    'mdp'   => 'COLLER_ICI_LE_MOT_DE_PASSE',
    // Facultatif : API LWS, lecture seule (vérification des noms de domaine). Sans ces deux lignes, seuls les registres officiels sont utilisés.
    // 'lws_login' => 'COLLER_ICI_L_IDENTIFIANT_API',
    // 'lws_pass'  => 'COLLER_ICI_LE_MOT_DE_PASSE_API',
    // Liens de paiement (publics, pas des secrets) utilisés dans les e-mails d'échéance :
    'liens' => [
        'socle' => 'https://…',   // lien à 19,99 € (réutilisable)
        'libre' => 'https://…',   // lien à montant saisi par le client
    ],
];
