<?php
require_once dirname(__DIR__, 2) . '/abonnements/lib_domaines.php';
$reg = lws_reglages($cfg);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_jeton();
    [$ok, $message] = acheter_domaine($pdo, $cfg, (string) ($_POST['domaine'] ?? ''), (string) ($_POST['confirmation'] ?? ''), (int) ($_POST['mois'] ?? 0));
    flash($message, !$ok);
    aller('?o=domaines');
}
$historique = $pdo->query("SELECT le, type, destinataire, detail FROM journal WHERE type IN ('domaine acheté', 'domaine (essai)') ORDER BY id DESC LIMIT 30")->fetchAll(PDO::FETCH_ASSOC);
?>
<h1>Noms de domaine</h1>
<p class="note">Achat d'un nom de domaine pour un client du socle, via l'API LWS. Cette page est réservée à l'administration : l'achat n'est jamais proposé sur le site public.</p>

<?php if (!lws_pret($reg)): ?>
<div class="msg err">Réglages LWS manquants. Ajoutez <b>lws_login</b>, <b>lws_pass</b> et <b>lws_owner</b> (votre numéro de client LWS) dans <code>config-{{SLUG}}.php</code>, hors de <code>public_html</code>.</div>
<?php elseif ($reg['reel']): ?>
<div class="msg err"><b>Mode réel activé :</b> chaque achat débite votre compte LWS.</div>
<?php else: ?>
<div class="msg"><b>Mode essai :</b> LWS simule l'achat, rien n'est débité. Pour acheter pour de vrai, ajoutez <code>'lws_achat_reel' =&gt; true</code> dans <code>config-{{SLUG}}.php</code>.</div>
<?php endif; ?>

<h2>Acheter un nom</h2>
<form class="grille" method="post" action="?o=domaines" autocomplete="off"><?= champ_jeton() ?>
  <div><label for="d">Nom de domaine complet</label><input id="d" name="domaine" placeholder="boulangerie-dupont.fr" required></div>
  <div><label for="c">Retapez le même nom pour confirmer</label><input id="c" name="confirmation" required></div>
  <div><label for="m">Durée</label><select id="m" name="mois"><?php foreach (DUREES_ACHAT as $m): ?><option value="<?= $m ?>"><?= $m / 12 ?> an<?= $m > 12 ? 's' : '' ?></option><?php endforeach; ?></select></div>
  <div class="large"><button class="pl" <?= lws_pret($reg) ? '' : 'disabled' ?> onclick="return confirm('Lancer l\'achat de ce nom de domaine ?')">Acheter ce nom</button></div>
</form>
<p class="note">Seul le nom de domaine est acheté (pas d'hébergement supplémentaire). Vérifiez toujours le résultat en mode essai avant de passer en mode réel. N'inscrivez ici aucune donnée de santé.</p>

<h2>Historique</h2>
<table><tr><th>Date</th><th>Type</th><th>Nom</th><th>Détail</th></tr>
<?php foreach ($historique as $l): ?><tr><td><?= h($l['le']) ?></td><td><?= h($l['type']) ?></td><td><?= h($l['destinataire']) ?></td><td class="gris"><?= h($l['detail']) ?></td></tr><?php endforeach; ?>
<?php if (!$historique): ?><tr><td colspan="4" class="gris">Aucun achat pour le moment.</td></tr><?php endif; ?></table>
