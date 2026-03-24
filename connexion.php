<?php
session_start();
require 'configuration.php';

if (!empty($_SESSION['utilisateur'])) {
    $dest = ['etudiant'=>'eleve/tableau_de_bord.php','professeur'=>'professeur/tableau_de_bord.php','rup'=>'rup/tableau_de_bord.php','superadmin'=>'administration/tableau_de_bord.php'];
    header('Location: '.($dest[$_SESSION['utilisateur']['role']] ?? 'connexion.php')); exit;
}
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$erreur = '';
$succes = '';
if (($_GET['erreur'] ?? '') === 'acces') $erreur = "Accès refusé — droits insuffisants.";
if (($_GET['ok'] ?? '') === 'reset') $succes = "Votre demande de réinitialisation a été envoyée au SuperAdmin.";

if (isset($_POST['connexion'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $erreur = "Requête invalide.";
    } else {
        $login = trim($_POST['login'] ?? ''); $mdp = $_POST['mot_de_passe'] ?? '';
        if (!$login || !$mdp) {
            $erreur = "Tous les champs sont obligatoires.";
        } else {
            $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE (login=? OR email=?) AND actif=1 LIMIT 1");
            $stmt->execute([$login, $login]); $u = $stmt->fetch();
            if ($u && password_verify($mdp, $u['mot_de_passe'])) {
                session_regenerate_id(true);
                $_SESSION['utilisateur'] = [
                    'id_utilisateur' => $u['id_utilisateur'],
                    'nom'    => $u['nom']    ?? '',
                    'prenom' => $u['prenom'] ?? '',
                    'email'  => $u['email'],
                    'login'  => $u['login']  ?? $u['email'],
                    'role'   => $u['role'],
                ];
                journaliser($pdo, 'CONNEXION', 'utilisateurs', 'Rôle : '.$u['role']);
                $dest = [
                    'etudiant'   => 'eleve/tableau_de_bord.php',
                    'professeur' => 'professeur/tableau_de_bord.php',
                    'rup'        => 'rup/tableau_de_bord.php',
                    'superadmin' => 'administration/tableau_de_bord.php',
                    'admin'      => 'administration/tableau_de_bord.php',
                ];
                $url = $dest[$u['role']] ?? 'connexion.php';
                header('Location: ' . $url);
                exit;
            } else {
                $erreur = "Identifiant ou mot de passe incorrect.";
                sleep(1);
            }
        }
    }
} elseif (isset($_POST['reset_mdp'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $erreur = "Requête invalide.";
    } else {
        $login_email = trim($_POST['reset_login'] ?? '');
        if (!$login_email) {
            $erreur = "Veuillez renseigner votre identifiant ou email.";
        } else {
            $stmt = $pdo->prepare("SELECT id_utilisateur FROM utilisateurs WHERE login=? OR email=?");
            $stmt->execute([$login_email, $login_email]);
            if ($stmt->fetch()) {
                $stmt = $pdo->prepare("INSERT INTO demande_reset (login_email, statut) VALUES (?, 'en_attente')");
                $stmt->execute([$login_email]);
                header('Location: connexion.php?ok=reset');
                exit;
            } else {
                $erreur = "Aucun compte correspondant trouvé.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — GestionNotes EIT</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            margin: 0;
            background: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.6)), url('assets/inp.jpg') no-repeat center center fixed;
            background-size: cover;
            padding: 20px;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.45);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 80px 50px 70px 50px;
            width: 100%;
            max-width: 480px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
            position: relative;
            border: 1px solid rgba(255, 255, 255, 0.3);
            margin-bottom: 30px;
            margin-top: 50px;
        }

        .avatar-circle {
            width: 100px;
            height: 100px;
            background-color: #0b1f41;
            border-radius: 50%;
            position: absolute;
            top: -50px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            justify-content: center;
            align-items: center;
            box-shadow: 0 10px 20px rgba(0,0,0,0.15);
            border: 3px solid rgba(255,255,255,0.2);
        }
        .avatar-circle i {
            font-size: 3rem;
            color: #fff;
        }

        .glass-input-group {
            display: flex;
            background-color: #3b5074;
            border-radius: 6px;
            margin-bottom: 20px;
            box-shadow: inset 0 2px 5px rgba(0,0,0,0.1);
        }
        .glass-input-icon {
            background-color: #0b1f41;
            color: #fff;
            padding: 12px 18px;
            border-top-left-radius: 6px;
            border-bottom-left-radius: 6px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 1.1rem;
        }
        .glass-input {
            background: transparent;
            border: none;
            color: #fff;
            width: 100%;
            padding: 12px 15px;
            outline: none;
            font-size: 1rem;
        }
        .glass-input::placeholder {
            color: #a4b5cc;
        }

        .glass-links {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.9rem;
            color: #1a2a47;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .glass-links a {
            color: #1a2a47;
            text-decoration: none;
            font-style: italic;
        }
        .glass-links a:hover {
            color: #000;
            text-decoration: underline;
        }
        .form-check-input {
            background-color: transparent;
            border-color: #1a2a47;
            cursor: pointer;
        }
        .form-check-input:checked {
            background-color: #0b1f41;
            border-color: #0b1f41;
        }

        .btn-login-float {
            position: absolute;
            bottom: -24px;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(135deg, #ffffff, #e6ebf5);
            color: #2b3954;
            border: none;
            padding: 14px 45px;
            border-radius: 30px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
            transition: all 0.2s;
            width: 70%;
        }
        .btn-login-float:hover {
            transform: translateX(-50%) translateY(-2px);
            box-shadow: 0 12px 25px rgba(0,0,0,0.2);
            color: #0b1f41;
        }

        .demo-badge {
            cursor: pointer;
            transition: all 0.2s;
            background: rgba(255,255,255,0.3);
            border: 1px solid rgba(255,255,255,0.4);
            color: #fff!important;
            backdrop-filter: blur(5px);
        }
        .demo-badge:hover {
            transform: translateY(-2px);
            background: rgba(255,255,255,0.5);
        }

        .go-home-btn {
            position: absolute;
            top: 20px;
            left: 20px;
            color: #fff;
            text-decoration: none;
            font-size: 1.5rem;
            background: rgba(255,255,255,0.2);
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.2s;
        }
        .go-home-btn:hover {
            background: rgba(255,255,255,0.4);
            color: #fff;
        }

        .alert {
            font-size: 0.9rem;
            padding: 10px 15px;
            border-radius: 8px;
            border: none;
        }
    </style>
</head>
<body>

<a href="index.php" class="go-home-btn"><i class="bi bi-arrow-left"></i></a>

<form method="POST" id="form-cnx" class="glass-card">
    <div class="avatar-circle" style="background-color: #ffffff; padding: 0; overflow: hidden;">
        <img src="assets/r.png" alt="Logo" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
    </div>
    
    <!-- Zone de messages -->
    <?php if ($erreur): ?>
    <div class="alert alert-danger d-flex align-items-center mb-3" role="alert" style="background: rgba(220, 53, 69, 0.8); color: #fff;">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <div><?= htmlspecialchars($erreur) ?></div>
    </div>
    <?php endif; ?>
    
    <?php if ($succes): ?>
    <div class="alert alert-success d-flex align-items-center mb-3" role="alert" style="background: rgba(25, 135, 84, 0.8); color: #fff;">
        <i class="bi bi-check-circle-fill me-2"></i>
        <div><?= htmlspecialchars($succes) ?></div>
    </div>
    <?php endif; ?>

    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

    <div class="glass-input-group">
        <div class="glass-input-icon"><i class="bi bi-person-fill"></i></div>
        <input type="text" id="login" name="login" class="glass-input" value="<?= htmlspecialchars($_POST['login'] ?? '') ?>" placeholder="Identifiant ou Email" required autofocus autocomplete="username">
    </div>

    <div class="glass-input-group position-relative">
        <div class="glass-input-icon"><i class="bi bi-lock-fill"></i></div>
        <input type="password" id="mdp" name="mot_de_passe" class="glass-input" placeholder="Mot de passe" required autocomplete="current-password" style="padding-right: 40px;">
        <i class="bi bi-eye position-absolute" id="btn-eye" style="right: 15px; top: 12px; color: #a4b5cc; cursor: pointer; font-size: 1.1rem;"></i>
    </div>

    <div class="glass-links">
        <div class="form-check m-0 d-flex align-items-center gap-2">
            <input type="checkbox" class="form-check-input mt-0" id="souvenir" name="souvenir">
            <label class="form-check-label" for="souvenir" style="cursor: pointer;">Se souvenir de moi</label>
        </div>
        <a href="#" data-bs-toggle="modal" data-bs-target="#modalResetMdp">Oublié ?</a>
    </div>

    <button type="submit" name="connexion" class="btn-login-float" id="btn-sub">
        <span id="txt-n">CONNEXION</span>
        <span id="txt-l" class="d-none">
            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
        </span>
    </button>
</form>

<div class="d-flex flex-column align-items-center" style="z-index: 10;">
    <div class="d-flex flex-wrap gap-2 justify-content-center mb-3">
        <span class="badge demo-badge p-2 px-3 fw-medium" onclick="remplir('superadmin','SuperAdmin@2025')">SuperAdmin</span>
        <span class="badge demo-badge p-2 px-3 fw-medium" onclick="remplir('rup','Rup@2025')">RUP</span>
        <span class="badge demo-badge p-2 px-3 fw-medium" onclick="remplir('deroh.moise','Prof@2025')">Prof</span>
        <span class="badge demo-badge p-2 px-3 fw-medium" onclick="remplir('24INP00956','24INP00956')">Étudiant</span>
    </div>

    <div class="text-white-50 small fw-medium">
        Pas encore de compte ? <a href="inscription.php" class="text-white text-decoration-none fw-bold ms-1" style="text-decoration: underline!important;">S'inscrire</a>
    </div>
</div>

<!-- Modal Reset MDP -->
<div class="modal fade" id="modalResetMdp" tabindex="-1" aria-labelledby="modalResetMdpLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow" style="border-radius: 1rem;">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold text-dark" id="modalResetMdpLabel">Mot de passe oublié</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
        <p class="text-muted small mb-4">Entrez votre identifiant ou email. Une demande sera transmise au SuperAdmin pour réinitialiser votre mot de passe.</p>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <div class="mb-3">
                <label class="form-label fw-medium text-dark">Identifiant ou Email</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                    <input type="text" name="reset_login" class="form-control" placeholder="Matricule, login ou email" required style="border-left: none;">
                </div>
            </div>
            <button type="submit" name="reset_mdp" class="btn btn-primary w-100 rounded-3 shadow-sm mt-2" style="background-color: #0b1f41; border:none; padding: 10px;">Envoyer la demande</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('btn-eye').addEventListener('click', function () {
        const c = document.getElementById('mdp');
        if (c.type === 'password') {
            c.type = 'text';
            this.className = 'bi bi-eye-slash position-absolute';
        } else {
            c.type = 'password';
            this.className = 'bi bi-eye position-absolute';
        }
    });

    function remplir(login, mdp) {
        document.getElementById('login').value = login;
        document.getElementById('mdp').value = mdp;
    }

    document.getElementById('form-cnx').addEventListener('submit', function () {
        document.getElementById('txt-n').classList.add('d-none');
        document.getElementById('txt-l').classList.remove('d-none');
    });
</script>
</body>
</html>