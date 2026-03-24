<?php
session_start();
require '../configuration.php';
require '../securite.php';
verifierAcces('etudiant');

$profondeur = 1;
$u = moi();


$stmt = $pdo->prepare("SELECT e.* FROM etudiant e WHERE e.fk_user = ?");
$stmt->execute([$u['id_utilisateur']]);
$etudiant = $stmt->fetch();
$eid = $etudiant['idetudiant'] ?? null;


$notes = [];
$moyenne = null;
$totalPts = $totalCoef = 0;

if ($eid) {
    $stmt = $pdo->prepare("
        SELECT n.*, c.libelle, c.coefficient, c.code, c.semestre,
               p.nom as prof_nom, p.prenom as prof_prenom
        FROM note n
        JOIN cours c ON n.fk_cours = c.id_cours
        JOIN professeur p ON c.fk_prof = p.id_prof
        WHERE n.fk_etudiant = ?
        ORDER BY c.semestre, c.libelle
    ");
    $stmt->execute([$eid]);
    $notes = $stmt->fetchAll();

    foreach ($notes as $n) {
        $totalPts  += $n['valeur'] * $n['coefficient'];
        $totalCoef += $n['coefficient'];
    }
    $moyenne = $totalCoef > 0 ? round($totalPts / $totalCoef, 2) : null;
}


$reclamations = [];
if ($eid) {
    $stmt = $pdo->prepare("SELECT * FROM reclamation WHERE fk_etudiant = ? ORDER BY date_creation DESC");
    $stmt->execute([$eid]);
    $reclamations = $stmt->fetchAll();
}


$planches = $pdo->query("
    SELECT p.*, c.libelle as cours_libelle,
           pr.nom as auteur_nom, pr.prenom as auteur_prenom
    FROM planche p
    JOIN cours c ON p.fk_cours = c.id_cours
    JOIN professeur pr ON c.fk_prof = pr.id_prof
    ORDER BY p.date_creation DESC LIMIT 10
")->fetchAll();


$cours = $pdo->query("
    SELECT c.*, p.nom as prof_nom, p.prenom as prof_prenom
    FROM cours c JOIN professeur p ON c.fk_prof = p.id_prof
    ORDER BY c.semestre, c.libelle
")->fetchAll();


$emploi = $pdo->query("
    SELECT e.*, c.libelle as cours_libelle
    FROM emploi_du_temps e
    JOIN cours c ON e.fk_cours = c.id_cours
    WHERE e.jour >= CURDATE() AND e.jour <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY e.jour, e.heure_debut
")->fetchAll();


$onglet = $_GET['onglet'] ?? 'accueil';


$msgSucces = $msgErreur = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['soumettre_recla']) && $eid) {
    $objet = trim($_POST['objet'] ?? '');
    $desc  = trim($_POST['description'] ?? '');
    $fkNote = !empty($_POST['fk_note']) ? (int)$_POST['fk_note'] : null;

    if (!$objet || !$desc) {
        $msgErreur = "Veuillez remplir tous les champs obligatoires.";
    } else {
        $pdo->prepare("
            INSERT INTO reclamation (fk_etudiant, fk_note, objet, description)
            VALUES (?, ?, ?, ?)
        ")->execute([$eid, $fkNote, $objet, $desc]);
        journaliser($pdo, 'RECLAMATION_CREATION', 'reclamation', $objet);
        header('Location: tableau_de_bord.php?onglet=reclamations&ok=1');
        exit;
    }
}


function classeNote(float $n): string {
    if ($n >= 14) return 'note-haute';
    if ($n >= 10) return 'note-moyenne';
    return 'note-basse';
}
function mentionNote(float $n): array {
    if ($n >= 16) return ['Très Bien',  'success'];
    if ($n >= 14) return ['Bien',        'primary'];
    if ($n >= 12) return ['Assez Bien',  'info'];
    if ($n >= 10) return ['Passable',    'warning'];
    return               ['Insuffisant', 'danger'];
}

$titrePage = 'Espace Étudiant';
$sousTitre = ($etudiant['matricule'] ?? '') . ' — ' . ($etudiant['filiere'] ?? '');
$menuItems = [[
    'titre' => 'Mon espace',
    'liens' => [
        ['label'=>'Tableau de bord', 'icone'=>'bi-grid-1x2-fill',   'href'=>'?onglet=accueil',      'actif'=>$onglet==='accueil'],
        ['label'=>'Mes notes',       'icone'=>'bi-journal-text',     'href'=>'?onglet=notes',        'actif'=>$onglet==='notes'],
        ['label'=>'Planches',        'icone'=>'bi-file-earmark-text','href'=>'?onglet=planches',     'actif'=>$onglet==='planches'],
        ['label'=>'Cours',           'icone'=>'bi-book-fill',        'href'=>'?onglet=cours',        'actif'=>$onglet==='cours'],
        ['label'=>'Emploi du temps', 'icone'=>'bi-calendar-week',   'href'=>'?onglet=emploi',       'actif'=>$onglet==='emploi'],
        ['label'=>'Réclamations',    'icone'=>'bi-chat-square-text', 'href'=>'?onglet=reclamations', 'actif'=>$onglet==='reclamations',
         'badge'=> count(array_filter($reclamations, fn($r)=>$r['statut']==='en_attente')),
         'badgeClass'=>'bg-danger'],
    ]
]];

include '../gabarit.php';
?>

<?php if (($_GET['ok']??'')==='1'): ?>
<div class="gn-toast"><i class="bi bi-check-circle-fill text-success me-2"></i>Réclamation soumise avec succès</div>
<?php endif; ?>


<?php if ($onglet === 'accueil'): ?>

<nav aria-label="breadcrumb" class="gn-ariane">
    <span>Étudiant</span> <i class="bi bi-chevron-right"></i>
    <span class="actuel">Tableau de bord</span>
</nav>


<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="gn-stat s-bleu">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div class="gn-stat-ico bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-journal-text"></i>
                </div>
            </div>
            <div class="gn-stat-val"><?= count($notes) ?></div>
            <div class="gn-stat-lbl">Notes enregistrées</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="gn-stat s-vert">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div class="gn-stat-ico bg-success bg-opacity-10 text-success">
                    <i class="bi bi-graph-up"></i>
                </div>
            </div>
            <div class="gn-stat-val"><?= $moyenne ?? '—' ?></div>
            <div class="gn-stat-lbl">Moyenne générale / 20</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="gn-stat s-orange">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div class="gn-stat-ico bg-warning bg-opacity-10 text-warning">
                    <i class="bi bi-file-earmark-text"></i>
                </div>
            </div>
            <div class="gn-stat-val"><?= count($planches) ?></div>
            <div class="gn-stat-lbl">Planches disponibles</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="gn-stat s-rouge">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div class="gn-stat-ico bg-danger bg-opacity-10 text-danger">
                    <i class="bi bi-chat-square-text"></i>
                </div>
            </div>
            <div class="gn-stat-val"><?= count($reclamations) ?></div>
            <div class="gn-stat-lbl">Réclamations soumises</div>
        </div>
    </div>
</div>


<div class="row g-3">
    <?php if ($moyenne !== null): ?>
    <div class="col-md-4">
        <div class="gn-carte h-100">
            <div class="gn-carte-entete">
                <div class="gn-carte-titre">Résultat général</div>
            </div>
            <div class="gn-carte-corps text-center py-4">
                <?php [$ml, $mc] = mentionNote($moyenne); ?>
                <div style="font-family:'Playfair Display',serif;font-size:3.5rem;font-weight:700;color:#1e293b;line-height:1">
                    <?= $moyenne ?>
                </div>
                <div class="text-muted mb-3" style="font-size:.8rem">sur 20 — moyenne pondérée</div>
                <span class="badge bg-<?= $mc ?> rounded-pill px-3 py-2" style="font-size:.82rem">
                    <?= $ml ?>
                </span>
                <div class="progress mt-3" style="height:8px;border-radius:99px">
                    <div class="progress-bar bg-<?= $mc ?>" style="width:<?= ($moyenne/20)*100 ?>%"></div>
                </div>
                <div class="text-muted mt-2" style="font-size:.72rem"><?= count($notes) ?> cours — coef. total <?= $totalCoef ?></div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="col-md-<?= $moyenne !== null ? '8' : '12' ?>">
        <div class="gn-carte">
            <div class="gn-carte-entete">
                <div>
                    <div class="gn-carte-titre">Mes dernières notes</div>
                    <div class="gn-carte-sous"><?= count($notes) ?> notes enregistrées</div>
                </div>
                <a href="?onglet=notes" class="btn btn-sm btn-outline-primary">
                    Voir tout <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
            <?php if (empty($notes)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox fs-1 d-block mb-2 opacity-25"></i>
                Aucune note enregistrée
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="gn-table">
                    <thead>
                        <tr>
                            <th>Cours</th>
                            <th>Enseignant</th>
                            <th>Coef.</th>
                            <th>Note</th>
                            <th>Mention</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach (array_slice($notes, 0, 6) as $n):
                        [$ml, $mc] = mentionNote($n['valeur']);
                        $cls = classeNote($n['valeur']);
                    ?>
                        <tr>
                            <td>
                                <strong><?= h($n['libelle']) ?></strong><br>
                                <small class="text-muted"><?= h($n['code'] ?? '') ?></small>
                            </td>
                            <td class="text-muted"><?= h($n['prof_prenom'].' '.$n['prof_nom']) ?></td>
                            <td>
                                <span class="badge bg-primary bg-opacity-10 text-primary fw-bold">
                                    ×<?= $n['coefficient'] ?>
                                </span>
                            </td>
                            <td><span class="note-badge <?= $cls ?>"><?= $n['valeur'] ?>/20</span></td>
                            <td><span class="badge bg-<?= $mc ?> rounded-pill"><?= $ml ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>


<?php elseif ($onglet === 'notes'): ?>

<nav aria-label="breadcrumb" class="gn-ariane">
    <span>Étudiant</span> <i class="bi bi-chevron-right"></i>
    <span class="actuel">Mes notes</span>
</nav>

<?php if ($moyenne !== null): ?>
<div class="gn-carte mb-4">
    <div class="gn-carte-corps">
        <div class="row align-items-center">
            <div class="col-auto">
                <?php [$ml, $mc] = mentionNote($moyenne); ?>
                <div style="font-family:'Playfair Display',serif;font-size:3rem;font-weight:700;color:#1a56db;line-height:1">
                    <?= $moyenne ?>
                </div>
                <div class="text-muted" style="font-size:.75rem">Moyenne pondérée / 20</div>
            </div>
            <div class="col">
                <div class="progress mb-2" style="height:10px;border-radius:99px">
                    <div class="progress-bar bg-<?= $mc ?>" style="width:<?= ($moyenne/20)*100 ?>%"></div>
                </div>
                <span class="badge bg-<?= $mc ?> rounded-pill"><?= $ml ?></span>
                <span class="text-muted ms-2" style="font-size:.75rem"><?= count($notes) ?> cours — coef. total <?= $totalCoef ?></span>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="gn-carte">
    <div class="gn-carte-entete">
        <div class="gn-carte-titre">Relevé de notes complet</div>
        <span class="badge bg-primary rounded-pill"><?= count($notes) ?> notes</span>
    </div>
    <?php if (empty($notes)): ?>
    <div class="text-center py-5 text-muted">
        <i class="bi bi-inbox fs-1 d-block mb-2 opacity-25"></i>
        Aucune note enregistrée
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="gn-table">
            <thead>
                <tr>
                    <th>Cours</th><th>Code</th><th>Sem.</th>
                    <th>Enseignant</th><th>Type</th><th>Coef.</th>
                    <th>Note</th><th>Mention</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($notes as $n):
                [$ml, $mc] = mentionNote($n['valeur']);
                $cls = classeNote($n['valeur']);
            ?>
                <tr>
                    <td><strong><?= h($n['libelle']) ?></strong></td>
                    <td><small class="text-muted"><?= h($n['code'] ?? '') ?></small></td>
                    <td class="text-muted">S<?= $n['semestre'] ?></td>
                    <td class="text-muted"><?= h($n['prof_prenom'].' '.$n['prof_nom']) ?></td>
                    <td><span class="badge bg-secondary bg-opacity-10 text-secondary"><?= h($n['type_evaluation'] ?? 'Examen') ?></span></td>
                    <td><span class="badge bg-primary bg-opacity-10 text-primary fw-bold">×<?= $n['coefficient'] ?></span></td>
                    <td><span class="note-badge <?= $cls ?>"><?= $n['valeur'] ?>/20</span></td>
                    <td><span class="badge bg-<?= $mc ?> rounded-pill"><?= $ml ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>


<?php elseif ($onglet === 'planches'): ?>

<nav aria-label="breadcrumb" class="gn-ariane">
    <span>Étudiant</span> <i class="bi bi-chevron-right"></i>
    <span class="actuel">Planches de cours</span>
</nav>

<div class="gn-carte">
    <div class="gn-carte-entete">
        <div class="gn-carte-titre">Supports de cours disponibles</div>
        <span class="badge bg-primary rounded-pill"><?= count($planches) ?></span>
    </div>
    <?php if (empty($planches)): ?>
    <div class="text-center py-5 text-muted">
        <i class="bi bi-file-earmark-x fs-1 d-block mb-2 opacity-25"></i>
        Aucune planche disponible
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="gn-table">
            <thead><tr><th>Titre</th><th>Cours</th><th>Auteur</th><th>Date</th></tr></thead>
            <tbody>
            <?php foreach ($planches as $p): ?>
            <tr>
                <td>
                    <strong><?= h($p['titre']) ?></strong>
                    <?php if ($p['description']): ?>
                    <br><small class="text-muted"><?= h(mb_substr($p['description'],0,60)) ?>…</small>
                    <?php endif; ?>
                </td>
                <td><span class="badge bg-primary bg-opacity-10 text-primary"><?= h($p['cours_libelle']) ?></span></td>
                <td class="text-muted"><?= h($p['auteur_prenom'].' '.$p['auteur_nom']) ?></td>
                <td class="text-muted"><small><?= date('d/m/Y', strtotime($p['date_creation'])) ?></small></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>


<?php elseif ($onglet === 'cours'): ?>

<nav aria-label="breadcrumb" class="gn-ariane">
    <span>Étudiant</span> <i class="bi bi-chevron-right"></i>
    <span class="actuel">Catalogue des cours</span>
</nav>

<div class="row g-3">
<?php foreach ($cours as $c): ?>
<div class="col-md-6 col-lg-4">
    <div class="gn-carte h-100">
        <div class="gn-carte-corps">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="badge bg-primary bg-opacity-10 text-primary"><?= h($c['code'] ?? 'N/A') ?></span>
                <span class="badge bg-secondary bg-opacity-10 text-secondary">S<?= $c['semestre'] ?></span>
            </div>
            <div class="fw-bold mb-1" style="color:#1e293b"><?= h($c['libelle']) ?></div>
            <div class="text-muted mb-3" style="font-size:.78rem">
                <i class="bi bi-person-fill me-1"></i>
                <?= h($c['prof_prenom'].' '.$c['prof_nom']) ?>
            </div>
            <div class="d-flex justify-content-between align-items-center">
                <span class="badge bg-warning bg-opacity-10 text-warning fw-bold">
                    Coefficient ×<?= $c['coefficient'] ?>
                </span>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>


<?php elseif ($onglet === 'emploi'): ?>

<nav aria-label="breadcrumb" class="gn-ariane">
    <span>Étudiant</span> <i class="bi bi-chevron-right"></i>
    <span class="actuel">Emploi du temps</span>
</nav>

<div class="gn-carte">
    <div class="gn-carte-entete">
        <div class="gn-carte-titre">Prochaines séances (7 jours)</div>
    </div>
    <?php if (empty($emploi)): ?>
    <div class="text-center py-5 text-muted">
        <i class="bi bi-calendar-x fs-1 d-block mb-2 opacity-25"></i>
        Aucune séance planifiée cette semaine
    </div>
    <?php else: ?>
    <?php
    $typeCouleurs = ['Cours'=>'primary','TP'=>'success','TD'=>'info','Examen'=>'danger'];
    $dateActuelle = '';
    foreach ($emploi as $e):
        $dateSeance = date('d/m/Y', strtotime($e['jour']));
        if ($dateSeance !== $dateActuelle):
            $dateActuelle = $dateSeance;
    ?>
    <div class="px-3 py-2 bg-light border-bottom fw-semibold text-muted" style="font-size:.78rem">
        <i class="bi bi-calendar3 me-1"></i>
        <?= date('l d F Y', strtotime($e['jour'])) ?>
    </div>
    <?php endif; ?>
    <div class="d-flex align-items-center gap-3 px-3 py-2 border-bottom">
        <div class="text-center" style="min-width:70px">
            <div class="fw-bold" style="font-size:.85rem"><?= substr($e['heure_debut'],0,5) ?></div>
            <div class="text-muted" style="font-size:.72rem"><?= substr($e['heure_fin'],0,5) ?></div>
        </div>
        <div class="flex-grow-1">
            <div class="fw-semibold"><?= h($e['cours_libelle']) ?></div>
            <small class="text-muted">Salle : <?= h($e['salle'] ?? 'N/A') ?></small>
        </div>
        <span class="badge bg-<?= $typeCouleurs[$e['type_seance']] ?? 'secondary' ?> rounded-pill">
            <?= h($e['type_seance']) ?>
        </span>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>


<?php elseif ($onglet === 'reclamations'): ?>

<nav aria-label="breadcrumb" class="gn-ariane">
    <span>Étudiant</span> <i class="bi bi-chevron-right"></i>
    <span class="actuel">Mes réclamations</span>
</nav>

<?php if ($msgErreur): ?>
<div class="alert alert-danger d-flex gap-2 mb-3"><i class="bi bi-exclamation-triangle-fill"></i><?= h($msgErreur) ?></div>
<?php endif; ?>

<div class="row g-3">
    
    <div class="col-md-5">
        <div class="gn-carte">
            <div class="gn-carte-entete">
                <div class="gn-carte-titre">Nouvelle réclamation</div>
            </div>
            <div class="gn-carte-corps">
                <form method="POST" class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Objet *</label>
                        <input type="text" name="objet" class="form-control"
                               placeholder="Ex : Erreur de note — Algorithmique" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Note concernée (optionnel)</label>
                        <select name="fk_note" class="form-select">
                            <option value="">— Aucune —</option>
                            <?php foreach ($notes as $n): ?>
                            <option value="<?= $n['id_note'] ?>">
                                <?= h($n['libelle']) ?> (<?= $n['valeur'] ?>/20)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description détaillée *</label>
                        <textarea name="description" class="form-control" rows="4"
                                  placeholder="Expliquez votre réclamation…" required></textarea>
                    </div>
                    <div class="col-12">
                        <button type="submit" name="soumettre_recla" class="btn btn-primary w-100">
                            <i class="bi bi-send-fill me-2"></i>Soumettre
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    
    <div class="col-md-7">
        <div class="gn-carte">
            <div class="gn-carte-entete">
                <div class="gn-carte-titre">Mes réclamations</div>
                <span class="badge bg-primary rounded-pill"><?= count($reclamations) ?></span>
            </div>
            <?php
            $statutCfg = [
                'en_attente' => ['warning', 'En attente'],
                'en_cours'   => ['primary', 'En cours'],
                'resolu'     => ['success', 'Résolu'],
                'rejete'     => ['danger',  'Rejeté'],
            ];
            if (empty($reclamations)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-chat-square-x fs-1 d-block mb-2 opacity-25"></i>
                Aucune réclamation soumise
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="gn-table">
                    <thead><tr><th>Objet</th><th>Date</th><th>Statut</th><th>Réponse</th></tr></thead>
                    <tbody>
                    <?php foreach ($reclamations as $r):
                        [$sc, $sl] = $statutCfg[$r['statut']] ?? ['secondary','?'];
                    ?>
                    <tr>
                        <td><strong><?= h($r['objet']) ?></strong></td>
                        <td><small class="text-muted"><?= date('d/m/Y', strtotime($r['date_creation'])) ?></small></td>
                        <td><span class="badge bg-<?= $sc ?> rounded-pill"><?= $sl ?></span></td>
                        <td><small class="text-muted"><?= $r['reponse'] ? h(mb_substr($r['reponse'],0,40)).'…' : '<em>—</em>' ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include '../gabarit_fin.php'; ?>
