<?php






const HIERARCHIE = [
    'etudiant'   => 1,
    'professeur' => 2,
    'rup'        => 3,
    'superadmin' => 4,
];


function verifierAcces(string $roleMinimum = 'etudiant'): void
{
    if (session_status() === PHP_SESSION_NONE) session_start();

    
    if (empty($_SESSION['utilisateur'])) {
        header('Location: ' . _racine() . 'connexion.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }

    
    $monRole    = $_SESSION['utilisateur']['role'] ?? 'etudiant';
    $monNiveau  = HIERARCHIE[$monRole]        ?? 0;
    $niveauReq  = HIERARCHIE[$roleMinimum]    ?? 99;

    if ($monNiveau < $niveauReq) {
        
        header('Location: ' . _racine() . 'connexion.php?erreur=acces');
        exit;
    }
}


function peutFaire(string $roleMinimum): bool
{
    $monNiveau = HIERARCHIE[moi()['role'] ?? 'etudiant'] ?? 0;
    $niveauReq = HIERARCHIE[$roleMinimum] ?? 99;
    return $monNiveau >= $niveauReq;
}


function _racine(): string
{
    $profondeur = substr_count(str_replace('\\', '/', dirname($_SERVER['PHP_SELF'])), '/') - 1;
    return str_repeat('../', max($profondeur, 0));
}


function moi(): array
{
    return $_SESSION['utilisateur'] ?? [];
}


function initiales(): string
{
    $u = moi();
    return strtoupper(
        substr($u['prenom'] ?? '?', 0, 1) .
        substr($u['nom']    ?? '',  0, 1)
    );
}


function libelleRole(string $role): string
{
    return [
        'etudiant'   => 'Étudiant',
        'professeur' => 'Professeur',
        'rup'        => 'RUP',
        'superadmin' => 'Super Admin',
    ][$role] ?? ucfirst($role);
}


function couleurRole(string $role): string
{
    return [
        'etudiant'   => 'primary',
        'professeur' => 'success',
        'rup'        => 'warning',
        'superadmin' => 'danger',
    ][$role] ?? 'secondary';
}


function monNiveau(): int
{
    return HIERARCHIE[moi()['role'] ?? 'etudiant'] ?? 0;
}