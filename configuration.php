<?php

define('BD_HOTE',        'localhost');
define('BD_NOM',         'gestion_note');
define('BD_UTILISATEUR', 'root');
define('BD_MOTPASSE',    '');

try {
    $pdo = new PDO(
        "mysql:host=".BD_HOTE.";dbname=".BD_NOM.";charset=utf8mb4",
        BD_UTILISATEUR,
        BD_MOTPASSE,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die('<html><body style="font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#fef2f2"><div style="background:#fff;border:1px solid #fecaca;border-left:4px solid #dc2626;border-radius:10px;padding:24px 32px;max-width:480px"><h3 style="color:#dc2626;margin:0 0 10px">Connexion BD échouée</h3><p style="color:#64748b;margin:0;font-size:.88rem">'.$e->getMessage().'</p></div></body></html>');
}


function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}


function mention(float $note): array {
    if ($note >= 16) return ['Très Bien',  'success'];
    if ($note >= 14) return ['Bien',        'primary'];
    if ($note >= 12) return ['Assez Bien',  'info'];
    if ($note >= 10) return ['Passable',    'warning'];
    return                  ['Insuffisant', 'danger'];
}


function couleurNote(float $note): string {
    if ($note >= 14) return 'success';
    if ($note >= 10) return 'warning';
    return 'danger';
}


function journaliser(PDO $pdo, string $action, string $table = '', string $details = ''): void {
    $idUser = $_SESSION['utilisateur']['id_utilisateur'] ?? null;
    $ip     = $_SERVER['REMOTE_ADDR'] ?? '';
    try {
        $pdo->prepare("
            INSERT INTO historique_actions
                (fk_utilisateur, action, table_concernee, details, adresse_ip)
            VALUES (?, ?, ?, ?, ?)
        ")->execute([$idUser, $action, $table, $details, $ip]);
    } catch (Exception $e) {
        
    }
}