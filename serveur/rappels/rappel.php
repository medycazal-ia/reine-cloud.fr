<?php
// Rappels automatiques avant l'échéance de l'hébergement.
// À placer HORS de public_html, par exemple dans le dossier « rappels » du dossier
// principal du compte, et à lancer toutes les 10 minutes par une tâche Cron cPanel :
//   /usr/local/bin/php /home/COMPTE/rappels/rappel.php
// Essai immédiat (envoie un e-mail de test, sans rien enregistrer) :
//   /usr/local/bin/php /home/COMPTE/rappels/rappel.php test
// Aucun secret ici : la base utilise le fichier config-reine-cloud.php du compte.

date_default_timezone_set('Europe/Paris');

// ---- Réglages -------------------------------------------------------------
const OBJET_RAPPEL   = 'Hébergement reine-cloud.fr';
const ECHEANCE       = '2026-10-27 00:00:00';   // à changer après chaque renouvellement
const DESTINATAIRE   = 'cazal@medy.site';
const EXPEDITEUR     = 'contact@reine-cloud.fr';
const LIEN_RENOUVELLEMENT = 'https://panel.lws.fr';
// Délais avant l'échéance, du plus lointain au plus proche (en secondes).
const DELAIS = [
    '1 mois'    => 30 * 86400,
    '15 jours'  => 15 * 86400,
    '1 semaine' => 7 * 86400,
    '2 jours'   => 2 * 86400,
    '1 jour'    => 86400,
    '6 heures'  => 6 * 3600,
    '1 heure'   => 3600,
];
// ---------------------------------------------------------------------------

/**
 * Renvoie les libellés de délais déjà atteints à l'instant $maintenant
 * (timestamp) pour l'échéance $echeance (timestamp).
 */
function delais_atteints(int $maintenant, int $echeance): array
{
    $atteints = [];
    foreach (DELAIS as $libelle => $secondes) {
        if ($maintenant >= $echeance - $secondes) {
            $atteints[] = $libelle;
        }
    }
    return $atteints;
}

/** Texte lisible du temps restant. */
function temps_restant(int $maintenant, int $echeance): string
{
    $reste = $echeance - $maintenant;
    if ($reste <= 0) {
        return "l'échéance est dépassée";
    }
    $j = intdiv($reste, 86400);
    $h = intdiv($reste % 86400, 3600);
    $m = intdiv($reste % 3600, 60);
    return $j > 0 ? "$j jour(s) et $h heure(s)" : ($h > 0 ? "$h heure(s) et $m minute(s)" : "$m minute(s)");
}

function envoyer(string $sujet, string $corps): bool
{
    $en_tetes = [
        'From: reine-cloud.fr <' . EXPEDITEUR . '>',
        'Content-Type: text/plain; charset=UTF-8',
    ];
    return mail(DESTINATAIRE, mb_encode_mimeheader($sujet, 'UTF-8'), $corps, implode("\r\n", $en_tetes), '-f' . EXPEDITEUR);
}

function message(int $maintenant, int $echeance): string
{
    return "Rappel : l'hébergement de reine-cloud.fr arrive à échéance le "
        . date('d/m/Y à H:i', $echeance) . " (" . temps_restant($maintenant, $echeance) . ").\n\n"
        . "À faire : renouveler l'hébergement dans votre espace LWS.\n"
        . LIEN_RENOUVELLEMENT . "\n\n"
        . "Après le renouvellement, ouvrez le fichier rappel.php du dossier « rappels » et changez la date ECHEANCE.\n";
}

// Sortie sans exécution quand le fichier est simplement inclus (tests).
if (PHP_SAPI !== 'cli' || !isset($_SERVER['argv'][0]) || realpath($_SERVER['argv'][0]) !== realpath(__FILE__)) {
    return;
}

$maintenant = time();
$echeance   = strtotime(ECHEANCE);

if (($argv[1] ?? '') === 'test') {
    $ok = envoyer('[TEST] Rappel ' . OBJET_RAPPEL, "Ceci est un essai.\n\n" . message($maintenant, $echeance));
    echo $ok ? "E-mail de test envoyé.\n" : "Échec de l'envoi.\n";
    exit($ok ? 0 : 1);
}

if ($maintenant > $echeance + 7 * 86400) {
    exit; // échéance très ancienne : plus rien à rappeler (penser à changer la date)
}

$atteints = delais_atteints($maintenant, $echeance);
if (!$atteints) {
    exit;
}

$config = dirname(__DIR__) . '/config-reine-cloud.php';
if (!is_file($config)) {
    fwrite(STDERR, "Fichier de réglages introuvable.\n");
    exit(1);
}
$c = require $config;
$pdo = new PDO(
    'mysql:host=' . $c['hote'] . ';dbname=' . $c['base'] . ';charset=utf8mb4',
    $c['user'],
    $c['mdp'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$pdo->exec('CREATE TABLE IF NOT EXISTS rappels (
    cle VARCHAR(80) NOT NULL PRIMARY KEY,
    envoye_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

$cle = fn(string $libelle): string => ECHEANCE . '|' . $libelle;
$deja = $pdo->query('SELECT cle FROM rappels')->fetchAll(PDO::FETCH_COLUMN);

// On n'envoie qu'un seul e-mail : celui du délai le plus récent atteint.
// Les délais plus anciens sont marqués comme traités (ex. « 1 mois » déjà passé).
$dernier = end($atteints);
$a_envoyer = !in_array($cle($dernier), $deja, true);

$marque = $pdo->prepare('INSERT IGNORE INTO rappels (cle) VALUES (?)');
if ($a_envoyer) {
    $ok = envoyer('Rappel (' . $dernier . ') : ' . OBJET_RAPPEL, message($maintenant, $echeance));
    if (!$ok) {
        fwrite(STDERR, "Échec de l'envoi, nouvel essai au prochain passage.\n");
        exit(1);
    }
}
foreach ($atteints as $libelle) {
    $marque->execute([$cle($libelle)]);
}
