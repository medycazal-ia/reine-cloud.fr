<?php
$champs = [
    'Identité de l\'éditeur (utilisée sur les factures)' => [
        'ent_nom' => 'Nom et prénom', 'ent_commercial' => 'Nom commercial', 'ent_forme' => 'Forme juridique', 'ent_siret' => 'SIRET',
        'ent_adresse' => 'Adresse', 'ent_email' => 'E-mail', 'ent_tel' => 'Téléphone', 'ent_iban' => 'IBAN (facultatif, affiché sur les factures)',
    ],
    'Facturation' => [
        'mention_tva' => 'Mention de TVA', 'cond_paiement' => 'Conditions de paiement (pied de facture)', 'prefixe_facture' => 'Préfixe des numéros de facture',
    ],
    'Suivi' => [
        'seuil_ca' => 'Seuil d\'alerte de chiffre d\'affaires annuel, en € (facultatif)', 'hebergement_echeance' => 'Échéance de l\'hébergement (AAAA-MM-JJ)',
    ],
];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_jeton();
    $erreurs = [];
    foreach ($champs as $groupe) {
        foreach ($groupe as $k => $_) {
            $v = trim((string) ($_POST[$k] ?? ''));
            if ($k === 'hebergement_echeance' && $v !== '' && !date_valide($v)) { $erreurs[] = 'Date d\'échéance invalide.'; continue; }
            if ($k === 'ent_email' && $v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) { $erreurs[] = 'E-mail invalide.'; continue; }
            if ($k === 'seuil_ca' && $v !== '' && montant_saisi($v) <= 0) { $erreurs[] = 'Seuil invalide.'; continue; }
            set_param($pdo, $k, mb_substr($v, 0, 1000));
        }
    }
    journaliser($pdo, 'paramètres', '-', 'Paramètres enregistrés');
    flash($erreurs ? implode(' ', $erreurs) . ' Les autres champs sont enregistrés.' : 'Paramètres enregistrés.', (bool) $erreurs);
    aller('?o=parametres');
}
?>
<h1>Paramètres</h1>
<p class="note">Ces informations apparaissent sur vos factures. Si un champ est laissé vide, la valeur par défaut est utilisée.</p>
<form method="post" action="?o=parametres"><?= champ_jeton() ?>
<?php foreach ($champs as $titre => $groupe): ?>
<h2><?= h($titre) ?></h2>
<div class="grille" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:10px;background:#fff;padding:16px;border:1px solid #E4DCCD">
  <?php foreach ($groupe as $k => $l): $long = in_array($k, ['cond_paiement', 'ent_adresse'], true); ?>
  <div<?= $long ? ' style="grid-column:1/-1"' : '' ?>><label style="font-size:.74rem;text-transform:uppercase;letter-spacing:.06em;color:#6B6275;display:block"><?= h($l) ?></label>
    <?= $long ? '<textarea name="' . h($k) . '" rows="3" style="width:100%;padding:8px;font:inherit">' . h(param($k)) . '</textarea>'
              : '<input name="' . h($k) . '" value="' . h(param($k)) . '" style="width:100%;padding:8px;font:inherit;box-sizing:border-box">' ?></div>
  <?php endforeach; ?>
</div>
<?php endforeach; ?>
<p><button class="pl">Enregistrer les paramètres</button></p>
</form>
