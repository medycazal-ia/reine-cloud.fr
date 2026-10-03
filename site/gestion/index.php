<?php
// Espace d'administration de reine-cloud.fr (privé) : clients, demandes, paiements, comptabilité, site et technique.
// À protéger avec « Confidentialité du répertoire » de cPanel (dossier public_html/gestion).
declare(strict_types=1);

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

// Sécurité : sans identifiant de connexion demandé par le serveur, la page refuse de s'ouvrir.
$connecte = $_SERVER['REMOTE_USER'] ?? $_SERVER['REDIRECT_REMOTE_USER'] ?? $_SERVER['PHP_AUTH_USER'] ?? '';
if ($connecte === '') {
    http_response_code(403);
    echo '<!doctype html><meta charset="utf-8"><title>Accès protégé</title><p style="font-family:sans-serif;max-width:600px;margin:40px auto">'
       . 'Cette page n\'est pas encore protégée. Dans cPanel, ouvrez « Confidentialité du répertoire », choisissez le dossier '
       . '<b>public_html/gestion</b>, activez la protection par mot de passe, puis rechargez cette page.</p>';
    exit;
}

require dirname(__DIR__, 2) . '/abonnements/lib.php';
require __DIR__ . '/commun.php';
session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Strict', 'cookie_secure' => !empty($_SERVER['HTTPS'])]);
if (empty($_SESSION['jeton'])) {
    $_SESSION['jeton'] = bin2hex(random_bytes(16));
}
$pdo = db();
$cfg = config();
rattacher_clients($pdo);

const ONGLETS = [
    'tableau'    => ['Tableau de bord', '▦'],
    'clients'    => ['Clients', '☺'],
    'demandes'   => ['Demandes', '✉'],
    'paiements'  => ['Paiements', '€'],
    'compta'     => ['Comptabilité', '≡'],
    'domaines'   => ['Noms de domaine', '◎'],
    'technique'  => ['Site et technique', '⚙'],
    'parametres' => ['Paramètres', '✎'],
];
$onglet = (string) ($_GET['o'] ?? 'tableau');
if (!isset(ONGLETS[$onglet])) {
    $onglet = 'tableau';
}
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

ob_start();
require __DIR__ . '/onglets/' . $onglet . '.php';
$contenu = ob_get_clean();
?>
<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title><?= h(ONGLETS[$onglet][0]) ?> — Administration reine-cloud.fr</title>
<style>
*{box-sizing:border-box}body{margin:0;font-family:system-ui,sans-serif;background:#FAF7F0;color:#25182F;line-height:1.5;display:flex;min-height:100vh}
nav.lateral{width:230px;background:#22103A;color:#E5DCF2;padding:18px 0;flex:none}
nav.lateral .marque{padding:0 20px 16px;font-weight:700;color:#F3D77A;letter-spacing:.02em;border-bottom:1px solid rgba(255,255,255,.12);margin-bottom:10px}
nav.lateral .marque small{display:block;font-weight:400;color:#BFAFD6;font-size:.75rem}
nav.lateral a{display:flex;gap:10px;align-items:center;padding:10px 20px;color:#E5DCF2;text-decoration:none;font-size:.95rem}
nav.lateral a:hover{background:rgba(255,255,255,.07)}nav.lateral a.actif{background:#491E65;color:#fff;border-left:3px solid #D9A93A;padding-left:17px}
nav.lateral .bas{padding:16px 20px;font-size:.75rem;color:#9C8BB8;margin-top:14px}
main{flex:1;padding:24px 28px 60px;min-width:0;max-width:1180px}
h1{color:#491E65;margin:.1em 0 .4em}h2{color:#491E65;margin:1.8em 0 .5em;font-size:1.25rem}h3{color:#491E65}
table{width:100%;border-collapse:collapse;background:#fff;font-size:.9rem}
th,td{padding:9px 10px;border-bottom:1px solid #E4DCCD;text-align:left;vertical-align:top}
th{background:#F2ECE0;font-size:.75rem;text-transform:uppercase;letter-spacing:.06em}
.retard{color:#B3261E;font-weight:600}.gris{color:#8a8296}.droite{text-align:right}
.badge{display:inline-block;font-size:.72rem;padding:1px 8px;border-radius:10px;background:#EFE7FA;color:#491E65;margin-right:3px}
.badge.m{background:#FFF1D6;color:#8a5a00}.badge.v{background:#E3F4EA;color:#1E7A4C}.badge.r{background:#FDECEA;color:#B3261E}
form.ligne{display:inline}button,.bouton{cursor:pointer;padding:6px 11px;border:1px solid #491E65;background:#fff;color:#491E65;border-radius:4px;font:inherit;font-size:.82rem;margin:2px 2px 2px 0;text-decoration:none;display:inline-block}
button.pl,.bouton.pl{background:#491E65;color:#fff}
.msg{background:#E8F6EE;border:1px solid #1E9A5C;padding:10px 14px;margin:0 0 16px}.msg.err{background:#FDECEA;border-color:#B3261E}
form.grille{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:10px;background:#fff;padding:16px;border:1px solid #E4DCCD}
form.grille label{font-size:.74rem;text-transform:uppercase;letter-spacing:.06em;color:#6B6275;display:block}
form.grille input,form.grille select,form.grille textarea{width:100%;padding:8px;font:inherit;box-sizing:border-box}
.large{grid-column:1/-1}
details{margin-top:6px}summary{cursor:pointer;color:#491E65;font-size:.85rem}details form.grille{margin-top:8px}
.cartes{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:14px;margin:12px 0}
.carte{background:#fff;border:1px solid #E4DCCD;padding:16px}.carte .n{font-size:1.7rem;font-weight:700;color:#491E65;line-height:1.1}.carte .l{font-size:.8rem;color:#6B6275;text-transform:uppercase;letter-spacing:.05em}
.carte.alerte{border-color:#B3261E}.carte.alerte .n{color:#B3261E}
.note{font-size:.85rem;color:#6B6275}.filtres a{margin-right:10px;color:#491E65}.filtres a.on{font-weight:700;text-decoration:none}
@media(max-width:800px){body{flex-direction:column}nav.lateral{width:100%;display:flex;flex-wrap:wrap;padding:8px}nav.lateral .marque,nav.lateral .bas{display:none}nav.lateral a{padding:8px 12px}nav.lateral a.actif{border-left:0;padding-left:12px}main{padding:16px}}
</style></head><body>
<nav class="lateral">
  <div class="marque">reine-cloud.fr<small>Administration</small></div>
  <?php foreach (ONGLETS as $k => [$lib, $icone]): ?>
  <a href="?o=<?= h($k) ?>" class="<?= $k === $onglet ? 'actif' : '' ?>"><span><?= h($icone) ?></span> <?= h($lib) ?></a>
  <?php endforeach; ?>
  <div class="bas">Connecté : <?= h($connecte) ?></div>
</nav>
<main>
<?php if ($flash): ?><div class="msg<?= $flash[1] ? ' err' : '' ?>"><?= h($flash[0]) ?></div><?php endif; ?>
<?= $contenu ?>
</main></body></html>
