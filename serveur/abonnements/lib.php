<?php
// Gestion des abonnements de reine-cloud.fr : échéances, liens de paiement, rappels.
// Aucun secret ici : les réglages sont dans config-reine-cloud.php, hors de public_html.
declare(strict_types=1);

date_default_timezone_set('Europe/Paris');

const EXPEDITEUR = 'contact@reine-cloud.fr';
const COPIE      = 'cazal@medy.site';
// Étapes de rappel, en jours par rapport à l'échéance (négatif = avant).
const ETAPES = ['J-7' => -7, 'J-1' => -1, 'J+3' => 3];
const OFFRES = ['socle' => 'Le socle', 'agent' => "L'agent standard", 'autre' => 'Sur mesure / autre'];

function config(): array
{
    static $c = null;
    if ($c === null) {
        $fichier = dirname(__DIR__) . '/config-reine-cloud.php';
        if (!is_file($fichier)) {
            throw new RuntimeException('Fichier de réglages introuvable.');
        }
        $c = require $fichier;
    }
    return $c;
}

function db(?PDO $force = null): PDO
{
    static $pdo = null;
    if ($force !== null) {
        $pdo = $force;
        creer_tables($pdo);
    }
    if ($pdo === null) {
        $c = config();
        $pdo = isset($c['dsn'])   // réservé aux essais en local
            ? new PDO($c['dsn'], null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION])
            : new PDO(
                'mysql:host=' . $c['hote'] . ';dbname=' . $c['base'] . ';charset=utf8mb4',
                $c['user'],
                $c['mdp'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        creer_tables($pdo);
    }
    return $pdo;
}

function creer_tables(PDO $pdo): void
{
    $sqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    $id = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
    $fin = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    $pdo->exec("CREATE TABLE IF NOT EXISTS abonnes (
        id $id,
        nom VARCHAR(120) NOT NULL,
        email VARCHAR(200) NOT NULL,
        offre VARCHAR(20) NOT NULL,
        libelle VARCHAR(120) NOT NULL,
        montant DECIMAL(8,2) NOT NULL,
        jour INT NOT NULL,
        prochaine_echeance DATE NOT NULL,
        actif INT NOT NULL DEFAULT 1,
        cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    )$fin");
    $pdo->exec("CREATE TABLE IF NOT EXISTS envois (
        abonne_id INT NOT NULL,
        echeance DATE NOT NULL,
        etape VARCHAR(8) NOT NULL,
        envoye_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (abonne_id, echeance, etape)
    )$fin");
    $pdo->exec("CREATE TABLE IF NOT EXISTS paiements (
        id $id,
        abonne_id INT NOT NULL,
        echeance DATE NOT NULL,
        montant DECIMAL(8,2) NOT NULL,
        recu_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    )$fin");
}

/** Même jour du mois suivant (ramené à la fin du mois si besoin : 31 janvier -> 28 février). */
function mois_suivant(string $date, int $jour): string
{
    $d = new DateTimeImmutable($date);
    $premier = $d->modify('first day of next month');
    $max = (int) $premier->format('t');
    return $premier->setDate((int) $premier->format('Y'), (int) $premier->format('n'), min($jour, $max))->format('Y-m-d');
}

/**
 * Décide quoi envoyer pour une échéance.
 * Renvoie [étape à envoyer ou null, étapes à marquer comme traitées].
 * Un seul e-mail par passage : celui de l'étape la plus récente atteinte.
 */
function etapes_dues(string $aujourdhui, string $echeance, array $deja): array
{
    $diff = (int) (new DateTimeImmutable($echeance))->diff(new DateTimeImmutable($aujourdhui))->format('%r%a');
    if ($diff > 30) {
        return [null, []]; // trop en retard : plus de rappel automatique
    }
    $atteintes = [];
    foreach (ETAPES as $nom => $decalage) {
        if ($diff >= $decalage) {
            $atteintes[] = $nom;
        }
    }
    if (!$atteintes) {
        return [null, []];
    }
    $derniere = end($atteintes);
    return [in_array($derniere, $deja, true) ? null : $derniere, $atteintes];
}

/** Lien de paiement à utiliser : lien fixe du socle, sinon lien à montant libre. */
function lien_pour(array $abonne, array $cfg): array
{
    $liens = $cfg['liens'] ?? [];
    if ($abonne['offre'] === 'socle' && !empty($liens['socle'])) {
        return [$liens['socle'], false];
    }
    return [$liens['libre'] ?? '', true];
}

function euros($m): string
{
    return number_format((float) $m, 2, ',', ' ') . ' €';
}

function date_fr(string $d): string
{
    return (new DateTimeImmutable($d))->format('d/m/Y');
}

function construire_mail(array $a, string $etape, string $lien, bool $libre): array
{
    $montant = euros($a['montant']);
    $echeance = date_fr($a['prochaine_echeance']);
    $bloc_lien = $lien !== ''
        ? "Pour régler en ligne, en toute sécurité : $lien\n"
          . ($libre ? "Sur cette page, saisissez exactement le montant : $montant.\n" : '')
          . "Ce lien reste valable : vous pouvez payer dès maintenant.\n"
        : "Pour régler, répondez à ce message : nous vous indiquons le moyen de paiement.\n";
    $debut = "Bonjour {$a['nom']},\n\n";
    $fin = "\nUne facture acquittée vous est adressée après paiement. Pour toute question ou pour résilier, répondez simplement à cet e-mail.\n"
         . "Conditions : https://reine-cloud.fr/cgv.html\n\nCordialement,\nMedy Harry CAZAL — La Maison du CREL\nreine-cloud.fr · +33 6 74 20 16 62\n";
    switch ($etape) {
        case 'J-1':
            $sujet = "Rappel : échéance demain — {$a['libelle']}";
            $corps = $debut . "Rappel : votre abonnement « {$a['libelle']} » ($montant par mois) arrive à échéance demain, le $echeance.\n\n" . $bloc_lien . $fin;
            break;
        case 'J+3':
            $sujet = "Échéance dépassée — {$a['libelle']}";
            $corps = $debut . "Nous n'avons pas encore reçu le règlement de votre abonnement « {$a['libelle']} » ($montant), échu le $echeance.\n\n" . $bloc_lien
                   . "\nSans règlement, le service pourra être suspendu 15 jours après relance (conditions générales de vente, article 6). Si vous avez déjà payé, merci d'ignorer ce message.\n" . $fin;
            break;
        default:
            $sujet = "Votre échéance du $echeance — {$a['libelle']}";
            $corps = $debut . "Votre abonnement « {$a['libelle']} » ($montant par mois) arrive à échéance le $echeance.\n\n" . $bloc_lien . $fin;
    }
    return [$sujet, $corps];
}

function envoyer_mail(string $a, string $sujet, string $corps): bool
{
    if (isset($GLOBALS['MAILER']) && is_callable($GLOBALS['MAILER'])) {
        return (bool) $GLOBALS['MAILER']($a, $sujet, $corps);
    }
    $en_tetes = [
        'From: reine-cloud.fr <' . EXPEDITEUR . '>',
        'Reply-To: ' . COPIE,
        'Bcc: ' . COPIE,
        'Content-Type: text/plain; charset=UTF-8',
    ];
    return mail($a, mb_encode_mimeheader($sujet, 'UTF-8'), $corps, implode("\r\n", $en_tetes), '-f' . EXPEDITEUR);
}

/** Passage quotidien : envoie les rappels dus. Renvoie la liste des actions faites. */
function traiter_rappels(PDO $pdo, string $aujourdhui, array $cfg): array
{
    $actions = [];
    $abonnes = $pdo->query('SELECT * FROM abonnes WHERE actif = 1 ORDER BY prochaine_echeance')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($abonnes as $a) {
        $q = $pdo->prepare('SELECT etape FROM envois WHERE abonne_id = ? AND echeance = ?');
        $q->execute([$a['id'], $a['prochaine_echeance']]);
        $deja = $q->fetchAll(PDO::FETCH_COLUMN);
        [$etape, $atteintes] = etapes_dues($aujourdhui, $a['prochaine_echeance'], $deja);
        if ($etape === null) {
            continue;
        }
        [$lien, $libre] = lien_pour($a, $cfg);
        [$sujet, $corps] = construire_mail($a, $etape, $lien, $libre);
        if (!envoyer_mail($a['email'], $sujet, $corps)) {
            $actions[] = "ÉCHEC d'envoi ($etape) pour {$a['nom']}";
            continue; // nouvel essai au prochain passage
        }
        $ins = $pdo->prepare('INSERT INTO envois (abonne_id, echeance, etape) VALUES (?, ?, ?)');
        foreach ($atteintes as $e) {
            if (!in_array($e, $deja, true)) {
                $ins->execute([$a['id'], $a['prochaine_echeance'], $e]);
            }
        }
        $actions[] = "Envoyé $etape à {$a['nom']} (échéance {$a['prochaine_echeance']})";
    }
    return $actions;
}

function marquer_paye(PDO $pdo, int $id): void
{
    $a = $pdo->prepare('SELECT * FROM abonnes WHERE id = ?');
    $a->execute([$id]);
    $a = $a->fetch(PDO::FETCH_ASSOC);
    if (!$a) {
        return;
    }
    $pdo->prepare('INSERT INTO paiements (abonne_id, echeance, montant) VALUES (?, ?, ?)')
        ->execute([$id, $a['prochaine_echeance'], $a['montant']]);
    $pdo->prepare('UPDATE abonnes SET prochaine_echeance = ? WHERE id = ?')
        ->execute([mois_suivant($a['prochaine_echeance'], (int) $a['jour']), $id]);
}
