<?php
// Affichage d'une facture par lien secret (envoyé au client). Aucune connexion nécessaire : le lien est impossible à deviner.
declare(strict_types=1);

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
require dirname(__DIR__) . '/abonnements/lib.php';

$jeton = (string) ($_GET['t'] ?? '');
$f = null;
if (preg_match('/^[a-f0-9]{32}$/', $jeton)) {
    $q = db()->prepare('SELECT * FROM factures WHERE jeton = ?');
    $q->execute([$jeton]);
    $f = $q->fetch(PDO::FETCH_ASSOC) ?: null;
}
if (!$f) {
    http_response_code(404);
    exit('<!doctype html><meta charset="utf-8"><title>Facture introuvable</title><p style="font-family:sans-serif;max-width:560px;margin:40px auto">Facture introuvable. Vérifiez le lien reçu par e-mail.</p>');
}
echo facture_html($f);
