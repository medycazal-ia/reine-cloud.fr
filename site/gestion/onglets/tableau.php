<?php
$annee = (int) date('Y');
$aujourdhui = date('Y-m-d');
$actifs = $pdo->query("SELECT * FROM abonnes WHERE actif = 1 ORDER BY prochaine_echeance")->fetchAll(PDO::FETCH_ASSOC);
$mrr = array_sum(array_map(static fn($a) => (float) $a['montant'], $actifs));
$retards = array_filter($actifs, static fn($a) => $a['prochaine_echeance'] < $aujourdhui);
$bientot = array_filter($actifs, static fn($a) => $a['prochaine_echeance'] >= $aujourdhui && $a['prochaine_echeance'] <= date('Y-m-d', strtotime('+14 days')));
$ca = array_sum(array_map(static fn($r) => (float) $r['montant'], recettes($pdo, $annee)));
$a_encaisser = (float) $pdo->query("SELECT COALESCE(SUM(montant), 0) FROM factures WHERE statut = 'emise'")->fetchColumn();
$nouvelles = (int) $pdo->query("SELECT COUNT(*) FROM demandes WHERE statut = 'nouvelle'")->fetchColumn();
$nb_clients = (int) $pdo->query("SELECT COUNT(*) FROM clients WHERE statut = 'client'")->fetchColumn();
$echeance_h = param('hebergement_echeance');
$jours_h = date_valide($echeance_h) ? (int) (new DateTimeImmutable($aujourdhui))->diff(new DateTimeImmutable($echeance_h))->format('%r%a') : null;
$cron = param('cron_dernier');
$cron_ok = $cron !== '' && strtotime($cron) > time() - 36 * 3600;
$seuil = (float) str_replace(',', '.', param('seuil_ca'));
?>
<h1>Tableau de bord</h1>
<p class="note">Vue d'ensemble au <?= h(date_fr($aujourdhui)) ?>.</p>
<div class="cartes">
  <div class="carte"><div class="n"><?= h(euros($mrr)) ?></div><div class="l">Revenu mensuel récurrent</div></div>
  <div class="carte"><div class="n"><?= h(euros($ca)) ?></div><div class="l">Encaissé en <?= $annee ?></div></div>
  <div class="carte <?= $a_encaisser > 0 ? 'alerte' : '' ?>"><div class="n"><?= h(euros($a_encaisser)) ?></div><div class="l">Factures à encaisser</div></div>
  <div class="carte"><div class="n"><?= count($actifs) ?></div><div class="l">Abonnements actifs</div></div>
  <div class="carte"><div class="n"><?= $nb_clients ?></div><div class="l">Clients</div></div>
  <div class="carte <?= $nouvelles > 0 ? 'alerte' : '' ?>"><div class="n"><?= $nouvelles ?></div><div class="l">Demandes à traiter</div></div>
</div>

<h2>À surveiller</h2>
<div class="cartes">
  <div class="carte <?= $retards ? 'alerte' : '' ?>"><div class="n"><?= count($retards) ?></div><div class="l">Échéances en retard</div></div>
  <div class="carte"><div class="n"><?= count($bientot) ?></div><div class="l">Échéances sous 14 jours</div></div>
  <div class="carte <?= ($jours_h !== null && $jours_h <= 14) ? 'alerte' : '' ?>"><div class="n"><?= $jours_h === null ? '—' : h($jours_h . ' j') ?></div><div class="l">Avant l'échéance de l'hébergement</div></div>
  <div class="carte <?= $cron_ok ? '' : 'alerte' ?>"><div class="n"><?= $cron_ok ? 'OK' : 'À vérifier' ?></div><div class="l">Tâche automatique<?= $cron ? ' · ' . h($cron) : ' · jamais lancée' ?></div></div>
  <?php if ($seuil > 0): ?><div class="carte <?= $ca >= $seuil * 0.9 ? 'alerte' : '' ?>"><div class="n"><?= h(number_format($ca / $seuil * 100, 0)) ?> %</div><div class="l">Du seuil d'alerte de chiffre d'affaires</div></div><?php endif; ?>
</div>

<?php if ($retards): ?><h2>Échéances en retard</h2>
<table><tr><th>Client</th><th>Offre</th><th>Montant</th><th>Échéance</th></tr>
<?php foreach ($retards as $a): ?><tr><td><?= h($a['nom']) ?></td><td><?= h($a['libelle']) ?></td><td><?= h(euros($a['montant'])) ?></td><td class="retard"><?= h(date_fr($a['prochaine_echeance'])) ?></td></tr><?php endforeach; ?></table>
<p><a class="bouton pl" href="?o=paiements">Aller aux paiements</a></p>
<?php endif; ?>

<?php if ($bientot): ?><h2>Prochaines échéances</h2>
<table><tr><th>Client</th><th>Offre</th><th>Montant</th><th>Échéance</th></tr>
<?php foreach ($bientot as $a): ?><tr><td><?= h($a['nom']) ?></td><td><?= h($a['libelle']) ?></td><td><?= h(euros($a['montant'])) ?></td><td><?= h(date_fr($a['prochaine_echeance'])) ?></td></tr><?php endforeach; ?></table>
<?php endif; ?>

<h2>Raccourcis</h2>
<p>
  <a class="bouton" href="?o=clients&nouveau=1">Nouveau client</a>
  <a class="bouton" href="?o=paiements#lien-ponctuel">Envoyer un lien de paiement</a>
  <a class="bouton" href="?o=compta#nouvelle-facture">Créer une facture</a>
  <a class="bouton" href="?o=demandes">Voir les demandes</a>
</p>
