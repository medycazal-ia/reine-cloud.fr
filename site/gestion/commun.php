<?php
// Fonctions communes de l'espace d'administration.
declare(strict_types=1);

function h($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function montant_saisi($v): float
{
    return (float) str_replace([',', ' '], ['.', ''], (string) $v);
}
function date_valide(string $d): bool
{
    return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;
}
function champ_jeton(): string
{
    return '<input type="hidden" name="jeton" value="' . h($_SESSION['jeton']) . '">';
}
function verifier_jeton(): void
{
    if (!hash_equals((string) $_SESSION['jeton'], (string) ($_POST['jeton'] ?? ''))) {
        http_response_code(400);
        exit('Requête refusée.');
    }
}
function flash(string $message, bool $erreur = false): void
{
    $_SESSION['flash'] = [$message, $erreur];
}
function aller(string $url): void
{
    header('Location: ' . $url, true, 303);
    exit;
}
/** Bouton d'action à ligne (POST + jeton). */
function bouton_action(string $onglet, string $action, int $id, string $libelle, string $classe = '', string $confirme = '', array $extra = []): string
{
    $x = '';
    foreach ($extra as $k => $v) {
        $x .= '<input type="hidden" name="' . h($k) . '" value="' . h($v) . '">';
    }
    return '<form class="ligne" method="post" action="?o=' . h($onglet) . '">' . champ_jeton() . '<input type="hidden" name="id" value="' . $id . '">' . $x
        . '<button class="' . h($classe) . '" name="action" value="' . h($action) . '"'
        . ($confirme !== '' ? ' onclick="return confirm(\'' . h(addslashes($confirme)) . '\')"' : '') . '>' . h($libelle) . '</button></form>';
}
