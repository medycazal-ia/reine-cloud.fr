<?php
const STATUTS_DEMANDE = ['nouvelle' => 'Nouvelle', 'traitee' => 'Traitée', 'archivee' => 'Archivée'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_jeton();
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    $q = $pdo->prepare('SELECT * FROM demandes WHERE id = ?'); $q->execute([$id]); $d = $q->fetch(PDO::FETCH_ASSOC);
    if ($d && in_array($action, ['nouvelle', 'traitee', 'archivee'], true)) {
        $pdo->prepare('UPDATE demandes SET statut = ? WHERE id = ?')->execute([$action, $id]);
        flash('Demande passée en « ' . STATUTS_DEMANDE[$action] . ' ».');
    } elseif ($d && $action === 'creer_client') {
        $cid = creer_client($pdo, ['nom' => $d['nom'], 'email' => $d['email'], 'statut' => 'prospect', 'notes' => "Demande du {$d['cree_le']} — {$d['sujet']}"]);
        $pdo->prepare("UPDATE demandes SET statut = 'traitee' WHERE id = ?")->execute([$id]);
        journaliser($pdo, 'client créé', $d['email'], 'depuis une demande');
        flash('Fiche client créée à partir de la demande.');
        aller('?o=clients&id=' . $cid);
    }
    aller('?o=demandes' . (isset($_GET['statut']) ? '&statut=' . urlencode((string) $_GET['statut']) : ''));
}
$st = (string) ($_GET['statut'] ?? 'nouvelle');
$tous = $st === 'tous';
$req = $tous ? $pdo->query('SELECT * FROM demandes ORDER BY id DESC LIMIT 200') : $pdo->prepare('SELECT * FROM demandes WHERE statut = ? ORDER BY id DESC LIMIT 200');
if (!$tous) { $req->execute([isset(STATUTS_DEMANDE[$st]) ? $st : 'nouvelle']); }
$liste = $req->fetchAll(PDO::FETCH_ASSOC);
?>
<h1>Demandes</h1>
<p class="note">Messages envoyés depuis le formulaire de contact du site.</p>
<p class="filtres">
<?php foreach (STATUTS_DEMANDE as $k => $l): ?><a class="<?= $st === $k ? 'on' : '' ?>" href="?o=demandes&statut=<?= h($k) ?>"><?= h($l) ?></a><?php endforeach; ?>
<a class="<?= $tous ? 'on' : '' ?>" href="?o=demandes&statut=tous">Toutes</a></p>
<?php if (!$liste): ?><p>Aucune demande dans cette catégorie.</p><?php else: ?>
<table><tr><th>Reçue le</th><th>De</th><th>Sujet et message</th><th>Actions</th></tr>
<?php foreach ($liste as $d): ?><tr>
  <td><?= h($d['cree_le']) ?><br><span class="badge <?= $d['statut'] === 'nouvelle' ? 'r' : 'v' ?>"><?= h(STATUTS_DEMANDE[$d['statut']] ?? $d['statut']) ?></span></td>
  <td><b><?= h($d['nom']) ?></b><br><span class="gris"><?= h($d['email']) ?></span></td>
  <td><b><?= h($d['sujet']) ?></b><br><?= nl2br(h($d['message'])) ?></td>
  <td>
    <a class="bouton pl" href="mailto:<?= h($d['email']) ?>?subject=<?= rawurlencode('Re : ' . $d['sujet']) ?>">Répondre</a>
    <?= bouton_action('demandes', 'creer_client', (int) $d['id'], 'Créer le client') ?>
    <?php foreach (['traitee' => 'Traitée', 'archivee' => 'Archiver', 'nouvelle' => 'Rouvrir'] as $a => $l): if ($a !== $d['statut']) { echo bouton_action('demandes', $a, (int) $d['id'], $l); } endforeach; ?>
  </td></tr><?php endforeach; ?></table>
<?php endif; ?>
