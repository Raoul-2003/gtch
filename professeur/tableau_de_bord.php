<?php
session_start();
require '../configuration.php';
require '../securite.php';
verifierAcces('professeur');

$profondeur = 1;
$u = moi();


$stmt = $pdo->prepare("SELECT * FROM professeur WHERE fk_user = ?");
$stmt->execute([$u['id_utilisateur']]);
$prof = $stmt->fetch();
$pid  = $prof['id_prof'] ?? null;


$mesCours = [];
if ($pid) {
    $stmt = $pdo->prepare("
        SELECT c.*, COUNT(DISTINCT n.fk_etudiant) as nb_etudiants
        FROM cours c
        LEFT JOIN note n ON n.fk_cours = c.id_cours
        WHERE c.fk_prof = ?
        GROUP BY c.id_cours ORDER BY c.semestre, c.libelle
    ");
    $stmt->execute([$pid]);
    $mesCours = $stmt->fetchAll();
}


$etudiants = $pdo->query("
    SELECT e.idetudiant, e.matricule, u.nom, u.prenom
    FROM etudiant e JOIN utilisateurs u ON e.fk_user = u.id_utilisateur
    ORDER BY u.nom, u.prenom
")->fetchAll();


$notesSaisies = [];
if ($pid) {
    $stmt = $pdo->prepare("
        SELECT n.*, u.nom as etu_nom, u.prenom as etu_prenom,
               e.matricule, c.libelle as cours_libelle,
               c.id_cours as id_cours
        FROM note n
        JOIN etudiant e ON n.fk_etudiant = e.idetudiant
        JOIN utilisateurs u ON e.fk_user = u.id_utilisateur
        JOIN cours c ON n.fk_cours = c.id_cours
        WHERE c.fk_prof = ?
        ORDER BY n.date_saisie DESC LIMIT 200
    ");
    $stmt->execute([$pid]);
    $notesSaisies = $stmt->fetchAll();
}


$reclamations = $pdo->query("
    SELECT r.*, u.nom as etu_nom, u.prenom as etu_prenom, e.matricule
    FROM reclamation r
    JOIN etudiant e ON r.fk_etudiant = e.idetudiant
    JOIN utilisateurs u ON e.fk_user = u.id_utilisateur
    WHERE r.statut IN ('en_attente','en_cours')
    ORDER BY r.date_creation DESC
")->fetchAll();

$onglet = $_GET['onglet'] ?? 'accueil';
$msgSucces = $msgErreur = '';


if (isset($_POST['saisir_note'])) {
    $fkEtu   = (int)$_POST['fk_etudiant'];
    $fkCours = (int)$_POST['fk_cours'];
    $valeur  = (float)str_replace(',', '.', $_POST['valeur']);
    $type    = $_POST['type_evaluation'] ?? 'Examen';

    
    $check = $pdo->prepare("SELECT id_cours FROM cours WHERE id_cours = ? AND fk_prof = ?");
    $check->execute([$fkCours, $pid]);

    if (!$check->fetch()) {
        $msgErreur = "Ce cours ne vous appartient pas.";
    } elseif ($valeur < 0 || $valeur > 20) {
        $msgErreur = "La note doit être entre 0 et 20.";
    } else {
        try {
            $pdo->prepare("
                INSERT INTO note (fk_etudiant, fk_cours, valeur, type_evaluation, fk_saisi_par)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE valeur = VALUES(valeur), date_saisie = NOW()
            ")->execute([$fkEtu, $fkCours, $valeur, $type, $u['id_utilisateur']]);
            journaliser($pdo, 'NOTE_SAISIE', 'note', "etu=$fkEtu cours=$fkCours val=$valeur");
            header('Location: tableau_de_bord.php?onglet=notes&ok=1'); exit;
        } catch (Exception $e) {
            $msgErreur = "Erreur : " . $e->getMessage();
        }
    }
}


if (isset($_GET['suppr_note_etu']) && isset($_GET['suppr_note_cours'])) {
    $ke = (int)$_GET['suppr_note_etu'];
    $kc = (int)$_GET['suppr_note_cours'];

    $check = $pdo->prepare("SELECT id_cours FROM cours WHERE id_cours = ? AND fk_prof = ?");
    $check->execute([$kc, $pid]);
    if ($check->fetch()) {
        $pdo->prepare("DELETE FROM note WHERE fk_etudiant = ? AND fk_cours = ?")
            ->execute([$ke, $kc]);
        journaliser($pdo, 'NOTE_SUPPRESSION', 'note', "etu=$ke cours=$kc par prof=$pid");
    }
    
    $redirect = isset($_GET['from']) && $_GET['from'] === 'cours_details' ? "tableau_de_bord.php?onglet=cours_details&id_cours=$kc&ok=1" : 'tableau_de_bord.php?onglet=notes&ok=1';
    header('Location: ' . $redirect); exit;
}


if (isset($_POST['repondre_recla'])) {
    $rid     = (int)$_POST['rid'];
    $reponse = trim($_POST['reponse'] ?? '');
    $statut  = $_POST['statut_recla'] ?? 'en_cours';
    if ($reponse) {
        $pdo->prepare("
            UPDATE reclamation SET reponse=?, statut=?, fk_traite_par=?
            WHERE id_reclamation=?
        ")->execute([$reponse, $statut, $u['id_utilisateur'], $rid]);
        journaliser($pdo, 'RECLA_REPONSE', 'reclamation', "id=$rid statut=$statut");
        header('Location: tableau_de_bord.php?onglet=reclamations&ok=1'); exit;
    } else {
        $msgErreur = "Veuillez saisir une réponse.";
    }
}

function classeNote(float $n): string {
    if ($n >= 14) return 'note-haute';
    if ($n >= 10) return 'note-moyenne';
    return 'note-basse';
}

$titrePage = 'Espace Professeur';
$sousTitre = $prof['grade'] ?? '';
$menuItems = [[
    'titre' => 'Mes fonctions',
    'liens' => [
        ['label'=>'Tableau de bord', 'icone'=>'bi-grid-1x2-fill',  'href'=>'?onglet=accueil',      'actif'=>$onglet==='accueil'],
        ['label'=>'Saisir les notes','icone'=>'bi-pencil-square',   'href'=>'?onglet=notes',        'actif'=>$onglet==='notes'],
        ['label'=>'Mes cours',       'icone'=>'bi-book-fill',       'href'=>'?onglet=cours',        'actif'=>$onglet==='cours'],
        ['label'=>'Réclamations',    'icone'=>'bi-chat-square-text','href'=>'?onglet=reclamations', 'actif'=>$onglet==='reclamations',
         'badge'=>count($reclamations), 'badgeClass'=>'bg-danger'],
    ]
]];

include '../gabarit.php';
?>

<?php if (($_GET['ok']??'')==='1'): ?>
<div class="gn-toast"><i class="bi bi-check-circle-fill text-success me-2"></i>Opération réussie</div>
<?php endif; ?>
<?php if ($msgErreur): ?>
<div class="alert alert-danger d-flex gap-2 mb-3"><i class="bi bi-exclamation-triangle-fill"></i><?= h($msgErreur) ?></div>
<?php endif; ?>


<?php if ($onglet === 'accueil'): ?>
<nav aria-label="breadcrumb" class="gn-ariane">
    <span>Professeur</span> <i class="bi bi-chevron-right"></i>
    <span class="actuel">Tableau de bord</span>
</nav>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-4">
        <div class="gn-stat s-bleu">
            <div class="gn-stat-ico bg-primary bg-opacity-10 text-primary mb-2">
                <i class="bi bi-book-fill"></i>
            </div>
            <div class="gn-stat-val"><?= count($mesCours) ?></div>
            <div class="gn-stat-lbl">Cours enseignés</div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="gn-stat s-vert">
            <div class="gn-stat-ico bg-success bg-opacity-10 text-success mb-2">
                <i class="bi bi-journal-check"></i>
            </div>
            <div class="gn-stat-val"><?= count($notesSaisies) ?></div>
            <div class="gn-stat-lbl">Notes saisies</div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="gn-stat s-rouge">
            <div class="gn-stat-ico bg-danger bg-opacity-10 text-danger mb-2">
                <i class="bi bi-chat-square-text"></i>
            </div>
            <div class="gn-stat-val"><?= count($reclamations) ?></div>
            <div class="gn-stat-lbl">Réclamations en attente</div>
        </div>
    </div>
</div>

<div class="gn-carte">
    <div class="gn-carte-entete">
        <div class="gn-carte-titre">Dernières notes saisies</div>
        <a href="?onglet=notes" class="btn btn-sm btn-outline-primary">
            Saisir une note <i class="bi bi-plus ms-1"></i>
        </a>
    </div>
    <?php if (empty($notesSaisies)): ?>
    <div class="text-center py-5 text-muted">
        <i class="bi bi-inbox fs-1 d-block mb-2 opacity-25"></i>
        Aucune note saisie
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="gn-table">
            <thead><tr><th>Étudiant</th><th>Cours</th><th>Note</th><th>Type</th><th>Date</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach (array_slice($notesSaisies, 0, 8) as $n): ?>
            <tr>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-2 bg-primary text-white d-flex align-items-center justify-content-center fw-bold"
                             style="width:30px;height:30px;font-size:11px;flex-shrink:0">
                            <?= strtoupper(substr($n['etu_prenom'],0,1).substr($n['etu_nom'],0,1)) ?>
                        </div>
                        <div>
                            <strong><?= h($n['etu_prenom'].' '.$n['etu_nom']) ?></strong><br>
                            <small class="text-muted"><?= h($n['matricule']) ?></small>
                        </div>
                    </div>
                </td>
                <td class="text-muted"><?= h($n['cours_libelle']) ?></td>
                <td><span class="note-badge <?= classeNote($n['valeur']) ?>"><?= $n['valeur'] ?>/20</span></td>
                <td><span class="badge bg-secondary bg-opacity-10 text-secondary"><?= h($n['type_evaluation'] ?? 'Examen') ?></span></td>
                <td><small class="text-muted"><?= date('d/m/Y', strtotime($n['date_saisie'])) ?></small></td>
                <td><a href="?onglet=notes&suppr_note_etu=<?=$n['fk_etudiant']?>&suppr_note_cours=<?=$n['fk_cours']?>" class="btn btn-outline-danger btn-sm py-0 px-2" data-confirm="Supprimer cette note ?"><i class="bi bi-x"></i></a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>


<?php elseif ($onglet === 'notes'): ?>
<nav aria-label="breadcrumb" class="gn-ariane">
    <span>Professeur</span> <i class="bi bi-chevron-right"></i>
    <span class="actuel">Saisir les notes</span>
</nav>

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
                        <label class="form-label">Cours (vos cours uniquement) *</label>
                        <select name="fk_cours" class="form-select" required>
                            <option value="">— Sélectionner —</option>
                            <?php foreach ($mesCours as $c): ?>
                            <option value="<?= $c['id_cours'] ?>">
                                <?= h($c['libelle']) ?> (coef. <?= $c['coefficient'] ?>)
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
                <div class="gn-carte-titre">Notes récentes</div>
                <span class="badge bg-primary rounded-pill"><?= count($notesSaisies) ?></span>
            </div>
            <div class="table-responsive">
                <table class="gn-table">
                    <thead><tr><th>Étudiant</th><th>Cours</th><th>Note</th><th>Type</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($notesSaisies as $n): ?>
                    <tr>
                        <td><?= h($n['etu_prenom'].' '.$n['etu_nom']) ?></td>
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


<?php elseif ($onglet === 'cours'): ?>
<nav aria-label="breadcrumb" class="gn-ariane">
    <span>Professeur</span> <i class="bi bi-chevron-right"></i>
    <span class="actuel">Mes cours</span>
</nav>
<div class="gn-carte">
    <div class="gn-carte-entete">
        <div class="gn-carte-titre">Mes cours</div>
        <span class="badge bg-primary rounded-pill"><?= count($mesCours) ?></span>
    </div>
    <div class="table-responsive">
        <table class="gn-table">
            <thead><tr><th>Libellé</th><th>Code</th><th>Semestre</th><th>Coefficient</th><th>Étudiants notés</th></tr></thead>
            <tbody>
            <?php foreach ($mesCours as $c): ?>
            <tr>
                <td><strong><?= h($c['libelle']) ?></strong></td>
                <td><small class="text-muted"><?= h($c['code']??'') ?></small></td>
                <td>S<?= $c['semestre'] ?></td>
                <td><span class="badge bg-warning bg-opacity-10 text-warning fw-bold">×<?= $c['coefficient'] ?></span></td>
                <td><?= $c['nb_etudiants'] ?> étudiant(s)</td>
                <td>
                    <a href="?onglet=cours_details&id_cours=<?= $c['id_cours'] ?>" class="btn btn-sm btn-outline-primary py-0">
                        Voir les notes <i class="bi bi-arrow-right-short"></i>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>


<?php elseif ($onglet === 'cours_details' && isset($_GET['id_cours'])):
    $idCours = (int)$_GET['id_cours'];
    $leCours = null;
    foreach ($mesCours as $c) {
        if ($c['id_cours'] == $idCours) { $leCours = $c; break; }
    }
    if (!$leCours) { echo "<div class='alert alert-danger'>Cours introuvable ou vous n'y avez pas accès.</div>"; }
    else {
        // Obtenir toutes les notes de CE cours
        $notesDuCours = array_filter($notesSaisies, fn($n) => $n['id_cours'] == $idCours);

        $moyenneClasse = 0;
        if (count($notesDuCours) > 0) {
            $sum = array_sum(array_column($notesDuCours, 'valeur'));
            $moyenneClasse = round($sum / count($notesDuCours), 2);
        }
?>
<nav aria-label="breadcrumb" class="gn-ariane">
    <span>Professeur</span> <i class="bi bi-chevron-right"></i>
    <a href="?onglet=cours" class="text-decoration-none">Mes cours</a> <i class="bi bi-chevron-right"></i>
    <span class="actuel"><?= h($leCours['libelle']) ?></span>
</nav>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="gn-stat s-bleu">
            <div class="gn-stat-lbl">Cours</div>
            <div class="fw-bold mt-1" style="font-size:1.1rem"><?= h($leCours['libelle']) ?></div>
            <div class="small text-muted mt-1">S<?= $leCours['semestre'] ?> — Coef. <?= $leCours['coefficient'] ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="gn-stat s-vert">
            <div class="gn-stat-lbl">Nombre d'étudiants notés</div>
            <div class="gn-stat-val mt-1"><?= count($notesDuCours) ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="gn-stat <?= classeNote($moyenneClasse) ?>">
            <div class="gn-stat-lbl">Moyenne de la classe</div>
            <div class="gn-stat-val mt-1"><?= $moyenneClasse ?>/20</div>
        </div>
    </div>
</div>

<div class="gn-carte">
    <div class="gn-carte-entete">
        <div class="gn-carte-titre">Liste des notes (<?= h($leCours['libelle']) ?>)</div>
        <a href="?onglet=notes" class="btn btn-sm btn-primary"><i class="bi bi-plus me-1"></i>Ajouter / Modifier une note</a>
    </div>
    <?php if (empty($notesDuCours)): ?>
    <div class="text-center py-5 text-muted">
        <i class="bi bi-journal-x fs-1 d-block mb-2 opacity-25"></i>
        Aucun étudiant n'a encore reçu de note pour ce cours.
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="gn-table">
            <thead>
                <tr>
                    <th>Étudiant</th>
                    <th>Matricule</th>
                    <th>Note</th>
                    <th>Type</th>
                    <th>Date d'édition</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($notesDuCours as $n): ?>
                <tr>
                    <td><strong><?= h($n['etu_prenom'] . ' ' . $n['etu_nom']) ?></strong></td>
                    <td class="text-muted"><?= h($n['matricule']) ?></td>
                    <td><span class="note-badge <?= classeNote($n['valeur']) ?>"><?= $n['valeur'] ?>/20</span></td>
                    <td><span class="badge bg-secondary bg-opacity-10 text-secondary"><?= h($n['type_evaluation'] ?? 'Examen') ?></span></td>
                    <td><small class="text-muted"><?= date('d/m/Y H:i', strtotime($n['date_saisie'])) ?></small></td>
                    <td>
                        <a href="?onglet=notes&suppr_note_etu=<?=$n['fk_etudiant']?>&suppr_note_cours=<?=$idCours?>&from=cours_details" class="btn btn-outline-danger btn-sm py-0 px-2" data-confirm="Attention : Supprimer définitivement cette note ?">
                            <i class="bi bi-trash"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php } ?>


<?php elseif ($onglet === 'reclamations'): ?>
<nav aria-label="breadcrumb" class="gn-ariane">
    <span>Professeur</span> <i class="bi bi-chevron-right"></i>
    <span class="actuel">Réclamations</span>
</nav>

<?php if (empty($reclamations)): ?>
<div class="gn-carte text-center py-5 text-muted">
    <i class="bi bi-check-circle fs-1 d-block mb-2 text-success opacity-50"></i>
    <strong>Aucune réclamation en attente</strong>
</div>
<?php else: ?>
<div class="d-flex flex-column gap-3">
<?php foreach ($reclamations as $r): ?>
<div class="gn-carte">
    <div class="gn-carte-entete">
        <div>
            <div class="gn-carte-titre"><?= h($r['objet']) ?></div>
            <div class="gn-carte-sous">
                <?= h($r['etu_prenom'].' '.$r['etu_nom']) ?>
                (<?= h($r['matricule']) ?>) —
                <?= date('d/m/Y', strtotime($r['date_creation'])) ?>
            </div>
        </div>
        <span class="badge bg-warning rounded-pill">En attente</span>
    </div>
    <div class="gn-carte-corps">
        <div class="alert alert-light border mb-3 p-2" style="font-size:.83rem">
            <?= h($r['description']) ?>
        </div>
        <form method="POST" class="row g-2">
            <input type="hidden" name="rid" value="<?= $r['id_reclamation'] ?>">
            <div class="col-md-6">
                <label class="form-label">Votre réponse *</label>
                <input type="text" name="reponse" class="form-control"
                       placeholder="Réponse à l'étudiant…" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Décision</label>
                <select name="statut_recla" class="form-select">
                    <option value="en_cours">En cours</option>
                    <option value="resolu">Résolu</option>
                    <option value="rejete">Rejeté</option>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" name="repondre_recla" class="btn btn-primary w-100">
                    <i class="bi bi-send-fill me-1"></i>Envoyer
                </button>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<?php include '../gabarit_fin.php'; ?>
