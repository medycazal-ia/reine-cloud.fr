<?php
// Tests de la recherche de nom de domaine (réponses des registres simulées : aucun accès réseau).
define('DOMAINE_TEST', true);
require dirname(__DIR__, 2) . '/site/domaine.php';
$ok = 0; $ko = 0;
function eq($nom, $a, $b) { global $ok, $ko; if ($a === $b) { $ok++; } else { $ko++; echo "ECHEC $nom : " . json_encode($a) . ' != ' . json_encode($b) . "\n"; } }

// nettoyage des saisies
eq('simple', nettoyer_nom('monsite'), 'monsite');
eq('accents et espaces', nettoyer_nom('  Café Dupont  '), 'cafe-dupont');
eq('apostrophe', nettoyer_nom("L'Atelier d'Éric"), 'l-atelier-d-eric');
eq('avec extension', nettoyer_nom('monsite.fr'), 'monsite');
eq('avec www et https', nettoyer_nom('https://www.Mon-Site.com/page'), 'mon-site');
eq('caractères interdits', nettoyer_nom('mon$site<script>'), 'monsitescript');
eq('tirets doubles et bords', nettoyer_nom('--ma--boite--'), 'ma-boite');
eq('vide', nettoyer_nom('   '), null);
eq('que des symboles', nettoyer_nom('$$$'), null);
eq('trop long', nettoyer_nom(str_repeat('a', 64)), null);
eq('63 caractères ok', strlen(nettoyer_nom(str_repeat('a', 63))), 63);
eq('injection de chemin', nettoyer_nom('../../etc/passwd'), null);

// liste officielle
$json = '{"services":[[["com","net"],["https://rdap.verisign.com/com/v1/"]],[["fr"],["http://non-https.fr/","https://rdap.nic.fr"]],[["xyz"],["http://seulement-http/"]]]}';
$b = bootstrap_depuis_json($json);
eq('bootstrap com', $b['com'], 'https://rdap.verisign.com/com/v1/'); eq('bootstrap fr https choisi', $b['fr'], 'https://rdap.nic.fr/');
eq('bootstrap http ignoré', isset($b['xyz']), false); eq('bootstrap json invalide', bootstrap_depuis_json('pas du json'), []);

// états
eq('200 = indisponible', etat_depuis_code(200), 'indisponible'); eq('404 = disponible', etat_depuis_code(404), 'disponible');
eq('429 = inconnu', etat_depuis_code(429), 'inconnu'); eq('0 = inconnu', etat_depuis_code(0), 'inconnu'); eq('500 = inconnu', etat_depuis_code(500), 'inconnu');

// vérification avec de fausses réponses
foreach (glob(sys_get_temp_dir() . '/reine-domaine-*') ?: [] as $f) { @unlink($f); }
$appels = [];
$GLOBALS['RDAP_FETCH'] = function ($url) use (&$appels) {
    $appels[] = $url;
    if (str_contains($url, 'pris.fr') || str_contains($url, 'pris.com')) { return [200, '{}']; }
    if (str_contains($url, 'lent.')) { return [0, '']; }
    return [404, '{}'];
};
$r = verifier('pris', ['fr', 'com', 'org'], SECOURS);
eq('pris.fr indisponible', $r[0], ['domaine' => 'pris.fr', 'etat' => 'indisponible']);
eq('pris.com indisponible', $r[1]['etat'], 'indisponible'); eq('pris.org disponible', $r[2]['etat'], 'disponible');
eq('adresse du registre', $appels[0], 'https://rdap.nic.fr/domain/pris.fr');
$n = count($appels); verifier('pris', ['fr', 'com', 'org'], SECOURS); eq('cache : aucun nouvel appel', count($appels), $n);
$r = verifier('lent', ['fr'], SECOURS); eq('registre injoignable -> inconnu', $r[0]['etat'], 'inconnu');
$n = count($appels); verifier('lent', ['fr'], SECOURS); eq('inconnu non mis en cache', count($appels), $n + 1);
$r = verifier('x', ['zz'], SECOURS); eq('extension sans registre connu', $r[0]['etat'], 'inconnu');

// requête complète
$GLOBALS['RDAP_FETCH'] = fn($u) => str_contains($u, 'dns.json') ? [200, $json] : [404, '{}'];
@unlink(sys_get_temp_dir() . '/reine-rdap-bootstrap.json');
[$code, $c] = traiter_requete(['q' => 'Mon Café'], '1.2.3.4');
eq('requête ok', $code, 200); eq('nom normalisé', $c['nom'], 'mon-cafe'); eq('une ligne par extension', count($c['resultats']), count(EXTENSIONS));
eq('requête invalide', traiter_requete(['q' => '%%%'], '1.2.3.4')[0], 400); eq('sans paramètre', traiter_requete([], '1.2.3.4')[0], 400);

// limitation du débit
foreach (glob(sys_get_temp_dir() . '/reine-domaine-rl-*') ?: [] as $f) { @unlink($f); }
$passes = 0; for ($i = 0; $i < LIMITE_REQUETES + 5; $i++) { if (debit_autorise('9.9.9.9')) { $passes++; } }
eq('limite de débit', $passes, LIMITE_REQUETES); eq('autre visiteur non bloqué', debit_autorise('8.8.8.8'), true);
[$code] = traiter_requete(['q' => 'abc'], '9.9.9.9'); eq('429 quand limite atteinte', $code, 429);
foreach (glob(sys_get_temp_dir() . '/reine-domaine-*') ?: [] as $f) { @unlink($f); }
// API LWS (réponses simulées)
foreach (glob(sys_get_temp_dir() . '/reine-domaine-*') ?: [] as $f) { @unlink($f); }
$vus = [];
$GLOBALS['LWS_CONFIG'] = ['login' => 'L', 'pass' => 'P'];
$GLOBALS['RDAP_FETCH'] = function ($url, $h = []) use (&$vus) {
    $vus[] = [$url, $h];
    if (str_contains($url, 'api.lws.net')) {
        if (str_contains($url, 'libre.fr')) { return [200, '{"code":200,"info":"x","data":true}']; }
        if (str_contains($url, 'pris.fr')) { return [200, '{"code":200,"info":"x","data":false}']; }
        return [400, '{}'];   // LWS ne gère pas cette extension -> repli sur les registres
    }
    return [404, '{}'];
};
$r = verifier('libre', ['fr'], SECOURS); eq('LWS : libre', $r[0]['etat'], 'disponible');
eq('LWS : identifiants en en-têtes', in_array('X-Auth-Login: L', $vus[0][1], true) && in_array('X-Auth-Pass: P', $vus[0][1], true), true);
eq('LWS : adresse', $vus[0][0], 'https://api.lws.net/v1/domain/libre.fr/availability');
$r = verifier('pris', ['fr'], SECOURS); eq('LWS : pris', $r[0]['etat'], 'indisponible');
$r = verifier('autre', ['eu'], SECOURS); eq('LWS en échec -> repli registre', $r[0]['etat'], 'disponible');
eq('repli : le registre est interrogé', str_contains(end($vus)[0], 'rdap.eurid.eu'), true);
eq('repli : pas d identifiants envoyés au registre', end($vus)[1], []);
$GLOBALS['LWS_CONFIG'] = [];
foreach (glob(sys_get_temp_dir() . '/reine-domaine-*') ?: [] as $f) { @unlink($f); }
$vus = []; verifier('sanslws', ['fr'], SECOURS); eq('sans config LWS : aucun appel LWS', count(array_filter($vus, fn($v) => str_contains($v[0], 'lws.net'))), 0);
foreach (glob(sys_get_temp_dir() . '/reine-domaine-*') ?: [] as $f) { @unlink($f); }
echo "TOTAL domaine -> OK: $ok, ECHECS: $ko\n";
