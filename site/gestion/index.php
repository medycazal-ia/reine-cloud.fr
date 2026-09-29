<?php
// Espace d'administration des abonnements et des demandes de paiement (privé).
// À protéger avec « Confidentialité du répertoire » de cPanel.
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
session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Strict', 'cookie_secure' => !empty($_SERVER['HTTPS'])]);
if (empty($_SESSION['jeton'])) {
    $_SESSION['jeton'] = bin2hex(random_bytes(16));
}
$jeton = $_SESSION['jeton'];
$pdo = db();
$cfg = config();
$message = '';
$erreur = false;

function h($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function montant_saisi($v): float
{
    return (float) str_replace([',', ' '], ['.', ''], (string) $v);
}
function date_valide(string $d): bool
{
    return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($jeton, (string) ($_POST['jeton'] ?? ''))) {
        http_response_code(400);
        exit('Requête refusée.');
    }
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    $q = $pdo->prepare('SELECT * FROM abonnes WHERE id = ?');
    $q->execute([$id]);
    $ab = $q->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($action === 'ajouter' || $action === 'modifier') {
        $nom = trim((string) ($_POST['nom'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $offre = (string) ($_POST['offre'] ?? 'autre');
        $montant = montant_saisi($_POST['montant'] ?? '0');
        $date = (string) ($_POST['echeance'] ?? '');
        $mode = ($_POST['mode'] ?? 'auto') === 'manuel' ? 'manuel' : 'auto';
        $lien = trim((string) ($_POST['lien_perso'] ?? ''));
        $libelle = trim((string) ($_POST['libelle'] ?? '')) ?: (OFFRES[$offre] ?? 'Abonnement');
        $valide = $nom !== '' && mb_strlen($nom) <= 120 && filter_var($email, FILTER_VALIDATE_EMAIL) && isset(OFFRES[$offre])
            && $montant > 0 && date_valide($date) && ($lien === '' || lien_valide($lien));
        if (!$valide) {
            $message = 'Vérifiez les champs (nom, e-mail, montant, date, lien https).';
            $erreur = true;
        } elseif ($action === 'ajouter') {
            $pdo->prepare('INSERT INTO abonnes (nom, email, offre, libelle, montant, jour, prochaine_echeance, mode, lien_perso) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$nom, $email, $offre, $libelle, $montant, (int) substr($date, 8, 2), $date, $mode, $lien]);
            journaliser($pdo, 'ajout', $email, "$libelle, " . euros($montant) . ", échéance $date, mode $mode");
            $message = 'Abonné ajouté.';
        } elseif ($ab) {
            $pdo->prepare('UPDATE abonnes SET nom=?, email=?, offre=?, libelle=?, montant=?, jour=?, prochaine_echeance=?, mode=?, lien_perso=? WHERE id=?')
                ->execute([$nom, $email, $offre, $libelle, $montant, (int) substr($date, 8, 2), $date, $mode, $lien, $id]);
            journaliser($pdo, 'modification', $email, "$libelle, " . euros($montant) . ", échéance $date, mode $mode" . ($lien ? ', lien personnel' : ''));
            $message = 'Modifications enregistrées : elles s\'appliquent aux prochains e-mails.';
        }
    } elseif ($action === 'paye' && $ab) {
        marquer_paye($pdo, $id);
        journaliser($pdo, 'paiement', $ab['email'], 'Paiement enregistré : ' . euros($ab['montant']) . ' (échéance ' . $ab['prochaine_echeance'] . ')');
        $message = 'Paiement enregistré : prochaine échéance avancée d\'un mois.';
    } elseif (($action === 'resilier' || $action === 'reactiver') && $ab) {
        $pdo->prepare('UPDATE abonnes SET actif = ? WHERE id = ?')->execute([$action === 'reactiver' ? 1 : 0, $id]);
        journaliser($pdo, $action === 'resilier' ? 'résiliation' : 'réactivation', $ab['email'], $ab['libelle']);
        $message = $action === 'resilier' ? 'Abonnement résilié.' : 'Abonnement réactivé.';
    } elseif ($action === 'envoyer' && $ab) {
        [$lien, $libre] = lien_pour($ab, $cfg);
        [$sujet, $corps] = construire_mail($ab, 'J-7', $lien, $libre);
        $ok = envoyer_mail($ab['email'], $sujet, $corps);
        if ($ok) {
            journaliser($pdo, 'envoi lien', $ab['email'], "Lien envoyé pour l'échéance " . $ab['prochaine_echeance']);
        }
        $message = $ok ? 'Lien de paiement envoyé.' : 'Échec de l\'envoi.';
        $erreur = !$ok;
    } elseif ($action === 'ponctuel') {
        $nom = trim((string) ($_POST['nom'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $motif = trim((string) ($_POST['motif'] ?? ''));
        $montant = montant_saisi($_POST['montant'] ?? '0');
        $lien = trim((string) ($_POST['lien'] ?? ''));
        $libre = false;
        if ($lien === '') {
            $lien = (string) ($cfg['liens']['libre'] ?? '');
            $libre = true;
        }
        if ($nom === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $motif === '' || $montant <= 0 || !lien_valide($lien)) {
            $message = 'Vérifiez les champs : nom, e-mail, motif, montant et lien https (ou laissez le lien vide pour utiliser le lien à montant libre).';
            $erreur = true;
        } else {
            [$sujet, $corps] = mail_ponctuel($nom, $motif, $montant, $lien, $libre);
            $ok = envoyer_mail($email, $sujet, $corps);
            if ($ok) {
                journaliser($pdo, 'lien ponctuel', $email, "$motif, " . euros($montant));
            }
            $message = $ok ? 'Demande de paiement envoyée.' : 'Échec de l\'envoi.';
            $erreur = !$ok;
        }
    }
}

$abonnes = $pdo->query('SELECT * FROM abonnes ORDER BY actif DESC, prochaine_echeance')->fetchAll(PDO::FETCH_ASSOC);
$journal = $pdo->query('SELECT * FROM journal ORDER BY id DESC LIMIT 15')->fetchAll(PDO::FETCH_ASSOC);
$aujourdhui = date('Y-m-d');

function champs_abonne(array $v, string $suffixe): void
{
    $n = static fn(string $k) => h($v[$k] ?? '');
    ?>
  <div><label>Nom ou entreprise</label><input name="nom" required maxlength="120" value="<?= $n('nom') ?>"></div>
  <div><label>E-mail</label><input type="email" name="email" required maxlength="200" value="<?= $n('email') ?>"></div>
  <div><label>Offre</label><select name="offre"><?php foreach (OFFRES as $k => $lib): ?><option value="<?= h($k) ?>"<?= ($v['offre'] ?? 'socle') === $k ? ' selected' : '' ?>><?= h($lib) ?></option><?php endforeach; ?></select></div>
  <div><label>Intitulé (facultatif)</label><input name="libelle" maxlength="120" placeholder="ex. 3 agents standard" value="<?= $n('libelle') ?>"></div>
  <div><label>Montant mensuel (€)</label><input name="montant" required inputmode="decimal" placeholder="19,99" value="<?= isset($v['montant']) ? h(number_format((float) $v['montant'], 2, ',', '')) : '' ?>"></div>
  <div><label>Prochaine échéance</label><input type="date" name="echeance" required value="<?= $n('prochaine_echeance') ?>"></div>
  <div><label>Mode</label><select name="mode"><option value="auto"<?= ($v['mode'] ?? 'auto') === 'auto' ? ' selected' : '' ?>>Automatique (e-mails envoyés seuls)</option><option value="manuel"<?= ($v['mode'] ?? '') === 'manuel' ? ' selected' : '' ?>>Manuel (j'envoie moi-même)</option></select></div>
  <div style="grid-column:1/-1"><label>Lien de paiement personnel (facultatif, https)</label><input name="lien_perso" maxlength="500" placeholder="Collez ici un lien Revolut valable pour ce client" value="<?= $n('lien_perso') ?>"></div>
<?php
}
?>
<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>Administration — reine-cloud.fr</title>
<style>
body{font-family:system-ui,sans-serif;background:#FAF7F0;color:#25182F;margin:0;line-height:1.5}
main{max-width:1040px;margin:0 auto;padding:24px 16px 60px}
h1{color:#491E65;margin:.2em 0}h2{color:#491E65;margin-top:1.9em}
table{width:100%;border-collapse:collapse;background:#fff;font-size:.92rem}
th,td{padding:9px 10px;border-bottom:1px solid #E4DCCD;text-align:left;vertical-align:top}
th{background:#F2ECE0;font-size:.78rem;text-transform:uppercase;letter-spacing:.06em}
.retard{color:#B3261E;font-weight:600}.gris{color:#8a8296}
.badge{display:inline-block;font-size:.72rem;padding:1px 8px;border-radius:10px;background:#EFE7FA;color:#491E65}.badge.m{background:#FFF1D6;color:#8a5a00}
form.ligne{display:inline}button{cursor:pointer;padding:6px 10px;border:1px solid #491E65;background:#fff;color:#491E65;border-radius:4px;font:inherit;font-size:.82rem;margin:2px 2px 2px 0}
button.pl{background:#491E65;color:#fff}
.msg{background:#E8F6EE;border:1px solid #1E9A5C;padding:10px 14px;margin:14px 0}.msg.err{background:#FDECEA;border-color:#B3261E}
form.grille{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:10px;background:#fff;padding:16px;border:1px solid #E4DCCD}
form.grille label{font-size:.76rem;text-transform:uppercase;letter-spacing:.06em;color:#6B6275;display:block}
form.grille input,form.grille select{width:100%;padding:8px;font:inherit;box-sizing:border-box}
details{margin-top:6px}summary{cursor:pointer;color:#491E65;font-size:.85rem}
details form.grille{margin-top:8px}
.note{font-size:.85rem;color:#6B6275}
</style></head><body><main>
<h1>Administration</h1>
<p class="note">Connecté : <?= h($connecte) ?>. En mode automatique, le lien part 7 jours avant l'échéance, un rappel la veille, une relance 3 jours après. En mode manuel, rien ne part sans votre clic.</p>
<?php if ($message): ?><div class="msg<?= $erreur ? ' err' : '' ?>"><?= h($message) ?></div><?php endif; ?>

<h2>Abonnés</h2>
<?php if (!$abonnes): ?><p>Aucun abonné pour l'instant.</p><?php else: ?>
<table>
<tr><th>Client</th><th>Offre</th><th>Montant</th><th>Échéance</th><th>Actions</th></tr>
<?php foreach ($abonnes as $a):
    $retard = $a['actif'] && $a['prochaine_echeance'] < $aujourdhui; ?>
<tr>
  <td><b><?= h($a['nom']) ?></b><br><span class="gris"><?= h($a['email']) ?></span><br>
    <?= $a['actif'] ? '<span class="badge' . ($a['mode'] === 'manuel' ? ' m' : '') . '">' . ($a['mode'] === 'manuel' ? 'manuel' : 'automatique') . '</span>' : '<span class="gris">résilié</span>' ?>
    <?= !empty($a['lien_perso']) ? '<span class="badge">lien perso</span>' : '' ?></td>
  <td><?= h($a['libelle']) ?></td>
  <td><?= h(euros($a['montant'])) ?> / mois</td>
  <td class="<?= $retard ? 'retard' : '' ?>"><?= h(date_fr($a['prochaine_echeance'])) ?><?= $retard ? '<br>en retard' : '' ?></td>
  <td>
  <?php foreach ([['paye', 'Marquer payé', 'pl'], ['envoyer', 'Envoyer le lien', ''], [$a['actif'] ? 'resilier' : 'reactiver', $a['actif'] ? 'Résilier' : 'Réactiver', '']] as [$act, $lib, $cl]):
      if (!$a['actif'] && in_array($act, ['paye', 'envoyer'], true)) { continue; } ?>
    <form class="ligne" method="post"><input type="hidden" name="jeton" value="<?= h($jeton) ?>"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
      <button class="<?= $cl ?>" name="action" value="<?= h($act) ?>"<?= $act === 'resilier' ? ' onclick="return confirm(\'Résilier cet abonnement ?\')"' : '' ?>><?= h($lib) ?></button></form>
  <?php endforeach; ?>
    <details><summary>Modifier (montant, date, lien, mode…)</summary>
      <form class="grille" method="post"><input type="hidden" name="jeton" value="<?= h($jeton) ?>"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><input type="hidden" name="action" value="modifier">
        <?php champs_abonne($a, (string) $a['id']); ?>
        <div style="align-self:end"><button class="pl" type="submit">Enregistrer</button></div>
      </form>
    </details>
  </td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>

<h2>Ajouter un abonné</h2>
<form class="grille" method="post">
  <input type="hidden" name="jeton" value="<?= h($jeton) ?>"><input type="hidden" name="action" value="ajouter">
  <?php champs_abonne([], 'n'); ?>
  <div style="align-self:end"><button class="pl" type="submit">Ajouter</button></div>
</form>

<h2>Envoyer un lien de paiement ponctuel</h2>
<p class="note">Pour un client sans abonnement, un montant différent, un cadrage, une mise en place, des heures… Laissez le lien vide pour utiliser le lien à montant libre, ou collez un lien Revolut valable (créé pour l'occasion).</p>
<form class="grille" method="post">
  <input type="hidden" name="jeton" value="<?= h($jeton) ?>"><input type="hidden" name="action" value="ponctuel">
  <div><label>Nom ou entreprise</label><input name="nom" required maxlength="120"></div>
  <div><label>E-mail</label><input type="email" name="email" required maxlength="200"></div>
  <div><label>Motif</label><input name="motif" required maxlength="120" placeholder="ex. Cadrage, acompte mise en place"></div>
  <div><label>Montant (€)</label><input name="montant" required inputmode="decimal" placeholder="199"></div>
  <div style="grid-column:1/-1"><label>Lien de paiement (facultatif, https)</label><input name="lien" maxlength="500" placeholder="Vide = lien à montant libre"></div>
  <div style="align-self:end"><button class="pl" type="submit">Envoyer</button></div>
</form>

<h2>Journal</h2>
<?php if (!$journal): ?><p class="note">Rien pour l'instant.</p><?php else: ?>
<table><tr><th>Date</th><th>Action</th><th>Client</th><th>Détail</th></tr>
<?php foreach ($journal as $j): ?><tr><td><?= h($j['le']) ?></td><td><?= h($j['type']) ?></td><td><?= h($j['destinataire']) ?></td><td><?= h($j['detail']) ?></td></tr><?php endforeach; ?>
</table><?php endif; ?>
<p class="note">Après chaque paiement reçu, cliquez sur « Marquer payé » : la prochaine échéance avance d'un mois et le cycle de rappels recommence.</p>
</main></body></html>
