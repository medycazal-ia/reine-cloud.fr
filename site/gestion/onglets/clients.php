<?php
function champs_client(array $v): void
{
    $n = static fn(string $k) => h($v[$k] ?? '');
    ?>
  <div><label>Nom du contact</label><input name="nom" required maxlength="120" value="<?= $n('nom') ?>"></div>
  <div><label>Entreprise</label><input name="entreprise" maxlength="160" value="<?= $n('entreprise') ?>"></div>
  <div><label>E-mail</label><input type="email" name="email" maxlength="200" value="<?= $n('email') ?>"></div>
  <div><label>Téléphone</label><input name="telephone" maxlength="40" value="<?= $n('telephone') ?>"></div>
  <div><label>Statut</label><select name="statut"><?php foreach (STATUTS_CLIENT as $k => $l): ?><option value="<?= h($k) ?>"<?= ($v['statut'] ?? 'prospect') === $k ? ' selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select></div>
  <div class="large"><label>Adresse de facturation</label><input name="adresse" maxlength="300" value="<?= $n('adresse') ?>"></div>
  <div class="large"><label>Notes (jamais de donnée de santé)</label><textarea name="notes" rows="3"><?= $n('notes') ?></textarea></div>
<?php
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_jeton();
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    $nom = trim((string) ($_POST['nom'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    if (($action === 'creer' || $action === 'modifier') && ($nom === '' || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)))) {
        flash('Vérifiez le nom et l\'adresse e-mail.', true);
        aller('?o=clients' . ($id ? '&id=' . $id : '&nouveau=1'));
    }
    if ($action === 'creer') {
        $cid = creer_client($pdo, $_POST);
        journaliser($pdo, 'client créé', $email ?: $nom, $nom);
        flash('Client créé.');
        aller('?o=clients&id=' . $cid);
    }
    if ($action === 'modifier' && client_par_id($pdo, $id)) {
        $pdo->prepare('UPDATE clients SET nom=?, entreprise=?, email=?, telephone=?, adresse=?, statut=?, notes=? WHERE id=?')->execute([
            mb_substr($nom, 0, 120), mb_substr(trim((string) ($_POST['entreprise'] ?? '')), 0, 160), mb_substr($email, 0, 200),
            mb_substr(trim((string) ($_POST['telephone'] ?? '')), 0, 40), mb_substr(trim((string) ($_POST['adresse'] ?? '')), 0, 300),
            isset(STATUTS_CLIENT[$_POST['statut'] ?? '']) ? $_POST['statut'] : 'prospect', (string) ($_POST['notes'] ?? ''), $id,
        ]);
        journaliser($pdo, 'client modifié', $email ?: $nom, $nom);
        flash('Fiche client enregistrée.');
        aller('?o=clients&id=' . $id);
    }
    aller('?o=clients');
}

$id = (int) ($_GET['id'] ?? 0);
$fiche = $id ? client_par_id($pdo, $id) : null;

if (isset($_GET['nouveau'])): ?>
<h1>Nouveau client</h1>
<form class="grille" method="post" action="?o=clients"><?= champ_jeton() ?><input type="hidden" name="action" value="creer">
  <?php champs_client([]); ?>
  <div style="align-self:end"><button class="pl">Créer</button> <a class="bouton" href="?o=clients">Annuler</a></div>
</form>

<?php elseif ($fiche):
    $abos = $pdo->prepare('SELECT * FROM abonnes WHERE client_id = ? ORDER BY actif DESC, prochaine_echeance'); $abos->execute([$id]); $abos = $abos->fetchAll(PDO::FETCH_ASSOC);
    $facs = $pdo->prepare('SELECT * FROM factures WHERE client_id = ? OR (email <> \'\' AND email = ?) ORDER BY date_facture DESC, id DESC'); $facs->execute([$id, $fiche['email']]); $facs = $facs->fetchAll(PDO::FETCH_ASSOC);
    $dems = $pdo->prepare('SELECT * FROM demandes WHERE email <> \'\' AND email = ? ORDER BY id DESC'); $dems->execute([$fiche['email']]); $dems = $dems->fetchAll(PDO::FETCH_ASSOC);
?>
<p><a href="?o=clients">← Tous les clients</a></p>
<h1><?= h($fiche['entreprise'] ?: $fiche['nom']) ?> <span class="badge"><?= h(STATUTS_CLIENT[$fiche['statut']] ?? $fiche['statut']) ?></span></h1>
<form class="grille" method="post" action="?o=clients"><?= champ_jeton() ?><input type="hidden" name="action" value="modifier"><input type="hidden" name="id" value="<?= $id ?>">
  <?php champs_client($fiche); ?>
  <div style="align-self:end"><button class="pl">Enregistrer la fiche</button></div>
</form>

<h2>Abonnements</h2>
<?php if (!$abos): ?><p class="note">Aucun abonnement.</p><?php else: ?>
<table><tr><th>Offre</th><th>Montant</th><th>Échéance</th><th>État</th></tr>
<?php foreach ($abos as $a): ?><tr><td><?= h($a['libelle']) ?></td><td><?= h(euros($a['montant'])) ?> / mois</td><td><?= h(date_fr($a['prochaine_echeance'])) ?></td><td><?= $a['actif'] ? 'actif (' . h($a['mode']) . ')' : 'résilié' ?></td></tr><?php endforeach; ?></table>
<?php endif; ?>
<p><a class="bouton pl" href="?o=paiements&client=<?= $id ?>#nouvel-abonnement">Nouvel abonnement pour ce client</a>
   <a class="bouton" href="?o=compta&client=<?= $id ?>#nouvelle-facture">Créer une facture</a></p>

<h2>Factures</h2>
<?php if (!$facs): ?><p class="note">Aucune facture.</p><?php else: ?>
<table><tr><th>Numéro</th><th>Date</th><th>Objet</th><th class="droite">Montant</th><th>État</th></tr>
<?php foreach ($facs as $f): ?><tr><td><a href="<?= h(facture_url($f)) ?>" target="_blank" rel="noopener"><?= h($f['numero']) ?></a></td><td><?= h(date_fr($f['date_facture'])) ?></td><td><?= h($f['objet']) ?></td><td class="droite"><?= h(euros($f['montant'])) ?></td><td><?= $f['statut'] === 'payee' ? '<span class="badge v">payée</span>' : '<span class="badge r">à régler</span>' ?></td></tr><?php endforeach; ?></table>
<?php endif; ?>

<h2>Messages reçus</h2>
<?php if (!$dems): ?><p class="note">Aucun message avec cette adresse e-mail.</p><?php else: ?>
<table><tr><th>Date</th><th>Sujet</th><th>Message</th></tr>
<?php foreach ($dems as $d): ?><tr><td><?= h($d['cree_le']) ?></td><td><?= h($d['sujet']) ?></td><td><?= nl2br(h($d['message'])) ?></td></tr><?php endforeach; ?></table>
<?php endif; ?>

<?php else:
    $q = trim((string) ($_GET['q'] ?? ''));
    $st = (string) ($_GET['statut'] ?? '');
    $sql = 'SELECT * FROM clients WHERE 1=1'; $args = [];
    if (isset(STATUTS_CLIENT[$st])) { $sql .= ' AND statut = ?'; $args[] = $st; }
    if ($q !== '') { $sql .= ' AND (nom LIKE ? OR entreprise LIKE ? OR email LIKE ?)'; array_push($args, "%$q%", "%$q%", "%$q%"); }
    $sql .= ' ORDER BY nom';
    $req = $pdo->prepare($sql); $req->execute($args); $liste = $req->fetchAll(PDO::FETCH_ASSOC);
?>
<h1>Clients</h1>
<p class="filtres"><a class="<?= $st === '' ? 'on' : '' ?>" href="?o=clients">Tous</a>
<?php foreach (STATUTS_CLIENT as $k => $l): ?><a class="<?= $st === $k ? 'on' : '' ?>" href="?o=clients&statut=<?= h($k) ?>"><?= h($l) ?></a><?php endforeach; ?>
 <a class="bouton pl" href="?o=clients&nouveau=1">+ Nouveau client</a></p>
<form method="get" style="margin:0 0 12px"><input type="hidden" name="o" value="clients"><input name="q" value="<?= h($q) ?>" placeholder="Rechercher (nom, entreprise, e-mail)" style="padding:8px;width:280px"> <button>Rechercher</button></form>
<?php if (!$liste): ?><p>Aucun client pour l'instant.</p><?php else: ?>
<table><tr><th>Client</th><th>Contact</th><th>Statut</th></tr>
<?php foreach ($liste as $c): ?><tr>
  <td><a href="?o=clients&id=<?= (int) $c['id'] ?>"><b><?= h($c['entreprise'] ?: $c['nom']) ?></b></a><?= $c['entreprise'] ? '<br><span class="gris">' . h($c['nom']) . '</span>' : '' ?></td>
  <td><?= h($c['email']) ?><br><span class="gris"><?= h($c['telephone']) ?></span></td>
  <td><span class="badge"><?= h(STATUTS_CLIENT[$c['statut']] ?? $c['statut']) ?></span></td></tr><?php endforeach; ?></table>
<?php endif; endif; ?>
