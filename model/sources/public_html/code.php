<?php
// Vérification d'un code parrain pour la page de commande. Réponse JSON, aucune donnée personnelle.
//   GET  code.php?code=XXXX&offre=socle           -> {"valide":true,"pourcentage":20,"prix_initial":19.99,"prix_remise":15.99,…}
//   POST code.php (action=utiliser, code, offre)  -> compte une utilisation (clic sur « Payer »)
declare(strict_types=1);

if (!defined('CODE_TEST')) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex');
    try {
        require dirname(__DIR__) . '/abonnements/lib.php';
        require dirname(__DIR__) . '/abonnements/lib_remises.php';
        $pdo = db();
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $methode = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (!verifications_autorisees($ip)) {
            http_response_code(429);
            exit(json_encode(['erreur' => 'trop_d_essais']));
        }
        if ($methode === 'POST' && ($_POST['action'] ?? '') === 'utiliser') {
            echo json_encode(['ok' => utiliser_code($pdo, (string) ($_POST['code'] ?? ''), (string) ($_POST['offre'] ?? ''))]);
        } elseif ($methode === 'GET') {
            echo json_encode(verifier_code($pdo, (string) ($_GET['code'] ?? ''), (string) ($_GET['offre'] ?? '')), JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(405);
            echo json_encode(['erreur' => 'methode']);
        }
    } catch (Throwable $e) {
        http_response_code(503);
        echo json_encode(['erreur' => 'indisponible']);
    }
}
