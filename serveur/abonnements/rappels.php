<?php
// Tâche automatique quotidienne : envoie les liens de paiement et rappels d'échéance.
// Commande Cron (une fois par jour) :
//   /usr/local/bin/php /home/COMPTE/abonnements/rappels.php
// Essai sans rien envoyer : ajouter « simulation » à la fin de la commande.
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/lib.php';

$simulation = ($argv[1] ?? '') === 'simulation';
$cfg = config();
$pdo = db();
if ($simulation) {
    $GLOBALS['MAILER'] = function ($a, $s, $c) { echo "[SIMULATION] à $a : $s\n"; return true; };
}
$actions = traiter_rappels($pdo, date('Y-m-d'), $cfg);
echo $actions ? implode("\n", $actions) . "\n" : "Rien à envoyer aujourd'hui.\n";
