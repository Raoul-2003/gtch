<?php
session_start();
require '../configuration.php';
require '../securite.php';
verifierAcces('rup');

$profondeur = 1;
$u   = moi();
$tab = $_GET['onglet'] ?? 'accueil';


$nbEtu   = $pdo->query("SELECT COUNT(*) FROM etudiant")->fetchColumn();
$nbProf  = $pdo->query("SELECT COUNT(*) FROM professeur")->fetchColumn();
$nbCours = $pdo->query("SELECT COUNT(*) FROM cours")->fetchColumn();
$nbRecla = $pdo->query("SELECT COUNT(*) FROM reclamation WHERE statut IN('en_attente','en_cours')")->fetchColumn();


$utilisateurs = $pdo->query("
    SELECT u.*, e.idetudiant, e.matricule, e.filiere
    FROM utilisateurs u
    LEFT JOIN etudiant e ON e.fk_user = u.id_utilisateur
    ORDER BY u.role, u.nom
")->fetchAll();

$cours = $pdo->query("
    SELECT c.*, p.nom as prof_nom, p.prenom as prof_prenom
    FROM cours c JOIN professeur p ON c.fk_prof = p.id_prof
    ORDER BY c.semestre, c.libelle
")->fetchAll();

$professeurs = $pdo->query("
    SELECT p.id_prof, u.nom, u.prenom
    FROM professeur p JOIN utilisateurs u ON p.fk_user = u.id_utilisateur
    ORDER BY u.nom
")->fetchAll();

$planches = $pdo->query("
    SELECT pl.*, c.libelle as cours_libelle, p.nom as auteur_nom
    FROM planche pl
    JOIN cours c ON pl.fk_cours = c.id_cours
    JOIN professeur p ON c.fk_prof = p.id_prof
    ORDER BY pl.date_creation DESC
")->fetchAll();

$planning = $pdo->query("
    SELECT e.*, c.libelle as cours_libelle
    FROM emploi_du_temps e JOIN cours c ON e.fk_cours = c.id_cours
    ORDER BY e.jour, e.heure_debut
")->fetchAll();

$reclamations = $pdo->query("
    SELECT r.*, u.nom as etu_nom, u.prenom as etu_prenom, e.matricule
    FROM reclamation r
    JOIN etudiant e ON r.fk_etudiant = e.idetudiant
    JOIN utilisateurs u ON e.fk_user = u.id_utilisateur
    WHERE r.statut IN ('en_attente', 'en_cours')
    ORDER BY r.date_creation DESC
")->fetchAll();

$toutesNotes = [];
if ($tab === 'notes') {
    $toutesNotes = $pdo->query("
        SELECT n.*, u.nom as etu_nom, u.prenom as etu_prenom,
               e.matricule, c.libelle as cours_libelle
        FROM note n
        JOIN etudiant e ON n.fk_etudiant = e.idetudiant
        JOIN utilisateurs u ON e.fk_user = u.id_utilisateur
        JOIN cours c ON n.fk_cours = c.id_cours
        ORDER BY n.date_saisie DESC LIMIT 200
    ")->fetchAll();
    
    $etudiants = $pdo->query("
        SELECT e.idetudiant, e.matricule, u.nom, u.prenom
        FROM etudiant e JOIN utilisateurs u ON e.fk_user = u.id_utilisateur
        ORDER BY u.nom, u.prenom
    ")->fetchAll();
}

function classeNote(float $n): string {
    if ($n >= 14) return 'note-haute';
    if ($n >= 10) return 'note-moyenne';
    return 'note-basse';
}

$msg = $err = '';


if (isset($_POST['ajouter_user'])) {
    $nom  = trim($_POST['nom']); $prenom = trim($_POST['prenom']);
    $email = trim($_POST['email']); $login = trim($_POST['login']);
    $mdp  = $_POST['mdp']; $role = $_POST['role'];
    $matricule = trim($_POST['matricule'] ?? '');
    $filiere   = trim($_POST['filiere'] ?? '');
    if ($nom && $prenom && $email && $login && $mdp) {
        try {
            $hash = password_hash($mdp, PASSWORD_BCRYPT);
            $pdo->prepare("INSERT INTO utilisateurs (nom,prenom,email,login,mot_de_passe,role,actif) VALUES (?,?,?,?,?,?,1)")
                ->execute([$nom,$prenom,$email,$login,$hash,$role]);
            $uid = $pdo->lastInsertId();
            if ($role === 'etudiant' && $matricule)
                $pdo->prepare("INSERT INTO etudiant (fk_user,matricule,filiere) VALUES (?,?,?)")->execute([$uid,$matricule,$filiere]);
            elseif ($role === 'professeur')
                $pdo->prepare("INSERT INTO professeur (fk_user) VALUES (?)")->execute([$uid]);
            journaliser($pdo,'USER_CREATION','utilisateurs',"login=$login role=$role");
            header('Location: tableau_de_bord.php?onglet=utilisateurs&ok=1'); exit;
        } catch(Exception $e) { $err = "Erreur : ".$e->getMessage(); }
    } else { $err = "Tous les champs obligatoires doivent être remplis."; }
}


if (isset($_GET['suppr_user']) && peutFaire('rup')) {
    $pdo->prepare("DELETE FROM utilisateurs WHERE id_utilisateur=?")->execute([(int)$_GET['suppr_user']]);
    journaliser($pdo,'USER_SUPPRESSION','utilisateurs',"id=".(int)$_GET['suppr_user']);
    header('Location: tableau_de_bord.php?onglet=utilisateurs&ok=1'); exit;
}


if (isset($_POST['modifier_user']) && peutFaire('rup')) {
    $uid = (int)$_POST['uid'];
    $pdo->prepare("UPDATE utilisateurs SET nom=?, prenom=?, email=?, login=?, role=?, actif=? WHERE id_utilisateur=?")
        ->execute([trim($_POST['nom']), trim($_POST['prenom']), trim($_POST['email']), trim($_POST['login']), $_POST['role'], isset($_POST['actif'])?1:0, $uid]);
    if (!empty($_POST['mdp'])) {
        $pdo->prepare("UPDATE utilisateurs SET mot_de_passe=? WHERE id_utilisateur=?")->execute([password_hash($_POST['mdp'], PASSWORD_BCRYPT), $uid]);
    }
    journaliser($pdo,'USER_MODIFICATION','utilisateurs',"id=$uid");
    header('Location: tableau_de_bord.php?onglet=utilisateurs&ok=1'); exit;
}


if (isset($_POST['modifier_cours']) && peutFaire('rup')) {
    $cid = (int)$_POST['cid'];
    $pdo->prepare("UPDATE cours SET libelle=?, code=?, coefficient=?, fk_prof=?, semestre=? WHERE id_cours=?")
        ->execute([trim($_POST['libelle']), trim($_POST['code']), (float)$_POST['coefficient'], (int)$_POST['fk_prof'], (int)$_POST['semestre'], $cid]);
    journaliser($pdo,'COURS_MODIFICATION','cours',"id=$cid");
    header('Location: tableau_de_bord.php?onglet=cours&ok=1'); exit;
}


if (isset($_POST['modifier_planning']) && peutFaire('rup')) {
    $pid = (int)$_POST['pid'];
    $pdo->prepare("UPDATE emploi_du_temps SET fk_cours=?, salle=?, jour=?, heure_debut=?, heure_fin=?, type_seance=? WHERE id_emploi=?")
        ->execute([(int)$_POST['fk_cours'], trim($_POST['salle']), $_POST['jour'], $_POST['heure_debut'], $_POST['heure_fin'], $_POST['type_seance'], $pid]);
    header('Location: tableau_de_bord.php?onglet=planning&ok=1'); exit;
}


if (isset($_GET['suppr_planning']) && peutFaire('rup')) {
    $pdo->prepare("DELETE FROM emploi_du_temps WHERE id_emploi=?")->execute([(int)$_GET['suppr_planning']]);
    header('Location: tableau_de_bord.php?onglet=planning&ok=1'); exit;
}


if (isset($_GET['suppr_planche']) && peutFaire('rup')) {
    $pdo->prepare("DELETE FROM planche WHERE id_planche=?")->execute([(int)$_GET['suppr_planche']]);
    header('Location: tableau_de_bord.php?onglet=planches&ok=1'); exit;
}


if (isset($_POST['ajouter_cours'])) {
    try {
        $pdo->prepare("INSERT INTO cours (libelle,code,coefficient,fk_prof,semestre) VALUES (?,?,?,?,?)")
            ->execute([trim($_POST['libelle']),trim($_POST['code']),(float)$_POST['coefficient'],(int)$_POST['fk_prof'],(int)$_POST['semestre']]);
        journaliser($pdo,'COURS_CREATION','cours',"code=".$_POST['code']);
        header('Location: tableau_de_bord.php?onglet=cours&ok=1'); exit;
    } catch(Exception $e) { $err = "Erreur : ".$e->getMessage(); }
}


if (isset($_GET['suppr_cours'])) {
    $pdo->prepare("DELETE FROM cours WHERE id_cours=?")->execute([(int)$_GET['suppr_cours']]);
    header('Location: tableau_de_bord.php?onglet=cours&ok=1'); exit;
}


if (isset($_POST['ajouter_planche'])) {
    $pdo->prepare("INSERT INTO planche (titre,description,fk_cours,fk_auteur) VALUES (?,?,?,?)")
        ->execute([trim($_POST['titre']),trim($_POST['description']??''),(int)$_POST['fk_cours'],$u['id_utilisateur']]);
    header('Location: tableau_de_bord.php?onglet=planches&ok=1'); exit;
}


if (isset($_POST['ajouter_planning'])) {
    $pdo->prepare("INSERT INTO emploi_du_temps (fk_cours,salle,jour,heure_debut,heure_fin,type_seance) VALUES (?,?,?,?,?,?)")
        ->execute([(int)$_POST['fk_cours'],$_POST['salle'],$_POST['jour'],$_POST['heure_debut'],$_POST['heure_fin'],$_POST['type_seance']]);
    header('Location: tableau_de_bord.php?onglet=planning&ok=1'); exit;
}


if (isset($_POST['traiter_recla'])) {
    $pdo->prepare("UPDATE reclamation SET statut=?,reponse=?,fk_traite_par=? WHERE id_reclamation=?")
        ->execute([$_POST['statut_recla'],trim($_POST['reponse']),$u['id_utilisateur'],(int)$_POST['rid']]);
    journaliser($pdo,'RECLA_TRAITEMENT','reclamation',"id=".$_POST['rid']);
    header('Location: tableau_de_bord.php?onglet=reclamations&ok=1'); exit;
}

if (isset($_POST['saisir_note']) && peutFaire('rup')) {
    $fkEtu   = (int)$_POST['fk_etudiant'];
    $fkCours = (int)$_POST['fk_cours'];
    $valeur  = (float)str_replace(',', '.', $_POST['valeur']);
    $type    = $_POST['type_evaluation'] ?? 'Examen';

    if ($valeur < 0 || $valeur > 20) {
        $err = "La note doit être entre 0 et 20.";
    } else {
        try {
            $pdo->prepare("
                INSERT INTO note (fk_etudiant, fk_cours, valeur, type_evaluation, fk_saisi_par)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE valeur = VALUES(valeur), date_saisie = NOW()
            ")->execute([$fkEtu, $fkCours, $valeur, $type, $u['id_utilisateur']]);
            journaliser($pdo, 'NOTE_SAISIE', 'note', "etu=$fkEtu cours=$fkCours val=$valeur (par RUP)");
            header('Location: tableau_de_bord.php?onglet=notes&ok=1'); exit;
        } catch (Exception $e) {
            $err = "Erreur : " . $e->getMessage();
        }
    }
}

if (isset($_GET['suppr_note_etu']) && isset($_GET['suppr_note_cours']) && peutFaire('rup')) {
    $ke = (int)$_GET['suppr_note_etu'];
    $kc = (int)$_GET['suppr_note_cours'];
    $pdo->prepare("DELETE FROM note WHERE fk_etudiant = ? AND fk_cours = ?")
        ->execute([$ke, $kc]);
    journaliser($pdo, 'NOTE_SUPPRESSION', 'note', "etu=$ke cours=$kc (par RUP)");
    header('Location: tableau_de_bord.php?onglet=notes&ok=1'); exit;
}

$titrePage = $u['role']==='superadmin' ? 'Super Administration' : 'Espace RUP';
$menuItems = [[
    'titre'=>'Gestion',
    'liens'=>[
        ['label'=>'Tableau de bord', 'icone'=>'bi-grid-1x2-fill',   'href'=>'?onglet=accueil',      'actif'=>$tab==='accueil'],
        ['label'=>'Utilisateurs',   'icone'=>'bi-people-fill',      'href'=>'?onglet=utilisateurs', 'actif'=>$tab==='utilisateurs'],
        ['label'=>'Cours',          'icone'=>'bi-book-fill',        'href'=>'?onglet=cours',        'actif'=>$tab==='cours'],
        ['label'=>'Notes',          'icone'=>'bi-journal-check',    'href'=>'?onglet=notes',        'actif'=>$tab==='notes'],
        ['label'=>'Planches',       'icone'=>'bi-file-earmark-text','href'=>'?onglet=planches',     'actif'=>$tab==='planches'],
        ['label'=>'Planning',       'icone'=>'bi-calendar-week',    'href'=>'?onglet=planning',     'actif'=>$tab==='planning'],
        ['label'=>'Réclamations',   'icone'=>'bi-chat-square-text', 'href'=>'?onglet=reclamations', 'actif'=>$tab==='reclamations',
         'badge'=>$nbRecla?:null, 'badgeClass'=>'bg-danger'],
    ]
]];
include '../gabarit.php';
?>

<?php if(($_GET['ok']??'')==='1'): ?><div class="gn-toast"><i class="bi bi-check-circle-fill text-success me-2"></i>Opération réussie</div><?php endif; ?>
<?php if($err): ?><div class="alert alert-danger d-flex gap-2 mb-3"><i class="bi bi-exclamation-triangle-fill"></i><?= h($err) ?></div><?php endif; ?>


<?php if($tab==='accueil'): ?>
<nav aria-label="breadcrumb" class="gn-ariane"><span>RUP</span><i class="bi bi-chevron-right"></i><span class="actuel">Tableau de bord</span></nav>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="gn-stat s-bleu"><div class="gn-stat-ico bg-primary bg-opacity-10 text-primary mb-2"><i class="bi bi-mortarboard-fill"></i></div><div class="gn-stat-val"><?=$nbEtu?></div><div class="gn-stat-lbl">Étudiants inscrits</div></div></div>
    <div class="col-6 col-md-3"><div class="gn-stat s-vert"><div class="gn-stat-ico bg-success bg-opacity-10 text-success mb-2"><i class="bi bi-person-workspace"></i></div><div class="gn-stat-val"><?=$nbProf?></div><div class="gn-stat-lbl">Professeurs</div></div></div>
    <div class="col-6 col-md-3"><div class="gn-stat s-orange"><div class="gn-stat-ico bg-warning bg-opacity-10 text-warning mb-2"><i class="bi bi-book-fill"></i></div><div class="gn-stat-val"><?=$nbCours?></div><div class="gn-stat-lbl">Cours au catalogue</div></div></div>
    <div class="col-6 col-md-3"><div class="gn-stat s-rouge"><div class="gn-stat-ico bg-danger bg-opacity-10 text-danger mb-2"><i class="bi bi-chat-square-text"></i></div><div class="gn-stat-val"><?=$nbRecla?></div><div class="gn-stat-lbl">Réclamations actives</div></div></div>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="gn-carte">
            <div class="gn-carte-entete"><div class="gn-carte-titre">Accès rapides</div></div>
            <div class="gn-carte-corps">
                <div class="row g-2">
                    <?php foreach([
                        ['?onglet=utilisateurs','bi-people-fill','primary','Utilisateurs','Gérer les comptes'],
                        ['?onglet=cours','bi-book-fill','success','Cours','Catalogue complet'],
                        ['?onglet=notes','bi-journal-check','info','Notes','Gérer les notes'],
                        ['?onglet=reclamations','bi-chat-square-text','danger','Réclamations','À traiter'],
                    ] as [$href,$ico,$col,$lbl,$sub]): ?>
                    <div class="col-6">
                        <a href="<?=$href?>" class="d-flex align-items-center gap-2 p-3 rounded-3 border text-decoration-none bg-<?=$col?> bg-opacity-10 border-<?=$col?> border-opacity-25 h-100" style="transition:opacity .15s" onmouseover="this.style.opacity='.8'" onmouseout="this.style.opacity='1'">
                            <i class="bi <?=$ico?> text-<?=$col?> fs-5"></i>
                            <div><div class="fw-semibold text-dark" style="font-size:.83rem"><?=$lbl?></div><div class="text-muted" style="font-size:.72rem"><?=$sub?></div></div>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="gn-carte">
            <div class="gn-carte-entete"><div class="gn-carte-titre">Réclamations récentes</div><a href="?onglet=reclamations" class="btn btn-sm btn-outline-primary">Voir tout</a></div>
            <?php $r5=array_slice(array_filter($reclamations,fn($r)=>in_array($r['statut'],['en_attente','en_cours'])),0,5);
            if(empty($r5)): ?><div class="text-center py-4 text-muted"><i class="bi bi-check-circle text-success fs-2 d-block mb-1 opacity-50"></i>Tout est traité</div>
            <?php else: ?><div class="table-responsive"><table class="gn-table"><thead><tr><th>Étudiant</th><th>Objet</th><th>Statut</th></tr></thead><tbody>
            <?php foreach($r5 as $r): ?><tr><td><?=h($r['etu_prenom'].' '.$r['etu_nom'])?></td><td class="text-muted"><?=h(mb_substr($r['objet'],0,30))?>…</td><td><span class="badge bg-warning rounded-pill">En attente</span></td></tr><?php endforeach; ?>
            </tbody></table></div><?php endif; ?>
        </div>
    </div>
</div>


<?php elseif($tab==='utilisateurs'): ?>
<nav aria-label="breadcrumb" class="gn-ariane"><span>RUP</span><i class="bi bi-chevron-right"></i><span class="actuel">Utilisateurs</span></nav>
<div class="row g-3">
    <div class="col-md-4">
        <div class="gn-carte">
            <div class="gn-carte-entete"><div class="gn-carte-titre">Ajouter un utilisateur</div></div>
            <div class="gn-carte-corps">
                <form method="POST" class="row g-2" id="form-user">
                    <div class="col-12"><label class="form-label">Rôle *</label>
                        <select name="role" class="form-select" id="sel-role" onchange="toggleChamps()">
                            <option value="etudiant">Étudiant</option>
                            <option value="professeur">Professeur</option>
                            <option value="rup">RUP</option>
                        </select>
                    </div>
                    <div class="col-6"><label class="form-label">Nom *</label><input type="text" name="nom" class="form-control" required></div>
                    <div class="col-6"><label class="form-label">Prénom *</label><input type="text" name="prenom" class="form-control" required></div>
                    <div class="col-12"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" required></div>
                    <div class="col-12"><label class="form-label">Login *</label><input type="text" name="login" class="form-control" placeholder="ETU004, prof3…" required></div>
                    <div class="col-12"><label class="form-label">Mot de passe *</label><input type="password" name="mdp" class="form-control" required></div>
                    <div id="champs-etu">
                        <div class="row g-2 mt-0">
                            <div class="col-6"><label class="form-label">Matricule</label><input type="text" name="matricule" class="form-control" placeholder="ETU004"></div>
                            <div class="col-6"><label class="form-label">Filière</label><input type="text" name="filiere" class="form-control"></div>
                        </div>
                    </div>
                    <div class="col-12 mt-2"><button type="submit" name="ajouter_user" class="btn btn-primary w-100"><i class="bi bi-person-plus-fill me-2"></i>Créer le compte</button></div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="gn-carte">
            <div class="gn-carte-entete"><div class="gn-carte-titre">Tous les utilisateurs</div><span class="badge bg-primary rounded-pill"><?=count($utilisateurs)?></span></div>
            <div class="table-responsive">
                <table class="gn-table">
                    <thead><tr><th>Utilisateur</th><th>Login</th><th>Rôle</th><th>Statut</th><?php if(peutFaire('rup')): ?><th>Action</th><?php endif; ?></tr></thead>
                    <tbody>
                    <?php $rc=['etudiant'=>'primary','professeur'=>'success','rup'=>'warning','superadmin'=>'danger'];
                    $rl=['etudiant'=>'Étudiant','professeur'=>'Prof','rup'=>'RUP','superadmin'=>'SuperAdmin'];
                    foreach($utilisateurs as $uu): ?>
                    <tr>
                        <td><div class="d-flex align-items-center gap-2">
                            <div class="rounded-2 bg-<?=$rc[$uu['role']]??'secondary'?> text-white d-flex align-items-center justify-content-center fw-bold" style="width:30px;height:30px;font-size:11px;flex-shrink:0">
                                <?=strtoupper(substr($uu['prenom']??'?',0,1).substr($uu['nom']??'',0,1))?>
                            </div>
                            <div><strong><?=h(($uu['prenom']??'').' '.($uu['nom']??''))?></strong><br><small class="text-muted"><?=h($uu['email']??'')?></small></div>
                        </div></td>
                        <td class="text-muted"><?=h($uu['login']??'')?></td>
                        <td><span class="badge bg-<?=$rc[$uu['role']]??'secondary'?> rounded-pill"><?=$rl[$uu['role']]??$uu['role']?></span></td>
                        <td><span class="badge <?=$uu['actif']?'bg-success':'bg-danger'?> bg-opacity-10 <?=$uu['actif']?'text-success':'text-danger'?>"><?=$uu['actif']?'Actif':'Inactif'?></span></td>
                        <?php if(peutFaire('rup') && ($uu['role']??'')==='superadmin'): ?><td><small class="text-muted">Protégé</small></td>
                        <?php elseif(peutFaire('rup')): ?>
                        <td>
                            <div class="d-flex gap-1">
                                <?php if($uu['role']==='etudiant' && !empty($uu['idetudiant'])): ?>
                                <a href="../bulletin_pdf.php?idetudiant=<?=$uu['idetudiant']?>" target="_blank" class="btn btn-outline-warning btn-sm" title="Imprimer Bulletin"><i class="bi bi-printer"></i></a>
                                <?php endif; ?>
                                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modal-user-<?=$uu['id_utilisateur']?>"><i class="bi bi-pencil"></i></button>
                                <a href="?onglet=utilisateurs&suppr_user=<?=$uu['id_utilisateur']?>" class="btn btn-outline-danger btn-sm" data-confirm="Confirmer la suppression de ce compte ?"><i class="bi bi-trash"></i></a>
                            </div>
                            
                            
                            <div class="modal fade" id="modal-user-<?=$uu['id_utilisateur']?>" tabindex="-1" aria-hidden="true">
                              <div class="modal-dialog">
                                <form method="POST" class="modal-content text-start">
                                  <div class="modal-header">
                                    <h5 class="modal-title">Modifier <?=h($uu['nom'].' '.$uu['prenom'])?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                  </div>
                                  <div class="modal-body row g-2">
                                    <input type="hidden" name="uid" value="<?=$uu['id_utilisateur']?>">
                                    <div class="col-12"><label class="form-label">Rôle</label>
                                      <select name="role" class="form-select">
                                        <option value="etudiant" <?=$uu['role']==='etudiant'?'selected':''?>>Étudiant</option>
                                        <option value="professeur" <?=$uu['role']==='professeur'?'selected':''?>>Professeur</option>
                                        <option value="rup" <?=$uu['role']==='rup'?'selected':''?>>RUP</option>
                                      </select>
                                    </div>
                                    <div class="col-6"><label class="form-label">Nom *</label><input type="text" name="nom" class="form-control" value="<?=h($uu['nom'])?>" required></div>
                                    <div class="col-6"><label class="form-label">Prénom *</label><input type="text" name="prenom" class="form-control" value="<?=h($uu['prenom'])?>" required></div>
                                    <div class="col-12"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" value="<?=h($uu['email'])?>" required></div>
                                    <div class="col-12"><label class="form-label">Login *</label><input type="text" name="login" class="form-control" value="<?=h($uu['login'])?>" required></div>
                                    <div class="col-12"><label class="form-label">Nouveau MDP (laisser vide pour ne pas changer)</label><input type="password" name="mdp" class="form-control"></div>
                                    <div class="col-12 mt-2">
                                      <div class="form-check form-switch ps-5">
                                        <input class="form-check-input ms-n4" type="checkbox" name="actif" role="switch" id="act-<?=$uu['id_utilisateur']?>" <?=$uu['actif']?'checked':''?>>
                                        <label class="form-check-label ms-1" for="act-<?=$uu['id_utilisateur']?>">Compte actif</label>
                                      </div>
                                    </div>
                                  </div>
                                  <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                    <button type="submit" name="modifier_user" class="btn btn-primary">Enregistrer</button>
                                  </div>
                                </form>
                              </div>
                            </div>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>


<?php elseif($tab==='cours'): ?>
<nav aria-label="breadcrumb" class="gn-ariane"><span>RUP</span><i class="bi bi-chevron-right"></i><span class="actuel">Cours</span></nav>
<div class="row g-3">
    <div class="col-md-4">
        <div class="gn-carte">
            <div class="gn-carte-entete"><div class="gn-carte-titre">Ajouter un cours</div></div>
            <div class="gn-carte-corps">
                <form method="POST" class="row g-2">
                    <div class="col-12"><label class="form-label">Libellé *</label><input type="text" name="libelle" class="form-control" required placeholder="Algorithmique"></div>
                    <div class="col-6"><label class="form-label">Code *</label><input type="text" name="code" class="form-control" required placeholder="ALG301"></div>
                    <div class="col-6"><label class="form-label">Coefficient</label><input type="number" name="coefficient" class="form-control" value="3" min="0.5" max="10" step="0.5"></div>
                    <div class="col-8"><label class="form-label">Professeur *</label>
                        <select name="fk_prof" class="form-select" required>
                            <option value="">— Choisir —</option>
                            <?php foreach($professeurs as $p): ?><option value="<?=$p['id_prof']?>"><?=h($p['nom'].' '.$p['prenom'])?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-4"><label class="form-label">Semestre</label>
                        <select name="semestre" class="form-select"><option value="1">S1</option><option value="2">S2</option></select>
                    </div>
                    <div class="col-12 mt-1"><button type="submit" name="ajouter_cours" class="btn btn-primary w-100"><i class="bi bi-plus-circle-fill me-2"></i>Ajouter</button></div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="gn-carte">
            <div class="gn-carte-entete"><div class="gn-carte-titre">Catalogue des cours</div><span class="badge bg-primary rounded-pill"><?=count($cours)?></span></div>
            <div class="table-responsive"><table class="gn-table">
                <thead><tr><th>Libellé</th><th>Code</th><th>Professeur</th><th>Coef.</th><th>Sem.</th><th>Action</th></tr></thead>
                <tbody>
                <?php foreach($cours as $c): ?><tr>
                    <td><strong><?=h($c['libelle'])?></strong></td>
                    <td><small class="text-muted"><?=h($c['code']??'')?></small></td>
                    <td class="text-muted"><?=h($c['prof_prenom'].' '.$c['prof_nom'])?></td>
                    <td><span class="badge bg-warning bg-opacity-10 text-warning fw-bold">×<?=$c['coefficient']?></span></td>
                    <td>S<?=$c['semestre']?></td>
                    <td>
                        <div class="d-flex gap-1">
                            <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modal-cours-<?=$c['id_cours']?>"><i class="bi bi-pencil"></i></button>
                            <a href="?onglet=cours&suppr_cours=<?=$c['id_cours']?>" class="btn btn-outline-danger btn-sm" data-confirm="Supprimer ce cours ?"><i class="bi bi-trash"></i></a>
                        </div>
                        
                        
                        <div class="modal fade" id="modal-cours-<?=$c['id_cours']?>" tabindex="-1" aria-hidden="true">
                          <div class="modal-dialog">
                            <form method="POST" class="modal-content text-start">
                              <div class="modal-header">
                                <h5 class="modal-title">Modifier le cours <?=h($c['code'])?></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                              </div>
                              <div class="modal-body row g-2">
                                <input type="hidden" name="cid" value="<?=$c['id_cours']?>">
                                <div class="col-12"><label class="form-label">Libellé *</label><input type="text" name="libelle" class="form-control" value="<?=h($c['libelle'])?>" required></div>
                                <div class="col-6"><label class="form-label">Code *</label><input type="text" name="code" class="form-control" value="<?=h($c['code'])?>" required></div>
                                <div class="col-6"><label class="form-label">Coefficient</label><input type="number" name="coefficient" class="form-control" value="<?=h($c['coefficient'])?>" min="0.5" max="10" step="0.5"></div>
                                <div class="col-8"><label class="form-label">Professeur *</label>
                                    <select name="fk_prof" class="form-select" required>
                                        <?php foreach($professeurs as $p): ?><option value="<?=$p['id_prof']?>" <?=$p['id_prof']==$c['fk_prof']?'selected':''?>><?=h($p['nom'].' '.$p['prenom'])?></option><?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-4"><label class="form-label">Semestre</label>
                                    <select name="semestre" class="form-select"><option value="1" <?=$c['semestre']==1?'selected':''?>>S1</option><option value="2" <?=$c['semestre']==2?'selected':''?>>S2</option></select>
                                </div>
                              </div>
                              <div class="modal-footer text-end mt-3">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                <button type="submit" name="modifier_cours" class="btn btn-primary">Enregistrer</button>
                              </div>
                            </form>
                          </div>
                        </div>
                    </td>
                </tr><?php endforeach; ?>
                </tbody>
            </table></div>
        </div>
    </div>
</div>


<?php elseif($tab==='planches'): ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <nav aria-label="breadcrumb" class="gn-ariane mb-0"><span>RUP</span><i class="bi bi-chevron-right"></i><span class="actuel">Planches & Délibérations</span></nav>
    <a href="../planche_pdf.php" target="_blank" class="btn btn-warning fw-bold shadow-sm text-dark"><i class="bi bi-printer-fill me-2"></i>Générer Planche Officielle</a>
</div>
<div class="row g-3">
    <div class="col-md-4">
        <div class="gn-carte">
            <div class="gn-carte-entete"><div class="gn-carte-titre">Ajouter une planche</div></div>
            <div class="gn-carte-corps">
                <form method="POST" class="row g-2">
                    <div class="col-12"><label class="form-label">Titre *</label><input type="text" name="titre" class="form-control" required></div>
                    <div class="col-12"><label class="form-label">Cours *</label>
                        <select name="fk_cours" class="form-select" required>
                            <option value="">— Choisir —</option>
                            <?php foreach($cours as $c): ?><option value="<?=$c['id_cours']?>"><?=h($c['libelle'])?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3"></textarea></div>
                    <div class="col-12 mt-1"><button type="submit" name="ajouter_planche" class="btn btn-primary w-100"><i class="bi bi-plus-circle-fill me-2"></i>Ajouter</button></div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="gn-carte">
            <div class="gn-carte-entete"><div class="gn-carte-titre">Toutes les planches</div><span class="badge bg-primary rounded-pill"><?=count($planches)?></span></div>
            <div class="table-responsive"><table class="gn-table">
                <thead><tr><th>Titre</th><th>Cours</th><th>Auteur</th><th>Date</th><th>Action</th></tr></thead>
                <tbody>
                <?php foreach($planches as $p): ?><tr>
                    <td><strong><?=h($p['titre'])?></strong><?php if($p['description']): ?><br><small class="text-muted"><?=h(mb_substr($p['description'],0,50))?>…</small><?php endif; ?></td>
                    <td><span class="badge bg-primary bg-opacity-10 text-primary"><?=h($p['cours_libelle'])?></span></td>
                    <td class="text-muted"><?=h($p['auteur_nom'])?></td>
                    <td><small class="text-muted"><?=date('d/m/Y',strtotime($p['date_creation']))?></small></td>
                    <td><a href="?onglet=planches&suppr_planche=<?=$p['id_planche']?>" class="btn btn-outline-danger btn-sm" data-confirm="Supprimer cette planche ?"><i class="bi bi-trash"></i></a></td>
                </tr><?php endforeach; ?>
                </tbody>
            </table></div>
        </div>
    </div>
</div>


<?php elseif($tab==='planning'): ?>
<nav aria-label="breadcrumb" class="gn-ariane"><span>RUP</span><i class="bi bi-chevron-right"></i><span class="actuel">Planning</span></nav>
<div class="row g-3">
    <div class="col-md-4">
        <div class="gn-carte">
            <div class="gn-carte-entete"><div class="gn-carte-titre">Planifier une séance</div></div>
            <div class="gn-carte-corps">
                <form method="POST" class="row g-2">
                    <div class="col-12"><label class="form-label">Cours *</label>
                        <select name="fk_cours" class="form-select" required>
                            <option value="">— Choisir —</option>
                            <?php foreach($cours as $c): ?><option value="<?=$c['id_cours']?>"><?=h($c['libelle'])?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6"><label class="form-label">Type</label>
                        <select name="type_seance" class="form-select"><option>Cours</option><option>TP</option><option>TD</option><option>Examen</option></select>
                    </div>
                    <div class="col-6"><label class="form-label">Salle</label><input type="text" name="salle" class="form-control" placeholder="Amphi A"></div>
                    <div class="col-12"><label class="form-label">Date *</label><input type="date" name="jour" class="form-control" required></div>
                    <div class="col-6"><label class="form-label">Début *</label><input type="time" name="heure_debut" class="form-control" required></div>
                    <div class="col-6"><label class="form-label">Fin *</label><input type="time" name="heure_fin" class="form-control" required></div>
                    <div class="col-12 mt-1"><button type="submit" name="ajouter_planning" class="btn btn-primary w-100"><i class="bi bi-calendar-plus-fill me-2"></i>Planifier</button></div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="gn-carte">
            <div class="gn-carte-entete"><div class="gn-carte-titre">Emploi du temps</div></div>
            <div class="table-responsive"><table class="gn-table">
                <thead><tr><th>Cours</th><th>Type</th><th>Date</th><th>Horaire</th><th>Salle</th><th>Action</th></tr></thead>
                <tbody>
                <?php $tc=['Cours'=>'primary','TP'=>'success','TD'=>'info','Examen'=>'danger'];
                foreach($planning as $p): ?><tr>
                    <td><strong><?=h($p['cours_libelle'])?></strong></td>
                    <td><span class="badge bg-<?=$tc[$p['type_seance']]??'secondary'?> rounded-pill"><?=$p['type_seance']?></span></td>
                    <td><?=date('d/m/Y',strtotime($p['jour']))?></td>
                    <td><?=substr($p['heure_debut'],0,5)?> – <?=substr($p['heure_fin'],0,5)?></td>
                    <td class="text-muted"><?=h($p['salle']??'—')?></td>
                    <td>
                        <div class="d-flex gap-1">
                            <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modal-planning-<?=$p['id_emploi']?>"><i class="bi bi-pencil"></i></button>
                            <a href="?onglet=planning&suppr_planning=<?=$p['id_emploi']?>" class="btn btn-outline-danger btn-sm" data-confirm="Supprimer cette séance ?"><i class="bi bi-trash"></i></a>
                        </div>
                        
                        
                        <div class="modal fade" id="modal-planning-<?=$p['id_emploi']?>" tabindex="-1" aria-hidden="true">
                          <div class="modal-dialog">
                            <form method="POST" class="modal-content text-start">
                              <div class="modal-header">
                                <h5 class="modal-title">Modifier Séance</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                              </div>
                              <div class="modal-body row g-2">
                                <input type="hidden" name="pid" value="<?=$p['id_emploi']?>">
                                <div class="col-12"><label class="form-label">Cours *</label>
                                    <select name="fk_cours" class="form-select" required>
                                        <?php foreach($cours as $c): ?><option value="<?=$c['id_cours']?>" <?=$c['id_cours']==$p['fk_cours']?'selected':''?>><?=h($c['libelle'])?></option><?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-6"><label class="form-label">Type</label>
                                    <select name="type_seance" class="form-select">
                                        <option <?=$p['type_seance']=='Cours'?'selected':''?>>Cours</option>
                                        <option <?=$p['type_seance']=='TP'?'selected':''?>>TP</option>
                                        <option <?=$p['type_seance']=='TD'?'selected':''?>>TD</option>
                                        <option <?=$p['type_seance']=='Examen'?'selected':''?>>Examen</option>
                                    </select>
                                </div>
                                <div class="col-6"><label class="form-label">Salle</label><input type="text" name="salle" class="form-control" value="<?=h($p['salle'])?>"></div>
                                <div class="col-12"><label class="form-label">Date *</label><input type="date" name="jour" class="form-control" value="<?=$p['jour']?>" required></div>
                                <div class="col-6"><label class="form-label">Début *</label><input type="time" name="heure_debut" class="form-control" value="<?=$p['heure_debut']?>" required></div>
                                <div class="col-6"><label class="form-label">Fin *</label><input type="time" name="heure_fin" class="form-control" value="<?=$p['heure_fin']?>" required></div>
                              </div>
                              <div class="modal-footer mt-3">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                <button type="submit" name="modifier_planning" class="btn btn-primary">Enregistrer</button>
                              </div>
                            </form>
                          </div>
                        </div>
                    </td>
                </tr><?php endforeach; ?>
                <?php if(empty($planning)): ?><tr><td colspan="5" class="text-center text-muted py-4">Aucune séance planifiée</td></tr><?php endif; ?>
                </tbody>
            </table></div>
        </div>
    </div>
</div>


<?php elseif($tab==='reclamations'): ?>
<nav aria-label="breadcrumb" class="gn-ariane"><span>RUP</span><i class="bi bi-chevron-right"></i><span class="actuel">Réclamations</span></nav>
<?php $sc=['en_attente'=>['warning','En attente'],'en_cours'=>['primary','En cours'],'resolu'=>['success','Résolu'],'rejete'=>['danger','Rejeté']];
if(empty($reclamations)): ?><div class="gn-carte text-center py-5 text-muted"><i class="bi bi-check-circle fs-1 text-success opacity-50 d-block mb-2"></i><strong>Aucune réclamation</strong></div>
<?php else: ?><div class="d-flex flex-column gap-3">
<?php foreach($reclamations as $r): [$cc,$ll]=$sc[$r['statut']]??['secondary','?']; ?>
<div class="gn-carte">
    <div class="gn-carte-entete">
        <div><div class="gn-carte-titre"><?=h($r['objet'])?></div><div class="gn-carte-sous"><?=h($r['etu_prenom'].' '.$r['etu_nom'])?> (<?=h($r['matricule'])?>)— <?=date('d/m/Y',strtotime($r['date_creation']))?></div></div>
        <span class="badge bg-<?=$cc?> rounded-pill"><?=$ll?></span>
    </div>
    <div class="gn-carte-corps">
        <div class="alert alert-light border mb-3 p-2" style="font-size:.83rem"><?=h($r['description'])?></div>
        <?php if($r['reponse']): ?><div class="alert alert-info p-2 mb-3" style="font-size:.82rem"><i class="bi bi-chat-fill me-1"></i><?=h($r['reponse'])?></div><?php endif; ?>
        <?php if(!in_array($r['statut'],['resolu','rejete'])): ?>
        <form method="POST" class="row g-2">
            <input type="hidden" name="rid" value="<?=$r['id_reclamation']?>">
            <div class="col-md-6"><label class="form-label">Réponse *</label><input type="text" name="reponse" class="form-control" required placeholder="Votre réponse…"></div>
            <div class="col-md-3"><label class="form-label">Décision</label>
                <select name="statut_recla" class="form-select"><option value="en_cours">En cours</option><option value="resolu">Résolu</option><option value="rejete">Rejeté</option></select>
            </div>
            <div class="col-md-3 d-flex align-items-end"><button type="submit" name="traiter_recla" class="btn btn-primary w-100"><i class="bi bi-send-fill me-1"></i>Envoyer</button></div>
        </form>
        <?php else: ?><span class="badge bg-<?=$cc?>">Clôturée — <?=$ll?></span><?php endif; ?>
    </div>
</div>
<?php endforeach; ?></div><?php endif; ?>

<?php elseif($tab==='notes'): ?>
<nav aria-label="breadcrumb" class="gn-ariane"><span>RUP</span><i class="bi bi-chevron-right"></i><span class="actuel">Gestion globale des Notes</span></nav>

<div class="row g-3">
    <div class="col-md-5">
        <div class="gn-carte">
            <div class="gn-carte-entete"><div class="gn-carte-titre">Saisir / Modifier une note</div></div>
            <div class="gn-carte-corps">
                <form method="POST" class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Étudiant *</label>
                        <select name="fk_etudiant" class="form-select" required>
                            <option value="">— Sélectionner —</option>
                            <?php foreach ($etudiants as $e): ?>
                            <option value="<?= $e['idetudiant'] ?>">
                                <?= h($e['nom'].' '.$e['prenom']) ?> (<?= h($e['matricule']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Cours *</label>
                        <select name="fk_cours" class="form-select" required>
                            <option value="">— Sélectionner —</option>
                            <?php foreach ($cours as $c): ?>
                            <option value="<?= $c['id_cours'] ?>">
                                <?= h($c['libelle']) ?> (<?= h($c['prof_prenom'].' '.$c['prof_nom']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Type d'évaluation</label>
                        <select name="type_evaluation" class="form-select">
                            <option value="Examen">Examen</option>
                            <option value="Devoir">Devoir Surveillé</option>
                            <option value="TP">TP</option>
                            <option value="Rattrapage">Rattrapage</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Note (0 – 20) *</label>
                        <input type="number" name="valeur" class="form-control"
                               min="0" max="20" step="0.25" placeholder="15.50" required>
                    </div>
                    <div class="col-12">
                        <button type="submit" name="saisir_note" class="btn btn-primary w-100">
                            <i class="bi bi-save-fill me-2"></i>Enregistrer la note
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-7">
        <div class="gn-carte">
            <div class="gn-carte-entete">
                <div class="gn-carte-titre">Toutes les notes (<?= count($toutesNotes) ?>)</div>
            </div>
            <div style="max-height: 600px; overflow-y: auto;">
                <table class="gn-table">
                    <thead style="position: sticky; top: 0; background: #fff; z-index: 1;">
                        <tr><th>Étudiant</th><th>Cours</th><th>Note</th><th>Type</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                    <?php if (empty($toutesNotes)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">Aucune note saisie</td></tr>
                    <?php endif; ?>
                    <?php foreach ($toutesNotes as $n): ?>
                    <tr>
                        <td><strong><?= h($n['etu_prenom'].' '.$n['etu_nom']) ?></strong><br><small class="text-muted"><?= h($n['matricule']) ?></small></td>
                        <td class="text-muted"><?= h($n['cours_libelle']) ?></td>
                        <td><span class="note-badge <?= classeNote($n['valeur']) ?>"><?= $n['valeur'] ?>/20</span></td>
                        <td><span class="badge bg-secondary bg-opacity-10 text-secondary"><?= h($n['type_evaluation']??'Examen') ?></span></td>
                        <td><a href="?onglet=notes&suppr_note_etu=<?=$n['fk_etudiant']?>&suppr_note_cours=<?=$n['fk_cours']?>" class="btn btn-outline-danger btn-sm py-0 px-2" data-confirm="Supprimer cette note ?"><i class="bi bi-trash"></i></a></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include '../gabarit_fin.php'; ?>
<script>
function toggleChamps(){
    const r=document.getElementById('sel-role').value;
    document.getElementById('champs-etu').style.display=r==='etudiant'?'block':'none';
}
toggleChamps();
</script>