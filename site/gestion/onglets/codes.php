<?php
require_once dirname(__DIR__, 3) . '/abonnements/lib_remises.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_jeton();
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    if ($action === 'creer') {
        [$ok, $message] = creer_code($pdo, (string) ($_POST['code'] ?? ''), $_POST['pourcentage'] ?? '', (int) ($_POST['max'] ?? 0), (string) ($_POST['note'] ?? ''));
        flash($message, !$ok);
    } elseif ($action === 'basculer') {
        $pdo->prepare('UPDATE codes_remise SET actif = 1 - actif WHERE id = ?')->execute([$id]);
        flash('Code mis à jour.');
    } elseif ($action === 'pourcentage') {
        $p = pourcentage_valide($_POST['pourcentage'] ?? '');
        if ($p === null) {
            flash('Pourcentage : un nombre de 0 à 100.', true);
        } else {
            $pdo->prepare('UPDATE codes_remise SET pourcentage = ? WHERE id = ?')->execute([$p, $id]);
            flash('Pourcentage modifié.');
        }
    } elseif ($action === 'supprimer') {
        $pdo->prepare('DELETE FROM codes_remise WHERE id = ?')->execute([$id]);
        flash('Code supprimé.');
    }
    aller('?o=codes');
}
$codes = $pdo->query('SELECT * FROM codes_remise ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
$hote = preg_replace('/[^a-z0-9.\-:]/i', '', (string) ($_SERVER['HTTP_HOST'] ?? 'reine-cloud.fr'));
function pct_fr($p): string { return rtrim(rtrim(number_format((float) $p, 2, ',', ''), '0'), ',') . ' %'; }
?>
<h1>Codes parrain</h1>
<p class="note">Un code parrain donne un pourcentage de remise (de 0 à 100 %) sur le montant d'une commande du socle, de l'agent standard ou du cadrage. Le sur mesure se chiffre sur devis : pas de code. La remise porte sur le montant de la commande affiché au client, c'est-à-dire le premier paiement. Aucune donnée personnelle n'est enregistrée avec un code.</p>

<h2>Créer un code</h2>
<form class="grille" method="post" action="?o=codes" autocomplete="off"><?= champ_jeton() ?><input type="hidden" name="action" value="creer">
  <div><label>Code (laissez vide pour en générer un)</label><input name="code" maxlength="32" placeholder="PARRAIN-AB12CD"></div>
  <div><label>Remise en % (0 à 100)</label><input name="pourcentage" type="number" min="0" max="100" step="0.01" required placeholder="20"></div>
  <div><label>Utilisations maximum (0 = illimité)</label><input name="max" type="number" min="0" value="0"></div>
  <div><label>Note pour vous (facultatif, jamais de donnée de santé)</label><input name="note" maxlength="200"></div>
  <div class="large"><button class="pl">Créer le code</button></div>
</form>

<h2>Vos codes</h2>
<table><tr><th>Code</th><th>Remise</th><th>Utilisations</th><th>État</th><th>Liens à partager</th><th>Actions</th></tr>
<?php foreach ($codes as $c): $u = code_utilisable($c); ?>
<tr>
  <td><b><?= h($c['code']) ?></b><?= $c['note'] !== '' ? '<br><span class="gris">' . h($c['note']) . '</span>' : '' ?></td>
  <td><?= h(pct_fr($c['pourcentage'])) ?>
    <form class="ligne" method="post" action="?o=codes"><?= champ_jeton() ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><input type="hidden" name="action" value="pourcentage">
    <input name="pourcentage" type="number" min="0" max="100" step="0.01" style="width:70px" aria-label="Nouveau pourcentage"><button>OK</button></form></td>
  <td><?= (int) $c['utilisations'] ?><?= (int) $c['max_utilisations'] > 0 ? ' / ' . (int) $c['max_utilisations'] : ' (illimité)' ?></td>
  <td><?= $u ? '<span class="badge v">Actif</span>' : ((int) $c['actif'] === 0 ? '<span class="badge m">Désactivé</span>' : '<span class="badge r">Épuisé</span>') ?></td>
  <td><?php foreach (['socle' => 'Socle', 'agent' => 'Agent', 'cadrage' => 'Cadrage'] as $o => $lib): $url = 'https://' . $hote . '/commander.html?offre=' . $o . '&code=' . rawurlencode($c['code']); ?>
    <div><span class="gris"><?= h($lib) ?> :</span> <input readonly value="<?= h($url) ?>" style="width:230px;font-size:.75rem" onclick="this.select()"></div>
  <?php endforeach; ?></td>
  <td><?= bouton_action('codes', 'basculer', (int) $c['id'], (int) $c['actif'] === 1 ? 'Désactiver' : 'Activer') ?>
      <?= bouton_action('codes', 'supprimer', (int) $c['id'], 'Supprimer', '', 'Supprimer ce code définitivement ?') ?></td>
</tr>
<?php endforeach; ?>
<?php if (!$codes): ?><tr><td colspan="6" class="gris">Aucun code pour le moment.</td></tr><?php endif; ?></table>
<p class="note">Pour partager : copiez le lien de l'offre voulue. Le code s'applique tout seul sur la page de commande. Vous pouvez aussi donner le code seul : le client le saisit dans « Code parrain ». Chaque clic sur « Payer » avec un code compte pour une utilisation.</p>
