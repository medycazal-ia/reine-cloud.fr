<?php
function champs_abo(array $v): void
{
    $n = static fn(string $k) => h($v[$k] ?? '');
    ?>
  <div><label>Offre</label><select name="offre"><?php foreach (OFFRES as $k => $lib): ?><option value="<?= h($k) ?>"<?= ($v['offre'] ?? 'socle') === $k ? ' selected' : '' ?>><?= h($lib) ?></option><?php endforeach; ?></select></div>
  <div><label>Intitulé (facultatif)</label><input name="libelle" maxlength="120" placeholder="ex. 3 agents standard" value="<?= $n('libelle') ?>"></div>
  <div><label>Montant mensuel (€)</label><input name="montant" required inputmode="decimal" placeholder="19,99" value="<?= isset($v['montant']) ? h(number_format((float) $v['montant'], 2, ',', '')) : '' ?>"></div>
  <div><label>Prochaine échéance</label><input type="date" name="echeance" required value="<?= $n('prochaine_echeance') ?>"></div>
  <div><label>Mode</label><select name="mode"><option value="auto"<?= ($v['mode'] ?? 'auto') === 'auto' ? ' selected' : '' ?>>Automatique (e-mails envoyés seuls)</option><option value="manuel"<?= ($v['mode'] ?? '') === 'manuel' ? ' selected' : '' ?>>Manuel (j'envoie moi-même)</option></select></div>
  <div class="large"><label>Lien de paiement personnel (facultatif, https)</label><input name="lien_perso" maxlength="500" placeholder="Collez ici un lien Revolut valable pour ce client" value="<?= $n('lien_perso') ?>"></div>
<?php
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_jeton();
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    $q = $pdo->prepare('SELECT * FROM abonnes WHERE id = ?'); $q->execute([$id]); $ab = $q->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($action === 'ajouter' || $action === 'modifier') {
        $offre = (string) ($_POST['offre'] ?? 'autre');
        $montant = montant_saisi($_POST['montant'] ?? '0');
        $date = (string) ($_POST['echeance'] ?? '');
        $mode = ($_POST['mode'] ?? 'auto') === 'manuel' ? 'manuel' : 'auto';
        $lien = trim((string) ($_POST['lien_perso'] ?? ''));
        $libelle = trim((string) ($_POST['libelle'] ?? '')) ?: (OFFRES[$offre] ?? 'Abonnement');
        $client = null; $nom = trim((string) ($_POST['nom'] ?? '')); $email = trim((string) ($_POST['email'] ?? ''));
        $cid = (int) ($_POST['client_id'] ?? 0);
        if ($action === 'modifier' && $ab) { $cid = (int) $ab['client_id']; }
        if ($cid) { $client = client_par_id($pdo, $cid); }
        if ($client) { $nom = $client['entreprise'] ?: $client['nom']; $email = $client['email']; }
        $valide = $nom !== '' && mb_strlen($nom) <= 120 && filter_var($email, FILTER_VALIDATE_EMAIL) && isset(OFFRES[$offre])
            && $montant > 0 && date_valide($date) && ($lien === '' || lien_valide($lien));
        if (!$valide) {
            flash('Vérifiez les champs (client, e-mail, montant, date, lien https).', true);
        } elseif ($action === 'ajouter') {
            if (!$client) { $cid = creer_client($pdo, ['nom' => $nom, 'email' => $email, 'statut' => 'client']); }
            elseif ($client['statut'] === 'prospect') { $pdo->prepare("UPDATE clients SET statut = 'client' WHERE id = ?")->execute([$cid]); }
            $pdo->prepare('INSERT INTO abonnes (nom, email, offre, libelle, montant, jour, prochaine_echeance, mode, lien_perso, client_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$nom, $email, $offre, $libelle, $montant, (int) substr($date, 8, 2), $date, $mode, $lien, $cid]);
            journaliser($pdo, 'ajout', $email, "$libelle, " . euros($montant) . ", échéance $date, mode $mode");
            flash('Abonnement ajouté.');
        } elseif ($ab) {
            $pdo->prepare('UPDATE abonnes SET offre=?, libelle=?, montant=?, jour=?, prochaine_echeance=?, mode=?, lien_perso=? WHERE id=?')
                ->execute([$offre, $libelle, $montant, (int) substr($date, 8, 2), $date, $mode, $lien, $id]);
            journaliser($pdo, 'modification', $ab['email'], "$libelle, " . euros($montant) . ", échéance $date, mode $mode" . ($lien ? ', lien personnel' : ''));
            flash('Modifications enregistrées : elles s\'appliquent aux prochains e-mails.');
        }
    } elseif ($action === 'paye' && $ab) {
        $fid = marquer_paye($pdo, $id, ($_POST['envoyer'] ?? '0') === '1', 'Paiement en ligne');
        $f = $fid ? facture_par_id($pdo, $fid) : null;
        journaliser($pdo, 'paiement', $ab['email'], 'Encaissé : ' . euros($ab['montant']) . ($f ? ', facture ' . $f['numero'] : ''));
        flash('Paiement enregistré' . ($f ? ' — facture ' . $f['numero'] . ' créée' : '') . ' : prochaine échéance avancée d\'un mois.');
    } elseif (($action === 'resilier' || $action === 'reactiver') && $ab) {
        $pdo->prepare('UPDATE abonnes SET actif = ? WHERE id = ?')->execute([$action === 'reactiver' ? 1 : 0, $id]);
        journaliser($pdo, $action === 'resilier' ? 'résiliation' : 'réactivation', $ab['email'], $ab['libelle']);
        flash($action === 'resilier' ? 'Abonnement résilié.' : 'Abonnement réactivé.');
    } elseif ($action === 'envoyer' && $ab) {
        [$lien, $libre] = lien_pour($ab, $cfg);
        [$sujet, $corps] = construire_mail($ab, 'J-7', $lien, $libre);
        $ok = envoyer_mail($ab['email'], $sujet, $corps);
        if ($ok) { journaliser($pdo, 'envoi lien', $ab['email'], "Lien envoyé pour l'échéance " . $ab['prochaine_echeance']); }
        flash($ok ? 'Lien de paiement envoyé.' : 'Échec de l\'envoi.', !$ok);
    } elseif ($action === 'ponctuel') {
        $nom = trim((string) ($_POST['nom'] ?? '')); $email = trim((string) ($_POST['email'] ?? ''));
        $motif = trim((string) ($_POST['motif'] ?? '')); $montant = montant_saisi($_POST['montant'] ?? '0');
        $lien = trim((string) ($_POST['lien'] ?? '')); $libre = false;
        if ($lien === '') { $lien = param_lien('libre', $cfg['liens'] ?? []); $libre = true; }
        if ($nom === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $motif === '' || $montant <= 0 || !lien_valide($lien)) {
            flash('Vérifiez les champs : nom, e-mail, motif, montant et lien https (ou laissez le lien vide pour utiliser le lien à montant libre, à renseigner plus bas).', true);
        } else {
            $reference = reference_ponctuelle();
            [$sujet, $corps] = mail_ponctuel($motif, $montant, $lien, $libre, $reference);
            $ok = envoyer_mail($email, $sujet, $corps);
            if ($ok) { journaliser($pdo, 'lien ponctuel', $email, "$reference — $motif, " . euros($montant)); }
            flash($ok ? 'Demande de paiement envoyée (référence ' . $reference . '). L\'e-mail ne contient aucun nom.' : 'Échec de l\'envoi.', !$ok);
        }
    } elseif ($action === 'liens') {
        $liens = [];
        foreach (['socle', 'agent', 'cadrage', 'surmesure', 'libre'] as $k) { $liens[$k] = trim((string) ($_POST['lien_' . $k] ?? '')); }
        $mauvais = array_filter($liens, static fn($v) => $v !== '' && !lien_valide($v));
        if ($mauvais) {
            flash('Chaque lien doit commencer par https:// (ou rester vide).', true);
        } else {
            foreach ($liens as $k => $v) { set_param($pdo, 'lien_' . $k, $v); }
            $ok = ecrire_paiement_js(dirname(__DIR__, 2) . '/paiement.js', $liens);
            journaliser($pdo, 'liens de paiement', '-', 'Liens enregistrés' . ($ok ? ' et publiés sur le site' : ' (publication impossible)'));
            flash($ok ? 'Liens enregistrés et publiés sur le site : les boutons sont à jour.' : 'Liens enregistrés, mais le fichier paiement.js du site n\'a pas pu être écrit (droits du dossier).', !$ok);
        }
    }
    aller('?o=paiements');
}

$abonnes = $pdo->query('SELECT * FROM abonnes ORDER BY actif DESC, prochaine_echeance')->fetchAll(PDO::FETCH_ASSOC);
$clients = $pdo->query('SELECT id, nom, entreprise, email FROM clients ORDER BY nom')->fetchAll(PDO::FETCH_ASSOC);
$aujourdhui = date('Y-m-d');
$preclient = (int) ($_GET['client'] ?? 0);
$liens_publies = lire_paiement_js(dirname(__DIR__, 2) . '/paiement.js');
$valeur_lien = static fn(string $k) => param('lien_' . $k) !== '' ? param('lien_' . $k) : ($liens_publies[$k] ?? '');
?>
<h1>Paiements</h1>
<p class="note">Abonnements, liens de paiement et paiements ponctuels. Mode automatique : le lien part 7 jours avant l'échéance, un rappel la veille, une relance 3 jours après. Mode manuel : rien ne part sans votre clic.</p>

<h2>Abonnements</h2>
<?php if (!$abonnes): ?><p>Aucun abonnement pour l'instant.</p><?php else: ?>
<table><tr><th>Client</th><th>Offre</th><th>Montant</th><th>Échéance</th><th>Actions</th></tr>
<?php foreach ($abonnes as $a): $retard = $a['actif'] && $a['prochaine_echeance'] < $aujourdhui; ?>
<tr>
  <td><b><?= !empty($a['client_id']) ? '<a href="?o=clients&id=' . (int) $a['client_id'] . '">' . h($a['nom']) . '</a>' : h($a['nom']) ?></b><br><span class="gris"><?= h($a['email']) ?></span><br>
    <?= $a['actif'] ? '<span class="badge' . ($a['mode'] === 'manuel' ? ' m' : '') . '">' . ($a['mode'] === 'manuel' ? 'manuel' : 'automatique') . '</span>' : '<span class="gris">résilié</span>' ?>
    <?= !empty($a['lien_perso']) ? '<span class="badge">lien perso</span>' : '' ?></td>
  <td><?= h($a['libelle']) ?></td>
  <td><?= h(euros($a['montant'])) ?> / mois</td>
  <td class="<?= $retard ? 'retard' : '' ?>"><?= h(date_fr($a['prochaine_echeance'])) ?><?= $retard ? '<br>en retard' : '' ?></td>
  <td>
    <?php if ($a['actif']): ?>
      <?= bouton_action('paiements', 'paye', (int) $a['id'], 'Payé + reçu par e-mail', 'pl', '', ['envoyer' => '1']) ?>
      <?= bouton_action('paiements', 'paye', (int) $a['id'], 'Payé (sans e-mail)', '', '', ['envoyer' => '0']) ?>
      <?= bouton_action('paiements', 'envoyer', (int) $a['id'], 'Envoyer le lien') ?>
      <?= bouton_action('paiements', 'resilier', (int) $a['id'], 'Résilier', '', 'Résilier cet abonnement ?') ?>
    <?php else: ?><?= bouton_action('paiements', 'reactiver', (int) $a['id'], 'Réactiver') ?><?php endif; ?>
    <details><summary>Modifier (montant, date, lien, mode…)</summary>
      <form class="grille" method="post" action="?o=paiements"><?= champ_jeton() ?><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><input type="hidden" name="action" value="modifier">
        <?php champs_abo($a); ?>
        <div style="align-self:end"><button class="pl">Enregistrer</button></div>
      </form>
    </details>
  </td>
</tr>
<?php endforeach; ?></table>
<?php endif; ?>

<h2 id="nouvel-abonnement">Nouvel abonnement</h2>
<form class="grille" method="post" action="?o=paiements"><?= champ_jeton() ?><input type="hidden" name="action" value="ajouter">
  <div class="large"><label>Client existant (sinon, remplir nom et e-mail ci-dessous)</label>
    <select name="client_id"><option value="0">— nouveau client —</option>
      <?php foreach ($clients as $c): ?><option value="<?= (int) $c['id'] ?>"<?= $preclient === (int) $c['id'] ? ' selected' : '' ?>><?= h(($c['entreprise'] ?: $c['nom']) . ($c['email'] ? ' — ' . $c['email'] : '')) ?></option><?php endforeach; ?></select></div>
  <div><label>Nom ou entreprise (nouveau client)</label><input name="nom" maxlength="120"></div>
  <div><label>E-mail (nouveau client)</label><input type="email" name="email" maxlength="200"></div>
  <?php champs_abo([]); ?>
  <div style="align-self:end"><button class="pl">Ajouter l'abonnement</button></div>
</form>

<h2 id="lien-ponctuel">Envoyer un lien de paiement ponctuel</h2>
<p class="note">Pour un client sans abonnement, un montant différent, un cadrage, une mise en place, des heures… L'e-mail envoyé ne contient aucun nom (référence de commande seulement). Laissez le lien vide pour utiliser le lien à montant libre, ou collez un lien Revolut valable créé pour l'occasion. Motif : jamais de donnée de santé.</p>
<form class="grille" method="post" action="?o=paiements"><?= champ_jeton() ?><input type="hidden" name="action" value="ponctuel">
  <div><label>Nom ou entreprise</label><input name="nom" required maxlength="120"></div>
  <div><label>E-mail</label><input type="email" name="email" required maxlength="200"></div>
  <div><label>Motif</label><input name="motif" required maxlength="120" placeholder="ex. Cadrage, acompte mise en place"></div>
  <div><label>Montant (€)</label><input name="montant" required inputmode="decimal" placeholder="199"></div>
  <div class="large"><label>Lien de paiement (facultatif, https)</label><input name="lien" maxlength="500" placeholder="Vide = lien à montant libre"></div>
  <div style="align-self:end"><button class="pl">Envoyer</button></div>
</form>

<h2>Liens de paiement du site</h2>
<p class="note">Ces liens alimentent les boutons « Payer en ligne » du site et les e-mails d'échéance. Collez chaque lien Revolut puis publiez : le site est mis à jour aussitôt. Laissez vide pour désactiver un bouton. Ce ne sont pas des secrets.</p>
<form class="grille" method="post" action="?o=paiements"><?= champ_jeton() ?><input type="hidden" name="action" value="liens">
  <div class="large"><label>Le socle (19,99 €, lien réutilisable)</label><input name="lien_socle" maxlength="500" value="<?= h($valeur_lien('socle')) ?>" placeholder="https://checkout.revolut.com/pay/…"></div>
  <div class="large"><label>L'agent standard (99 € par agent)</label><input name="lien_agent" maxlength="500" value="<?= h($valeur_lien('agent')) ?>"></div>
  <div class="large"><label>Le cadrage (199 €)</label><input name="lien_cadrage" maxlength="500" value="<?= h($valeur_lien('cadrage')) ?>"></div>
  <div class="large"><label>Le sur mesure (selon devis)</label><input name="lien_surmesure" maxlength="500" value="<?= h($valeur_lien('surmesure')) ?>"></div>
  <div class="large"><label>Montant libre (le client saisit le montant)</label><input name="lien_libre" maxlength="500" value="<?= h($valeur_lien('libre')) ?>"></div>
  <div style="align-self:end"><button class="pl">Enregistrer et publier sur le site</button></div>
</form>
