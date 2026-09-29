<?php
$annee = (int) ($_GET['a'] ?? date('Y'));
if ($annee < 2020 || $annee > 2100) { $annee = (int) date('Y'); }

if (($_GET['export'] ?? '') === 'recettes') {
    ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="livre-des-recettes-' . $annee . '.csv"');
    echo csv_recettes(recettes($pdo, $annee));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_jeton();
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    if ($action === 'nouvelle') {
        $cid = (int) ($_POST['client_id'] ?? 0);
        $client = $cid ? client_par_id($pdo, $cid) : null;
        $nom = $client ? ($client['entreprise'] ?: $client['nom']) : trim((string) ($_POST['nom'] ?? ''));
        $email = $client ? $client['email'] : trim((string) ($_POST['email'] ?? ''));
        $adresse = $client ? $client['adresse'] : trim((string) ($_POST['adresse'] ?? ''));
        $objet = trim((string) ($_POST['objet'] ?? ''));
        $montant = montant_saisi($_POST['montant'] ?? '0');
        $date = (string) ($_POST['date_facture'] ?? date('Y-m-d'));
        $statut = ($_POST['statut'] ?? 'emise') === 'payee' ? 'payee' : 'emise';
        $datep = (string) ($_POST['date_paiement'] ?? '') ?: $date;
        if ($nom === '' || $objet === '' || $montant <= 0 || !date_valide($date) || !date_valide($datep) || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            flash('Vérifiez le client, l\'objet, le montant et les dates.', true);
        } else {
            $fid = creer_facture($pdo, ['client_id' => $client['id'] ?? null, 'nom' => $nom, 'email' => $email, 'adresse' => $adresse, 'objet' => $objet,
                'montant' => $montant, 'statut' => $statut, 'date_facture' => $date, 'date_paiement' => $datep, 'mode_paiement' => trim((string) ($_POST['mode_paiement'] ?? ''))]);
            $f = facture_par_id($pdo, $fid);
            journaliser($pdo, 'facture', $email ?: $nom, $f['numero'] . ', ' . euros($montant));
            if (($_POST['envoyer'] ?? '') === '1' && $email !== '') { envoyer_facture($pdo, $fid); }
            flash('Facture ' . $f['numero'] . ' créée.');
        }
    } elseif ($action === 'encaisser' && ($f = facture_par_id($pdo, $id))) {
        $datep = date_valide((string) ($_POST['date_paiement'] ?? '')) ? (string) $_POST['date_paiement'] : date('Y-m-d');
        encaisser_facture($pdo, $id, trim((string) ($_POST['mode_paiement'] ?? '')) ?: 'Paiement en ligne', $datep);
        journaliser($pdo, 'encaissement', $f['email'] ?: $f['nom'], $f['numero'] . ', ' . euros($f['montant']));
        flash('Facture ' . $f['numero'] . ' marquée payée.');
    } elseif ($action === 'envoyer' && ($f = facture_par_id($pdo, $id))) {
        $ok = envoyer_facture($pdo, $id);
        flash($ok ? 'Facture envoyée à ' . $f['email'] . '.' : 'Envoi impossible (adresse e-mail manquante ou échec).', !$ok);
    }
    aller('?o=compta&a=' . $annee);
}

$rec = recettes($pdo, $annee);
$mois = totaux_par_mois($rec);
$ca = array_sum($mois);
$q = $pdo->prepare('SELECT * FROM factures WHERE date_facture >= ? AND date_facture <= ? ORDER BY date_facture DESC, id DESC');
$q->execute(["$annee-01-01", "$annee-12-31"]);
$factures = $q->fetchAll(PDO::FETCH_ASSOC);
$a_encaisser = (float) $pdo->query("SELECT COALESCE(SUM(montant), 0) FROM factures WHERE statut = 'emise'")->fetchColumn();
$clients = $pdo->query('SELECT id, nom, entreprise, email FROM clients ORDER BY nom')->fetchAll(PDO::FETCH_ASSOC);
$preclient = (int) ($_GET['client'] ?? 0);
$seuil = (float) str_replace(',', '.', param('seuil_ca'));
$noms_mois = ['', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
?>
<h1>Comptabilité</h1>
<p class="filtres">Année :
<?php for ($y = (int) date('Y') + 1; $y >= (int) date('Y') - 3; $y--): ?><a class="<?= $y === $annee ? 'on' : '' ?>" href="?o=compta&a=<?= $y ?>"><?= $y ?></a><?php endfor; ?></p>
<div class="cartes">
  <div class="carte"><div class="n"><?= h(euros($ca)) ?></div><div class="l">Encaissé en <?= $annee ?></div></div>
  <div class="carte <?= $a_encaisser > 0 ? 'alerte' : '' ?>"><div class="n"><?= h(euros($a_encaisser)) ?></div><div class="l">Factures à encaisser</div></div>
  <div class="carte"><div class="n"><?= count($rec) ?></div><div class="l">Encaissements en <?= $annee ?></div></div>
  <?php if ($seuil > 0): ?><div class="carte <?= $ca >= $seuil * 0.9 ? 'alerte' : '' ?>"><div class="n"><?= h(number_format($ca / $seuil * 100, 0)) ?> %</div><div class="l">Du seuil d'alerte (<?= h(euros($seuil)) ?>)</div></div><?php endif; ?>
</div>

<h2>Factures <?= $annee ?></h2>
<?php if (!$factures): ?><p class="note">Aucune facture cette année.</p><?php else: ?>
<table><tr><th>Numéro</th><th>Date</th><th>Client</th><th>Objet</th><th class="droite">Montant</th><th>État</th><th>Actions</th></tr>
<?php foreach ($factures as $f): ?><tr>
  <td><a href="<?= h(facture_url($f)) ?>" target="_blank" rel="noopener"><?= h($f['numero']) ?></a></td>
  <td><?= h(date_fr($f['date_facture'])) ?></td><td><?= h($f['nom']) ?></td><td><?= h($f['objet']) ?></td>
  <td class="droite"><?= h(euros($f['montant'])) ?></td>
  <td><?= $f['statut'] === 'payee' ? '<span class="badge v">payée le ' . h(date_fr($f['date_paiement'])) . '</span>' : '<span class="badge r">à régler</span>' ?></td>
  <td>
    <?php if ($f['statut'] === 'emise'): ?><?= bouton_action('compta', 'encaisser', (int) $f['id'], 'Marquer payée', 'pl', '', ['mode_paiement' => 'Paiement en ligne']) ?><?php endif; ?>
    <?php if ($f['email'] !== ''): ?><?= bouton_action('compta', 'envoyer', (int) $f['id'], 'Envoyer par e-mail') ?><?php endif; ?>
    <a class="bouton" href="<?= h(facture_url($f)) ?>" target="_blank" rel="noopener">Voir / imprimer</a>
  </td></tr><?php endforeach; ?></table>
<p class="note">Les factures ne se suppriment pas (numérotation continue obligatoire).</p>
<?php endif; ?>

<h2 id="nouvelle-facture">Créer une facture</h2>
<form class="grille" method="post" action="?o=compta&a=<?= $annee ?>"><?= champ_jeton() ?><input type="hidden" name="action" value="nouvelle">
  <div class="large"><label>Client existant (sinon, remplir les champs ci-dessous)</label>
    <select name="client_id"><option value="0">— autre client —</option>
      <?php foreach ($clients as $c): ?><option value="<?= (int) $c['id'] ?>"<?= $preclient === (int) $c['id'] ? ' selected' : '' ?>><?= h(($c['entreprise'] ?: $c['nom']) . ($c['email'] ? ' — ' . $c['email'] : '')) ?></option><?php endforeach; ?></select></div>
  <div><label>Nom ou entreprise</label><input name="nom" maxlength="160"></div>
  <div><label>E-mail</label><input type="email" name="email" maxlength="200"></div>
  <div class="large"><label>Adresse de facturation</label><input name="adresse" maxlength="300"></div>
  <div class="large"><label>Objet (jamais de donnée de santé)</label><input name="objet" required maxlength="250" placeholder="ex. Cadrage de l'agent sur mesure"></div>
  <div><label>Montant net (€)</label><input name="montant" required inputmode="decimal" placeholder="199"></div>
  <div><label>Date de la facture</label><input type="date" name="date_facture" value="<?= h(date('Y-m-d')) ?>" required></div>
  <div><label>État</label><select name="statut"><option value="emise">À régler</option><option value="payee">Déjà payée</option></select></div>
  <div><label>Date de paiement (si payée)</label><input type="date" name="date_paiement"></div>
  <div><label>Mode de règlement (si payée)</label><input name="mode_paiement" maxlength="40" placeholder="Virement, carte, Revolut…"></div>
  <div><label>Envoyer par e-mail ?</label><select name="envoyer"><option value="1">Oui, si e-mail renseigné</option><option value="0">Non</option></select></div>
  <div style="align-self:end"><button class="pl">Créer la facture</button></div>
</form>

<h2>Livre des recettes <?= $annee ?></h2>
<p><a class="bouton pl" href="?o=compta&a=<?= $annee ?>&export=recettes">Télécharger en CSV (Excel)</a></p>
<?php if (!$rec): ?><p class="note">Aucun encaissement cette année.</p><?php else: ?>
<table><tr><th>Date</th><th>Facture</th><th>Client</th><th>Objet</th><th>Mode</th><th class="droite">Montant</th></tr>
<?php foreach ($rec as $r): ?><tr><td><?= h(date_fr($r['date_paiement'])) ?></td><td><?= h($r['numero']) ?></td><td><?= h($r['nom']) ?></td><td><?= h($r['objet']) ?></td><td><?= h($r['mode_paiement']) ?></td><td class="droite"><?= h(euros($r['montant'])) ?></td></tr><?php endforeach; ?>
<tr><th colspan="5" class="droite">Total <?= $annee ?></th><th class="droite"><?= h(euros($ca)) ?></th></tr></table>
<h2>Par mois</h2>
<table><tr><?php foreach ($noms_mois as $i => $m): if ($i) echo '<th class="droite">' . h(mb_substr($m, 0, 4)) . '.</th>'; endforeach; ?></tr>
<tr><?php foreach ($mois as $m => $v): ?><td class="droite"><?= $v > 0 ? h(euros($v)) : '<span class="gris">—</span>' ?></td><?php endforeach; ?></tr></table>
<?php endif; ?>
