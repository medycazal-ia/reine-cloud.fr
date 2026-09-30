<?php
// Fonctions de l'espace d'administration : paramètres, clients, factures, comptabilité, export.
declare(strict_types=1);

const PARAM_DEFAUTS = [
    'ent_nom' => '{{EDITEUR_NOM}}',
    'ent_commercial' => '{{NOM_COMMERCIAL}}',
    'ent_forme' => 'Entrepreneur individuel',
    'ent_siret' => '{{SIRET}}',
    'ent_adresse' => '{{ADRESSE}}',
    'ent_email' => '{{EMAIL_CONTACT}}',
    'ent_tel' => '{{TELEPHONE}}',
    'ent_iban' => '',
    'mention_tva' => 'TVA non applicable, art. 293 B du CGI.',
    'cond_paiement' => "Paiement à réception de facture. En cas de retard : pénalités égales à trois fois le taux d'intérêt légal et indemnité forfaitaire de 40 € pour frais de recouvrement (art. L.441-10 du Code de commerce).",
    'prefixe_facture' => 'F',
    'seuil_ca' => '',
    'hebergement_echeance' => '2026-10-27',
    'lien_socle' => '',
    'lien_agent' => '',
    'lien_cadrage' => '',
    'lien_libre' => '',
    'cron_dernier' => '',
];
const STATUTS_CLIENT = ['prospect' => 'Prospect', 'client' => 'Client', 'ancien' => 'Ancien client'];
const TABLES_EXPORT = ['demandes', 'clients', 'abonnes', 'factures', 'paiements', 'journal'];

// ---------- Paramètres ----------
function param(string $cle): string
{
    try {
        $q = db()->prepare('SELECT valeur FROM parametres WHERE cle = ?');
        $q->execute([$cle]);
        $v = $q->fetchColumn();
        if ($v !== false && $v !== null && $v !== '') {
            return (string) $v;
        }
    } catch (Throwable $e) {
    }
    return PARAM_DEFAUTS[$cle] ?? '';
}

function set_param(PDO $pdo, string $cle, string $valeur): void
{
    if (!array_key_exists($cle, PARAM_DEFAUTS)) {
        return;
    }
    $pdo->prepare('DELETE FROM parametres WHERE cle = ?')->execute([$cle]);
    $pdo->prepare('INSERT INTO parametres (cle, valeur) VALUES (?, ?)')->execute([$cle, $valeur]);
}

/** Lien de paiement : valeur saisie dans l'administration, sinon celle du fichier de réglages. */
function param_lien(string $cle, array $liens_config): string
{
    $v = param('lien_' . $cle);
    if ($v !== '' && lien_valide($v)) {
        return $v;
    }
    $v = (string) ($liens_config[$cle] ?? '');
    return lien_valide($v) ? $v : '';
}

// ---------- Clients ----------
function client_par_id(PDO $pdo, int $id): ?array
{
    $q = $pdo->prepare('SELECT * FROM clients WHERE id = ?');
    $q->execute([$id]);
    return $q->fetch(PDO::FETCH_ASSOC) ?: null;
}

function creer_client(PDO $pdo, array $d): int
{
    $pdo->prepare('INSERT INTO clients (nom, entreprise, email, telephone, adresse, statut, notes) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([
            mb_substr(trim((string) $d['nom']), 0, 120),
            mb_substr(trim((string) ($d['entreprise'] ?? '')), 0, 160),
            mb_substr(trim((string) ($d['email'] ?? '')), 0, 200),
            mb_substr(trim((string) ($d['telephone'] ?? '')), 0, 40),
            mb_substr(trim((string) ($d['adresse'] ?? '')), 0, 300),
            isset(STATUTS_CLIENT[$d['statut'] ?? '']) ? $d['statut'] : 'prospect',
            (string) ($d['notes'] ?? ''),
        ]);
    return (int) $pdo->lastInsertId();
}

/** Rattache à une fiche client les abonnés créés avant l'existence des clients. */
function rattacher_clients(PDO $pdo): void
{
    $orphelins = $pdo->query('SELECT * FROM abonnes WHERE client_id IS NULL')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($orphelins as $a) {
        $q = $pdo->prepare('SELECT id FROM clients WHERE email = ? AND email <> \'\' LIMIT 1');
        $q->execute([$a['email']]);
        $cid = $q->fetchColumn();
        if (!$cid) {
            $cid = creer_client($pdo, ['nom' => $a['nom'], 'email' => $a['email'], 'statut' => 'client']);
        }
        $pdo->prepare('UPDATE abonnes SET client_id = ? WHERE id = ?')->execute([$cid, $a['id']]);
    }
}

// ---------- Factures ----------
function prochain_numero(PDO $pdo, int $annee): string
{
    $prefixe = preg_replace('/[^A-Za-z0-9]/', '', param('prefixe_facture')) ?: 'F';
    $q = $pdo->prepare('SELECT numero FROM factures WHERE numero LIKE ? ORDER BY numero DESC LIMIT 1');
    $q->execute(["$prefixe-$annee-%"]);
    $dernier = $q->fetchColumn();
    $n = $dernier ? ((int) substr((string) $dernier, -4)) + 1 : 1;
    return sprintf('%s-%d-%04d', $prefixe, $annee, $n);
}

function creer_facture(PDO $pdo, array $d): int
{
    $date = $d['date_facture'] ?? date('Y-m-d');
    $statut = ($d['statut'] ?? 'emise') === 'payee' ? 'payee' : 'emise';
    for ($essai = 0; $essai < 3; $essai++) {
        $numero = prochain_numero($pdo, (int) substr($date, 0, 4));
        try {
            $pdo->prepare('INSERT INTO factures (numero, date_facture, client_id, nom, email, adresse, objet, montant, statut, date_paiement, mode_paiement, jeton, jeton_recu, abonne_id)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([
                    $numero, $date, $d['client_id'] ?? null,
                    mb_substr((string) $d['nom'], 0, 160), mb_substr((string) ($d['email'] ?? ''), 0, 200), mb_substr((string) ($d['adresse'] ?? ''), 0, 300),
                    mb_substr((string) $d['objet'], 0, 250), (float) $d['montant'], $statut,
                    $statut === 'payee' ? ($d['date_paiement'] ?? $date) : null,
                    mb_substr((string) ($d['mode_paiement'] ?? ''), 0, 40),
                    bin2hex(random_bytes(16)), bin2hex(random_bytes(16)), $d['abonne_id'] ?? null,
                ]);
            return (int) $pdo->lastInsertId();
        } catch (PDOException $e) {
            if ($essai === 2) {
                throw $e;
            }
        }
    }
    throw new RuntimeException('Création de facture impossible.');
}

function facture_par_id(PDO $pdo, int $id): ?array
{
    $q = $pdo->prepare('SELECT * FROM factures WHERE id = ?');
    $q->execute([$id]);
    return $q->fetch(PDO::FETCH_ASSOC) ?: null;
}

function facture_url(array $f): string
{
    return 'https://{{DOMAINE}}/facture.php?t=' . $f['jeton'];
}

function recu_url(array $f): string
{
    return 'https://{{DOMAINE}}/recu.php?r=' . $f['jeton_recu'];
}

/**
 * Envoie le REÇU de paiement : aucun nom, aucune facture. Seulement la référence de la commande.
 * La facture nominative est retirée par le client lui-même (lien et QR code du reçu).
 */
function envoyer_recu(PDO $pdo, int $id): bool
{
    $f = facture_par_id($pdo, $id);
    if (!$f || $f['statut'] !== 'payee' || $f['email'] === '') {
        return false;
    }
    $corps = "Bonjour,\n\nNous avons bien reçu votre paiement de " . euros($f['montant']) . " (référence de la commande : {$f['numero']}).\n\n"
        . 'Votre reçu de paiement (il ne comporte aucun nom) : ' . recu_url($f) . "\n\n"
        . "Votre facture nominative est disponible sur ce reçu, grâce à un lien et à un QR code. Récupérez-la vous-même quand vous en avez besoin, et conservez le reçu.\n\n"
        . "Cordialement,\n{{NOM_COMMERCIAL}}\n{{DOMAINE}}\n";
    $ok = envoyer_mail($f['email'], "Reçu de paiement — référence {$f['numero']}", $corps);
    if ($ok) {
        journaliser($pdo, 'reçu envoyé', $f['email'], $f['numero']);
    }
    return $ok;
}

function encaisser_facture(PDO $pdo, int $id, string $mode, string $date): void
{
    $pdo->prepare("UPDATE factures SET statut = 'payee', date_paiement = ?, mode_paiement = ? WHERE id = ? AND statut = 'emise'")
        ->execute([$date, mb_substr($mode, 0, 40), $id]);
}

function facture_html(array $f): string
{
    $e = fn(string $k) => htmlspecialchars(param($k), ENT_QUOTES, 'UTF-8');
    $h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    $acquittee = $f['statut'] === 'payee';
    $tampon = $acquittee ? '<div class="tampon">ACQUITTÉE le ' . $h(date_fr($f['date_paiement'])) . '</div>' : '';
    $iban = param('ent_iban') !== '' ? '<p><b>Règlement par virement :</b> ' . $e('ent_iban') . '</p>' : '';
    return '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="robots" content="noindex,nofollow"><title>Facture ' . $h($f['numero']) . '</title><style>'
        . 'body{font-family:system-ui,sans-serif;color:#25182F;max-width:820px;margin:0 auto;padding:28px 18px;line-height:1.5}'
        . 'h1{color:#491E65;margin:0}.haut{display:flex;justify-content:space-between;gap:20px;flex-wrap:wrap;margin-bottom:28px}'
        . '.bloc{font-size:.92rem}.bloc b{display:block;color:#491E65;margin-bottom:4px}'
        . 'table{width:100%;border-collapse:collapse;margin:22px 0}th,td{padding:10px;border-bottom:1px solid #ddd;text-align:left}th{background:#F2ECE0;font-size:.8rem;text-transform:uppercase}'
        . '.tot{text-align:right;font-size:1.25rem;font-weight:700;color:#491E65}.petit{font-size:.8rem;color:#6B6275}'
        . '.tampon{display:inline-block;border:3px solid #1E9A5C;color:#1E9A5C;font-weight:800;padding:6px 14px;transform:rotate(-4deg);margin:8px 0}'
        . '.bt{margin:0 0 18px;padding:8px 14px;background:#491E65;color:#fff;border:0;border-radius:4px;cursor:pointer}@media print{.bt{display:none}}'
        . '</style></head><body><button class="bt" onclick="window.print()">Imprimer / enregistrer en PDF</button>'
        . '<div class="haut"><div class="bloc"><h1>Facture</h1><b>' . $h($f['numero']) . '</b>Date : ' . $h(date_fr($f['date_facture'])) . '<br>' . $tampon . '</div>'
        . '<div class="bloc"><b>' . $e('ent_nom') . ' — ' . $e('ent_forme') . '</b>' . $e('ent_commercial') . '<br>' . $e('ent_adresse')
        . '<br>SIRET ' . $e('ent_siret') . '<br>' . $e('ent_email') . ' · ' . $e('ent_tel') . '</div></div>'
        . '<div class="bloc"><b>Facturé à</b>' . $h($f['nom']) . '<br>' . nl2br($h($f['adresse'])) . ($f['email'] ? '<br>' . $h($f['email']) : '') . '</div>'
        . '<table><tr><th>Désignation</th><th style="text-align:right">Montant net</th></tr><tr><td>' . $h($f['objet']) . '</td><td style="text-align:right">' . $h(euros($f['montant'])) . '</td></tr></table>'
        . '<p class="tot">Total à payer : ' . $h(euros($acquittee ? 0 : $f['montant'])) . ' <span class="petit">(total facturé ' . $h(euros($f['montant'])) . ')</span></p>'
        . '<p>' . $e('mention_tva') . '</p>' . $iban
        . '<p class="petit">' . $e('cond_paiement') . '</p>'
        . ($acquittee ? '<p class="petit">Mode de règlement : ' . $h($f['mode_paiement'] ?: 'non précisé') . '.</p>' : '')
        . '</body></html>';
}

/** Page du reçu de paiement : sans nom. Contient le lien et le QR code de la facture nominative. */
function recu_html(array $f): string
{
    $e = fn(string $k) => htmlspecialchars(param($k), ENT_QUOTES, 'UTF-8');
    $h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    $lien = facture_url($f);
    return '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="robots" content="noindex,nofollow"><title>Reçu de paiement ' . $h($f['numero']) . '</title><style>'
        . 'body{font-family:system-ui,sans-serif;color:#25182F;max-width:640px;margin:0 auto;padding:28px 18px;line-height:1.55}'
        . 'h1{color:#491E65;margin:0 0 4px}.ref{font-size:1.3rem;font-weight:700;color:#491E65}'
        . '.tampon{display:inline-block;border:3px solid #1E9A5C;color:#1E9A5C;font-weight:800;padding:6px 14px;transform:rotate(-3deg);margin:10px 0}'
        . 'table{width:100%;border-collapse:collapse;margin:16px 0}td{padding:9px 6px;border-bottom:1px solid #ddd}td:last-child{text-align:right;font-weight:600}'
        . '.facture{margin-top:26px;padding:18px;border:1px solid #D9A93A;background:#FFFBF0;text-align:center}'
        . '.facture a{word-break:break-all;color:#491E65}#qr svg{width:170px;height:170px}.petit{font-size:.8rem;color:#6B6275}'
        . '.bt{margin:0 0 16px;padding:8px 14px;background:#491E65;color:#fff;border:0;border-radius:4px;cursor:pointer}@media print{.bt{display:none}}'
        . '</style></head><body><button class="bt" onclick="window.print()">Imprimer / enregistrer en PDF</button>'
        . '<h1>Reçu de paiement</h1><div class="ref">Référence de la commande : ' . $h($f['numero']) . '</div>'
        . '<div class="tampon">PAIEMENT REÇU le ' . $h(date_fr($f['date_paiement'])) . '</div>'
        . '<table><tr><td>Montant réglé</td><td>' . $h(euros($f['montant'])) . '</td></tr>'
        . '<tr><td>Mode de règlement</td><td>' . $h($f['mode_paiement'] ?: 'non précisé') . '</td></tr>'
        . '<tr><td>Vendeur</td><td>' . $e('ent_commercial') . ' — SIRET ' . $e('ent_siret') . '</td></tr></table>'
        . '<p class="petit">' . $e('mention_tva') . ' Ce reçu ne comporte aucun nom ni aucune donnée personnelle.</p>'
        . '<div class="facture"><b>Votre facture nominative</b><p class="petit">Scannez le QR code ou ouvrez le lien pour la récupérer vous-même. '
        . 'Ce lien est personnel : conservez-le et ne le partagez qu\'avec les personnes de votre choix.</p>'
        . '<div id="qr"></div><p><a href="' . $h($lien) . '">' . $h($lien) . '</a></p></div>'
        . '<script src="/js/qrcode.js"></script><script>(function(){var q=qrcode(0,"M");q.addData(' . json_encode($lien, JSON_UNESCAPED_SLASHES) . ');q.make();document.getElementById("qr").innerHTML=q.createSvgTag(4,2);})();</script>'
        . '</body></html>';
}

// ---------- Comptabilité ----------
function recettes(PDO $pdo, int $annee): array
{
    $q = $pdo->prepare("SELECT * FROM factures WHERE statut = 'payee' AND date_paiement >= ? AND date_paiement <= ? ORDER BY date_paiement, numero");
    $q->execute(["$annee-01-01", "$annee-12-31"]);
    return $q->fetchAll(PDO::FETCH_ASSOC);
}

function totaux_par_mois(array $recettes): array
{
    $t = array_fill(1, 12, 0.0);
    foreach ($recettes as $r) {
        $t[(int) substr($r['date_paiement'], 5, 2)] += (float) $r['montant'];
    }
    return $t;
}

function csv_ligne(array $champs): string
{
    return implode(';', array_map(static fn($c) => '"' . str_replace('"', '""', (string) $c) . '"', $champs)) . "\r\n";
}

function export_csv(PDO $pdo, string $table): string
{
    if (!in_array($table, TABLES_EXPORT, true)) {
        throw new InvalidArgumentException('Table inconnue.');
    }
    $lignes = $pdo->query("SELECT * FROM $table")->fetchAll(PDO::FETCH_ASSOC);
    $out = "\xEF\xBB\xBF";
    if ($lignes) {
        $out .= csv_ligne(array_keys($lignes[0]));
        foreach ($lignes as $l) {
            $out .= csv_ligne($l);
        }
    }
    return $out;
}

function csv_recettes(array $recettes): string
{
    $out = "\xEF\xBB\xBF" . csv_ligne(['Date', 'Facture', 'Client', 'Objet', 'Mode de règlement', 'Montant (€)']);
    foreach ($recettes as $r) {
        $out .= csv_ligne([date_fr($r['date_paiement']), $r['numero'], $r['nom'], $r['objet'], $r['mode_paiement'], number_format((float) $r['montant'], 2, ',', '')]);
    }
    return $out;
}

// ---------- Site : liens de paiement publiés dans paiement.js ----------
function ecrire_paiement_js(string $chemin, array $liens): bool
{
    $cles = ['socle', 'agent', 'cadrage', 'libre'];
    $corps = "// Généré par l'administration (onglet Paiements). Ne contient aucun secret : ces adresses sont publiques.\nwindow.LIENS_PAIEMENT = {\n";
    foreach ($cles as $i => $k) {
        $v = (string) ($liens[$k] ?? '');
        if ($v !== '' && !lien_valide($v)) {
            return false;
        }
        $corps .= '  ' . $k . ': ' . json_encode($v, JSON_UNESCAPED_SLASHES) . ($i < count($cles) - 1 ? ',' : '') . "\n";
    }
    $corps .= "};\n";
    $tmp = $chemin . '.tmp';
    if (file_put_contents($tmp, $corps) === false) {
        return false;
    }
    return rename($tmp, $chemin);
}
