<?php
// Reçu de paiement par lien secret : sans nom, avec la référence de la commande, un lien et un QR code vers la facture.
declare(strict_types=1);

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
require dirname(__DIR__) . '/abonnements/lib.php';

$jeton = (string) ($_GET['r'] ?? '');
$f = null;
if (preg_match('/^[a-f0-9]{32}$/', $jeton)) {
    $q = db()->prepare("SELECT * FROM factures WHERE jeton_recu = ? AND statut = 'payee'");
    $q->execute([$jeton]);
    $f = $q->fetch(PDO::FETCH_ASSOC) ?: null;
}
if (!$f) {
    http_response_code(404);
    exit('<!doctype html><meta charset="utf-8"><title>Reçu introuvable</title><p style="font-family:sans-serif;max-width:560px;margin:40px auto">Reçu introuvable. Vérifiez le lien reçu par e-mail.</p>');
}
echo recu_html($f);
