<?php
session_start();
require '../configuration.php';
require '../securite.php';
verifierAcces('superadmin');

$profondeur = 1;
$u   = moi();
$tab = $_GET['onglet'] ?? 'accueil';


$nbUsers = $pdo->query("SELECT COUNT(*) FROM utilisateurs")->fetchColumn();
$nbEtu   = $pdo->query("SELECT COUNT(*) FROM etudiant")->fetchColumn();
$nbProf  = $pdo->query("SELECT COUNT(*) FROM professeur")->fetchColumn();
$nbCours = $pdo->query("SELECT COUNT(*) FROM cours")->fetchColumn();
$nbNotes = $pdo->query("SELECT COUNT(*) FROM note")->fetchColumn();
$nbRecla = $pdo->query("SELECT COUNT(*) FROM reclamation WHERE statut IN('en_attente','en_cours')")->fetchColumn();
$nbReset = $pdo->query("SELECT COUNT(*) FROM demande_reset WHERE statut='en_attente'")->fetchColumn();

if (isset($_POST['traiter_reset'])) {
    $id = (int)$_POST['id_demande'];
    $email_login = $_POST['login_email'];
    
    
    $new_mdp = 'EIT@' . mt_rand(1000, 9999);
    $hash = password_hash($new_mdp, PASSWORD_BCRYPT);
    
    
    $stmt = $pdo->prepare("UPDATE utilisateurs SET mot_de_passe=? WHERE login=? OR email=?");
    $stmt->execute([$hash, $email_login, $email_login]);
    
    
    $pdo->prepare("UPDATE demande_reset SET statut='traite', date_traitement=CURRENT_TIMESTAMP WHERE id_demande=?")->execute([$id]);
    
    journaliser($pdo, 'RESET_MDP_TRAITE', 'demande_reset', "id=$id");
    
    $_SESSION['flash_mdp'] = "Le mot de passe pour <strong>" . h($email_login) . "</strong> a été réinitialisé. Nouveau mot de passe : <strong class='fs-5 text-primary'>$new_mdp</strong>";
    header('Location: ?onglet=reset_mdp');
    exit;
}


$logs = $pdo->query("
    SELECT h.*, u.nom, u.prenom, u.login, u.role
    FROM historique_actions h
    LEFT JOIN utilisateurs u ON h.fk_utilisateur = u.id_utilisateur
    ORDER BY h.cree_le DESC LIMIT 100
")->fetchAll();


$statsRoles = $pdo->query("SELECT role, COUNT(*) as nb FROM utilisateurs GROUP BY role")->fetchAll(PDO::FETCH_KEY_PAIR);


$moyennes = $pdo->query("
    SELECT e.filiere,
           ROUND(SUM(n.valeur * c.coefficient) / SUM(c.coefficient), 2) as moyenne,
           COUNT(DISTINCT e.idetudiant) as nb_etu
    FROM note n
    JOIN cours c ON n.fk_cours = c.id_cours
    JOIN etudiant e ON n.fk_etudiant = e.idetudiant
    WHERE e.filiere IS NOT NULL
    GROUP BY e.filiere ORDER BY moyenne DESC
")->fetchAll();


$classement = $pdo->query("
    SELECT u.nom, u.prenom, e.matricule, e.filiere,
           ROUND(SUM(n.valeur * c.coefficient) / SUM(c.coefficient), 2) as moyenne
    FROM note n
    JOIN etudiant e ON n.fk_etudiant = e.idetudiant
    JOIN utilisateurs u ON e.fk_user = u.id_utilisateur
    JOIN cours c ON n.fk_cours = c.id_cours
    GROUP BY e.idetudiant
    ORDER BY moyenne DESC
")->fetchAll();

function classeNote(float $n): string {
    if ($n >= 14) return 'note-haute';
    if ($n >= 10) return 'note-moyenne';
    return 'note-basse';
}
function mentionNote(float $n): array {
    if ($n >= 16) return ['Très Bien','success'];
    if ($n >= 14) return ['Bien','primary'];
    if ($n >= 12) return ['Assez Bien','info'];
    if ($n >= 10) return ['Passable','warning'];
    return               ['Insuffisant','danger'];
}

$titrePage = 'Super Administration';
$sousTitre = 'Accès complet — tous les droits';
$menuItems = [[
    'titre'=>'Administration',
    'liens'=>[
        ['label'=>'Tableau de bord', 'icone'=>'bi-grid-1x2-fill',  'href'=>'?onglet=accueil',  'actif'=>$tab==='accueil'],
        ['label'=>'Gestion complète','icone'=>'bi-sliders',         'href'=>'../rup/tableau_de_bord.php','actif'=>false],
        ['label'=>'Gestion Notes',   'icone'=>'bi-journal-check',   'href'=>'../rup/tableau_de_bord.php?onglet=notes','actif'=>false],
        ['label'=>'Reset MDP',       'icone'=>'bi-key-fill',        'href'=>'?onglet=reset_mdp', 'actif'=>$tab==='reset_mdp', 'badge'=>$nbReset>0?$nbReset:0, 'badgeClass'=>'bg-danger'],
        ['label'=>'Rapports',        'icone'=>'bi-bar-chart-fill',  'href'=>'?onglet=rapports', 'actif'=>$tab==='rapports'],
        ['label'=>'Rôles & Droits',  'icone'=>'bi-shield-fill-check','href'=>'?onglet=roles',  'actif'=>$tab==='roles'],
        ['label'=>'Historique',      'icone'=>'bi-clock-history',   'href'=>'?onglet=historique','actif'=>$tab==='historique',
         'badge'=>count($logs),'badgeClass'=>'bg-secondary'],
    ]
]];
include '../gabarit.php';
?>


<?php if($tab==='accueil'): ?>
<nav aria-label="breadcrumb" class="gn-ariane"><span>SuperAdmin</span><i class="bi bi-chevron-right"></i><span class="actuel">Tableau de bord</span></nav>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-2"><div class="gn-stat s-bleu"><div class="gn-stat-ico bg-primary bg-opacity-10 text-primary mb-2"><i class="bi bi-people-fill"></i></div><div class="gn-stat-val"><?=$nbUsers?></div><div class="gn-stat-lbl">Utilisateurs</div></div></div>
    <div class="col-6 col-md-2"><div class="gn-stat s-bleu"><div class="gn-stat-ico bg-primary bg-opacity-10 text-primary mb-2"><i class="bi bi-mortarboard-fill"></i></div><div class="gn-stat-val"><?=$nbEtu?></div><div class="gn-stat-lbl">Étudiants</div></div></div>
    <div class="col-6 col-md-2"><div class="gn-stat s-vert"><div class="gn-stat-ico bg-success bg-opacity-10 text-success mb-2"><i class="bi bi-person-workspace"></i></div><div class="gn-stat-val"><?=$nbProf?></div><div class="gn-stat-lbl">Professeurs</div></div></div>
    <div class="col-6 col-md-2"><div class="gn-stat s-orange"><div class="gn-stat-ico bg-warning bg-opacity-10 text-warning mb-2"><i class="bi bi-book-fill"></i></div><div class="gn-stat-val"><?=$nbCours?></div><div class="gn-stat-lbl">Cours</div></div></div>
    <div class="col-6 col-md-2"><div class="gn-stat s-cyan"><div class="gn-stat-ico bg-info bg-opacity-10 text-info mb-2"><i class="bi bi-journal-check"></i></div><div class="gn-stat-val"><?=$nbNotes?></div><div class="gn-stat-lbl">Notes saisies</div></div></div>
    <div class="col-6 col-md-2"><div class="gn-stat s-rouge"><div class="gn-stat-ico bg-danger bg-opacity-10 text-danger mb-2"><i class="bi bi-chat-square-text"></i></div><div class="gn-stat-val"><?=$nbRecla?></div><div class="gn-stat-lbl">Réclamations</div></div></div>
</div>

<div class="row g-3">
    
    <div class="col-md-4">
        <div class="gn-carte h-100">
            <div class="gn-carte-entete"><div class="gn-carte-titre">Répartition des rôles</div></div>
            <div class="gn-carte-corps">
                <?php $ri=['etudiant'=>['Étudiants','primary'],'professeur'=>['Professeurs','success'],'rup'=>['RUP','warning'],'superadmin'=>['Super Admin','danger']];
                foreach($ri as $rk=>[$rl,$rc]):
                    $nb=$statsRoles[$rk]??0; $pct=$nbUsers?round(($nb/$nbUsers)*100):0; ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span style="font-size:.82rem;font-weight:600"><?=$rl?></span>
                        <span class="text-muted" style="font-size:.78rem"><?=$nb?> (<?=$pct?>%)</span>
                    </div>
                    <div class="progress" style="height:8px;border-radius:99px">
                        <div class="progress-bar bg-<?=$rc?>" style="width:<?=$pct?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    
    <div class="col-md-4">
        <div class="gn-carte h-100">
            <div class="gn-carte-entete"><div class="gn-carte-titre">Top 5 étudiants</div><a href="?onglet=rapports" class="btn btn-sm btn-outline-primary">Voir tout</a></div>
            <div class="table-responsive"><table class="gn-table">
                <thead><tr><th>#</th><th>Étudiant</th><th>Moyenne</th></tr></thead>
                <tbody>
                <?php foreach(array_slice($classement,0,5) as $i=>$e): ?>
                <tr>
                    <td class="fw-bold text-muted"><?=$i+1?></td>
                    <td><?=h($e['prenom'].' '.$e['nom'])?><br><small class="text-muted"><?=h($e['matricule']??'')?></small></td>
                    <td><span class="note-badge <?=classeNote($e['moyenne'])?>"><?=$e['moyenne']?>/20</span></td>
                </tr>
                <?php endforeach; if(empty($classement)): ?><tr><td colspan="3" class="text-center text-muted py-3">Pas de données</td></tr><?php endif; ?>
                </tbody>
            </table></div>
        </div>
    </div>

    
    <div class="col-md-4">
        <div class="gn-carte h-100">
            <div class="gn-carte-entete"><div class="gn-carte-titre">Activité récente</div><a href="?onglet=historique" class="btn btn-sm btn-outline-primary">Voir tout</a></div>
            <div style="max-height:280px;overflow-y:auto">
            <?php foreach(array_slice($logs,0,8) as $l):
                $rc=['etudiant'=>'primary','professeur'=>'success','rup'=>'warning','superadmin'=>'danger'][$l['role']??'']??'secondary';
            ?>
            <div class="px-3 py-2 border-bottom d-flex align-items-start gap-2">
                <span class="badge bg-<?=$rc?> rounded-pill mt-1" style="font-size:.58rem;flex-shrink:0"><?=$l['role']??'sys'?></span>
                <div style="min-width:0">
                    <div style="font-size:.78rem;font-weight:600;color:#1e293b"><?=h($l['action'])?></div>
                    <div class="text-muted" style="font-size:.68rem"><?=h(($l['prenom']??'').' '.($l['nom']??''))?> — <?=date('d/m H:i',strtotime($l['cree_le']))?></div>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>


<?php elseif($tab==='rapports'): ?>
<nav aria-label="breadcrumb" class="gn-ariane"><span>SuperAdmin</span><i class="bi bi-chevron-right"></i><span class="actuel">Rapports</span></nav>
<div class="row g-3">
    <div class="col-md-5">
        <div class="gn-carte">
            <div class="gn-carte-entete"><div class="gn-carte-titre">Moyenne par filière</div></div>
            <?php if(empty($moyennes)): ?><div class="text-center py-4 text-muted">Pas assez de données</div>
            <?php else: ?><div class="gn-carte-corps">
                <?php foreach($moyennes as $m): [$ml,$mc]=mentionNote($m['moyenne']); ?>
                <div class="d-flex align-items-center justify-content-between py-2 border-bottom">
                    <div><div style="font-size:.83rem;font-weight:600"><?=h($m['filiere'])?></div><small class="text-muted"><?=$m['nb_etu']?> étudiant(s)</small></div>
                    <span class="note-badge <?=classeNote($m['moyenne'])?>"><?=$m['moyenne']?>/20</span>
                </div>
                <?php endforeach; ?>
            </div><?php endif; ?>
        </div>
    </div>
    <div class="col-md-7">
        <div class="gn-carte">
            <div class="gn-carte-entete"><div class="gn-carte-titre">Classement général</div><span class="badge bg-primary rounded-pill"><?=count($classement)?></span></div>
            <div class="table-responsive"><table class="gn-table">
                <thead><tr><th>#</th><th>Étudiant</th><th>Filière</th><th>Moyenne</th><th>Mention</th></tr></thead>
                <tbody>
                <?php foreach($classement as $i=>$e): [$ml,$mc]=mentionNote($e['moyenne']); ?>
                <tr>
                    <td class="fw-bold text-muted"><?=$i+1?></td>
                    <td><strong><?=h($e['prenom'].' '.$e['nom'])?></strong><br><small class="text-muted"><?=h($e['matricule']??'')?></small></td>
                    <td class="text-muted"><?=h($e['filiere']??'—')?></td>
                    <td><span class="note-badge <?=classeNote($e['moyenne'])?>"><?=$e['moyenne']?>/20</span></td>
                    <td><span class="badge bg-<?=$mc?> rounded-pill"><?=$ml?></span></td>
                </tr>
                <?php endforeach; if(empty($classement)): ?><tr><td colspan="5" class="text-center text-muted py-3">Aucune donnée</td></tr><?php endif; ?>
                </tbody>
            </table></div>
        </div>
    </div>
</div>


<?php elseif($tab==='roles'): ?>
<nav aria-label="breadcrumb" class="gn-ariane"><span>SuperAdmin</span><i class="bi bi-chevron-right"></i><span class="actuel">Rôles & Droits</span></nav>
<div class="row g-3">
<?php $rolesDef=[
    'etudiant'   =>['Étudiant',  'primary','bi-mortarboard-fill','Niveau 1',['Consulter matières et planches','Voir ses propres notes','Soumettre une réclamation','Se connecter']],
    'professeur' =>['Professeur','success','bi-person-workspace','Niveau 2', ['Tout ce que peut faire l\'étudiant','Saisir et modifier les notes','Voir les réclamations','Gérer ses cours']],
    'rup'        =>['RUP',       'warning','bi-briefcase-fill',  'Niveau 3', ['Tout ce que peut faire le prof','Gérer les utilisateurs (CRUD)','Gérer tous les cours','Gérer le planning','Valider les réclamations']],
    'superadmin' =>['Super Admin','danger','bi-shield-fill-check','Niveau 4',['Tout ce que peut faire le RUP','Configuration du système','Rapports et statistiques globaux','Gestion des rôles','Audit et historique complet']],
];
foreach($rolesDef as $rk=>[$rl,$rc,$ri,$niv,$perms]): ?>
<div class="col-md-3">
    <div class="gn-carte h-100" style="border-top:3px solid var(--bs-<?=$rc?>)">
        <div class="gn-carte-corps">
            <div class="mb-2"><i class="bi <?=$ri?> text-<?=$rc?> fs-3"></i></div>
            <div class="text-muted mb-1" style="font-size:.62rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase"><?=$niv?></div>
            <div class="fw-bold mb-1" style="font-size:1rem"><?=$rl?></div>
            <div class="text-muted mb-3" style="font-size:.75rem"><?=$statsRoles[$rk]??0?> utilisateur(s)</div>
            <ul class="list-unstyled mb-0">
            <?php foreach($perms as $p): ?>
            <li class="d-flex align-items-start gap-2 mb-1" style="font-size:.75rem;color:#475569">
                <i class="bi bi-check-circle-fill text-<?=$rc?> mt-1" style="font-size:11px;flex-shrink:0"></i>
                <?=$p?>
            </li>
            <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>


<?php elseif($tab==='historique'): ?>
<nav aria-label="breadcrumb" class="gn-ariane"><span>SuperAdmin</span><i class="bi bi-chevron-right"></i><span class="actuel">Historique des actions</span></nav>
<div class="gn-carte">
    <div class="gn-carte-entete"><div class="gn-carte-titre">Journal d'activité système</div><span class="badge bg-secondary rounded-pill"><?=count($logs)?> entrées</span></div>
    <div class="table-responsive"><table class="gn-table">
        <thead><tr><th>Date / Heure</th><th>Utilisateur</th><th>Rôle</th><th>Action</th><th>Table</th><th>IP</th></tr></thead>
        <tbody>
        <?php $ac=['CONNEXION'=>'success','DECONNEXION'=>'secondary','NOTE_SAISIE'=>'primary','USER_CREATION'=>'info','USER_SUPPRESSION'=>'danger','RECLA_REPONSE'=>'warning','RECLAMATION_CREATION'=>'warning'];
        $rc=['etudiant'=>'primary','professeur'=>'success','rup'=>'warning','superadmin'=>'danger'];
        foreach($logs as $l): ?>
        <tr>
            <td><small><?=date('d/m/Y H:i:s',strtotime($l['cree_le']))?></small></td>
            <td><?=$l['login']?h(($l['prenom']??'').' '.($l['nom']??'')):'<em class="text-muted">—</em>'?></td>
            <td><?php if($l['role']): ?><span class="badge bg-<?=$rc[$l['role']]??'secondary'?> rounded-pill" style="font-size:.62rem"><?=$l['role']?></span><?php endif; ?></td>
            <td><span class="badge bg-<?=$ac[$l['action']]??'secondary'?> bg-opacity-10 text-<?=$ac[$l['action']]??'secondary'?>" style="font-size:.72rem"><?=h($l['action'])?></span><?php if($l['details']): ?><br><small class="text-muted"><?=h($l['details'])?></small><?php endif; ?></td>
            <td><small class="text-muted"><?=h($l['table_concernee']??'')?></small></td>
            <td><small class="text-muted"><?=h($l['adresse_ip']??'')?></small></td>
        </tr>
        <?php endforeach; if(empty($logs)): ?><tr><td colspan="6" class="text-center text-muted py-4">Aucune activité enregistrée</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div>


<?php elseif($tab==='reset_mdp'): ?>
<nav aria-label="breadcrumb" class="gn-ariane"><span>SuperAdmin</span><i class="bi bi-chevron-right"></i><span class="actuel">Demandes de Réinitialisation</span></nav>

<?php if (!empty($_SESSION['flash_mdp'])): ?>
<div class="alert alert-success d-flex align-items-center mb-4" role="alert">
    <i class="bi bi-check-circle-fill me-2 fs-4"></i>
    <div><?= $_SESSION['flash_mdp'] ?></div>
</div>
<?php unset($_SESSION['flash_mdp']); endif; ?>

<?php
$demandes = [];
try {
    $demandes = $pdo->query("SELECT * FROM demande_reset ORDER BY date_demande DESC")->fetchAll();
} catch(Exception $e) {}
?>
<div class="gn-carte">
    <div class="gn-carte-entete"><div class="gn-carte-titre">Demandes de mot de passe oublié</div><span class="badge bg-secondary rounded-pill"><?=count($demandes)?></span></div>
    <div class="table-responsive"><table class="gn-table">
        <thead><tr><th>Date</th><th>Login/Email</th><th>Statut</th><th>Date Traitement</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach($demandes as $d): ?>
        <tr>
            <td><small><?=date('d/m/Y H:i',strtotime($d['date_demande']))?></small></td>
            <td class="fw-bold"><?=h($d['login_email'])?></td>
            <td>
                <?php if($d['statut']==='en_attente'): ?>
                    <span class="badge bg-danger rounded-pill">En attente</span>
                <?php else: ?>
                    <span class="badge bg-success rounded-pill">Traité</span>
                <?php endif; ?>
            </td>
            <td><small class="text-muted"><?= $d['date_traitement'] ? date('d/m/Y H:i',strtotime($d['date_traitement'])) : '—' ?></small></td>
            <td>
                <?php if($d['statut']==='en_attente'): ?>
                <form method="POST" style="display:inline-block">
                    <input type="hidden" name="id_demande" value="<?=$d['id_demande']?>">
                    <input type="hidden" name="login_email" value="<?=h($d['login_email'])?>">
                    <button type="submit" name="traiter_reset" class="btn btn-sm btn-success rounded-pill px-3"><i class="bi bi-arrow-repeat me-1"></i>Réinitialiser</button>
                </form>
                <?php else: ?>
                <em class="text-muted small">Terminé</em>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; if(empty($demandes)): ?><tr><td colspan="5" class="text-center text-muted py-4">Aucune demande enregistrée</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div>
<?php endif; ?>

<?php include '../gabarit_fin.php'; ?>
