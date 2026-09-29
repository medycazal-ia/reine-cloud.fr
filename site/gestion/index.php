<?php
// Page de gestion des abonnés (privée). À protéger avec « Confidentialité du répertoire » de cPanel.
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

function h($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($jeton, (string) ($_POST['jeton'] ?? ''))) {
        http_response_code(400);
        exit('Requête refusée.');
    }
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    if ($action === 'ajouter') {
        $nom = trim((string) ($_POST['nom'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $offre = (string) ($_POST['offre'] ?? 'autre');
        $montant = (float) str_replace(',', '.', (string) ($_POST['montant'] ?? '0'));
        $date = (string) ($_POST['echeance'] ?? '');
        $valide = $nom !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) && isset(OFFRES[$offre])
            && $montant > 0 && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && strtotime($date) !== false;
        if ($valide) {
            $libelle = trim((string) ($_POST['libelle'] ?? '')) ?: OFFRES[$offre];
            $pdo->prepare('INSERT INTO abonnes (nom, email, offre, libelle, montant, jour, prochaine_echeance) VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute([$nom, $email, $offre, $libelle, $montant, (int) substr($date, 8, 2), $date]);
            $message = 'Abonné ajouté.';
        } else {
            $message = 'Vérifiez les champs (nom, e-mail, montant, date).';
        }
    } elseif ($action === 'paye') {
        marquer_paye($pdo, $id);
        $message = 'Paiement enregistré : prochaine échéance avancée d\'un mois.';
    } elseif ($action === 'resilier' || $action === 'reactiver') {
        $pdo->prepare('UPDATE abonnes SET actif = ? WHERE id = ?')->execute([$action === 'reactiver' ? 1 : 0, $id]);
        $message = $action === 'resilier' ? 'Abonnement résilié.' : 'Abonnement réactivé.';
    } elseif ($action === 'envoyer') {
        $q = $pdo->prepare('SELECT * FROM abonnes WHERE id = ?');
        $q->execute([$id]);
        if ($a = $q->fetch(PDO::FETCH_ASSOC)) {
            [$lien, $libre] = lien_pour($a, $cfg);
            [$sujet, $corps] = construire_mail($a, 'J-7', $lien, $libre);
            $message = envoyer_mail($a['email'], $sujet, $corps) ? 'Lien de paiement envoyé.' : 'Échec de l\'envoi.';
        }
    }
}

$abonnes = $pdo->query('SELECT * FROM abonnes ORDER BY actif DESC, prochaine_echeance')->fetchAll(PDO::FETCH_ASSOC);
$aujourdhui = date('Y-m-d');
?>
<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>Gestion des abonnés</title>
<style>
body{font-family:system-ui,sans-serif;background:#FAF7F0;color:#25182F;margin:0;line-height:1.5}
main{max-width:1000px;margin:0 auto;padding:24px 16px 60px}
h1{color:#491E65;margin:.2em 0}h2{color:#491E65;margin-top:1.8em}
table{width:100%;border-collapse:collapse;background:#fff;font-size:.92rem}
th,td{padding:9px 10px;border-bottom:1px solid #E4DCCD;text-align:left;vertical-align:top}
th{background:#F2ECE0;font-size:.78rem;text-transform:uppercase;letter-spacing:.06em}
.retard{color:#B3261E;font-weight:600}.ok{color:#1E7A4C}.gris{color:#8a8296}
form.ligne{display:inline}button{cursor:pointer;padding:6px 10px;border:1px solid #491E65;background:#fff;color:#491E65;border-radius:4px;font:inherit;font-size:.82rem;margin:2px 2px 2px 0}
button.pl{background:#491E65;color:#fff}
.msg{background:#E8F6EE;border:1px solid #1E9A5C;padding:10px 14px;margin:14px 0}
form.ajout{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px;background:#fff;padding:16px;border:1px solid #E4DCCD}
form.ajout label{font-size:.78rem;text-transform:uppercase;letter-spacing:.06em;color:#6B6275;display:block}
form.ajout input,form.ajout select{width:100%;padding:8px;font:inherit;box-sizing:border-box}
.note{font-size:.85rem;color:#6B6275}
</style></head><body><main>
<h1>Gestion des abonnés</h1>
<p class="note">Connecté : <?= h($connecte) ?>. Les liens de paiement partent automatiquement 7 jours avant l'échéance, puis un rappel la veille et une relance 3 jours après.</p>
<?php if ($message): ?><div class="msg"><?= h($message) ?></div><?php endif; ?>

<h2>Abonnés</h2>
<?php if (!$abonnes): ?><p>Aucun abonné pour l'instant.</p><?php else: ?>
<table>
<tr><th>Client</th><th>Offre</th><th>Montant</th><th>Échéance</th><th>Actions</th></tr>
<?php foreach ($abonnes as $a):
    $retard = $a['actif'] && $a['prochaine_echeance'] < $aujourdhui; ?>
<tr>
  <td><b><?= h($a['nom']) ?></b><br><span class="gris"><?= h($a['email']) ?></span><?= $a['actif'] ? '' : '<br><span class="gris">résilié</span>' ?></td>
  <td><?= h($a['libelle']) ?></td>
  <td><?= h(euros($a['montant'])) ?> / mois</td>
  <td class="<?= $retard ? 'retard' : '' ?>"><?= h(date_fr($a['prochaine_echeance'])) ?><?= $retard ? '<br>en retard' : '' ?></td>
  <td>
  <?php foreach ([['paye', 'Marquer payé', 'pl'], ['envoyer', 'Envoyer le lien', ''], [$a['actif'] ? 'resilier' : 'reactiver', $a['actif'] ? 'Résilier' : 'Réactiver', '']] as [$act, $lib, $cl]):
      if (!$a['actif'] && in_array($act, ['paye', 'envoyer'], true)) { continue; } ?>
    <form class="ligne" method="post"><input type="hidden" name="jeton" value="<?= h($jeton) ?>"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
      <button class="<?= $cl ?>" name="action" value="<?= h($act) ?>"<?= $act === 'resilier' ? ' onclick="return confirm(\'Résilier cet abonnement ?\')"' : '' ?>><?= h($lib) ?></button></form>
  <?php endforeach; ?>
  </td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>

<h2>Ajouter un abonné</h2>
<form class="ajout" method="post">
  <input type="hidden" name="jeton" value="<?= h($jeton) ?>"><input type="hidden" name="action" value="ajouter">
  <div><label>Nom ou entreprise</label><input name="nom" required maxlength="120"></div>
  <div><label>E-mail</label><input type="email" name="email" required maxlength="200"></div>
  <div><label>Offre</label><select name="offre"><?php foreach (OFFRES as $k => $v): ?><option value="<?= h($k) ?>"><?= h($v) ?></option><?php endforeach; ?></select></div>
  <div><label>Intitulé (facultatif)</label><input name="libelle" maxlength="120" placeholder="ex. 3 agents standard"></div>
  <div><label>Montant mensuel (€)</label><input name="montant" required inputmode="decimal" placeholder="19,99"></div>
  <div><label>Première échéance</label><input type="date" name="echeance" required></div>
  <div style="align-self:end"><button class="pl" type="submit">Ajouter</button></div>
</form>
<p class="note">Après chaque paiement reçu, cliquez sur « Marquer payé » : la prochaine échéance avance d'un mois et le cycle de rappels recommence.</p>
</main></body></html>
