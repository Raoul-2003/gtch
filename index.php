<?php

session_start();
if (!empty($_SESSION['utilisateur'])) {
    $dest = [
        'etudiant'   => 'eleve/tableau_de_bord.php',
        'professeur' => 'professeur/tableau_de_bord.php',
        'rup'        => 'rup/tableau_de_bord.php',
        'superadmin' => 'administration/tableau_de_bord.php',
    ];
    header('Location: '.($dest[$_SESSION['utilisateur']['role']] ?? 'connexion.php'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GestionNotes — Plateforme de gestion scolaire INP-HB</title>
    <meta name="description" content="Plateforme de gestion des notes scolaires de l'INP-HB. Gérez vos évaluations, notes, planches et réclamations.">

    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Playfair+Display:wght@700;900&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary:   #1a56db;
            --primary-d: #1649c2;
            --secondary: #0e9f6e;
            --accent:    #f59e0b;
            --dark:      #0f172a;
            --dark2:     #1e293b;
            --text:      #334155;
            --muted:     #64748b;
            --light:     #E3DDDC;
            --border:    #d0c8c7;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        html { scroll-behavior: smooth; }

        body {
            font-family: 'Poppins', sans-serif;
            color: var(--text);
            background: #E3DDDC;
            overflow-x: hidden;
        }

        
        .gn-nav {
            position: fixed; top: 0; left: 0; right: 0; z-index: 1000;
            background: rgba(227, 221, 220, 0.97);
            border-bottom: 1px solid var(--border);
            box-shadow: 0 2px 20px rgba(0,0,0,.06);
            transition: all .3s;
        }
        .gn-nav .navbar-brand {
            font-family: 'Playfair Display', serif;
            font-size: 1.4rem; font-weight: 700;
            color: var(--dark) !important;
            display: flex; align-items: center; gap: 10px;
        }
        .brand-icon {
            width: 36px; height: 36px;
            background: linear-gradient(135deg, var(--primary), #312e81);
            border-radius: 9px;
            display: flex; align-items: center; justify-content: center;
            font-size: 16px;
            box-shadow: 0 3px 10px rgba(26,86,219,.3);
        }
        .gn-nav .nav-link {
            font-size: .85rem; font-weight: 500;
            color: var(--text) !important;
            padding: .5rem 1rem !important;
            border-radius: 6px;
            transition: all .15s;
        }
        .gn-nav .nav-link:hover { color: var(--primary) !important; background: #eff4ff; }
        .btn-nav-login {
            background: var(--primary) !important;
            color: #fff !important;
            border-radius: 8px !important;
            font-size: .85rem !important;
            font-weight: 600 !important;
            padding: .45rem 1.2rem !important;
            box-shadow: 0 3px 12px rgba(26,86,219,.25) !important;
            transition: all .15s !important;
        }
        .btn-nav-login:hover { background: var(--primary-d) !important; transform: translateY(-1px); }
        .btn-nav-signup {
            background: transparent !important;
            color: var(--primary) !important;
            border: 1.5px solid var(--primary) !important;
            border-radius: 8px !important;
            font-size: .85rem !important;
            font-weight: 600 !important;
            padding: .45rem 1.2rem !important;
            transition: all .15s !important;
        }
        .btn-nav-signup:hover { background: #eff4ff !important; }

        .hero {
            min-height: 100vh;
            background-image: url('assets/inp.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            position: relative;
            display: flex; align-items: center;
        }
        .hero::before {
            content: '';
            position: absolute; inset: 0;
            background: linear-gradient(135deg,
                rgba(00,00,00,.88) 0%,
                rgba(00,00,00,.80) 50%,
                rgba(00,00,00,.85) 100%);
        }
        .hero-content { position: relative; z-index: 1; }
        .hero-badge {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(255,255,255,.12);
            border: 1px solid rgba(255,255,255,.25);
            border-radius: 99px;
            padding: 6px 16px;
            font-size: .78rem; font-weight: 500;
            color: rgba(255,255,255,.9);
            margin-bottom: 24px;
            backdrop-filter: blur(6px);
        }
        .hero-badge span { color: #60a5fa; }
        .hero h1 {
            font-family: 'Playfair Display', serif;
            font-size: clamp(2.4rem, 5vw, 3.8rem);
            font-weight: 900;
            color: #fff;
            line-height: 1.15;
            margin-bottom: 20px;
            text-shadow: 0 2px 20px rgba(0,0,0,.3);
        }
        .hero h1 span { color: #60a5fa; }
        .hero p {
            font-size: 1.05rem;
            color: rgba(255,255,255,.82);
            line-height: 1.75;
            max-width: 520px;
            margin-bottom: 36px;
        }
        .hero-btns { display: flex; gap: 14px; flex-wrap: wrap; }
        .btn-hero-primary {
            background: linear-gradient(135deg, var(--primary), #1d4ed8);
            color: #fff; border: none;
            padding: .8rem 2rem; border-radius: 10px;
            font-size: .95rem; font-weight: 600;
            box-shadow: 0 4px 20px rgba(26,86,219,.4);
            transition: all .2s; text-decoration: none;
            display: inline-flex; align-items: center; gap: 8px;
        }
        .btn-hero-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(26,86,219,.45); color: #fff; }
        .btn-hero-outline {
            background: rgba(255,255,255,.12);
            color: #fff; border: 1.5px solid rgba(255,255,255,.35);
            padding: .8rem 2rem; border-radius: 10px;
            font-size: .95rem; font-weight: 600;
            backdrop-filter: blur(6px);
            transition: all .2s; text-decoration: none;
            display: inline-flex; align-items: center; gap: 8px;
        }
        .btn-hero-outline:hover { background: rgba(255,255,255,.2); color: #fff; }

        /* Stat cards dans le hero */
        .hero-stats { margin-top: 60px; }
        .stat-card {
            background: rgba(255,255,255,.1);
            border: 1px solid rgba(255,255,255,.2);
            border-radius: 14px; padding: 20px 24px;
            text-align: center;
            backdrop-filter: blur(8px);
            transition: transform .2s;
        }
        .stat-card:hover { transform: translateY(-4px); }
        .stat-card .nb {
            font-family: 'Playfair Display', serif;
            font-size: 2.2rem; font-weight: 900;
            color: #fff; line-height: 1;
        }
        .stat-card .lb {
            font-size: .72rem; color: rgba(255,255,255,.7);
            margin-top: 6px;
        }

        .carousel-section {
            background: var(--light);
            padding: 60px 0;
        }
        .carousel-section .section-badge { margin-bottom: 14px; }
        .carousel-item-inner {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 48px 40px;
            text-align: center;
            box-shadow: 0 8px 32px rgba(0,0,0,.07);
            margin: 0 auto;
            max-width: 520px;
            position: relative;
        }
        .carousel-item-inner::before {
            content: '';
            position: absolute; top: 0; left: 0; right: 0; height: 4px;
            border-radius: 20px 20px 0 0;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }
        .carousel-avatar {
            width: 72px; height: 72px;
            background: linear-gradient(135deg, var(--primary), #312e81);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.8rem; margin: 0 auto 16px;
            box-shadow: 0 4px 16px rgba(26,86,219,.25);
        }
        .carousel-name {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem; font-weight: 700;
            color: var(--dark2); margin-bottom: 6px;
        }
        .carousel-role {
            font-size: .78rem; color: var(--muted);
            font-weight: 500; letter-spacing: .05em;
            text-transform: uppercase; margin-bottom: 24px;
        }
        .carousel-moyenne-label {
            font-size: .72rem; font-weight: 700;
            letter-spacing: .1em; text-transform: uppercase;
            color: var(--muted); margin-bottom: 8px;
        }
        .carousel-moyenne-val {
            font-family: 'Playfair Display', serif;
            font-size: 3.2rem; font-weight: 900;
            line-height: 1;
        }
        .carousel-moyenne-val.excellent { color: #0e9f6e; }
        .carousel-moyenne-val.bien      { color: var(--primary); }
        .carousel-moyenne-val.passable  { color: var(--accent); }
        .carousel-mention {
            display: inline-block;
            font-size: .72rem; font-weight: 700;
            padding: 4px 14px; border-radius: 99px;
            margin-top: 10px; letter-spacing: .05em;
        }
        .carousel-section .carousel-control-prev,
        .carousel-section .carousel-control-next {
            width: 5%; filter: invert(1) opacity(.4);
        }
        .carousel-section .carousel-indicators [data-bs-target] {
            background-color: var(--primary);
            border-radius: 50%;
            width: 8px; height: 8px;
            border: none;
        }

        section { padding: 90px 0; }
        .section-badge {
            display: inline-block;
            background: #eff4ff; color: var(--primary);
            border: 1px solid #c7d7fd;
            border-radius: 99px; padding: 5px 16px;
            font-size: .72rem; font-weight: 700;
            letter-spacing: .1em; text-transform: uppercase;
            margin-bottom: 14px;
        }
        .section-title {
            font-family: 'Playfair Display', serif;
            font-size: clamp(1.8rem, 3.5vw, 2.6rem);
            font-weight: 700; color: var(--dark2);
            line-height: 1.2; margin-bottom: 14px;
        }
        .section-sub {
            font-size: .95rem; color: var(--muted);
            line-height: 1.75; max-width: 560px;
        }

        /* ══ SECTION FONCTIONNALITÉS */
        .features { background: var(--light); }
        .feat-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 16px; padding: 28px 24px;
            height: 100%;
            transition: all .2s;
            position: relative; overflow: hidden;
        }
        .feat-card::before {
            content: ''; position: absolute;
            top: 0; left: 0; right: 0; height: 3px;
            border-radius: 16px 16px 0 0;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            opacity: 0; transition: opacity .2s;
        }
        .feat-card:hover { transform: translateY(-6px); box-shadow: 0 12px 36px rgba(0,0,0,.1); }
        .feat-card:hover::before { opacity: 1; }
        .feat-icon {
            width: 52px; height: 52px;
            border-radius: 13px; margin-bottom: 18px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px;
        }
        .feat-title { font-size: 1rem; font-weight: 700; color: var(--dark2); margin-bottom: 8px; }
        .feat-desc  { font-size: .84rem; color: var(--muted); line-height: 1.65; }

        /*  SECTION RÔLES  */
        .roles { background: var(--dark); }
        .roles .section-title { color: #fff; }
        .roles .section-sub  { color: rgba(255,255,255,.65); }
        .roles .section-badge { background: rgba(26,86,219,.2); border-color: rgba(26,86,219,.4); }
        .role-card {
            background: rgba(255,255,255,.06);
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 16px; padding: 32px 28px;
            height: 100%;
            transition: all .2s;
        }
        .role-card:hover {
            background: rgba(255,255,255,.1);
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0,0,0,.3);
        }
        .role-icon {
            font-size: 2.5rem; margin-bottom: 16px; display: block;
        }
        .role-title { font-size: 1.1rem; font-weight: 700; color: #fff; margin-bottom: 8px; }
        .role-level {
            display: inline-block;
            font-size: .62rem; font-weight: 700;
            letter-spacing: .1em; text-transform: uppercase;
            padding: 3px 10px; border-radius: 99px;
            margin-bottom: 12px;
        }
        .role-desc { font-size: .82rem; color: rgba(255,255,255,.65); line-height: 1.65; }
        .role-perms { list-style: none; padding: 0; margin-top: 14px; }
        .role-perms li {
            font-size: .78rem; color: rgba(255,255,255,.7);
            padding: 4px 0;
            display: flex; align-items: center; gap: 8px;
        }
        .role-perms li i { font-size: 11px; flex-shrink: 0; }

        /*  SECTION COMMENT ÇA MARCHE  */
        .how { background: #E3DDDC; }
        .step-num {
            width: 48px; height: 48px;
            background: linear-gradient(135deg, var(--primary), #312e81);
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-family: 'Playfair Display', serif;
            font-size: 1.3rem; font-weight: 700; color: #fff;
            flex-shrink: 0;
            box-shadow: 0 4px 14px rgba(26,86,219,.3);
        }
        .step-title { font-size: .95rem; font-weight: 700; color: var(--dark2); margin-bottom: 4px; }
        .step-desc  { font-size: .82rem; color: var(--muted); line-height: 1.6; }
        .step-connector {
            width: 2px; height: 40px;
            background: linear-gradient(var(--primary), #312e81);
            margin: 6px 0 6px 23px;
            opacity: .3;
        }

        /*  CTA  */
        .cta-section {
            background: linear-gradient(135deg, var(--primary), #312e81);
            position: relative; overflow: hidden;
        }
        .cta-section::before {
            content: '';
            position: absolute; width: 500px; height: 500px;
            background: rgba(255,255,255,.06);
            border-radius: 50%;
            top: -200px; right: -150px;
        }
        .cta-section::after {
            content: '';
            position: absolute; width: 300px; height: 300px;
            background: rgba(255,255,255,.04);
            border-radius: 50%;
            bottom: -100px; left: -80px;
        }
        .cta-section .container { position: relative; z-index: 1; }
        .cta-section h2 {
            font-family: 'Playfair Display', serif;
            font-size: clamp(1.8rem, 3vw, 2.4rem);
            font-weight: 700; color: #fff; margin-bottom: 14px;
        }
        .cta-section p { color: rgba(255,255,255,.8); font-size: .95rem; line-height: 1.7; }
        .btn-cta-white {
            background: #fff; color: var(--primary);
            padding: .8rem 2rem; border-radius: 10px;
            font-size: .95rem; font-weight: 700;
            border: none; text-decoration: none;
            display: inline-flex; align-items: center; gap: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,.15);
            transition: all .2s;
        }
        .btn-cta-white:hover { transform: translateY(-2px); box-shadow: 0 8px 30px rgba(0,0,0,.2); color: var(--primary); }
        .btn-cta-outline {
            background: transparent; color: #fff;
            padding: .8rem 2rem; border-radius: 10px;
            font-size: .95rem; font-weight: 600;
            border: 1.5px solid rgba(255,255,255,.4);
            text-decoration: none;
            display: inline-flex; align-items: center; gap: 8px;
            transition: all .2s;
        }
        .btn-cta-outline:hover { background: rgba(255,255,255,.12); color: #fff; }

        /*  FOOTER  */
        footer {
            background: var(--dark);
            padding: 70px 0 30px;
        }
        .footer-logo {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem; font-weight: 700; color: #fff;
            display: flex; align-items: center; gap: 10px;
            margin-bottom: 16px;
        }
        .footer-desc { font-size: .84rem; color: rgba(255,255,255,.55); line-height: 1.7; max-width: 280px; }
        .footer-title { font-size: .72rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: rgba(255,255,255,.4); margin-bottom: 16px; }
        .footer-link {
            display: block; font-size: .84rem; color: rgba(255,255,255,.6);
            text-decoration: none; padding: 4px 0;
            transition: color .15s;
        }
        .footer-link:hover { color: #60a5fa; }
        .footer-divider { border-color: rgba(255,255,255,.1); margin: 40px 0 24px; }
        .footer-copy { font-size: .78rem; color: rgba(255,255,255,.35); }
        .footer-copy a { color: rgba(255,255,255,.5); text-decoration: none; }
        .footer-copy a:hover { color: #60a5fa; }

        /*  ANIMATIONS  */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(30px); }
            to   { opacity: 1; transform: none; }
        }
        .fade-up { animation: fadeUp .6s ease both; }
        .delay-1 { animation-delay: .1s; }
        .delay-2 { animation-delay: .2s; }
        .delay-3 { animation-delay: .3s; }
        .delay-4 { animation-delay: .4s; }

        /*  RESPONSIVE  */
        @media (max-width: 768px) {
            section { padding: 60px 0; }
            .hero { min-height: auto; padding: 120px 0 60px; background-attachment: scroll; }
            .hero-stats { margin-top: 40px; }
        }
    </style>
</head>
<body>


<nav class="gn-nav navbar navbar-expand-lg py-2">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <img src="assets/r.png" alt="Logo GestionNotes" style="height: 45px; width: 45px; object-fit: cover; border-radius: 50%;">
            <span class="ms-2">GestionNotes</span>
        </a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
            <i class="bi bi-list fs-4" style="color:var(--dark)"></i>
        </button>
        <div class="collapse navbar-collapse" id="navMenu">
            <ul class="navbar-nav mx-auto gap-1">
                <li class="nav-item"><a class="nav-link" href="#fonctionnalites">Fonctionnalités</a></li>
                <li class="nav-item"><a class="nav-link" href="#roles">Portails</a></li>
                <li class="nav-item"><a class="nav-link" href="#comment">Comment ça marche</a></li>
                <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
            </ul>
            <div class="d-flex gap-2 mt-3 mt-lg-0">
                <a href="inscription.php" class="btn btn-nav-signup">S'inscrire</a>
                <a href="connexion.php"   class="btn btn-nav-login"><i class="bi bi-box-arrow-in-right me-1"></i>Connexion</a>
            </div>
        </div>
    </div>
</nav>



<section class="hero">
    <div class="container hero-content">
        <div class="row align-items-center gy-5">
            <div class="col-lg-7">
                <div class="hero-badge fade-up">
                    <i class="bi bi-mortarboard-fill text-warning"></i>
                    <span>INP-HB</span> — Institut National Polytechnique Houphouët-Boigny
                </div>
                <h1 class="fade-up delay-1">
                    La plateforme de<br>
                    <span>gestion des Notes</span><br>
                    des EIT
                </h1>
                <p class="fade-up delay-2">
                    Gérez vos notes, planches de cours, réclamations et emplois du temps
                    depuis un espace personnalisé selon votre rôle.
                </p>
                <div class="hero-btns fade-up delay-3">
                    <a href="inscription.php" class="btn-hero-primary">
                        <i class="bi bi-person-plus-fill"></i>
                        Créer un compte
                    </a>
                    <a href="connexion.php" class="btn-hero-outline">
                        <i class="bi bi-box-arrow-in-right"></i>
                        Se connecter
                    </a>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="row g-3 hero-stats fade-up delay-4">
                    <div class="col-6">
                        <div class="stat-card">
                            <div class="nb">4</div>
                            <div class="lb">Niveaux d'accès</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stat-card">
                            <div class="nb">100%</div>
                            <div class="lb">En ligne</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stat-card">
                            <div class="nb">CSRF</div>
                            <div class="lb">Sécurisé</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stat-card">
                            <div class="nb">24/7</div>
                            <div class="lb">Accessible</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>



<?php

require_once 'configuration.php';

$top_etudiants = [];
try {
    $top_etudiants = $pdo->query("
        SELECT
            u.nom,
            u.prenom,
            e.matricule,
            e.filiere,
            ROUND(SUM(n.valeur * c.coefficient) / SUM(c.coefficient), 2) AS moyenne
        FROM etudiant e
        JOIN utilisateurs u ON e.fk_user = u.id_utilisateur
        JOIN note n   ON n.fk_etudiant = e.idetudiant
        JOIN cours c  ON n.fk_cours    = c.id_cours
        GROUP BY e.idetudiant
        HAVING SUM(c.coefficient) > 0
        ORDER BY moyenne DESC
        LIMIT 3
    ")->fetchAll();
} catch (Exception $ex) {
    
}

$emojis  = ['🥇','🥈','🥉'];
$classes = ['excellent','bien','passable'];

if (!function_exists('mentionCarousel')) {
    function mentionCarousel(float $m): array {
        if ($m >= 16) return ['Très Bien',   '#0e9f6e', '#f0fdf4'];
        if ($m >= 14) return ['Bien',         '#1a56db', '#eff4ff'];
        if ($m >= 12) return ['Assez Bien',   '#0891b2', '#ecfeff'];
        if ($m >= 10) return ['Passable',     '#b45309', '#fef3c7'];
        return               ['Insuffisant',  '#dc2626', '#fef2f2'];
    }
}
?>
<div class="carousel-section">
    <div class="container">
        <div class="text-center mb-4">
            <span class="section-badge">Classement</span>
            <h2 class="section-title">Meilleurs étudiants</h2>
        </div>
        <div id="carouselExampleFade" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="3000">
            <?php if (empty($top_etudiants)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox fs-1 d-block mb-2 opacity-25"></i>
                Aucune note enregistrée pour le moment
            </div>
            <?php else: ?>
            <div class="carousel-indicators mb-0 pb-0" style="bottom: -15px;">
                <?php foreach ($top_etudiants as $i => $e): ?>
                <button type="button" data-bs-target="#carouselExampleFade"
                        data-bs-slide-to="<?= $i ?>"
                        class="<?= $i === 0 ? 'active' : '' ?>"
                        <?= $i === 0 ? 'aria-current="true"' : '' ?>></button>
                <?php endforeach; ?>
            </div>
            <div class="carousel-inner pb-4">
                <?php foreach ($top_etudiants as $i => $e):
                    $actif         = $i === 0 ? 'active' : '';
                    $emoji         = $emojis[$i]  ?? '🎓';
                    $classe        = $classes[$i] ?? 'passable';
                    [$mention_label, $mention_color, $mention_bg] = mentionCarousel((float)$e['moyenne']);
                ?>
                <div class="carousel-item <?= $actif ?>">
                    <div class="carousel-item-inner">
                        <div class="carousel-avatar"><?= $emoji ?></div>
                        <div class="carousel-name"><?= htmlspecialchars($e['prenom'].' '.$e['nom']) ?></div>
                        <div class="carousel-role">
                            <?= htmlspecialchars($e['filiere'] ?? $e['matricule'] ?? '') ?>
                        </div>
                        <div class="carousel-moyenne-label">Moyenne générale</div>
                        <div class="carousel-moyenne-val <?= $classe ?>"><?= number_format($e['moyenne'], 2, ',', ' ') ?></div>
                        <div>
                            <span class="carousel-mention" style="background:<?= $mention_bg ?>;color:<?= $mention_color ?>">
                                <?= $mention_label ?>
                            </span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <button class="carousel-control-prev" type="button" data-bs-target="#carouselExampleFade" data-bs-slide="prev" style="width: 10%;">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Précédent</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#carouselExampleFade" data-bs-slide="next" style="width: 10%;">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Suivant</span>
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>



<section class="features" id="fonctionnalites">
    <div class="container">
        <div class="row mb-5">
            <div class="col-lg-6">
                <span class="section-badge">Fonctionnalités</span>
                <h2 class="section-title">Tout ce dont vous avez besoin</h2>
                <p class="section-sub">Une suite complète d'outils pour gérer les Notes des EIT, accessible depuis n'importe quel appareil connecté.</p>
            </div>
        </div>
        <div class="row g-4">
            <?php
            $feats = [
                ['bi bi-journal-check', '#eff4ff', 'text-primary', 'Gestion des notes', 'Saisie, consultation et calcul automatique des moyennes pondérées. Relevé complet par semestre et par matière.'],
                ['bi bi-file-earmark-text', '#f0fdf4', 'text-success', 'Planches de cours', 'Accès aux supports de cours déposés par les professeurs. Organisés par matière et par date.'],
                ['bi bi-chat-square-text', '#fef3c7', 'text-warning', 'Réclamations', 'Soumettez vos réclamations en ligne et suivez leur traitement en temps réel.'],
                ['bi bi-calendar-week', '#fef2f2', 'text-danger', 'Emploi du temps', 'Consultez vos séances planifiées : Cours, TP, TD et examens sur les 7 prochains jours.'],
                ['bi bi-people-fill', '#f5f3ff', 'text-purple', 'Gestion des utilisateurs', 'Création et administration des comptes étudiants, professeurs et personnels.'],
                ['bi bi-clock-history', '#fff7ed', 'text-orange', 'Historique & Audit', 'Journal complet de toutes les actions effectuées sur la plateforme.'],
                ['bi bi-shield-fill-check', '#f0fdf4', 'text-success', 'Sécurité renforcée', 'Protection CSRF, sessions sécurisées, hachage bcrypt des mots de passe.'],
                ['bi bi-bar-chart-fill', '#eff4ff', 'text-primary', 'Rapports & Statistiques', 'Classement, moyennes par filière, performance globale — tout en un coup d\'œil.'],
            ];
            foreach ($feats as [$icon, $bg, $col, $title, $desc]):
            ?>
            <div class="col-md-6 col-lg-3">
                <div class="feat-card">
                    <div class="feat-icon" style="background:<?= $bg ?>">
                        <i class="<?= $icon ?> <?= $col ?>" style="font-size:22px"></i>
                    </div>
                    <div class="feat-title"><?= $title ?></div>
                    <div class="feat-desc"><?= $desc ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>



<section class="roles" id="roles">
    <div class="container">
        <div class="row mb-5 justify-content-center text-center">
            <div class="col-lg-7">
                <span class="section-badge">Portails utilisateurs</span>
                <h2 class="section-title">Un espace dédié à chaque rôle</h2>
                <p class="section-sub mx-auto">Quatre niveaux d'accès hiérarchiques avec des droits adaptés à chaque profil.</p>
            </div>
        </div>
        <div class="row g-4">
            <?php
            $roles = [
                ['🎓', 'Étudiant', 'Niveau 1', '#eff4ff', '#1a56db', [
                    'Consulter mes notes et moyennes',
                    'Accéder aux planches de cours',
                    'Voir l\'emploi du temps',
                    'Soumettre des réclamations',
                ]],
                ['👨‍🏫', 'Professeur', 'Niveau 2', '#f0fdf4', '#0e9f6e', [
                    'Saisir et modifier les notes',
                    'Gérer ses cours',
                    'Traiter les réclamations',
                    'Consulter ses étudiants',
                ]],
                ['🧑‍💼', 'RUP', 'Niveau 3', '#fef3c7', '#b45309', [
                    'Gérer tous les utilisateurs',
                    'Administrer le catalogue de cours',
                    'Gérer les planches et planning',
                    'Traiter toutes les réclamations',
                ]],
                ['🛡️', 'Super Admin', 'Niveau 4', '#fef2f2', '#dc2626', [
                    'Accès complet à tout',
                    'Rapports et statistiques globaux',
                    'Gestion des rôles et droits',
                    'Audit et historique complet',
                ]],
            ];
            foreach ($roles as [$emoji, $nom, $niv, $badgeBg, $badgeColor, $perms]):
            ?>
            <div class="col-md-6 col-lg-3">
                <div class="role-card">
                    <span class="role-icon"><?= $emoji ?></span>
                    <span class="role-level" style="background:<?= $badgeBg.'22' ?>;color:<?= $badgeColor ?>">
                        <?= $niv ?>
                    </span>
                    <div class="role-title"><?= $nom ?></div>
                    <ul class="role-perms">
                        <?php foreach ($perms as $p): ?>
                        <li>
                            <i class="bi bi-check-circle-fill" style="color:<?= $badgeColor ?>"></i>
                            <?= $p ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>



<section class="how" id="comment">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-5">
                <span class="section-badge">Comment ça marche</span>
                <h2 class="section-title">3 étapes pour commencer</h2>
                <p class="section-sub">Rejoignez la plateforme et accédez à votre espace en quelques minutes.</p>
            </div>
            <div class="col-lg-6 offset-lg-1">
                <div class="d-flex align-items-start gap-3">
                    <div class="step-num">1</div>
                    <div>
                        <div class="step-title">Créez votre compte</div>
                        <div class="step-desc">Inscrivez-vous avec votre matricule, email et choisissez votre rôle. Votre compte est activé immédiatement.</div>
                    </div>
                </div>
                <div class="step-connector"></div>
                <div class="d-flex align-items-start gap-3">
                    <div class="step-num">2</div>
                    <div>
                        <div class="step-title">Connectez-vous</div>
                        <div class="step-desc">Utilisez votre matricule ou email avec votre mot de passe. Vous serez redirigé automatiquement vers votre espace.</div>
                    </div>
                </div>
                <div class="step-connector"></div>
                <div class="d-flex align-items-start gap-3">
                    <div class="step-num">3</div>
                    <div>
                        <div class="step-title">Accédez à votre espace</div>
                        <div class="step-desc">Consultez vos notes, planches, emploi du temps et gérez vos réclamations depuis un tableau de bord dédié.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>



<section class="cta-section py-5">
    <div class="container py-4">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <h2>Prêt à rejoindre GestionNotes ?</h2>
                <p>Inscrivez-vous gratuitement et accédez à votre espace étudiant, professeur ou administratif dès aujourd'hui.</p>
            </div>
            <div class="col-lg-5">
                <div class="d-flex gap-3 justify-content-lg-end flex-wrap">
                    <a href="inscription.php" class="btn-cta-white">
                        <i class="bi bi-person-plus-fill"></i>
                        Créer un compte
                    </a>
                    <a href="connexion.php" class="btn-cta-outline">
                        <i class="bi bi-box-arrow-in-right"></i>
                        Se connecter
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>



<footer id="contact">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-4">
                <div class="footer-logo">
                    <img src="assets/r.png" alt="Logo" style="height: 40px; width: 40px; object-fit: cover; border-radius: 50%;">
                    <span class="ms-2">GestionNotes</span>
                </div>
                <p class="footer-desc">
                    Plateforme officielle de gestion des Notes des EIT — Institut National Polytechnique Houphouët-Boigny, Yamoussoukro.
                </p>
                <div class="d-flex gap-2 mt-4">
                    <?php foreach ([
                        ['bi-facebook','#1877f2', 'https://www.facebook.com/in/n-guessan-raoul'],
                        ['bi-twitter-x','#000', 'https://www.twitter.com/in/n-guessan-raoul'],
                        ['bi-linkedin','#0a66c2', 'https://www.linkedin.com/in/n-guessan-raoul'],
                        ['bi-youtube','#ff0000', 'https://www.youtube.com/in/n-guessan-raoul'],
                    ] as [$ico, $col, $url]): ?>
                    <a href="<?= htmlspecialchars($url) ?>" target="_blank" rel="noopener noreferrer" style="width:36px;height:36px;background:rgba(255,255,255,.08);border-radius:8px;display:flex;align-items:center;justify-content:center;color:rgba(255,255,255,.6);text-decoration:none;font-size:16px;transition:all .15s"
                       onmouseover="this.style.background='rgba(255,255,255,.15)';this.style.color='#fff'"
                       onmouseout="this.style.background='rgba(255,255,255,.08)';this.style.color='rgba(255,255,255,.6)'">
                        <i class="bi <?= $ico ?>"></i>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="footer-title">Plateforme</div>
                <a href="connexion.php"   class="footer-link">Connexion</a>
                <a href="inscription.php" class="footer-link">Inscription</a>
                <a href="#fonctionnalites" class="footer-link">Fonctionnalités</a>
                <a href="#roles"          class="footer-link">Portails</a>
            </div>
            <div class="col-6 col-lg-2">
                <div class="footer-title">Espaces</div>
                <a href="connexion.php" class="footer-link">Espace Étudiant</a>
                <a href="connexion.php" class="footer-link">Espace Professeur</a>
                <a href="connexion.php" class="footer-link">Espace RUP</a>
                <a href="connexion.php" class="footer-link">Administration</a>
            </div>
            <div class="col-lg-4">
                <div class="footer-title">Contact</div>
                <div class="d-flex flex-column gap-2">
                    <div style="font-size:.83rem;color:rgba(255,255,255,.55);display:flex;align-items:center;gap:8px">
                        <i class="bi bi-geo-alt-fill text-primary"></i>
                        EIT-INP-HB, Yamoussoukro, Côte d'Ivoire
                    </div>
                    <div style="font-size:.83rem;color:rgba(255,255,255,.55);display:flex;align-items:center;gap:8px">
                        <i class="bi bi-envelope-fill text-primary"></i>
                        nguessan.koffi24@inphb.ci 
                    </div>
                    <div style="font-size:.83rem;color:rgba(255,255,255,.55);display:flex;align-items:center;gap:8px">
                        <i class="bi bi-telephone-fill text-primary"></i>
                        +225 07 79 55 30 55/ 05 85 72 24 72
                    </div>
                </div>
            </div>
        </div>
        <hr class="footer-divider">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="footer-copy">
                © <?= date('Y') ?> GestionNotes — EIT. Tous droits réservés.
            </div>
            <div class="d-flex gap-3">
                <a href="#" class="footer-copy" style="color:rgba(255,255,255,.35)">Mentions légales</a>
                <a href="#" class="footer-copy" style="color:rgba(255,255,255,.35)">Confidentialité</a>
            </div>
        </div>
    </div>
</footer>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Navbar sticky effect
    window.addEventListener('scroll', function() {
        const nav = document.querySelector('.gn-nav');
        if (window.scrollY > 50) {
            nav.style.boxShadow = '0 4px 30px rgba(0,0,0,.12)';
        } else {
            nav.style.boxShadow = '0 2px 20px rgba(0,0,0,.06)';
        }
    });

    // Smooth reveal on scroll
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(e => {
            if (e.isIntersecting) {
                e.target.style.opacity = '1';
                e.target.style.transform = 'translateY(0)';
            }
        });
    }, { threshold: 0.1 });

    document.querySelectorAll('.feat-card, .role-card, .stat-card').forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(20px)';
        el.style.transition = 'opacity .5s ease, transform .5s ease';
        observer.observe(el);
    });
</script>

</body>
</html>