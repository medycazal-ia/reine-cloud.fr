<?php
// Tests de l'achat de nom de domaine (API LWS simulée : aucun accès réseau, aucun achat).
require dirname(__DIR__, 2) . '/serveur/abonnements/lib.php';
require dirname(__DIR__, 2) . '/serveur/abonnements/lib_domaines.php';
$ok = 0; $ko = 0;
function eq($n, $a, $b) { global $ok, $ko; if ($a === $b) { $ok++; } else { $ko++; echo "ECHEC $n : " . json_encode($a) . ' != ' . json_encode($b) . "\n"; } }
$pdo = db(new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]));
$cfg = ['lws_login' => 'L', 'lws_pass' => 'P', 'lws_owner' => 123];
$appels = [];
$GLOBALS['LWS_HTTP'] = function ($m, $chemin, $corps, $test) use (&$appels) {
    $appels[] = [$m, $chemin, $corps, $test];
    if ($m === 'GET') { return [200, str_contains($chemin, 'pris.') ? '{"code":200,"data":false}' : '{"code":200,"data":true}']; }
    return [200, '{"code":200,"info":"simulé"}'];
};

eq('domaine valide', domaine_valide(' Ma-Boite.FR '), 'ma-boite.fr');
eq('sans extension', domaine_valide('maboite'), null); eq('extension refusée', domaine_valide('x.xyz'), null);
eq('injection', domaine_valide('a.fr/../b'), null); eq('tiret en tête', domaine_valide('-a.fr'), null);

[$s, $m] = acheter_domaine($pdo, [], 'a.fr', 'a.fr', 12); eq('sans réglages : refus', $s, false); eq('aucun appel', count($appels), 0);
[$s] = acheter_domaine($pdo, $cfg, 'a.fr', 'b.fr', 12); eq('confirmation différente : refus', $s, false); eq('aucun appel (confirmation)', count($appels), 0);
[$s] = acheter_domaine($pdo, $cfg, 'a.fr', 'a.fr', 7); eq('durée refusée', $s, false);
[$s, $m] = acheter_domaine($pdo, $cfg, 'pris.fr', 'pris.fr', 12); eq('domaine pris : refus', $s, false); eq('pas de POST', count(array_filter($appels, fn($a) => $a[0] === 'POST')), 0);

$appels = [];
[$s, $m] = acheter_domaine($pdo, $cfg, 'Libre.fr', 'libre.fr', 12);
eq('achat essai ok', $s, true);
eq('mode essai par défaut', $appels[1][3], true);
eq('corps envoyé', $appels[1][2], ['package' => 'domaine', 'domain' => 'libre.fr', 'owner' => 123, 'type' => 'buy', 'period' => 12]);
eq('message signale l essai', str_contains($m, 'MODE ESSAI'), true);
eq('journalisé', (int) $pdo->query("SELECT COUNT(*) FROM journal WHERE type = 'domaine (essai)' AND destinataire = 'libre.fr'")->fetchColumn(), 1);
eq('aucun secret dans le journal', (int) $pdo->query("SELECT COUNT(*) FROM journal WHERE detail LIKE '%P%' AND detail LIKE '%X-Auth%'")->fetchColumn(), 0);

$appels = [];
[$s, $m] = acheter_domaine($pdo, $cfg + ['lws_achat_reel' => true], 'libre.fr', 'libre.fr', 24);
eq('mode réel seulement si true', $appels[1][3], false); eq('réel : journal', (int) $pdo->query("SELECT COUNT(*) FROM journal WHERE type = 'domaine acheté'")->fetchColumn(), 1);
$appels = []; acheter_domaine($pdo, $cfg + ['lws_achat_reel' => 'oui'], 'libre.fr', 'libre.fr', 12);
eq('valeur douteuse = essai', $appels[1][3], true);
$GLOBALS['LWS_HTTP'] = fn($m, $c, $b, $t) => $m === 'GET' ? [200, '{"data":true}'] : [400, '{"info":"erreur"}'];
[$s] = acheter_domaine($pdo, $cfg, 'libre.fr', 'libre.fr', 12); eq('refus LWS = échec', $s, false);
$GLOBALS['LWS_HTTP'] = fn($m, $c, $b, $t) => [500, ''];
[$s] = acheter_domaine($pdo, $cfg, 'libre.fr', 'libre.fr', 12); eq('LWS en panne = échec sans achat', $s, false);
$GLOBALS['LWS_HTTP'] = fn($m, $c, $b, $t) => [400, '{"code":400,"info":"Invalid credentials"}'];
[$s, $m] = acheter_domaine($pdo, $cfg, 'libre.fr', 'libre.fr', 12); eq('400 : refus', $s, false); eq('400 : raison de LWS affichée', str_contains($m, 'Invalid credentials'), true);
eq('400 : aucun identifiant dans le message', str_contains($m, 'P') && str_contains($m, 'X-Auth'), false);
$GLOBALS['LWS_HTTP'] = fn($m, $c, $b, $t) => [400, '{"code":400,"info":["domaine invalide",{"champ":"domain"}]}'];
[$s, $m] = acheter_domaine($pdo, $cfg, 'libre.fr', 'libre.fr', 12);
eq('info en liste : affichée', str_contains($m, 'domaine invalide') && !str_contains($m, ': Array'), true);
$vu = [];
$GLOBALS['LWS_HTTP'] = function ($m, $c, $b, $t) use (&$vu) { $vu = [$m, $c, $t]; return [200, '{"code":200,"info":"Customer list fetched","data":{"565487":{"firstname":"Jean","lastname":"Dupont","company":"Ma Société","city":"Paris"},"12":{"firstname":"Ana","lastname":"Roy"}}}']; };
[$s, $m, $l] = lister_proprietaires($cfg);
eq('propriétaires : ok', $s, true); eq('propriétaires : appel', $vu, ['GET', '/contact/0/list', true]);
eq('propriétaires : libellé', $l['565487'], 'Ma Société — Jean Dupont (Paris)'); eq('propriétaires : sans société', $l['12'], 'Ana Roy');
[$s] = lister_proprietaires([]); eq('propriétaires : sans réglages', $s, false);
$GLOBALS['LWS_HTTP'] = fn($m, $c, $b, $t) => [400, '{"code":400,"info":"bad source 1.2.3.4"}'];
[$s, $m] = lister_proprietaires($cfg); eq('propriétaires : erreur LWS affichée', $s === false && str_contains($m, 'bad source'), true);
$ok_s = ['company' => '', 'lastname' => 'Dupont', 'firstname' => 'Jean', 'address' => '1 rue de la Paix', 'postal' => '75000', 'city' => 'Paris', 'country' => 'fr', 'phone' => '0033612345678', 'email' => 'a@b.fr', 'password' => 'unmotdepasselong'];
$pris = [];
$GLOBALS['LWS_HTTP'] = function ($m, $c, $b, $t) use (&$pris) { $pris = [$m, $c, $b, $t]; return [200, '{"code":200,"info":"Customer created","data":{"id":777}}']; };
[$s, $m] = creer_proprietaire($pdo, $cfg, $ok_s);
eq('contact : créé', $s, true); eq('contact : numéro lu', str_contains($m, '777'), true); eq('contact : appel', array_slice($pris, 0, 2), ['POST', '/contact']);
eq('contact : mode essai', $pris[3], true); eq('contact : pays en majuscules', $pris[2]['country'], 'FR'); eq('contact : société vide omise', isset($pris[2]['company']), false);
eq('contact : mot de passe jamais dans le journal', (int) $pdo->query("SELECT COUNT(*) FROM journal WHERE detail LIKE '%unmotdepasselong%'")->fetchColumn(), 0);
eq('contact : mot de passe jamais dans le message', str_contains($m, 'unmotdepasselong'), false);
foreach ([['phone', '06 12'], ['email', 'xx'], ['password', 'court'], ['country', 'FRA'], ['lastname', '']] as [$k, $v]) { $pris = []; [$s] = creer_proprietaire($pdo, $cfg, [$k => $v] + $ok_s); eq("contact : $k invalide refusé", $s === false && !$pris, true); }
[$s] = creer_proprietaire($pdo, [], $ok_s); eq('contact : sans réglages', $s, false);
$GLOBALS['LWS_HTTP'] = fn($m, $c, $b, $t) => [400, '{"code":400,"info":["email déjà utilisé"]}'];
[$s, $m] = creer_proprietaire($pdo, $cfg, $ok_s); eq('contact : refus LWS affiché', $s === false && str_contains($m, 'email déjà utilisé'), true);
echo "TOTAL achat domaine -> OK: $ok, ECHECS: $ko\n";
