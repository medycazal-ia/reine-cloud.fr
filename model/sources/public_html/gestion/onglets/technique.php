<?php
$racine = dirname(__DIR__, 2);   // public_html

if (isset($_GET['export'])) {
    ob_end_clean();
    $t = (string) $_GET['export'];
    if (!in_array($t, TABLES_EXPORT, true)) {
        http_response_code(404);
        exit('Table inconnue.');
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $t . '-' . date('Y-m-d') . '.csv"');
    echo export_csv($pdo, $t);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_jeton();
    if (($_POST['action'] ?? '') === 'test_mail') {
        $ok = envoyer_mail(COPIE, '[Test] Administration {{DOMAINE}}', "Ceci est un e-mail de test envoyé depuis l'administration, le " . date('d/m/Y à H:i') . ".\n");
        flash($ok ? 'E-mail de test envoyé à ' . COPIE . ' (vérifiez aussi les indésirables).' : 'Échec de l\'envoi de l\'e-mail de test.', !$ok);
    }
    aller('?o=technique');
}

function pastille(bool $ok, string $oui = 'OK', string $non = 'À vérifier'): string
{
    return $ok ? '<span class="badge v">' . h($oui) . '</span>' : '<span class="badge r">' . h($non) . '</span>';
}

$cron = param('cron_dernier');
$cron_ok = $cron !== '' && strtotime($cron) > time() - 36 * 3600;
$fichiers = ['index.html' => 'Page d\'accueil', 'commander.html' => 'Page de commande', 'payer.html' => 'Page de règlement', 'merci.html' => 'Page de remerciement',
    'cgv.html' => 'CGV', 'mentions-legales.html' => 'Mentions légales', 'confidentialite.html' => 'Confidentialité', 'contact.php' => 'Formulaire de contact', 'domaine.php' => 'Recherche de nom de domaine', 'code.php' => 'Vérification des codes parrain',
    'paiement.js' => 'Liens de paiement', 'facture.php' => 'Affichage des factures', 'favicon.ico' => 'Icône du site'];
$comptes = [];
foreach (TABLES_EXPORT as $t) { $comptes[$t] = (int) $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn(); }
$echeance_h = param('hebergement_echeance');
?>
<h1>Site et technique</h1>
<p class="note">État de santé du site et de l'espace d'administration.</p>

<h2>Composants</h2>
<table>
<tr><td>Connexion sécurisée (HTTPS)</td><td><?= pastille(!empty($_SERVER['HTTPS'])) ?></td></tr>
<tr><td>Base de données</td><td><?= pastille(true, 'Connectée') ?> <span class="gris"><?= h($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)) ?></span></td></tr>
<tr><td>Fichier de réglages hors du dossier public</td><td><?= pastille(is_file(dirname($racine) . '/config-{{SLUG}}.php'), 'Présent', 'Introuvable') ?></td></tr>
<tr><td>Envoi d'e-mails (PHP)</td><td><?= pastille(function_exists('mail'), 'Disponible', 'Indisponible') ?></td></tr>
<tr><td>Tâche automatique quotidienne</td><td><?= pastille($cron_ok, 'Active', 'Inactive') ?> <span class="gris"><?= $cron ? 'dernier passage : ' . h($cron) : 'jamais lancée' ?></span></td></tr>
<tr><td>Version de PHP</td><td><?= h(PHP_VERSION) ?></td></tr>
<tr><td>Échéance de l'hébergement</td><td><?= h(date_valide($echeance_h) ? date_fr($echeance_h) : 'non renseignée') ?> <span class="gris">(modifiable dans Paramètres)</span></td></tr>
</table>
<form method="post" action="?o=technique" style="margin-top:10px"><?= champ_jeton() ?><input type="hidden" name="action" value="test_mail"><button class="pl">Envoyer un e-mail de test</button></form>

<h2>Fichiers du site</h2>
<table><tr><th>Fichier</th><th>Rôle</th><th>État</th></tr>
<?php foreach ($fichiers as $f => $role): ?><tr><td><?= h($f) ?></td><td><?= h($role) ?></td><td><?= pastille(is_file($racine . '/' . $f), 'En ligne', 'Absent') ?></td></tr><?php endforeach; ?></table>

<h2>Sauvegarde des données</h2>
<p class="note">Téléchargez régulièrement les tableaux (fichiers CSV lisibles avec Excel) et rangez-les en lieu sûr. Ils contiennent des données personnelles : ne les partagez pas.</p>
<table><tr><th>Tableau</th><th>Lignes</th><th>Export</th></tr>
<?php foreach ($comptes as $t => $n): ?><tr><td><?= h($t) ?></td><td><?= $n ?></td><td><a class="bouton" href="?o=technique&export=<?= h($t) ?>">Télécharger</a></td></tr><?php endforeach; ?></table>

<h2>Rappels utiles</h2>
<ul class="note">
  <li>Sauvegarde complète du site : cPanel &gt; Fichiers &gt; Sauvegardes.</li>
  <li>Ne jamais enregistrer de donnée de santé dans cet espace (notes, motifs, intitulés, factures).</li>
  <li>Hébergement actuel non certifié HDS : aucune donnée de santé ne doit y être hébergée.</li>
</ul>
