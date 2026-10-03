<?php
// Recherche de nom de domaine : interroge les registres officiels (service public RDAP) depuis le serveur.
// Aucune clé, aucun cookie, aucune donnée enregistrée : le nom cherché n'est ni gardé ni lié au visiteur.
// Réponse JSON : {"nom":"monsite","resultats":[{"domaine":"monsite.fr","etat":"disponible|indisponible|inconnu"},…]}
declare(strict_types=1);

const EXTENSIONS = ['fr', 'com', 'eu', 'net', 'org', 'info'];
const BOOTSTRAP_URL = 'https://data.iana.org/rdap/dns.json';
const SECOURS = [
    'fr' => 'https://rdap.nic.fr/', 'com' => 'https://rdap.verisign.com/com/v1/', 'net' => 'https://rdap.verisign.com/net/v1/',
    'org' => 'https://rdap.publicinterestregistry.org/rdap/', 'eu' => 'https://rdap.eurid.eu/', 'info' => 'https://rdap.identitydigital.services/rdap/',
];
const LIMITE_REQUETES = 30;    // par visiteur et par fenêtre
const FENETRE_SECONDES = 600;  // 10 minutes
const CACHE_SECONDES = 600;

/** Nom saisi -> nom de domaine propre (sans accents, minuscules, tirets) ou null s'il est invalide. */
function nettoyer_nom(string $saisie): ?string
{
    $s = mb_strtolower(trim($saisie), 'UTF-8');
    $s = preg_replace('~^https?://~', '', $s) ?? '';
    $s = preg_replace('~^www\.~', '', $s) ?? '';
    $s = explode('/', $s)[0];
    $s = explode('.', $s)[0];   // « monsite.fr » -> « monsite »
    $s = strtr($s, ['à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o',
        'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'œ' => 'oe', 'æ' => 'ae', 'ÿ' => 'y', 'ñ' => 'n']);
    $s = preg_replace('~[\s_\'’]+~u', '-', $s) ?? '';
    $s = preg_replace('~[^a-z0-9-]~', '', $s) ?? '';
    $s = trim(preg_replace('~-{2,}~', '-', $s) ?? '', '-');
    if ($s === '' || strlen($s) > 63) {
        return null;
    }
    return $s;
}

/** Lit la liste officielle de l'IANA : extension -> adresse du service RDAP du registre. */
function bootstrap_depuis_json(string $json): array
{
    $carte = [];
    $d = json_decode($json, true);
    foreach (($d['services'] ?? []) as $service) {
        $urls = $service[1] ?? [];
        $https = array_values(array_filter($urls, static fn($u) => is_string($u) && str_starts_with($u, 'https://')));
        if (!$https) {
            continue;
        }
        foreach ($service[0] ?? [] as $ext) {
            $carte[strtolower((string) $ext)] = rtrim($https[0], '/') . '/';
        }
    }
    return $carte;
}

/** [code HTTP, corps] ; code 0 = échec réseau. Remplaçable pour les tests. */
function http_get(string $url, int $delai = 5): array
{
    if (isset($GLOBALS['RDAP_FETCH']) && is_callable($GLOBALS['RDAP_FETCH'])) {
        return $GLOBALS['RDAP_FETCH']($url);
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $delai, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_HTTPHEADER => ['Accept: application/rdap+json, application/json']]);
    $corps = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return [$code, is_string($corps) ? $corps : ''];
}

function charger_bootstrap(): array
{
    $cache = sys_get_temp_dir() . '/reine-rdap-bootstrap.json';
    if (is_file($cache) && filemtime($cache) > time() - 86400) {
        $carte = bootstrap_depuis_json((string) file_get_contents($cache));
        if ($carte) {
            return $carte;
        }
    }
    [$code, $corps] = http_get(BOOTSTRAP_URL);
    if ($code === 200) {
        $carte = bootstrap_depuis_json($corps);
        if ($carte) {
            @file_put_contents($cache, $corps);
            return $carte;
        }
    }
    return SECOURS;
}

/** 200 = déjà enregistré ; 404 = libre ; autre = on ne sait pas. */
function etat_depuis_code(int $code): string
{
    return $code === 200 ? 'indisponible' : ($code === 404 ? 'disponible' : 'inconnu');
}

function verifier(string $nom, array $extensions, array $bootstrap): array
{
    $resultats = [];
    foreach ($extensions as $ext) {
        $domaine = "$nom.$ext";
        $cache = sys_get_temp_dir() . '/reine-domaine-' . md5($domaine) . '.json';
        if (is_file($cache) && filemtime($cache) > time() - CACHE_SECONDES) {
            $etat = (string) json_decode((string) file_get_contents($cache), true);
        } else {
            $base = $bootstrap[$ext] ?? '';
            $etat = 'inconnu';
            if ($base !== '') {
                [$code] = http_get($base . 'domain/' . $domaine);
                $etat = etat_depuis_code($code);
                if ($etat !== 'inconnu') {
                    @file_put_contents($cache, json_encode($etat));
                }
            }
        }
        $resultats[] = ['domaine' => $domaine, 'etat' => $etat];
    }
    return $resultats;
}

/** Limite le nombre de recherches par visiteur, sans garder son adresse (empreinte du jour, dossier temporaire). */
function debit_autorise(string $ip): bool
{
    $fichier = sys_get_temp_dir() . '/reine-domaine-rl-' . substr(hash('sha256', $ip . '|' . date('Y-m-d')), 0, 24);
    $maintenant = time();
    $horodatages = [];
    if (is_file($fichier)) {
        $horodatages = array_values(array_filter(array_map('intval', explode(',', (string) file_get_contents($fichier))), static fn($t) => $t > $maintenant - FENETRE_SECONDES));
    }
    if (count($horodatages) >= LIMITE_REQUETES) {
        return false;
    }
    $horodatages[] = $maintenant;
    @file_put_contents($fichier, implode(',', $horodatages));
    return true;
}

/** @return array{0:int,1:array} code HTTP et contenu JSON */
function traiter_requete(array $get, string $ip): array
{
    $nom = nettoyer_nom((string) ($get['q'] ?? ''));
    if ($nom === null) {
        return [400, ['erreur' => 'nom_invalide']];
    }
    if (!debit_autorise($ip)) {
        return [429, ['erreur' => 'trop_de_recherches']];
    }
    return [200, ['nom' => $nom, 'resultats' => verifier($nom, EXTENSIONS, charger_bootstrap())]];
}

if (!defined('DOMAINE_TEST')) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex');
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        http_response_code(405);
        exit(json_encode(['erreur' => 'methode']));
    }
    [$code, $contenu] = traiter_requete($_GET, (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    http_response_code($code);
    echo json_encode($contenu, JSON_UNESCAPED_UNICODE);
}
