<?php
session_start();
require 'configuration.php';
if (!empty($_SESSION['utilisateur'])) { header('Location: connexion.php'); exit; }
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$erreurs = []; $succes = false; $donnees = [];

if (isset($_POST['inscrire'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $erreurs[] = "Requête invalide.";
    } else {
$donnees = [
            'nom'       => trim(strtoupper($_POST['nom'] ?? '')),
            'prenom'    => trim($_POST['prenom'] ?? ''),
            'matricule' => trim(strtoupper($_POST['matricule'] ?? '')),
            'email'     => trim(strtolower($_POST['email'] ?? '')),
            'filiere'   => trim($_POST['filiere'] ?? ''),
            'role'      => trim($_POST['role'] ?? 'etudiant'),
        ];
        $mdp  = $_POST['mot_de_passe'] ?? '';
        $mdp2 = $_POST['mot_de_passe_confirm'] ?? '';
        if (!in_array($donnees['role'], ['etudiant','professeur','rup'])) $donnees['role'] = 'etudiant';

        if (!$donnees['nom'])    $erreurs[] = "Le nom est obligatoire.";
        if (!$donnees['prenom']) $erreurs[] = "Le prénom est obligatoire.";
        if (!$donnees['email'])  $erreurs[] = "L'email est obligatoire.";
        elseif (!filter_var($donnees['email'], FILTER_VALIDATE_EMAIL)) $erreurs[] = "Email invalide.";
        if (!$mdp)               $erreurs[] = "Le mot de passe est obligatoire.";
        elseif (strlen($mdp) < 6) $erreurs[] = "Minimum 6 caractères.";
        elseif ($mdp !== $mdp2)  $erreurs[] = "Les mots de passe ne correspondent pas.";

        if (!$donnees['matricule']) {
            $erreurs[] = $donnees['role'] === 'etudiant' ? "Le matricule est obligatoire." : "L'identifiant est obligatoire.";
        } elseif (strlen($donnees['matricule']) < 3 || strlen($donnees['matricule']) > 20) {
            $erreurs[] = "Identifiant invalide (3 à 20 caractères).";
        }

        if (empty($erreurs)) {
            $ch = $pdo->prepare("SELECT id_utilisateur FROM utilisateurs WHERE email=? OR login=?");
            $ch->execute([$donnees['email'], $donnees['matricule']]);
            if ($ch->fetch()) $erreurs[] = "Cet identifiant ou email est déjà utilisé.";
        }

        if (empty($erreurs)) {
            try {
                $pdo->beginTransaction();
                $hash  = password_hash($mdp, PASSWORD_BCRYPT, ['cost' => 12]);
                $login = ($donnees['role'] === 'rup') ? $donnees['email'] : $donnees['matricule'];

                $pdo->prepare("INSERT INTO utilisateurs(nom,prenom,email,login,mot_de_passe,role,actif) VALUES(?,?,?,?,?,?,1)")
                    ->execute([$donnees['nom'],$donnees['prenom'],$donnees['email'],$login,$hash,$donnees['role']]);
                $uid = $pdo->lastInsertId();

                if ($donnees['role'] === 'etudiant') {
                    $pdo->prepare("INSERT INTO etudiant(fk_user,matricule,nom,prenom,filiere,annee) VALUES(?,?,?,?,?,1)")
                        ->execute([$uid,$donnees['matricule'],$donnees['nom'],$donnees['prenom'],$donnees['filiere']?:null]);
                } elseif ($donnees['role'] === 'professeur') {
                    $pdo->prepare("INSERT INTO professeur(fk_user,nom,prenom) VALUES(?,?,?)")
                        ->execute([$uid,$donnees['nom'],$donnees['prenom']]);
                }

                $pdo->commit();
                journaliser($pdo, 'INSCRIPTION', 'utilisateurs', "Rôle:".$donnees['role']);
                $succes = true;
                $donnees = [];
            } catch (Exception $e) {
                $pdo->rollBack();
                $erreurs[] = "Erreur BD : ".$e->getMessage();
                $succes = false;
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
    <title>Inscription — GestionNotes EIT</title>

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
            padding: 70px 40px 60px 40px;
            width: 100%;
            max-width: 650px;
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
            box-shadow: inset 0 2px 5px rgba(0,0,0,0.1);
        }
        .glass-input-icon {
            background-color: #0b1f41;
            color: #fff;
            padding: 10px 15px;
            border-top-left-radius: 6px;
            border-bottom-left-radius: 6px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 1.1rem;
        }
        .glass-input, .glass-select {
            background: transparent;
            border: none;
            color: #fff;
            width: 100%;
            padding: 10px 15px;
            outline: none;
            font-size: 1rem;
        }
        .glass-input::placeholder { color: #a4b5cc; }
        
        .glass-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='none' stroke='%23ffffff' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 16px 12px;
            color: #fff;
        }
        .glass-select option { color: #000; } /* Fallback for options */

        .section-title {
            color: #fff;
            font-weight: 600;
            font-size: 0.9rem;
            letter-spacing: 1px;
            margin-bottom: 15px;
            border-bottom: 1px solid rgba(255,255,255,0.3);
            padding-bottom: 5px;
        }
        .form-label { color: #f8f9fa !important; font-weight: 500; margin-bottom: 0.3rem; }

        /* Roles radios */
        .role-radio { display: none; }
        .role-card {
            background: rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 10px;
            padding: 15px 5px;
            text-align: center;
            color: #fff;
            cursor: pointer;
            transition: all 0.2s;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .role-radio:checked + .role-card {
            background: rgba(255, 255, 255, 0.3);
            border-color: #fff;
            box-shadow: 0 0 15px rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }
        .role-card i { font-size: 1.5rem; margin-bottom: 5px; display: block; color: #e6ebf5; }
        .role-radio:checked + .role-card i { color: #0b1f41; }
        .role-radio:checked + .role-card .role-txt { color: #0b1f41 !important; font-weight: bold; }

        /* Floating Button */
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
            max-width: 350px;
        }
        .btn-login-float:hover {
            transform: translateX(-50%) translateY(-2px);
            box-shadow: 0 12px 25px rgba(0,0,0,0.2);
            color: #0b1f41;
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
        .go-home-btn:hover { background: rgba(255,255,255,0.4); color: #fff; }

        .form-check-input {
            background-color: rgba(255,255,255,0.2);
            border-color: rgba(255,255,255,0.5);
            cursor: pointer;
        }
        .form-check-input:checked { background-color: #0b1f41; border-color: #0b1f41; }
        
        .force-bar { height: 4px; border-radius: 2px; background: rgba(255,255,255,0.2); margin-top: 8px; overflow: hidden; }
        .force-fill { height: 100%; width: 0; transition: all 0.3s ease; }
    </style>
</head>
<body>

<a href="index.php" class="go-home-btn"><i class="bi bi-arrow-left"></i></a>

<div class="glass-card">
    <div class="avatar-circle" style="background-color: #ffffff; padding: 0; overflow: hidden;">
        <img src="assets/r.png" alt="Logo" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
    </div>

    <?php if ($succes): ?>
        <div class="text-center py-4 text-white">
            <div class="display-1 text-white mb-4"><i class="bi bi-check-circle-fill" style="filter: drop-shadow(0 4px 6px rgba(0,0,0,0.2));"></i></div>
            <h3 class="fw-bold mb-3">Compte créé avec succès !</h3>
            <p class="mb-5 px-3" style="color: #e6ebf5;">Votre compte a bien été enregistré. Vous pouvez maintenant vous connecter.</p>
            <a href="connexion.php" class="btn btn-light px-4 py-2 mt-2 rounded-3 shadow-sm fw-bold text-dark">
                <i class="bi bi-box-arrow-in-right me-2"></i>Se connecter
            </a>
        </div>
    <?php else: ?>

        <?php if (!empty($erreurs)): ?>
        <div class="alert alert-danger shadow-sm rounded-3 mb-4" role="alert" style="background: rgba(220, 53, 69, 0.8); color: #fff; border: none;">
            <strong><i class="bi bi-exclamation-triangle-fill me-2"></i>Veuillez corriger les erreurs :</strong>
            <ul class="mb-0 mt-2">
                <?php foreach ($erreurs as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <form method="POST" id="form-insc" class="needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <!-- Section Rôle -->
            <div class="section-title text-uppercase mt-2">1. Votre profil</div>
            <div class="row g-3 mb-4">
                <?php foreach (['etudiant'=>['bi-mortarboard','Étudiant'],'professeur'=>['bi-person-workspace','Professeur'],'rup'=>['bi-briefcase','Responsable (RUP)']] as $rk => [$ri, $rn]): ?>
                <div class="col-4">
                    <input type="radio" class="role-radio" name="role" id="role_<?= $rk ?>" value="<?= $rk ?>" <?= ($donnees['role'] ?? 'etudiant') === $rk ? 'checked' : '' ?> onchange="adapterFormulaire()">
                    <label class="role-card" for="role_<?= $rk ?>">
                        <i class="bi <?= $ri ?>"></i>
                        <div class="fw-medium small text-white role-txt"><?= $rn ?></div>
                    </label>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Section Identité -->
            <div class="section-title text-uppercase mt-3">2. Identité</div>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label small" for="nom">Nom *</label>
                    <div class="glass-input-group m-0">
                        <span class="glass-input-icon"><i class="bi bi-person"></i></span>
                        <input type="text" id="nom" name="nom" class="glass-input" value="<?= htmlspecialchars($donnees['nom'] ?? '') ?>" placeholder="Ex: KONAN" required autofocus>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label small" for="prenom">Prénom *</label>
                    <div class="glass-input-group m-0">
                        <span class="glass-input-icon"><i class="bi bi-person"></i></span>
                        <input type="text" id="prenom" name="prenom" class="glass-input" value="<?= htmlspecialchars($donnees['prenom'] ?? '') ?>" placeholder="Ex: Kouamé" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label small" id="lbl-mat" for="matricule">Matricule *</label>
                    <div class="glass-input-group m-0">
                        <span class="glass-input-icon"><i class="bi bi-credit-card-2-front"></i></span>
                        <input type="text" id="matricule" name="matricule" class="glass-input" value="<?= htmlspecialchars($donnees['matricule'] ?? '') ?>" placeholder="24INP00001" required style="text-transform:uppercase">
                    </div>
                </div>
                <div class="col-md-6" id="bloc-filiere">
                    <label class="form-label small" for="filiere">Filière</label>
                    <div class="glass-input-group m-0">
                        <span class="glass-input-icon"><i class="bi bi-diagram-3"></i></span>
                        <select id="filiere" name="filiere" class="glass-select">
                            <option value="" style="color:#000;">— Choisir —</option>
                            <?php foreach (['Electronique, Informatique et Telecoms','Informatique','STIC 1','EIT 2','EIT 3','INFO 2', 'INFO 3'] as $f): ?>
                            <option value="<?= $f ?>" style="color:#000;" <?= ($donnees['filiere'] ?? '') === $f ? 'selected' : '' ?>><?= $f ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-12 mt-3">
                    <label class="form-label small" for="email">Adresse email *</label>
                    <div class="glass-input-group m-0">
                        <span class="glass-input-icon"><i class="bi bi-envelope"></i></span>
                        <input type="email" id="email" name="email" class="glass-input" value="<?= htmlspecialchars($donnees['email'] ?? '') ?>" placeholder="votre.email@inphb.ci" required>
                    </div>
                </div>
            </div>

            <!-- Section Sécurité -->
            <div class="section-title text-uppercase mt-4">3. Sécurité</div>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label small" for="mdp1">Mot de passe *</label>
                    <div class="glass-input-group position-relative m-0">
                        <span class="glass-input-icon"><i class="bi bi-lock"></i></span>
                        <input type="password" id="mdp1" name="mot_de_passe" class="glass-input" placeholder="Min. 6 carac." required oninput="evalForce(this.value)" style="padding-right: 40px;">
                        <i class="bi bi-eye position-absolute" onclick="toggleMdp('mdp1','o1')" id="o1" style="right: 15px; top: 12px; color: #a4b5cc; cursor: pointer; font-size: 1.1rem;"></i>
                    </div>
                    <div class="force-bar"><div class="force-fill" id="force-fill"></div></div>
                    <div class="small mt-1" id="force-hint" style="color:#e6ebf5; font-size: 0.75rem;">Saisissez un mot de passe</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label small" for="mdp2">Confirmer le mdp *</label>
                    <div class="glass-input-group position-relative m-0">
                        <span class="glass-input-icon"><i class="bi bi-lock"></i></span>
                        <input type="password" id="mdp2" name="mot_de_passe_confirm" class="glass-input" placeholder="Répéter" required oninput="verifMdp()" style="padding-right: 40px;">
                        <i class="bi bi-eye position-absolute" onclick="toggleMdp('mdp2','o2')" id="o2" style="right: 15px; top: 12px; color: #a4b5cc; cursor: pointer; font-size: 1.1rem;"></i>
                    </div>
                    <div class="small mt-1 ps-1" id="match-hint" style="font-size: 0.75rem;"></div>
                </div>
            </div>

            <div class="form-check p-3 mt-4 mb-2 rounded-3" style="background: rgba(0,0,0,0.1); border: 1px solid rgba(255,255,255,0.2); display: flex; align-items: center;">
                <input class="form-check-input ms-0 mt-0" type="checkbox" id="cond" required>
                <label class="form-check-label small text-white ms-2" for="cond">
                    J'accepte les <a href="#" class="text-white fw-bold">conditions d'utilisation</a>.
                </label>
            </div>

            <input type="hidden" name="inscrire" value="1">
            <button type="submit" class="btn-login-float" id="btn-insc">
                <span id="txt-n">S'INSCRIRE</span>
                <span id="txt-l" class="d-none"><span class="spinner-border spinner-border-sm"></span> Traitement...</span>
            </button>
        </form>
    <?php endif; ?>
</div>

<div class="text-center mt-3 text-white-50 small fw-medium" style="z-index: 10;">
    Vous avez déjà un compte ? <a href="connexion.php" class="text-white text-decoration-none fw-bold ms-1" style="text-decoration: underline!important;">Me connecter</a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleMdp(id, ico) {
    const c = document.getElementById(id), i = document.getElementById(ico);
    c.type = c.type === 'password' ? 'text' : 'password';
    i.className = c.type === 'text' ? 'bi bi-eye-slash position-absolute' : 'bi bi-eye position-absolute';
}

function evalForce(v) {
    let s = 0;
    if (v.length >= 6) s++; if (v.length >= 10) s++;
    if (/[A-Z]/.test(v)) s++; if (/[0-9]/.test(v)) s++; if (/[^A-Za-z0-9]/.test(v)) s++;
    const cfg = [
        {w:'0%',  b:'rgba(255,255,255,0.2)', l:'Saisissez un mot de passe', c:'#e6ebf5'},
        {w:'25%', b:'#ff6b6b', l:'Très faible',  c:'#ff6b6b'},
        {w:'45%', b:'#ffa8a8', l:'Faible',        c:'#ffa8a8'},
        {w:'65%', b:'#fcc419', l:'Moyen',          c:'#fcc419'},
        {w:'85%', b:'#51cf66', l:'Fort',            c:'#51cf66'},
        {w:'100%',b:'#339af0', l:'Très fort ✓',    c:'#339af0'},
    ][Math.min(s, 5)];
    const f = document.getElementById('force-fill');
    f.style.width = cfg.w; f.style.backgroundColor = cfg.b;
    const h = document.getElementById('force-hint');
    h.textContent = cfg.l; h.style.color = cfg.c;
    verifMdp();
}

function verifMdp() {
    const m = document.getElementById('mdp1').value, c = document.getElementById('mdp2').value;
    const el = document.getElementById('match-hint');
    if (!c) { el.textContent = ''; return; }
    if (m === c) { 
        el.innerHTML = '<i class="bi bi-check-circle-fill"></i> Les mots de passe correspondent'; 
        el.style.color = '#51cf66';
    } else { 
        el.innerHTML = '<i class="bi bi-x-circle-fill"></i> Les mots de passe ne correspondent pas'; 
        el.style.color = '#ff6b6b';
    }
}

function adapterFormulaire() {
    const r = document.querySelector('[name=role]:checked')?.value || 'etudiant';
    const bf = document.getElementById('bloc-filiere');
    const lm = document.getElementById('lbl-mat');
    const mat = document.getElementById('matricule');
    
    if (r === 'etudiant') {
        bf.style.display = 'block'; 
        lm.textContent = 'Matricule *'; 
        mat.placeholder = '24INP00001';
    } else {
        bf.style.display = 'none'; 
        lm.textContent = r === 'professeur' ? 'Identifiant Prof *' : 'Identifiant RUP *'; 
        mat.placeholder = r === 'professeur' ? 'PROF001' : 'RUP001';
    }
}

const matriculeInput = document.getElementById('matricule');
if (matriculeInput) {
    matriculeInput.addEventListener('input', function() { 
        this.value = this.value.toUpperCase(); 
    });
}

const formInsc = document.getElementById('form-insc');
if (formInsc) {
    formInsc.addEventListener('submit', function(e) {
        if (!document.getElementById('cond').checked) { 
            e.preventDefault(); 
            alert("Veuillez accepter les conditions d'utilisation."); 
            return; 
        }
        document.getElementById('txt-n').classList.add('d-none'); 
        document.getElementById('txt-l').classList.remove('d-none');
        document.getElementById('btn-insc').disabled = true;
    });
}

adapterFormulaire();
</script>
</body>
</html>