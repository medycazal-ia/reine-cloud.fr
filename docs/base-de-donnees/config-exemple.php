<?php
// MODÈLE — ne contient aucun secret.
// À copier sur le serveur sous le nom « config-reine-cloud.php », dans le dossier
// PRINCIPAL du compte (le dossier qui contient public_html, PAS dans public_html),
// puis à remplir avec les vrais identifiants dans le Gestionnaire de fichiers.
// Ne jamais mettre le fichier rempli dans le dépôt.
return [
    'hote'  => 'localhost',
    'base'  => 'COMPTE_reine',   // nom complet de la base, préfixe cPanel compris
    'user'  => 'COMPTE_rcweb',   // nom complet de l'utilisateur, préfixe compris
    'mdp'   => 'COLLER_ICI_LE_MOT_DE_PASSE',
    // Liens de paiement (publics, pas des secrets) utilisés dans les e-mails d'échéance :
    'liens' => [
        'socle' => 'https://…',   // lien à 19,99 € (réutilisable)
        'libre' => 'https://…',   // lien à montant saisi par le client
    ],
];
