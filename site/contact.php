<?php
// Formulaire de contact de reine-cloud.fr
// Envoie le message à l'adresse de réception ci-dessous. Aucun mot de passe ici.
// L'adresse d'expédition doit exister dans cPanel (Comptes de messagerie).

const DESTINATAIRE = 'cazal@medy.site';
const EXPEDITEUR   = 'contact@reine-cloud.fr';

function retour(string $etat): void
{
    header('Location: /?contact=' . $etat . '#contact', true, 303);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    retour('non');
}

// Piège à robots : ce champ est invisible pour les humains.
if (!empty($_POST['site_web'])) {
    retour('ok'); // on fait semblant, sans rien envoyer
}

// Nettoyage : pas de retour à la ligne dans les champs d'en-tête.
$nom     = trim(preg_replace('/[\r\n]+/', ' ', (string)($_POST['nom'] ?? '')));
$email   = trim((string)($_POST['email'] ?? ''));
$sujet   = trim(preg_replace('/[\r\n]+/', ' ', (string)($_POST['sujet'] ?? '')));
$message = trim((string)($_POST['message'] ?? ''));

$sujets_ok = ['Hébergement / site web', 'E-mails professionnels', 'Agent IA', 'Projet santé / données sensibles', 'Autre'];
if (!in_array($sujet, $sujets_ok, true)) {
    $sujet = 'Autre';
}

if (
    $nom === '' || mb_strlen($nom) > 100 ||
    !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 200 ||
    $message === '' || mb_strlen($message) > 5000 ||
    empty($_POST['accord'])
) {
    retour('erreur');
}

$corps  = "Nouveau message depuis reine-cloud.fr\n\n";
$corps .= "Nom     : $nom\n";
$corps .= "E-mail  : $email\n";
$corps .= "Sujet   : $sujet\n\n";
$corps .= "Message :\n$message\n";

$en_tetes = [
    'From: reine-cloud.fr <' . EXPEDITEUR . '>',
    'Reply-To: ' . $email,
    'Content-Type: text/plain; charset=UTF-8',
    'X-Mailer: reine-cloud.fr',
];

$objet = mb_encode_mimeheader('[reine-cloud.fr] ' . $sujet, 'UTF-8');

// Copie dans la base de données, si elle est configurée (le fichier de réglages
// se trouve hors de public_html). En cas de problème, l'e-mail part quand même.
$config = dirname(__DIR__) . '/config-reine-cloud.php';
if (is_file($config)) {
    try {
        $c = require $config;
        $pdo = new PDO(
            'mysql:host=' . $c['hote'] . ';dbname=' . $c['base'] . ';charset=utf8mb4',
            $c['user'],
            $c['mdp'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $pdo->prepare('INSERT INTO demandes (nom, email, sujet, message) VALUES (?, ?, ?, ?)')
            ->execute([$nom, $email, $sujet, $message]);
    } catch (Throwable $e) {
        // volontairement silencieux : ne jamais afficher d'erreur technique au visiteur
    }
}

$envoye = mail(DESTINATAIRE, $objet, $corps, implode("\r\n", $en_tetes), '-f' . EXPEDITEUR);

retour($envoye ? 'ok' : 'echec');
