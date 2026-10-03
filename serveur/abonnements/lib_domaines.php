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
        $raison = is_array($d) && isset($d['info']) ? ' : ' . mb_substr(is_scalar($d['info']) ? (string) $d['info'] : (string) json_encode($d['info'], JSON_UNESCAPED_UNICODE), 0, 300) : ($corps !== '' ? ' : ' . mb_substr(preg_replace('/\s+/', ' ', $corps) ?? '', 0, 200) : '');
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

/** Contacts (propriétaires possibles d'un nom) gérés par le compte LWS : [numéro => libellé lisible]. @return array{0:bool,1:string,2:array} */
function lister_proprietaires(array $config): array
{
    $r = lws_reglages($config);
    if ($r['login'] === '' || $r['pass'] === '') {
        return [false, 'Réglages LWS incomplets : lws_login et lws_pass doivent figurer dans config-reine-cloud.php.', []];
    }
    [$code, $corps] = lws_http('GET', '/contact/0/list', null, $r, !$r['reel']);
    $d = json_decode($corps, true);
    if ($code !== 200 || !is_array($d) || !is_array($d['data'] ?? null)) {
        $raison = is_array($d) && isset($d['info']) ? ' : ' . mb_substr(is_scalar($d['info']) ? (string) $d['info'] : (string) json_encode($d['info'], JSON_UNESCAPED_UNICODE), 0, 300) : '';
        return [false, 'Liste des propriétaires indisponible (code ' . $code . $raison . ').', []];
    }
    $liste = [];
    foreach ($d['data'] as $id => $c) {
        if (!is_array($c)) {
            continue;
        }
        $nom = trim(($c['firstname'] ?? '') . ' ' . ($c['lastname'] ?? ''));
        $societe = trim((string) ($c['company'] ?? ''));
        $ville = trim((string) ($c['city'] ?? ''));
        $liste[(string) $id] = trim(($societe !== '' ? $societe . ' — ' : '') . $nom . ($ville !== '' ? ' (' . $ville . ')' : ''));
    }
    return [true, count($liste) . ' propriétaire(s) trouvé(s).', $liste];
}

/** Crée un contact (futur propriétaire de noms) chez LWS. Le mot de passe n'est jamais gardé ni journalisé. @return array{0:bool,1:string} */
function creer_proprietaire(PDO $pdo, array $config, array $s): array
{
    $r = lws_reglages($config);
    if ($r['login'] === '' || $r['pass'] === '') {
        return [false, 'Réglages LWS incomplets : lws_login et lws_pass doivent figurer dans config-reine-cloud.php.'];
    }
    $c = [];
    foreach (['company', 'lastname', 'firstname', 'address', 'postal', 'city', 'country', 'phone', 'email'] as $k) {
        $c[$k] = trim((string) ($s[$k] ?? ''));
    }
    $mdp = (string) ($s['password'] ?? '');
    foreach (['lastname' => 'nom', 'firstname' => 'prénom', 'address' => 'adresse', 'postal' => 'code postal', 'city' => 'ville'] as $k => $lib) {
        if ($c[$k] === '' || mb_strlen($c[$k]) > 120) {
            return [false, "Champ « $lib » manquant ou trop long."];
        }
    }
    $c['country'] = strtoupper($c['country']);
    if (!preg_match('/^[A-Z]{2}$/', $c['country'])) {
        return [false, 'Pays : deux lettres, par exemple FR.'];
    }
    if (!preg_match('/^00\d{8,14}$/', $c['phone'])) {
        return [false, 'Téléphone au format international, par exemple 0033674000000 (sans espace ni +).'];
    }
    if (!filter_var($c['email'], FILTER_VALIDATE_EMAIL)) {
        return [false, 'Adresse e-mail invalide.'];
    }
    if (mb_strlen($mdp) < 12) {
        return [false, 'Mot de passe du contact : 12 caractères au minimum.'];
    }
    if ($c['company'] === '') {
        unset($c['company']);   // laisser vide = contact individuel
    }
    [$code, $corps] = lws_http('POST', '/contact', $c + ['password' => $mdp], $r, !$r['reel']);
    $d = json_decode($corps, true);
    $ok = $code >= 200 && $code < 300;
    $info = is_array($d) && isset($d['info']) ? (is_scalar($d['info']) ? (string) $d['info'] : (string) json_encode($d['info'], JSON_UNESCAPED_UNICODE)) : '';
    $id = '';
    if ($ok && is_array($d)) {
        $data = $d['data'] ?? null;
        $id = is_scalar($data) ? (string) $data : (is_array($data) ? (string) ($data['id'] ?? $data['ID'] ?? $data['owner'] ?? array_key_first($data) ?? '') : '');
    }
    $mode = $r['reel'] ? '' : ' [MODE ESSAI]';
    journaliser($pdo, $r['reel'] ? 'domaine (contact)' : 'domaine (essai)', 'contact', ($ok ? 'contact créé' : 'échec') . " code $code" . ($id !== '' ? ", numéro $id" : ''));
    return [$ok, $ok ? 'Contact créé' . ($id !== '' ? ', numéro ' . $id : ' (numéro non reconnu dans la réponse : cliquez sur « Voir mes propriétaires »)') . $mode . '.'
        : 'LWS a refusé la création du contact (code ' . $code . ($info !== '' ? ' : ' . mb_substr($info, 0, 300) : '') . ')' . $mode . '.'];
}
