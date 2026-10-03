<?php
// Tests des codes parrain (base en mémoire, aucun réseau).
require dirname(__DIR__, 2) . '/serveur/abonnements/lib.php';
require dirname(__DIR__, 2) . '/serveur/abonnements/lib_remises.php';
$ok = 0; $ko = 0;
function eq($n, $a, $b) { global $ok, $ko; if ($a === $b) { $ok++; } else { $ko++; echo "ECHEC $n : " . json_encode($a) . ' != ' . json_encode($b) . "\n"; } }
$pdo = db(new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]));

eq('code normalisé', code_normalise(' parrain-ab12 '), 'PARRAIN-AB12'); eq('code trop court', code_normalise('ab'), null);
eq('code avec caractère interdit', code_normalise('AB CD'), null); eq('code avec injection', code_normalise("A'; DROP--"), null);
eq('code trop long', code_normalise(str_repeat('A', 33)), null); eq('tiret en bord', code_normalise('-ABCD'), null);
eq('pourcentage 0', pourcentage_valide('0'), 0.0); eq('pourcentage 100', pourcentage_valide('100'), 100.0);
eq('pourcentage virgule', pourcentage_valide('12,5'), 12.5); eq('pourcentage > 100', pourcentage_valide('101'), null);
eq('pourcentage négatif', pourcentage_valide('-1'), null); eq('pourcentage texte', pourcentage_valide('abc'), null); eq('pourcentage vide', pourcentage_valide(''), null);
eq('remise 20 % sur 19,99', prix_apres_remise(19.99, 20), 15.99); eq('remise 100 %', prix_apres_remise(99, 100), 0.0); eq('remise 0 %', prix_apres_remise(99, 0), 99.0);
eq('remise 33,33 % sur 199', prix_apres_remise(199, 33.33), 132.67);
eq('code généré valide', code_normalise(generer_code()) !== null, true); eq('codes générés distincts', generer_code() !== generer_code(), true);

[$s, $m, $code] = creer_code($pdo, 'amis2026', '20', 0, 'pour les amis'); eq('création', $s, true); eq('code créé en majuscules', $code, 'AMIS2026');
[$s] = creer_code($pdo, 'AMIS2026', '10', 0, ''); eq('doublon refusé', $s, false);
[$s] = creer_code($pdo, 'x', '10', 0, ''); eq('code invalide refusé', $s, false);
[$s] = creer_code($pdo, 'BONCODE', '150', 0, ''); eq('pourcentage > 100 refusé', $s, false);
[$s, , $auto] = creer_code($pdo, '', '50', 0, ''); eq('code généré si vide', $s && str_starts_with($auto, 'PARRAIN-'), true);

$r = verifier_code($pdo, 'amis2026', 'socle');
eq('vérification ok', $r['valide'], true); eq('prix initial', $r['prix_initial'], 19.99); eq('prix remisé', $r['prix_remise'], 15.99); eq('pas gratuit', $r['gratuit'], false);
eq('offre agent', verifier_code($pdo, 'AMIS2026', 'agent')['prix_remise'], 79.2);
eq('offre cadrage', verifier_code($pdo, 'AMIS2026', 'cadrage')['prix_remise'], 159.2);
eq('sur mesure sans code', verifier_code($pdo, 'AMIS2026', 'surmesure'), ['valide' => false]);
eq('code inconnu', verifier_code($pdo, 'INCONNU1', 'socle'), ['valide' => false]);
eq('code mal formé', verifier_code($pdo, "x'--", 'socle'), ['valide' => false]);
creer_code($pdo, 'GRATUIT100', '100', 0, ''); $g = verifier_code($pdo, 'GRATUIT100', 'socle'); eq('100 % : gratuit', $g['gratuit'], true); eq('100 % : prix 0', $g['prix_remise'], 0.0);
creer_code($pdo, 'ZERO0', '0', 0, ''); eq('0 % : prix inchangé', verifier_code($pdo, 'ZERO0', 'socle')['prix_remise'], 19.99);

// désactivation, épuisement
$pdo->exec("UPDATE codes_remise SET actif = 0 WHERE code = 'AMIS2026'");
eq('désactivé : refusé', verifier_code($pdo, 'AMIS2026', 'socle'), ['valide' => false]); eq('désactivé : pas utilisable', utiliser_code($pdo, 'AMIS2026', 'socle'), false);
$pdo->exec("UPDATE codes_remise SET actif = 1 WHERE code = 'AMIS2026'");
creer_code($pdo, 'DEUXFOIS', '10', 2, '');
eq('1re utilisation', utiliser_code($pdo, 'DEUXFOIS', 'socle'), true); eq('2e utilisation', utiliser_code($pdo, 'DEUXFOIS', 'agent'), true);
eq('3e refusée', utiliser_code($pdo, 'DEUXFOIS', 'socle'), false); eq('épuisé : vérification refusée', verifier_code($pdo, 'DEUXFOIS', 'socle'), ['valide' => false]);
eq('compteur', (int) trouver_code($pdo, 'DEUXFOIS')['utilisations'], 2);
eq('utilisation illimitée', utiliser_code($pdo, 'AMIS2026', 'socle') && utiliser_code($pdo, 'AMIS2026', 'socle'), true);
eq('utilisation hors offre remisable', utiliser_code($pdo, 'AMIS2026', 'surmesure'), false);
eq('journal des utilisations', (int) $pdo->query("SELECT COUNT(*) FROM journal WHERE type = 'code parrain'")->fetchColumn(), 4);
eq('aucune donnée personnelle dans le journal', (int) $pdo->query("SELECT COUNT(*) FROM journal WHERE type = 'code parrain' AND detail LIKE '%@%'")->fetchColumn(), 0);

// limitation du débit
foreach (glob(sys_get_temp_dir() . '/reine-codes-rl-*') ?: [] as $f) { @unlink($f); }
$passes = 0; for ($i = 0; $i < LIMITE_VERIFICATIONS + 5; $i++) { if (verifications_autorisees('7.7.7.7')) { $passes++; } }
eq('limite de débit', $passes, LIMITE_VERIFICATIONS); eq('autre visiteur libre', verifications_autorisees('6.6.6.6'), true);
foreach (glob(sys_get_temp_dir() . '/reine-codes-rl-*') ?: [] as $f) { @unlink($f); }
echo "TOTAL remises -> OK: $ok, ECHECS: $ko\n";
