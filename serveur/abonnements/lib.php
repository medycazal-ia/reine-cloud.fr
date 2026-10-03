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
        mode VARCHAR(10) NOT NULL DEFAULT 'auto',
        lien_perso VARCHAR(500) NOT NULL DEFAULT '',
        cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    )$fin");
    // Mise à niveau d'une base créée par une version précédente (sans effet si les colonnes existent).
    foreach (["ALTER TABLE abonnes ADD COLUMN mode VARCHAR(10) NOT NULL DEFAULT 'auto'",
              "ALTER TABLE abonnes ADD COLUMN lien_perso VARCHAR(500) NOT NULL DEFAULT ''"] as $sql) {
        try {
            $pdo->exec($sql);
        } catch (Throwable $e) {
        }
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS clients (
        id $id,
        nom VARCHAR(120) NOT NULL,
        entreprise VARCHAR(160) NOT NULL DEFAULT '',
        email VARCHAR(200) NOT NULL DEFAULT '',
        telephone VARCHAR(40) NOT NULL DEFAULT '',
        adresse VARCHAR(300) NOT NULL DEFAULT '',
        statut VARCHAR(12) NOT NULL DEFAULT 'prospect',
        notes TEXT,
        cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    )$fin");
    $pdo->exec("CREATE TABLE IF NOT EXISTS demandes (
        id $id,
        cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        nom VARCHAR(100) NOT NULL,
        email VARCHAR(200) NOT NULL,
        sujet VARCHAR(100) NOT NULL,
        message TEXT NOT NULL,
        statut VARCHAR(12) NOT NULL DEFAULT 'nouvelle'
    )$fin");
    $pdo->exec("CREATE TABLE IF NOT EXISTS factures (
        id $id,
        numero VARCHAR(30) NOT NULL UNIQUE,
        date_facture DATE NOT NULL,
        client_id INT NULL,
        nom VARCHAR(160) NOT NULL,
        email VARCHAR(200) NOT NULL DEFAULT '',
        adresse VARCHAR(300) NOT NULL DEFAULT '',
        objet VARCHAR(250) NOT NULL,
        montant DECIMAL(10,2) NOT NULL,
        statut VARCHAR(10) NOT NULL DEFAULT 'emise',
        date_paiement DATE NULL,
        mode_paiement VARCHAR(40) NOT NULL DEFAULT '',
        jeton VARCHAR(40) NOT NULL,
        jeton_recu VARCHAR(40) NOT NULL DEFAULT '',
        abonne_id INT NULL,
        cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    )$fin");
    $pdo->exec("CREATE TABLE IF NOT EXISTS parametres (
        cle VARCHAR(60) NOT NULL PRIMARY KEY,
        valeur TEXT
    )$fin");
    $pdo->exec("CREATE TABLE IF NOT EXISTS codes_remise (
        id $id,
        code VARCHAR(32) NOT NULL UNIQUE,
        pourcentage DECIMAL(5,2) NOT NULL,
        actif TINYINT NOT NULL DEFAULT 1,
        max_utilisations INT NOT NULL DEFAULT 0,
        utilisations INT NOT NULL DEFAULT 0,
        note VARCHAR(200) NOT NULL DEFAULT '',
        cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    )$fin");
    foreach (['ALTER TABLE abonnes ADD COLUMN client_id INT NULL',
              "ALTER TABLE factures ADD COLUMN jeton_recu VARCHAR(40) NOT NULL DEFAULT ''"] as $sql) {
        try {
            $pdo->exec($sql);
        } catch (Throwable $e) {
        }
    }
    // Rattrapage : un jeton de reçu pour chaque facture qui n'en a pas encore.
    foreach ($pdo->query("SELECT id FROM factures WHERE jeton_recu = ''")->fetchAll(PDO::FETCH_COLUMN) as $fid) {
        $pdo->prepare('UPDATE factures SET jeton_recu = ? WHERE id = ?')->execute([bin2hex(random_bytes(16)), $fid]);
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS journal (
        id $id,
        le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        type VARCHAR(30) NOT NULL,
        destinataire VARCHAR(200) NOT NULL,
        detail VARCHAR(500) NOT NULL
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

function lien_valide(string $u): bool
{
    return (bool) preg_match('~^https://[^\s"\'<>]+$~', $u);
}

/**
 * Lien de paiement à utiliser pour un abonné :
 * 1. le lien personnel de l'abonné, s'il en a un ;
 * 2. le lien fixe du socle, si le montant est bien celui du socle ;
 * 3. sinon le lien à montant libre (le montant est alors indiqué dans l'e-mail).
 */
function lien_pour(array $abonne, array $cfg): array
{
    if (!empty($abonne['lien_perso']) && lien_valide((string) $abonne['lien_perso'])) {
        return [$abonne['lien_perso'], false];
    }
    $liens = $cfg['liens'] ?? [];
    $socle = param_lien('socle', $liens);
    $montant_socle = (float) ($cfg['montant_socle'] ?? 19.99);
    if ($abonne['offre'] === 'socle' && $socle !== '' && abs((float) $abonne['montant'] - $montant_socle) < 0.005) {
        return [$socle, false];
    }
    return [param_lien('libre', $liens), true];
}

function journaliser(PDO $pdo, string $type, string $destinataire, string $detail): void
{
    $pdo->prepare('INSERT INTO journal (type, destinataire, detail) VALUES (?, ?, ?)')
        ->execute([$type, mb_substr($destinataire, 0, 200), mb_substr($detail, 0, 500)]);
}

/** E-mail d'une demande de paiement ponctuelle (hors abonnement) : aucun nom, une référence. */
function mail_ponctuel(string $motif, float $montant, string $lien, bool $libre, string $reference): array
{
    $m = euros($montant);
    $corps = "Bonjour,\n\nVoici votre demande de paiement (référence $reference) : $motif — $m.\n\n"
        . "Pour régler en ligne, en toute sécurité : $lien\n"
        . ($libre ? "Sur cette page, saisissez exactement le montant : $m.\n" : '')
        . "Ce lien reste valable : vous pouvez payer dès maintenant.\n\n"
        . "Après paiement, un reçu de paiement (sans nom, avec la seule référence de la commande) vous est envoyé. Votre facture nominative y est accessible grâce à un lien et un QR code que vous récupérez vous-même.\n"
        . "Pour toute question, répondez à cet e-mail en rappelant la référence $reference.\n"
        . "Conditions : https://reine-cloud.fr/cgv.html\n\nCordialement,\nLa Maison du CREL\nreine-cloud.fr\n";
    return ["Demande de paiement — référence $reference", $corps];
}

function reference_ponctuelle(): string
{
    return 'PP-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
}

function euros($m): string
{
    return number_format((float) $m, 2, ',', ' ') . ' €';
}

function date_fr(string $d): string
{
    return (new DateTimeImmutable($d))->format('d/m/Y');
}

function reference_abonne(array $a): string
{
    return 'AB-' . sprintf('%04d', (int) ($a['id'] ?? 0));
}

/**
 * E-mail d'échéance. Volontairement SANS nom ni intitulé libre : uniquement la référence
 * de l'abonnement, le nom de l'offre, le montant et la date.
 */
function construire_mail(array $a, string $etape, string $lien, bool $libre): array
{
    $ref = reference_abonne($a);
    $offre = OFFRES[$a['offre'] ?? ''] ?? 'Abonnement';
    $montant = euros($a['montant']);
    $echeance = date_fr($a['prochaine_echeance']);
    $bloc_lien = $lien !== ''
        ? "Pour régler en ligne, en toute sécurité : $lien\n"
          . ($libre ? "Sur cette page, saisissez exactement le montant : $montant.\n" : '')
          . "Ce lien reste valable : vous pouvez payer dès maintenant.\n"
        : "Pour régler, répondez à ce message en rappelant la référence $ref.\n";
    $debut = "Bonjour,\n\n";
    $fin = "\nAprès paiement, un reçu de paiement (sans nom, avec la seule référence de la commande) vous est envoyé. Votre facture nominative y est accessible grâce à un lien et un QR code que vous récupérez vous-même.\n"
         . "Pour toute question ou pour résilier, répondez à cet e-mail en rappelant la référence $ref.\n"
         . "Conditions : https://reine-cloud.fr/cgv.html\n\nCordialement,\nLa Maison du CREL\nreine-cloud.fr\n";
    switch ($etape) {
        case 'J-1':
            $sujet = "Rappel : échéance demain — abonnement $ref";
            $corps = $debut . "Rappel : l'abonnement $ref ($offre, $montant par mois) arrive à échéance demain, le $echeance.\n\n" . $bloc_lien . $fin;
            break;
        case 'J+3':
            $sujet = "Échéance dépassée — abonnement $ref";
            $corps = $debut . "Nous n'avons pas encore reçu le règlement de l'abonnement $ref ($offre, $montant), échu le $echeance.\n\n" . $bloc_lien
                   . "\nSans règlement, le service pourra être suspendu 15 jours après relance (conditions générales de vente, article 6). Si vous avez déjà payé, merci d'ignorer ce message.\n" . $fin;
            break;
        default:
            $sujet = "Votre échéance du $echeance — abonnement $ref";
            $corps = $debut . "L'abonnement $ref ($offre, $montant par mois) arrive à échéance le $echeance.\n\n" . $bloc_lien . $fin;
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
    $abonnes = $pdo->query("SELECT * FROM abonnes WHERE actif = 1 AND mode = 'auto' ORDER BY prochaine_echeance")->fetchAll(PDO::FETCH_ASSOC);
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

function marquer_paye(PDO $pdo, int $id, bool $envoyer_recu = false, string $mode = 'Paiement en ligne'): ?int
{
    $a = $pdo->prepare('SELECT * FROM abonnes WHERE id = ?');
    $a->execute([$id]);
    $a = $a->fetch(PDO::FETCH_ASSOC);
    if (!$a) {
        return null;
    }
    $pdo->prepare('INSERT INTO paiements (abonne_id, echeance, montant) VALUES (?, ?, ?)')
        ->execute([$id, $a['prochaine_echeance'], $a['montant']]);
    $client = !empty($a['client_id']) ? client_par_id($pdo, (int) $a['client_id']) : null;
    $facture_id = creer_facture($pdo, [
        'client_id' => $client['id'] ?? null,
        'nom' => $client ? ($client['entreprise'] ?: $client['nom']) : $a['nom'],
        'email' => $client['email'] ?? $a['email'],
        'adresse' => $client['adresse'] ?? '',
        'objet' => "Abonnement « {$a['libelle']} » — échéance du " . date_fr($a['prochaine_echeance']),
        'montant' => (float) $a['montant'],
        'statut' => 'payee',
        'date_paiement' => date('Y-m-d'),
        'mode_paiement' => $mode,
        'abonne_id' => $id,
    ]);
    $pdo->prepare('UPDATE abonnes SET prochaine_echeance = ? WHERE id = ?')
        ->execute([mois_suivant($a['prochaine_echeance'], (int) $a['jour']), $id]);
    if ($envoyer_recu) {
        envoyer_recu($pdo, $facture_id);
    }
    return $facture_id;
}

require_once __DIR__ . '/lib_gestion.php';
