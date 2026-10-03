<?php
// Codes parrain : pourcentages de remise (0 à 100 %) gérés depuis l'administration, appliqués au montant de la commande.
// Aucune donnée personnelle ici : un code, un pourcentage, un compteur.
declare(strict_types=1);

// Prix de départ des offres remisables (mêmes montants que sur le site). Le sur mesure se chiffre sur devis : pas de code.
const TARIFS_REMISE = ['socle' => 19.99, 'agent' => 99.00, 'cadrage' => 199.00];
const ALPHABET_CODE = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';   // sans 0/O/1/I pour éviter les confusions
const LIMITE_VERIFICATIONS = 20;                            // par visiteur et par fenêtre
const FENETRE_VERIFICATIONS = 600;                          // secondes

/** Code saisi -> forme normalisée (majuscules, chiffres, tirets, 4 à 32 caractères) ou null. */
function code_normalise(string $saisie): ?string
{
    $c = strtoupper(trim($saisie));
    return preg_match('/^[A-Z0-9][A-Z0-9-]{2,30}[A-Z0-9]$/', $c) ? $c : null;
}

/** Pourcentage saisi (virgule acceptée) entre 0 et 100, arrondi au centième, sinon null. */
function pourcentage_valide($v): ?float
{
    $s = str_replace([',', ' '], ['.', ''], (string) $v);
    if (!is_numeric($s)) {
        return null;
    }
    $p = round((float) $s, 2);
    return ($p >= 0 && $p <= 100) ? $p : null;
}

function prix_apres_remise(float $prix, float $pourcentage): float
{
    return max(0.0, round($prix * (100 - $pourcentage) / 100, 2));
}

function generer_code(): string
{
    $s = '';
    for ($i = 0; $i < 6; $i++) {
        $s .= ALPHABET_CODE[random_int(0, strlen(ALPHABET_CODE) - 1)];
    }
    return 'PARRAIN-' . $s;
}

/** @return array{0:bool,1:string,2:string} succès, message, code créé */
function creer_code(PDO $pdo, string $saisie, $pourcentage, int $max, string $note): array
{
    $p = pourcentage_valide($pourcentage);
    if ($p === null) {
        return [false, 'Pourcentage : un nombre de 0 à 100.', ''];
    }
    $code = trim($saisie) === '' ? generer_code() : code_normalise($saisie);
    if ($code === null) {
        return [false, 'Code : 4 à 32 caractères, lettres, chiffres et tirets.', ''];
    }
    if ($max < 0 || $max > 100000) {
        return [false, 'Nombre maximal d\'utilisations invalide (0 = illimité).', ''];
    }
    $existe = $pdo->prepare('SELECT COUNT(*) FROM codes_remise WHERE code = ?');
    $existe->execute([$code]);
    if ((int) $existe->fetchColumn() > 0) {
        return [false, 'Ce code existe déjà.', ''];
    }
    $pdo->prepare('INSERT INTO codes_remise (code, pourcentage, max_utilisations, note) VALUES (?, ?, ?, ?)')
        ->execute([$code, $p, $max, mb_substr(trim($note), 0, 200)]);
    return [true, 'Code ' . $code . ' créé (' . rtrim(rtrim(number_format($p, 2, ',', ''), '0'), ',') . ' %).', $code];
}

function trouver_code(PDO $pdo, string $code): ?array
{
    $q = $pdo->prepare('SELECT * FROM codes_remise WHERE code = ?');
    $q->execute([$code]);
    $r = $q->fetch(PDO::FETCH_ASSOC);
    return $r ?: null;
}

function code_utilisable(array $r): bool
{
    return (int) $r['actif'] === 1 && ((int) $r['max_utilisations'] === 0 || (int) $r['utilisations'] < (int) $r['max_utilisations']);
}

/** Réponse publique : jamais la raison d'un refus (inexistant, désactivé, épuisé), pour ne pas aider à deviner les codes. */
function verifier_code(PDO $pdo, string $saisie, string $offre): array
{
    $code = code_normalise($saisie);
    if (!isset(TARIFS_REMISE[$offre]) || $code === null) {
        return ['valide' => false];
    }
    $r = trouver_code($pdo, $code);
    if (!$r || !code_utilisable($r)) {
        return ['valide' => false];
    }
    $p = (float) $r['pourcentage'];
    $initial = TARIFS_REMISE[$offre];
    $final = prix_apres_remise($initial, $p);
    return ['valide' => true, 'code' => $code, 'offre' => $offre, 'pourcentage' => $p, 'prix_initial' => $initial, 'prix_remise' => $final, 'gratuit' => $final <= 0];
}

/** Compte une utilisation (clic sur « Payer ») de façon atomique ; faux si le code n'est plus utilisable. */
function utiliser_code(PDO $pdo, string $saisie, string $offre): bool
{
    $code = code_normalise($saisie);
    if ($code === null || !isset(TARIFS_REMISE[$offre])) {
        return false;
    }
    $q = $pdo->prepare('UPDATE codes_remise SET utilisations = utilisations + 1 WHERE code = ? AND actif = 1 AND (max_utilisations = 0 OR utilisations < max_utilisations)');
    $q->execute([$code]);
    if ($q->rowCount() !== 1) {
        return false;
    }
    journaliser($pdo, 'code parrain', $code, 'utilisé pour : ' . $offre);
    return true;
}

/** Limite les vérifications par visiteur (empreinte du jour, dossier temporaire, aucune adresse gardée). */
function verifications_autorisees(string $ip): bool
{
    $f = sys_get_temp_dir() . '/reine-codes-rl-' . substr(hash('sha256', $ip . '|' . date('Y-m-d')), 0, 24);
    $now = time();
    $h = is_file($f) ? array_values(array_filter(array_map('intval', explode(',', (string) file_get_contents($f))), static fn($t) => $t > $now - FENETRE_VERIFICATIONS)) : [];
    if (count($h) >= LIMITE_VERIFICATIONS) {
        return false;
    }
    $h[] = $now;
    @file_put_contents($f, implode(',', $h));
    return true;
}
