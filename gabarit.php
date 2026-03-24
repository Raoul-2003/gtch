<?php
require_once str_repeat('../', $profondeur??0).'securite.php';
$u      = moi();
$role   = $u['role']??'etudiant';
$racine = str_repeat('../', $profondeur??0);
$init   = strtoupper(substr($u['prenom']??'?',0,1).substr($u['nom']??'',0,1));

$rolesLabel = ['etudiant'=>'Étudiant','professeur'=>'Professeur','rup'=>'RUP','superadmin'=>'Super Admin'];
$libelleRole = $rolesLabel[$role] ?? ucfirst($role);


$notifsReclamations = [];
$nbNotifs = 0;
if (in_array($role, ['professeur', 'rup', 'superadmin'])) {
    $nbNotifs = $pdo->query("SELECT COUNT(*) FROM reclamation WHERE statut = 'en_attente'")->fetchColumn();
    if ($nbNotifs > 0) {
        $notifsReclamations = $pdo->query("
            SELECT r.id_reclamation, r.objet, r.date_creation, u.nom, u.prenom 
            FROM reclamation r 
            JOIN etudiant e ON r.fk_etudiant = e.idetudiant 
            JOIN utilisateurs u ON e.fk_user = u.id_utilisateur 
            WHERE r.statut = 'en_attente' 
            ORDER BY r.date_creation DESC LIMIT 5
        ")->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?=h($titrePage??'GestionNotes')?> — ESI GestionNotes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #E3DDDC; }
        .sidebar { width: 260px; height: 100vh; position: fixed; top: 0; left: 0; background-color: #1e293b; border-right: 1px solid rgba(0,0,0,0.1); box-shadow: 2px 0 10px rgba(0,0,0,0.05); z-index: 1040; overflow-y: auto; transition: transform 0.3s ease; }
        .main-content { margin-left: 260px; min-height: 100vh; display: flex; flex-direction: column; transition: margin-left 0.3s ease; }
        
        .nav-link { color: #94a3b8; border-radius: 0.375rem; margin-bottom: 0.25rem; font-weight: 500; display: flex; align-items: center; gap: 0.75rem; padding: 0.6rem 1rem; transition: all 0.2s; }
        .nav-link:hover { background-color: rgba(255,255,255,0.05); color: #f8fafc; }
        .nav-link.active { background-color: rgba(59, 130, 246, 0.15); color: #60a5fa; font-weight: 600; border-left: 3px solid #3b82f6; }
        .nav-title { font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-top: 1.5rem; margin-bottom: 0.5rem; color: #475569; padding-left: 1rem; }
        
        .topbar { background-color: #ffffff; border-bottom: 1px solid #e9ecef; box-shadow: 0 2px 4px rgba(0,0,0,0.02); height: 70px; display: flex; align-items: center; padding: 0 1.5rem; }
        .content-area { padding: 1.5rem; flex-grow: 1; overflow-y: auto; }
        
        @media (max-width: 991.98px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0; }
        }
        
        /* Dashboard UI Components used by inner pages */
        .gn-carte { background: #fff; border-radius: 0.75rem; border: 1px solid #e9ecef; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); margin-bottom: 1rem; }
        .gn-carte-entete { padding: 1rem 1.25rem; border-bottom: 1px solid #e9ecef; display: flex; align-items: center; justify-content: space-between; }
        .gn-carte-titre { font-weight: 600; font-size: 1rem; color: #343a40; margin: 0; }
        .gn-carte-corps { padding: 1.25rem; }
        .gn-table { width: 100%; border-collapse: collapse; }
        .gn-table th { background: #f8f9fa; font-size: 0.75rem; text-transform: uppercase; color: #6c757d; padding: 0.75rem 1rem; border-bottom: 1px solid #e9ecef; font-weight: 600; }
        .gn-table td { padding: 1rem; border-bottom: 1px solid #f8f9fa; vertical-align: middle; font-size: 0.88rem; color: #495057; }
        .gn-table tbody tr:hover { background-color: #f8f9fa; }
        
        .note-badge { display: inline-block; padding: 0.25rem 0.6rem; border-radius: 99px; font-weight: 600; font-size: 0.8rem; }
        .note-haute { background-color: rgba(25, 135, 84, 0.1); color: #198754; }
        .note-moyenne { background-color: rgba(253, 126, 20, 0.1); color: #fd7e14; }
        .note-basse { background-color: rgba(220, 53, 69, 0.1); color: #dc3545; }
        
        .gn-ariane { display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; margin-bottom: 1.5rem; color: #6c757d; }
        .gn-ariane .actuel { color: #1a56db; font-weight: 600; }

        /* Stats Blocks */
        .gn-stat { background: #fff; border-radius: 0.75rem; border: 1px solid #e9ecef; padding: 1.25rem; display: flex; flex-direction: column; height: 100%; box-shadow: 0 2px 4px -1px rgba(0,0,0,0.02); }
        .gn-stat-ico { width: 44px; height: 44px; border-radius: 0.5rem; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; }
        .gn-stat-val { font-size: 1.5rem; font-weight: 700; color: #212529; margin-bottom: 0.25rem; line-height: 1; }
        .gn-stat-lbl { font-size: 0.8rem; color: #6c757d; font-weight: 500; }
    </style>
    <?= $stylesSupp ?? '' ?>
</head>
<body>


<div class="offcanvas-backdrop fade show d-none" id="sidebarOverlay" style="z-index: 1030;"></div>


<div class="sidebar d-flex flex-column" id="sidebar">
    <div class="d-flex align-items-center gap-3 p-4 border-bottom border-light border-opacity-10">
        <div class="bg-white rounded p-1 shadow-sm d-flex align-items-center justify-content-center" style="height: 48px; width: 48px; flex-shrink: 0;">
            <img src="<?=$racine?>assets/r.png" alt="Logo" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
        </div>
        <div style="min-width: 0;">
            <div class="fw-bold fs-5 text-white text-truncate" style="line-height: 1.2;">GestionNotes</div>
            <div class="small text-white-50" style="font-size: 0.75rem;">INP-HB ESI</div>
        </div>
    </div>
    
    <div class="p-3">
        
        <div class="d-flex align-items-center gap-3 p-3 mb-3 rounded-3" style="background-color: rgba(255,255,255,0.05);">
            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold shadow-sm" style="width: 40px; height: 40px; flex-shrink: 0; font-size: 1.1rem;">
                <?=$init?>
            </div>
            <div style="min-width: 0;">
                <div class="fw-semibold text-truncate text-white" style="font-size: 0.9rem;"><?=h(($u['prenom']??'').' '.($u['nom']??''))?></div>
                <span class="badge bg-primary bg-opacity-25 text-primary-emphasis fw-medium mb-0" style="font-size: 0.7rem; color: #93c5fd !important; border: 1px solid rgba(147,197,253,0.3);"><?=h($libelleRole)?></span>
            </div>
        </div>
        
        
        <div class="nav flex-column">
            <?php foreach($menuItems??[] as $section): ?>
            <div class="nav-title"><?=h($section['titre'])?></div>
            <?php foreach($section['liens'] as $lien): ?>
            <a href="<?=h($lien['href'])?>" class="nav-link <?=($lien['actif']??false)?'active':''?>">
                <i class="bi <?=h($lien['icone'])?> fs-5"></i>
                <span class="text-truncate"><?=h($lien['label'])?></span>
                <?php if(!empty($lien['badge'])): ?>
                <span class="badge bg-danger ms-auto rounded-pill px-2" style="font-size: 0.7rem;"><?=h($lien['badge'])?></span>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div class="mt-auto p-4 border-top border-light border-opacity-10">
        <a href="<?=$racine?>deconnexion.php" class="btn w-100 d-flex align-items-center justify-content-center gap-2 fw-medium py-2" style="background-color: rgba(255,255,255,0.05); color: #f87171; border: 1px solid rgba(248,113,113,0.3); transition: all 0.2s;" onmouseover="this.style.backgroundColor='rgba(248,113,113,0.1)'" onmouseout="this.style.backgroundColor='rgba(255,255,255,0.05)'">
            <i class="bi bi-box-arrow-left"></i> Déconnexion
        </a>
    </div>
</div>


<div class="main-content">
    
    
    <header class="topbar sticky-top d-flex align-items-center justify-content-between z-3">
        <div class="d-flex align-items-center gap-3" style="min-width: max-content;">
            <button class="btn btn-light d-lg-none d-flex align-items-center justify-content-center border" id="btn-menu" style="width: 40px; height: 40px;">
                <i class="bi bi-list fs-4"></i>
            </button>
            <div class="d-none d-md-block">
                <h5 class="mb-0 fw-bold text-dark"><?=h($titrePage??'GestionNotes')?></h5>
                <?php if(!empty($sousTitre)): ?><div class="small text-muted mt-1"><?=h($sousTitre)?></div><?php endif; ?>
            </div>
        </div>
        
        
        <div class="d-none d-sm-flex align-items-center mx-4 flex-grow-1" style="max-width: 400px;">
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0 text-muted border-secondary-subtle"><i class="bi bi-search"></i></span>
                <input type="search" id="global-search" class="form-control bg-light border-start-0 ps-0 border-secondary-subtle form-control-sm py-2" style="font-size: 0.85rem;" placeholder="Rechercher sur cette page (utilisateurs, cours, notes...)">
            </div>
        </div>
        
        <div class="d-flex align-items-center gap-3">
            <?php if (in_array($role, ['professeur', 'rup', 'superadmin'])): ?>
            <div class="dropdown">
                <button class="btn btn-light rounded-circle position-relative border d-flex align-items-center justify-content-center" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="width: 40px; height: 40px;">
                    <i class="bi bi-bell fs-5 text-dark"></i>
                    <?php if ($nbNotifs > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;">
                        <?=$nbNotifs > 99 ? '99+' : $nbNotifs?>
                        <span class="visually-hidden">notifications non lues</span>
                    </span>
                    <?php endif; ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="width: 320px; max-height: 400px; overflow-y: auto;">
                    <li><h6 class="dropdown-header fw-bold text-dark">Notifications (<?=$nbNotifs?>)</h6></li>
                    <?php if (empty($notifsReclamations)): ?>
                        <li><span class="dropdown-item-text text-muted small py-3 text-center d-block">Aucune nouvelle réclamation</span></li>
                    <?php else: ?>
                        <?php foreach($notifsReclamations as $notif): ?>
                        <li>
                            <a class="dropdown-item py-2 border-bottom text-wrap" href="?onglet=reclamations">
                                <div class="d-flex align-items-start gap-2">
                                    <div class="bg-warning bg-opacity-10 text-warning rounded-circle d-flex align-items-center justify-content-center mt-1" style="width: 28px; height: 28px; flex-shrink: 0;">
                                        <i class="bi bi-exclamation-circle-fill" style="font-size: 0.85rem;"></i>
                                    </div>
                                    <div style="min-width: 0;">
                                        <div class="fw-semibold text-dark mb-1" style="font-size: 0.8rem; line-height: 1.3;">Nouv. réclamation : <?=h($notif['objet'])?></div>
                                        <div class="text-muted" style="font-size: 0.72rem;">De : <?=h($notif['prenom'].' '.$notif['nom'])?></div>
                                        <div class="text-muted" style="font-size: 0.65rem;"><i class="bi bi-clock me-1"></i><?=date('d/m/Y H:i', strtotime($notif['date_creation']))?></div>
                                    </div>
                                </div>
                            </a>
                        </li>
                        <?php endforeach; ?>
                        <li><a class="dropdown-item text-center text-primary fw-medium py-2 small" href="?onglet=reclamations" style="background-color: #f8f9fa;">Voir toutes les réclamations</a></li>
                    <?php endif; ?>
                </ul>
            </div>
            <?php endif; ?>

            <div class="d-none d-sm-flex align-items-center gap-2 text-muted small bg-light px-3 py-1 rounded-pill border">
                <i class="bi bi-calendar3"></i>
                <span class="fw-medium"><?=date('d/m/Y')?></span>
            </div>
            
            <a href="<?=$racine?>deconnexion.php" class="btn btn-outline-danger btn-sm rounded-circle d-flex align-items-center justify-content-center d-md-none" style="width: 35px; height: 35px;" title="Déconnexion">
                <i class="bi bi-power"></i>
            </a>
        </div>
    </header>
    
    
    <main class="content-area">

