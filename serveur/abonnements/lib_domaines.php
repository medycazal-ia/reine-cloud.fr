<?php
// Achat d'un nom de domaine par l'API LWS, depuis l'administration protégée uniquement.
// Aucun secret ici : identifiants dans config-reine-cloud.php (clés lws_login, lws_pass, lws_owner ; facultatives : lws_package, lws_achat_reel).
// Par défaut : MODE TEST (en-tête X-Test-Mode), donc aucun débit. Le mode réel exige 'lws_achat_reel' => true dans le fichier de réglages.
declare(strict_types=1);

const LWS_API = 'https://api.lws.net/v1';
const EXTENSIONS_ACHAT = ['fr', 'com', 'eu', 'net', 'org', 'info'];
const DUREES_ACHAT = [12, 24, 36];   // en mois

function lws_reglages(array $c): array
{
    return [
        'login'  => (string) ($c['lws_login'] ?? ''),
        'pass'   => (string) ($c['lws_pass'] ?? ''),
        'owner'  => (int) ($c['lws_owner'] ?? 0),
        'package' => (string) ($c['lws_package'] ?? 'domaine'),
        'reel'   => ($c['lws_achat_reel'] ?? false) === true,
    ];
}

function lws_pret(array $r): bool
{
    return $r['login'] !== '' && $r['pass'] !== '' && $r['owner'] > 0;
}

/** Nom de domaine complet valide et d'une extension autorisée, sinon null. */
function domaine_valide(string $d): ?string
{
    $d = strtolower(trim($d));
    if (!preg_match('/^([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)\.([a-z]{2,10})$/', $d, $m) || !in_array($m[3], EXTENSIONS_ACHAT, true)) {
        return null;
    }
    return $d;
}

/** [code HTTP, corps] ; remplaçable pour les essais ($GLOBALS['LWS_HTTP']). */
function lws_http(string $methode, string $chemin, ?array $corps, array $r, bool $test): array
{
    $entetes = ['Accept: application/json', 'X-Auth-Login: ' . $r['login'], 'X-Auth-Pass: ' . $r['pass']];
    if ($test) {
        $entetes[] = 'X-Test-Mode: true';
    }
    if (isset($GLOBALS['LWS_HTTP']) && is_callable($GLOBALS['LWS_HTTP'])) {
        return $GLOBALS['LWS_HTTP']($methode, $chemin, $corps, $test);
    }
    $ch = curl_init(LWS_API . $chemin);
    $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_CUSTOMREQUEST => $methode];
    if ($corps !== null) {
        $entetes[] = 'Content-Type: application/json';
        $opts[CURLOPT_POSTFIELDS] = json_encode($corps);
    }
    $opts[CURLOPT_HTTPHEADER] = $entetes;
    curl_setopt_array($ch, $opts);
    $rep = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return [$code, is_string($rep) ? $rep : ''];
}

/** @return array{0:bool,1:string} succès et message lisible */
function acheter_domaine(PDO $pdo, array $config, string $saisie, string $confirmation, int $mois): array
{
    $r = lws_reglages($config);
    if (!lws_pret($r)) {
        return [false, 'Réglages LWS incomplets : lws_login, lws_pass et lws_owner doivent figurer dans config-reine-cloud.php.'];
    }
    $domaine = domaine_valide($saisie);
    if ($domaine === null) {
        return [false, 'Nom de domaine invalide ou extension non autorisée (' . implode(', ', EXTENSIONS_ACHAT) . ').'];
    }
    if (strtolower(trim($confirmation)) !== $domaine) {
        return [false, 'Confirmation différente du nom : retapez exactement ' . $domaine . ' pour valider l\'achat.'];
    }
    if (!in_array($mois, DUREES_ACHAT, true)) {
        return [false, 'Durée non autorisée.'];
    }
    $test = !$r['reel'];

    [$code, $corps] = lws_http('GET', '/domain/' . rawurlencode($domaine) . '/availability', null, $r, $test);
    $d = json_decode($corps, true);
    if ($code !== 200 || !is_array($d) || !is_bool($d['data'] ?? null)) {
        $raison = is_array($d) && isset($d['info']) ? ' : ' . mb_substr((string) $d['info'], 0, 200) : ($corps !== '' ? ' : ' . mb_substr(preg_replace('/\s+/', ' ', $corps) ?? '', 0, 200) : '');
        return [false, 'Vérification de disponibilité impossible auprès de LWS (code ' . $code . $raison . '). Aucun achat effectué.'];
    }
    if ($d['data'] !== true) {
        return [false, $domaine . ' n\'est pas disponible. Aucun achat effectué.'];
    }

    [$code, $corps] = lws_http('POST', '/hosting', ['package' => $r['package'], 'domain' => $domaine, 'owner' => $r['owner'], 'type' => 'buy', 'period' => $mois], $r, $test);
    $ok = $code >= 200 && $code < 300;
    $reponse = mb_substr(preg_replace('/\s+/', ' ', $corps) ?? '', 0, 300);
    journaliser($pdo, $test ? 'domaine (essai)' : 'domaine acheté', $domaine, ($ok ? 'ok' : 'échec') . " code $code, $mois mois : $reponse");
    $mode = $test ? ' [MODE ESSAI : rien n\'a été débité]' : '';
    return [$ok, ($ok ? 'Réponse de LWS pour ' . $domaine : 'LWS a refusé l\'achat de ' . $domaine) . " (code $code)$mode : $reponse"];
}
