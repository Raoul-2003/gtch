<?php
session_start();
require 'configuration.php';
require 'securite.php';

$uRole = $_SESSION['utilisateur']['role'] ?? '';
if ($uRole !== 'rup' && $uRole !== 'superadmin') {
    die("Accès refusé. Réservé aux RUP et Administrateurs.");
}

$idetudiant = (int)($_GET['idetudiant'] ?? 0);
if (!$idetudiant) die("ID Étudiant manquant.");

$semestre = (int)($_GET['semestre'] ?? 3);

$stmtEtu = $pdo->prepare("
    SELECT e.idetudiant, e.matricule, e.filiere, 
           u.nom, u.prenom, COALESCE(e.genre, 'M') as genre, e.annee
    FROM etudiant e
    JOIN utilisateurs u ON e.fk_user = u.id_utilisateur
    WHERE e.idetudiant = ?
");
$stmtEtu->execute([$idetudiant]);
$etu = $stmtEtu->fetch();
if (!$etu) die("Étudiant introuvable.");

$stmtCours = $pdo->prepare("
    SELECT c.id_cours, c.libelle, c.coefficient, c.code,
           c.semestre, COALESCE(c.ue, 'UE1') as ue,
           p.nom as pnom, p.prenom as pprenom
    FROM cours c
    LEFT JOIN professeur p ON c.fk_prof = p.id_prof
    WHERE c.semestre = ?
    ORDER BY c.ue, c.libelle
");
$stmtCours->execute([$semestre]);
$cours = $stmtCours->fetchAll();

$coursIds = array_column($cours, 'id_cours');
$notesEtu = [];
if (!empty($coursIds)) {
    $placeholders = implode(',', array_fill(0, count($coursIds), '?'));
    $stmtN = $pdo->prepare("
        SELECT fk_cours, valeur
        FROM note
        WHERE fk_etudiant = ? AND fk_cours IN ($placeholders)
    ");
    $stmtN->execute(array_merge([$idetudiant], $coursIds));
    foreach ($stmtN->fetchAll() as $n) {
        $notesEtu[$n['fk_cours']] = (float)$n['valeur'];
    }
}

$ueGroups = [];
foreach ($cours as $c) {
    if (!isset($ueGroups[$c['ue']])) $ueGroups[$c['ue']] = ['cours'=>[], 'pts'=>0, 'coef'=>0];
    $ueGroups[$c['ue']]['cours'][] = $c;
    
    if (isset($notesEtu[$c['id_cours']])) {
        $ueGroups[$c['ue']]['pts'] += $notesEtu[$c['id_cours']] * $c['coefficient'];
        $ueGroups[$c['ue']]['coef'] += $c['coefficient'];
    }
}

$totalPts = 0;
$totalCoef = 0;
foreach ($ueGroups as $ue => $g) {
    $totalPts += $g['pts'];
    $totalCoef += $g['coef'];
}
$moyenneGen = $totalCoef > 0 ? round($totalPts / $totalCoef, 2) : 0;

function classeNote(float $n): string {
    if ($n >= 14) return 'Bien';
    if ($n >= 12) return 'Assez Bien';
    if ($n >= 10) return 'Passable';
    return 'Insuffisant';
}

require('fpdf/fpdf.php');

class PDF extends FPDF {
    function Header() {
        $logoPath = __DIR__ . '/assets/r.png';
        if (file_exists($logoPath)) {
            $this->Image($logoPath, 10, 8, 20);
        }
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 8, utf8_decode("ÉCOLE SUPÉRIEURE D'INDUSTRIE (ESI)"), 0, 1, 'C');
        $this->SetFont('Arial', '', 11);
        $this->Cell(0, 6, utf8_decode("Bulletin de Notes"), 0, 1, 'C');
        $this->Ln(10);
    }
    
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page '.$this->PageNo().'/{nb}', 0, 0, 'C');
    }
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 11);

$pdf->SetFillColor(240, 240, 240);
$pdf->Cell(30, 8, 'Matricule :', 0, 0, 'L', true);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(60, 8, utf8_decode($etu['matricule']), 0, 0, 'L', true);

$pdf->SetFont('Arial', '', 11);
$pdf->Cell(30, 8, utf8_decode('Filière :'), 0, 0, 'L', true);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(70, 8, utf8_decode($etu['filiere']), 0, 1, 'L', true);

$pdf->SetFont('Arial', '', 11);
$pdf->Cell(30, 8, 'Nom :', 0, 0, 'L');
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(60, 8, utf8_decode(strtoupper($etu['nom'])), 0, 0, 'L');

$pdf->SetFont('Arial', '', 11);
$pdf->Cell(30, 8, utf8_decode('Prénom :'), 0, 0, 'L');
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(70, 8, utf8_decode($etu['prenom']), 0, 1, 'L');

$pdf->SetFont('Arial', '', 11);
$pdf->Cell(30, 8, 'Semestre :', 0, 0, 'L');
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(60, 8, "S" . $semestre, 0, 1, 'L');

$pdf->Ln(10);

$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(30, 58, 95);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(95, 8, utf8_decode('Matière'), 1, 0, 'C', true);
$pdf->Cell(20, 8, 'Coef', 1, 0, 'C', true);
$pdf->Cell(25, 8, 'Note / 20', 1, 0, 'C', true);
$pdf->Cell(25, 8, 'Note * Coef', 1, 0, 'C', true);
$pdf->Cell(25, 8, 'Mention', 1, 1, 'C', true);

$pdf->SetTextColor(0, 0, 0);

foreach ($ueGroups as $ue => $g) {
    if (count($g['cours']) === 0) continue;
    
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(220, 230, 240);
    $pdf->Cell(190, 7, utf8_decode($ue), 1, 1, 'L', true);
    
    $pdf->SetFont('Arial', '', 9);
    foreach ($g['cours'] as $c) {
        $cName = utf8_decode($c['libelle']);
        if (strlen($cName) > 55) $cName = substr($cName, 0, 52) . "...";
        
        $coef = $c['coefficient'];
        $nt = $notesEtu[$c['id_cours']] ?? null;
        
        $noteStr = $nt !== null ? number_format($nt, 2) : 'N/A';
        $nxC = $nt !== null ? number_format($nt * $coef, 2) : 'N/A';
        $mention = $nt !== null ? utf8_decode(classeNote($nt)) : '';
        
        $pdf->Cell(95, 7, $cName, 1, 0, 'L');
        $pdf->Cell(20, 7, $coef, 1, 0, 'C');
        $pdf->Cell(25, 7, $noteStr, 1, 0, 'C');
        $pdf->Cell(25, 7, $nxC, 1, 0, 'C');
        $pdf->Cell(25, 7, $mention, 1, 1, 'C');
    }
    
    if ($g['coef'] > 0) {
        $moyUE = number_format($g['pts'] / $g['coef'], 2);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(115, 6, 'Moyenne ' . utf8_decode($ue), 1, 0, 'R');
        $pdf->Cell(25, 6, $moyUE . " / 20", 1, 1, 'C');
    }
}

$pdf->Ln(5);
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetFillColor(30, 58, 95);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(190, 8, 'BILAN DU SEMESTRE', 1, 1, 'C', true);

$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(47.5, 8, 'Total Coefficients', 1, 0, 'C');
$pdf->Cell(47.5, 8, $totalCoef, 1, 0, 'C');
$pdf->Cell(47.5, 8, 'Total Points', 1, 0, 'C');
$pdf->Cell(47.5, 8, number_format($totalPts, 2), 1, 1, 'C');

$pdf->SetFont('Arial', 'B', 12);
$pdf->SetFillColor(240, 240, 240);
$pdf->Cell(95, 10, 'MOYENNE GENERALE :', 1, 0, 'R', true);
$pdf->Cell(95, 10, number_format($moyenneGen, 2) . ' / 20', 1, 1, 'C', true);

$decision = $moyenneGen >= 10 ? "Admis(e)" : "Ajourne(e)";
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(95, 8, utf8_decode('Décision du Jury :'), 1, 0, 'R');
$pdf->SetTextColor($moyenneGen >= 10 ? 0 : 200, $moyenneGen >= 10 ? 100 : 0, 0);
$pdf->Cell(95, 8, utf8_decode($decision), 1, 1, 'C');
$pdf->SetTextColor(0,0,0);

$pdf->Ln(15);
$pdf->Cell(95, 8, 'Le Directeur / Le RUP', 0, 0, 'C');
$pdf->Cell(95, 8, "L'Etudiant(e)", 0, 1, 'C');

$pdf->Output('I', "Bulletin_{$etu['matricule']}_S{$semestre}.pdf");
