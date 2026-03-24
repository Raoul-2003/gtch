<?php
session_start();
require 'configuration.php';
require 'securite.php';

$uRole = $_SESSION['utilisateur']['role'] ?? '';
if ($uRole !== 'rup' && $uRole !== 'superadmin') {
    die("Accès refusé. Réservé aux RUP et Administrateurs.");
}


$annee    = trim($_GET['annee']    ?? '2025-2026');
$parcours = trim($_GET['parcours'] ?? '');
$cycle    = trim($_GET['cycle']    ?? 'Technicien Supérieur');
$classe   = trim($_GET['classe']   ?? '');
$semestre = (int)($_GET['semestre'] ?? 3);
$filiere  = trim($_GET['filiere']  ?? '');


$stmtCours = $pdo->prepare("
    SELECT c.id_cours, c.libelle, c.coefficient, c.code,
           c.semestre, c.ue,
           p.nom as pnom, p.prenom as pprenom
    FROM cours c
    LEFT JOIN professeur p ON c.fk_prof = p.id_prof
    WHERE c.semestre = ?
    ORDER BY c.ue, c.libelle
");
$stmtCours->execute([$semestre]);
$cours = $stmtCours->fetchAll();

$ueGroups = [];
foreach ($cours as $c) {
    $ue = !empty($c['ue']) ? $c['ue'] : 'UE1';
    $ueGroups[$ue][] = $c;
}


$whereFiliere = $filiere ? "AND e.filiere = ?" : "";
$params = $filiere ? [$filiere] : [];
$stmtEtu = $pdo->prepare("
    SELECT e.idetudiant, e.matricule, e.filiere, 
           u.nom, u.prenom, u.role,
           COALESCE(e.genre, 'M') as genre,
           COALESCE(e.statut, 'NR') as statut
    FROM etudiant e
    JOIN utilisateurs u ON e.fk_user = u.id_utilisateur
    WHERE u.actif = 1 $whereFiliere
    ORDER BY u.nom, u.prenom
");
$stmtEtu->execute($params);
$etudiants = $stmtEtu->fetchAll();


$allNotes = [];
if (!empty($etudiants) && !empty($cours)) {
    $etuIds  = array_column($etudiants, 'idetudiant');
    $coursIds = array_column($cours, 'id_cours');
    $placeholders_e = implode(',', array_fill(0, count($etuIds), '?'));
    $placeholders_c = implode(',', array_fill(0, count($coursIds), '?'));
    $stmtN = $pdo->prepare("
        SELECT fk_etudiant, fk_cours, valeur
        FROM note
        WHERE fk_etudiant IN ($placeholders_e)
        AND fk_cours IN ($placeholders_c)
    ");
    $stmtN->execute(array_merge($etuIds, $coursIds));
    foreach ($stmtN->fetchAll() as $n) {
        $allNotes[$n['fk_etudiant']][$n['fk_cours']] = (float)$n['valeur'];
    }
}


function calcMoyUE(array $notesEtu, array $coursUE): ?float {
    $pts = 0; $coef = 0;
    foreach ($coursUE as $c) {
        if (isset($notesEtu[$c['id_cours']])) {
            $pts  += $notesEtu[$c['id_cours']] * $c['coefficient'];
            $coef += $c['coefficient'];
        }
    }
    return $coef > 0 ? round($pts / $coef, 2) : null;
}

function calcMoyGen(array $notesEtu, array $allCours): ?float {
    $pts = 0; $coef = 0;
    foreach ($allCours as $c) {
        if (isset($notesEtu[$c['id_cours']])) {
            $pts  += $notesEtu[$c['id_cours']] * $c['coefficient'];
            $coef += $c['coefficient'];
        }
    }
    return $coef > 0 ? round($pts / $coef, 2) : null;
}

function mention_courte(float $m): string {
    if ($m >= 16) return 'TB';
    if ($m >= 14) return 'B';
    if ($m >= 12) return 'AB';
    if ($m >= 10) return 'P';
    return 'AVERT';
}

function decision(float $m): string {
    if ($m >= 10) return 'VAL';
    return 'NVAL';
}

$lignes = [];
foreach ($etudiants as $etu) {
    $notesEtu = $allNotes[$etu['idetudiant']] ?? [];
    $moyUE    = [];
    foreach ($ueGroups as $ue => $coursUE) {
        $moyUE[$ue] = calcMoyUE($notesEtu, $coursUE);
    }
    $moyGen = calcMoyGen($notesEtu, $cours);
    $lignes[] = [
        'etu'    => $etu,
        'notes'  => $notesEtu,
        'moyUE'  => $moyUE,
        'moyGen' => $moyGen,
    ];
}

$moyennes_valides = array_filter(array_column($lignes, 'moyGen'), fn($m) => $m !== null);
$moyClasse = !empty($moyennes_valides) ? round(array_sum($moyennes_valides) / count($moyennes_valides), 2) : null;
$minClasse = !empty($moyennes_valides) ? min($moyennes_valides) : null;
$maxClasse = !empty($moyennes_valides) ? max($moyennes_valides) : null;

$filieres   = $pdo->query("SELECT DISTINCT filiere FROM etudiant WHERE filiere IS NOT NULL ORDER BY filiere")->fetchAll(PDO::FETCH_COLUMN);
$semestres  = $pdo->query("SELECT DISTINCT semestre FROM cours WHERE semestre IS NOT NULL ORDER BY semestre")->fetchAll(PDO::FETCH_COLUMN);

$genPdf = isset($_GET['pdf']) && $_GET['pdf'] === '1';

ob_start();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Planche de notes — ESI</title>
    <?php if (!$genPdf): ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php endif; ?>
    <style>
        body { font-family: 'DejaVu Sans', 'Poppins', sans-serif; background: #fff; <?php if (!$genPdf) echo "background: #f8fafc;"; ?> }

        <?php if (!$genPdf): ?>
        .params-card {
            background: #fff; border: 1px solid #e2e8f0; border-radius: 12px;
            padding: 20px 24px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,.05);
        }
        .params-title { font-size: .88rem; font-weight: 700; color: #1e293b; margin-bottom: 14px; display: flex; align-items: center; gap: 8px; }
        .params-title i { color: #7c1d4e; }
        .form-label { font-size: .7rem !important; font-weight: 600 !important; color: #64748b !important; }
        .form-control, .form-select { font-size: .82rem !important; border: 1.5px solid #e2e8f0 !important; border-radius: 8px !important; }
        .btn-esi { background: #7c1d4e; color: #fff; border: none; border-radius: 8px; font-size: .82rem; font-weight: 600; padding: .5rem 1.1rem; }
        .btn-outline-esi { background: transparent; color: #7c1d4e; border: 1.5px solid #7c1d4e; border-radius: 8px; font-size: .82rem; font-weight: 600; padding: .48rem 1.1rem; text-decoration: none; }
        <?php endif; ?>

        .planche-wrap { <?php if (!$genPdf) echo "overflow-x: auto;"; ?> }
        
        .planche { width: 100%; border-collapse: collapse; font-size: 7.5px; font-family: 'DejaVu Sans', Arial, sans-serif; border: 1px solid #000; }
        .planche th, .planche td { border: 1px solid #888; padding: 2px 3px; vertical-align: middle; text-align: center; }
        .planche thead { background: #f1f5f9; }

        .th-ecole { background: #fff; text-align: left; font-size: 8px; padding: 4px 6px; vertical-align: top; }
        .th-ue { background: #1e3a5f; color: #fff; font-weight: 700; font-size: 7.5px; padding: 3px 4px; }
        .th-mat { background: #dbe8f8; font-size: 6.5px; <?php if (!$genPdf) echo "writing-mode: vertical-rl; transform: rotate(180deg); height: 60px; max-width: 22px;"; else echo "height: 40px;"; ?> }
        .th-coef { background: #eef4fb; font-size: 6.5px; }
        .td-num { font-weight: 700; background: #fafafa; }
        .td-mat { font-weight: 700; text-align: left; min-width: 120px; }
        .td-note { min-width: 20px; }
        .td-moy { font-weight: 700; background: #fdf8f0; }
        .td-moygen { font-weight: 700; background: #e8f4e8; font-size: 8.5px; }

        .note-nr { color: #94a3b8; font-style: italic; }
        .tr-stats { background: #f8fafc; font-weight: 700; font-size: 7px; color: #1e293b; }

        @media print {
            .no-print { display: none !important; }
            body { background: #fff; padding: 0; margin: 0; }
            .planche-wrap { overflow: visible; }
            .planche { font-size: 6.5px; }
            @page { size: A3 landscape; margin: 5mm; }
        }
    </style>
</head>
<body class="<?= !$genPdf ? 'p-3' : '' ?>">

<?php if (!$genPdf): ?>
<div class="d-flex align-items-center gap-3 mb-3 no-print">
    <img src="assets/r.png" alt="ESI" style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:2px solid #7c1d4e">
    <div>
        <div style="font-size:.92rem;font-weight:700;color:#1e293b">Planche de notes — ESI</div>
        <div style="font-size:.72rem;color:#64748b">planche du semestre</div>
    </div>
    <a href="javascript:history.back()" class="ms-auto btn-outline-esi">
        <i class="bi bi-arrow-left"></i> Retour
    </a>
</div>

<div class="params-card no-print">
    <div class="params-title"><i class="bi bi-sliders"></i> Paramètres de la planche</div>
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-2">
            <label class="form-label">Année académique</label>
            <input type="text" name="annee" class="form-control" value="<?= h($annee) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Parcours</label>
            <input type="text" name="parcours" class="form-control" value="<?= h($parcours) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label">Cycle</label>
            <input type="text" name="cycle" class="form-control" value="<?= h($cycle) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label">Classe / Série</label>
            <input type="text" name="classe" class="form-control" value="<?= h($classe) ?>">
        </div>
        <div class="col-md-1">
            <label class="form-label">Semestre</label>
            <select name="semestre" class="form-select">
                <?php foreach (($semestres ?: [1,2,3,4,5,6]) as $s): ?>
                <option value="<?= $s ?>" <?= $semestre==$s?'selected':'' ?>>S<?= $s ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Filière</label>
            <select name="filiere" class="form-select">
                <option value="">— Toutes —</option>
                <?php foreach ($filieres as $f): ?>
                <option value="<?= h($f) ?>" <?= $filiere===$f?'selected':'' ?>><?= h($f) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 d-flex gap-2 mt-2">
            <button type="submit" class="btn-esi">Générer</button>
            <a href="?<?= http_build_query(array_merge($_GET, ['pdf'=>'1'])) ?>" class="btn-esi" style="background:#dc2626">Télécharger PDF</a>
            <button type="button" onclick="window.print()" class="btn-outline-esi">Imprimer</button>
        </div>
    </form>
</div>
<?php endif; ?>

<?php if (empty($cours)): ?>
<div style="padding:14px;color:#92400e;background:#fffbeb;">Aucun cours pour ce semestre.</div>
<?php elseif (empty($etudiants)): ?>
<div style="padding:14px;color:#92400e;background:#fffbeb;">Aucun étudiant trouvé.</div>
<?php else: ?>

<div class="planche-wrap">
<table class="planche">
    <thead>
    <tr>
        <td class="th-ecole" colspan="5" rowspan="3" style="font-size:7.5px;">
            <div style="font-weight:700;font-size:9px;margin-bottom:3px">École : ECOLE SUPERIEURE D'INDUSTRIE (ESI)</div>
            <div style="margin-top:5px;line-height:1.6">
                <strong>Année académique :</strong> <?= h($annee) ?><br>
                <?php if($parcours): ?><strong>Parcours :</strong> <?= h($parcours) ?><br><?php endif; ?>
                <strong>Cycle :</strong> <?= h($cycle) ?><br>
                <?php if($classe): ?><strong>Classe :</strong> <?= h($classe) ?><br><?php endif; ?>
                <strong>Semestre :</strong> <?= $semestre ?>
            </div>
        </td>
        <?php foreach ($ueGroups as $ue => $coursUE): ?>
        <th class="th-ue" colspan="<?= count($coursUE) + 2 ?>"><?= h($ue) ?></th>
        <?php endforeach; ?>
        <th class="th-ue" colspan="4" style="background:#3b0a2a">Bilan du semestre <?= $semestre ?></th>
        <th class="th-ue" colspan="4" style="background:#374151">Décisions</th>
    </tr>
    <tr>
        <?php foreach ($ueGroups as $ue => $coursUE): ?>
            <?php foreach ($coursUE as $c): ?>
            <th class="th-mat"><?= h($c['libelle']) ?></th>
            <?php endforeach; ?>
            <th class="th-mat" style="background:#e8f0fb;font-weight:700">Moy <?= h($ue) ?></th>
            <th class="th-mat" style="background:#e0ecf8;font-size:6px">Créd.</th>
        <?php endforeach; ?>
        <th class="th-mat" style="background:#d4edda;font-weight:700">Moy. Sém.</th>
        <th class="th-mat" style="background:#d4edda">Mention</th>
        <th class="th-mat" style="background:#d4edda">Rang</th>
        <th class="th-mat" style="background:#d4edda">Abs.</th>
        <th class="th-mat" style="background:#fce4e4">Décision</th>
        <th class="th-mat" style="background:#fce4e4">Jury</th>
        <th class="th-mat" style="background:#fce4e4">Obs.</th>
        <th class="th-mat" style="background:#fce4e4">Crédits</th>
    </tr>
    <tr style="background:#f0f4f8">
        <td colspan="2" style="font-size:6px;font-weight:700;text-align:right;">Coef :</td>
        <?php foreach ($ueGroups as $ue => $coursUE): ?>
            <?php foreach ($coursUE as $c): ?>
            <td class="th-coef" style="font-size:6.5px;font-weight:700"><?= $c['coefficient'] ?></td>
            <?php endforeach; ?>
            <td class="th-coef" style="background:#dbeafe"></td>
            <td class="th-coef" style="background:#dbeafe"></td>
        <?php endforeach; ?>
        <td colspan="8"></td>
    </tr>
    <tr style="background:#f8fafc">
        <td colspan="2" style="font-size:6px;text-align:right;">Enseignant :</td>
        <?php foreach ($ueGroups as $ue => $coursUE): ?>
            <?php foreach ($coursUE as $c): ?>
            <td style="font-size:5px;line-height:0.8;<?= !$genPdf ? 'writing-mode:vertical-rl;transform:rotate(180deg);height:30px;' : 'height:15px;' ?>">
                <?= h($c['pnom'].' '.(mb_substr($c['pprenom']??'',0,1)).'.') ?>
            </td>
            <?php endforeach; ?>
            <td colspan="2"></td>
        <?php endforeach; ?>
        <td colspan="8"></td>
    </tr>
    <tr style="background:#1e3a5f;color:#fff;font-size:7px;font-weight:700">
        <td>N°</td><td>Matricule</td><td style="text-align:left">NOM et Prénoms</td><td>G</td><td>St.</td>
        <?php foreach ($ueGroups as $ue => $coursUE): ?>
            <?php foreach ($coursUE as $c): ?>
            <td><?= h($c['code']??'—') ?></td>
            <?php endforeach; ?>
            <td style="background:#2d5a8e">Moy</td><td style="background:#2d5a8e">Cr</td>
        <?php endforeach; ?>
        <td style="background:#1a4a1a">Moy</td><td style="background:#1a4a1a">Men.</td>
        <td style="background:#1a4a1a">Rg</td><td style="background:#1a4a1a">Abs</td>
        <td style="background:#5a1a1a">Déc.</td><td style="background:#5a1a1a">Jury</td>
        <td style="background:#5a1a1a">Obs</td><td style="background:#5a1a1a">Cr</td>
    </tr>
    </thead>
    <tbody>
    <?php
    usort($lignes, fn($a, $b) => ($b['moyGen'] ?? -1) <=> ($a['moyGen'] ?? -1));
    $rang = 1; foreach ($lignes as &$l) { $l['rang'] = $l['moyGen'] !== null ? $rang++ : '—'; } unset($l);
    usort($lignes, fn($a, $b) => strcmp($a['etu']['nom'].$a['etu']['prenom'], $b['etu']['nom'].$b['etu']['prenom']));
    $num = 1;
    ?>
    <?php foreach ($lignes as $ligne):
        $n = $ligne['notes']; $m = $ligne['moyGen'];
    ?>
    <tr>
        <td class="td-num"><?= $num++ ?></td>
        <td style="font-size:7px;font-weight:700;color:#7c1d4e"><?= h($ligne['etu']['matricule']) ?></td>
        <td class="td-mat"><?= h(strtoupper($ligne['etu']['nom']).' '.$ligne['etu']['prenom']) ?></td>
        <td style="font-size:7px"><?= h($ligne['etu']['genre']) ?></td>
        <td style="font-size:7px"><?= h($ligne['etu']['statut']) ?></td>
        <?php foreach ($ueGroups as $ue => $coursUE): ?>
            <?php foreach ($coursUE as $c): $nt = $n[$c['id_cours']] ?? null; ?>
            <td class="td-note <?= $nt!==null?($nt>=10?'':'note-b'):'note-nr' ?>"><?= $nt!==null?number_format($nt,2):'NR' ?></td>
            <?php endforeach; ?>
            <?php $muy = $ligne['moyUE'][$ue] ?? null; ?>
            <td class="td-moy <?= $muy!==null?($muy>=10?'':'note-b'):'note-nr' ?>"><?= $muy!==null?number_format($muy,2):'NR' ?></td>
            <td><?= $muy!==null&&$muy>=10?count($coursUE):0 ?></td>
        <?php endforeach; ?>
        <td class="td-moygen <?= $m!==null?($m>=10?'':'note-b'):'note-nr' ?>"><?= $m!==null?number_format($m,2):'NR' ?></td>
        <td style="font-size:7px"><?= $m!==null?mention_courte($m):'' ?></td>
        <td style="font-size:7px"><?= $ligne['rang'] ?></td><td>0</td>
        <td style="font-size:7.5px;color:<?= $m!==null&&$m>=10?'#15803d':'#dc2626'?>"><?= $m!==null?decision($m):'' ?></td>
        <td></td><td></td>
        <td style="font-size:7px"><?= $m!==null&&$m>=10?count($cours):0 ?></td>
    </tr>
    <?php endforeach; ?>
    <tr class="tr-stats" style="background:#1e3a5f;color:#fff">
        <td colspan="5" style="text-align:right">Moyenne de classe</td>
        <?php foreach ($ueGroups as $ue => $coursUE): ?>
            <?php foreach ($coursUE as $c): ?>
            <td>
                <?php $notesCol = array_filter(array_map(fn($l)=>$l['notes'][$c['id_cours']]??null, $lignes), fn($v)=>$v!==null); echo $notesCol ? number_format(array_sum($notesCol)/count($notesCol),2) : '—'; ?>
            </td>
            <?php endforeach; ?>
            <td>
                <?php $moysCols = array_filter(array_map(fn($l)=>$l['moyUE'][$ue]??null,$lignes),fn($v)=>$v!==null); echo $moysCols ? number_format(array_sum($moysCols)/count($moysCols),2) : '—'; ?>
            </td>
            <td></td>
        <?php endforeach; ?>
        <td style="font-size:8px;font-weight:800;background:#1a5a1a"><?= $moyClasse !== null ? number_format($moyClasse, 2) : '—' ?></td>
        <td colspan="7">Min: <?= $minClasse !== null ? number_format($minClasse,2) : '—' ?> / Max: <?= $maxClasse !== null ? number_format($maxClasse,2) : '—' ?></td>
    </tr>
    </tbody>
</table>
</div>
<?php endif; ?>

</body></html>
<?php
$html = ob_get_clean();

if ($genPdf) {
    if (!file_exists(__DIR__ . '/fpdf/fpdf.php')) {
        die("FPDF introuvable dans le dossier fpdf/.");
    }
    require_once __DIR__ . '/fpdf/fpdf.php';
    
    $pdf = new FPDF('L', 'mm', 'A3');
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 10, utf8_decode("Planche de notes - ESI - S$semestre"), 0, 1, 'C');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 8, utf8_decode("Année : $annee | Parcours : $parcours | Filière : " . ($filiere ?: 'Toutes')), 0, 1, 'C');
    $pdf->Ln(5);

    $pdf->SetFont('Arial', 'B', 7);
    $pdf->SetFillColor(30, 58, 95);
    $pdf->SetTextColor(255, 255, 255);

    // Calc widths
    $wNum = 6; $wMat = 16; $wNom = 35; $wG = 5; $wSt = 5;
    $wFinMoy = 10; $wMen = 12; $wRg = 7; $wDc = 12; $wCr = 8;
    $fixW = $wNum + $wMat + $wNom + $wG + $wSt + $wFinMoy + $wMen + $wRg + $wDc + $wCr;
    $dynW = count($cours) + count($ueGroups)*2; // courses + (Moy UE + Cr UE)
    $avail = 400 - $fixW;
    $cellW = $dynW > 0 ? floor($avail / $dynW) : 10;
    if ($cellW < 8) $cellW = 8; // min size

    // Header Y1: UEs
    $h = 5;
    $pdf->Cell($wNum+$wMat+$wNom+$wG+$wSt, $h, "", 'LTR', 0, 'C', true); // empty top-left
    foreach ($ueGroups as $ue => $coursUE) {
        $pdf->Cell(count($coursUE)*$cellW + 2*$cellW, $h, utf8_decode($ue), 1, 0, 'C', true);
    }
    $pdf->Cell($wFinMoy+$wMen+$wRg+$wDc+$wCr, $h, "Bilan & Decis.", 1, 1, 'C', true);

    // Header Y2: Cours & details
    $pdf->SetFillColor(220, 230, 240);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell($wNum, $h, "N", 'L', 0, 'C', true);
    $pdf->Cell($wMat, $h, "Matricule", '0', 0, 'C', true);
    $pdf->Cell($wNom, $h, "Nom & Prenoms", '0', 0, 'C', true);
    $pdf->Cell($wG, $h, "G", '0', 0, 'C', true);
    $pdf->Cell($wSt, $h, "St", 'R', 0, 'C', true);
    
    foreach ($ueGroups as $ue => $coursUE) {
        foreach ($coursUE as $c) {
            $pdf->Cell($cellW, $h, utf8_decode(substr($c['code'], 0, 6)), 1, 0, 'C', true);
        }
        $pdf->SetFillColor(200, 210, 230);
        $pdf->Cell($cellW, $h, "Moy.", 1, 0, 'C', true);
        $pdf->Cell($cellW, $h, "Cr.", 1, 0, 'C', true);
        $pdf->SetFillColor(220, 230, 240);
    }
    $pdf->SetFillColor(200, 230, 200);
    $pdf->Cell($wFinMoy, $h, "M.Gen", 1, 0, 'C', true);
    $pdf->Cell($wMen, $h, "Mention", 1, 0, 'C', true);
    $pdf->Cell($wRg, $h, "Rg", 1, 0, 'C', true);
    $pdf->SetFillColor(240, 220, 220);
    $pdf->Cell($wDc, $h, "Decis.", 1, 0, 'C', true);
    $pdf->Cell($wCr, $h, "Cred.", 1, 1, 'C', true);

    // Body
    $pdf->SetFont('Arial', '', 7);
    $num = 1;
    foreach ($lignes as $ligne) {
        $pdf->Cell($wNum, $h, $num++, 1, 0, 'C');
        $pdf->Cell($wMat, $h, utf8_decode($ligne['etu']['matricule']), 1, 0, 'C');
        // Nom can be long
        $nom = utf8_decode(strtoupper($ligne['etu']['nom']).' '.$ligne['etu']['prenom']);
        if (strlen($nom) > 23) $nom = substr($nom, 0, 21) . '..';
        $pdf->Cell($wNom, $h, $nom, 1, 0, 'L');
        $pdf->Cell($wG, $h, utf8_decode($ligne['etu']['genre']), 1, 0, 'C');
        $pdf->Cell($wSt, $h, utf8_decode($ligne['etu']['statut']), 1, 0, 'C');

        foreach ($ueGroups as $ue => $coursUE) {
            foreach ($coursUE as $c) {
                $nt = $ligne['notes'][$c['id_cours']] ?? null;
                $pdf->Cell($cellW, $h, $nt !== null ? number_format($nt, 2) : 'NR', 1, 0, 'C');
            }
            $muy = $ligne['moyUE'][$ue] ?? null;
            $pdf->Cell($cellW, $h, $muy !== null ? number_format($muy, 2) : 'NR', 1, 0, 'C');
            $pdf->Cell($cellW, $h, $muy !== null && $muy >= 10 ? count($coursUE) : 0, 1, 0, 'C');
        }

        $m = $ligne['moyGen'];
        $pdf->SetFont('Arial', 'B', 7);
        $pdf->Cell($wFinMoy, $h, $m !== null ? number_format($m, 2) : 'NR', 1, 0, 'C');
        $pdf->SetFont('Arial', '', 7);
        
        $pdf->Cell($wMen, $h, $m !== null ? utf8_decode(mention_courte($m)) : '', 1, 0, 'C');
        $pdf->Cell($wRg, $h, utf8_decode($ligne['rang']), 1, 0, 'C');
        $pdf->Cell($wDc, $h, $m !== null ? utf8_decode(decision($m)) : '', 1, 0, 'C');
        $pdf->Cell($wCr, $h, $m !== null && $m >= 10 ? count($cours) : 0, 1, 1, 'C');
    }

    $pdf->Output("planche_S{$semestre}_ESI.pdf", 'D');
    exit;
}
echo $html;
