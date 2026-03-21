<?php
// ============================================================
// login.php — Connexion utilisateur (version corrigée + design premium)
// ============================================================
require_once 'db.php';
require_once 'includes/functions.php';

// ── Rediriger si déjà connecté (avec vérification du rôle) ──
if (isLoggedIn()) {
    if ($_SESSION['user_role'] === 'admin') {
        header('Location: /servilocal/admin.php');
    } else {
        header('Location: /servilocal/dashboard.php');
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    // ── Validation basique ────────────────────────────────
    if (empty($email) || empty($password)) {
        $error = 'Veuillez remplir tous les champs.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Adresse email invalide.';

    } else {
        // ── Requête sécurisée ─────────────────────────────
        $stmt = $pdo->prepare(
            'SELECT id, name, email, password, role
             FROM users
             WHERE email = ? AND is_active = 1
             LIMIT 1'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {

            // ── Sécurisation session ───────────────────────
            session_regenerate_id(true);
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];

            // ── Redirection selon le rôle ─────────────────
            switch ($user['role']) {

                case 'admin':
                    $redirect = '/servilocal/admin.php';
                    break;

                case 'provider':
                    $redirect = '/servilocal/provider_dashboard.php';
                    break;

                default: // client
                    $redirect = $_GET['redirect'] ?? '/servilocal/dashboard.php';
                    break;
            }

            // ── Redirection finale ─────────────────────────
            header('Location: ' . $redirect . '?toast=' . urlencode('👋 Bienvenue, ' . $user['name'] . ' !'));
            exit;

        } else {
            $error = 'Email ou mot de passe incorrect.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Connexion — ServiLocal</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;0,900;1,400&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root {
  --forest:      #1C3D2E;
  --sage:        #3A6B4A;
  --mint:        #7EB89A;
  --lime:        #B5D99C;
  --lime-light:  #EAF3DE;
  --cream:       #F7F4EF;
  --warm:        #FDFBF7;
  --amber:       #E8A838;
  --red:         #DC2626;
  --red-light:   #FEF2F2;
  --text:        #1A1A1A;
  --muted:       #6B6860;
  --border:      rgba(28,61,46,.12);
  --radius-sm:   12px;
}

*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

body {
  font-family: 'DM Sans', sans-serif;
  min-height: 100vh;
  background: var(--cream);
  color: var(--text);
  display: grid;
  grid-template-columns: 1fr 1fr;
}

/* ── Panneau gauche ──────────────────────────────────────── */
.panel-left {
  background: var(--forest);
  position: relative;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  padding: 3rem;
  overflow: hidden;
  min-height: 100vh;
}
.panel-left::before {
  content:'';
  position:absolute; top:-130px; right:-130px;
  width:420px; height:420px; border-radius:50%;
  background:rgba(181,217,156,.06);
}
.panel-left::after {
  content:'';
  position:absolute; bottom:-90px; left:-90px;
  width:320px; height:320px; border-radius:50%;
  background:rgba(126,184,154,.05);
}

.panel-logo {
  font-family:'Playfair Display',serif;
  font-size:1.85rem; font-weight:900;
  color:white; text-decoration:none;
  position:relative; z-index:1;
}
.panel-logo span { color:var(--lime); }

.panel-center {
  position:relative; z-index:1;
  flex:1; display:flex; flex-direction:column;
  justify-content:center; gap:2.5rem;
}

.panel-headline {
  font-family:'Playfair Display',serif;
  font-size:clamp(1.9rem,3vw,2.8rem);
  font-weight:700; color:white; line-height:1.18;
}
.panel-headline em { color:var(--lime); font-style:italic; }

.panel-sub {
  font-size:.97rem; color:rgba(255,255,255,.58);
  line-height:1.72; max-width:360px;
}

.panel-features { display:flex; flex-direction:column; gap:.7rem; }
.feature-item {
  display:flex; align-items:center; gap:.9rem;
  background:rgba(255,255,255,.06);
  border:1px solid rgba(255,255,255,.08);
  border-radius:var(--radius-sm);
  padding:.82rem 1.1rem;
  animation:slideInLeft .5s ease both;
}
.feature-item:nth-child(2){animation-delay:.1s}
.feature-item:nth-child(3){animation-delay:.2s}

@keyframes slideInLeft {
  from{opacity:0;transform:translateX(-18px)}
  to{opacity:1;transform:translateX(0)}
}

.feature-icon {
  width:38px; height:38px;
  background:rgba(181,217,156,.14);
  border-radius:10px;
  display:flex; align-items:center; justify-content:center;
  font-size:1.1rem; flex-shrink:0;
}
.feature-text { font-size:.87rem; color:rgba(255,255,255,.78); line-height:1.5; }
.feature-text strong { color:white; display:block; font-size:.9rem; margin-bottom:.1rem; }

.panel-stats {
  display:flex; gap:2rem;
  padding-top:1.5rem;
  border-top:1px solid rgba(255,255,255,.1);
}
.panel-stat-num {
  font-family:'Playfair Display',serif;
  font-size:1.6rem; font-weight:700;
  color:var(--lime); display:block;
}
.panel-stat-lbl {
  font-size:.73rem; color:rgba(255,255,255,.45);
  text-transform:uppercase; letter-spacing:.07em;
}

.panel-footer {
  position:relative; z-index:1;
  font-size:.78rem; color:rgba(255,255,255,.3);
}

/* ── Panneau droit ───────────────────────────────────────── */
.panel-right {
  display:flex; align-items:center; justify-content:center;
  padding:2rem; background:var(--warm); overflow-y:auto;
  min-height:100vh;
}

.auth-card {
  width:100%; max-width:430px;
  animation:fadeUp .5s ease .15s both;
}

@keyframes fadeUp {
  from{opacity:0;transform:translateY(22px)}
  to{opacity:1;transform:translateY(0)}
}

.auth-header { margin-bottom:2.25rem; }
.auth-title {
  font-family:'Playfair Display',serif;
  font-size:2rem; font-weight:700;
  color:var(--forest); margin-bottom:.4rem;
}
.auth-sub { font-size:.93rem; color:var(--muted); line-height:1.65; }

/* ── Alerte ──────────────────────────────────────────────── */
.alert {
  display:flex; align-items:flex-start; gap:.6rem;
  padding:.85rem 1.1rem;
  border-radius:var(--radius-sm);
  font-size:.88rem; font-weight:500;
  margin-bottom:1.4rem;
  animation:shake .38s ease;
}
.alert-danger {
  background:var(--red-light);
  border:1.5px solid rgba(220,38,38,.22);
  color:#991B1B;
}
@keyframes shake {
  0%,100%{transform:translateX(0)}
  20%,60%{transform:translateX(-5px)}
  40%,80%{transform:translateX(5px)}
}

/* ── Groupes de formulaire ───────────────────────────────── */
.form-group { margin-bottom:1.2rem; }
.form-label {
  display:block;
  font-size:.83rem; font-weight:600;
  color:var(--text); margin-bottom:.42rem;
  letter-spacing:.01em;
}
.input-wrap { position:relative; }
.input-icon {
  position:absolute; left:1rem; top:50%;
  transform:translateY(-50%);
  font-size:.95rem; color:var(--muted);
  pointer-events:none; transition:color .2s;
}
.form-control {
  width:100%; padding:.875rem 1rem .875rem 2.75rem;
  background:var(--cream);
  border:1.5px solid var(--border);
  border-radius:var(--radius-sm);
  font-family:'DM Sans',sans-serif;
  font-size:.95rem; color:var(--text);
  transition:all .22s; outline:none;
}
.form-control:hover {
  border-color:rgba(58,107,74,.28);
  background:white;
}
.form-control:focus {
  border-color:var(--sage); background:white;
  box-shadow:0 0 0 4px rgba(58,107,74,.08);
}
.form-control::placeholder { color:#BDB8B0; }

.password-wrap .form-control { padding-right:3rem; }
.toggle-pw {
  position:absolute; right:.9rem; top:50%;
  transform:translateY(-50%);
  background:none; border:none; cursor:pointer;
  font-size:.95rem; color:var(--muted); padding:0;
  transition:color .2s;
}
.toggle-pw:hover { color:var(--forest); }

/* ── Lien mot de passe oublié ────────────────────────────── */
.forgot-link {
  display:block; text-align:right;
  font-size:.82rem; color:var(--sage);
  text-decoration:none; margin-top:-.5rem;
  margin-bottom:1.4rem; transition:color .2s;
}
.forgot-link:hover { color:var(--forest); }

/* ── Bouton submit ───────────────────────────────────────── */
.btn-submit {
  width:100%; padding:1rem;
  background:var(--forest); color:white;
  border:none; border-radius:var(--radius-sm);
  font-family:'DM Sans',sans-serif;
  font-size:1rem; font-weight:600;
  cursor:pointer; transition:all .25s;
  display:flex; align-items:center; justify-content:center; gap:.5rem;
}
.btn-submit:hover {
  background:var(--sage);
  transform:translateY(-2px);
  box-shadow:0 8px 24px rgba(28,61,46,.28);
}
.btn-submit:active { transform:translateY(0); }
.btn-arrow { transition:transform .25s; display:inline-block; }
.btn-submit:hover .btn-arrow { transform:translateX(4px); }

/* ── Séparateur ──────────────────────────────────────────── */
.divider {
  display:flex; align-items:center; gap:.9rem;
  margin:1.5rem 0; color:var(--muted); font-size:.8rem;
}
.divider::before, .divider::after {
  content:''; flex:1; height:1px; background:var(--border);
}

/* ── Démos ───────────────────────────────────────────────── */
.demo-box {
  background:var(--lime-light);
  border:1px solid rgba(126,184,154,.38);
  border-radius:var(--radius-sm);
  padding:1rem 1.1rem;
}
.demo-title {
  font-size:.78rem; font-weight:600;
  color:var(--forest);
  text-transform:uppercase; letter-spacing:.07em;
  margin-bottom:.65rem;
  display:flex; align-items:center; gap:.35rem;
}
.demo-accounts { display:flex; flex-direction:column; gap:.35rem; }
.demo-account {
  display:flex; align-items:center; gap:.75rem;
  cursor:pointer; padding:.42rem .6rem;
  border-radius:8px; transition:background .15s;
}
.demo-account:hover { background:rgba(126,184,154,.22); }
.demo-badge {
  font-size:.7rem; font-weight:700;
  padding:.2rem .6rem; border-radius:50px; flex-shrink:0;
}
.badge-client   { background:rgba(24,95,165,.1);  color:#0C447C; }
.badge-provider { background:rgba(232,168,56,.15); color:#633806; }
.badge-admin    { background:rgba(220,38,38,.1);   color:#991B1B; }
.demo-email { font-size:.82rem; color:var(--muted); flex:1; }
.demo-pw    { font-size:.78rem; color:var(--muted); opacity:.65; }

/* ── Footer ──────────────────────────────────────────────── */
.auth-footer {
  text-align:center; margin-top:1.75rem;
  font-size:.9rem; color:var(--muted);
}
.auth-footer a {
  color:var(--sage); font-weight:600;
  text-decoration:none; transition:color .2s;
}
.auth-footer a:hover { color:var(--forest); }

/* ── Responsive ──────────────────────────────────────────── */
@media(max-width:900px){
  body { grid-template-columns:1fr; }
  .panel-left { display:none; }
  .panel-right { min-height:100vh; padding:2rem 1.25rem; }
}
</style>
</head>
<body>

<!-- ══════════════════════
     PANNEAU GAUCHE
     ══════════════════════ -->
<div class="panel-left">

  <a href="/servilocal/index.php" class="panel-logo">
    Servi<span>•</span>Local
  </a>

  <div class="panel-center">

    <div>
      <h1 class="panel-headline">
        Trouvez l'expert<br>local <em>idéal</em><br>en un instant.
      </h1>
      <p class="panel-sub" style="margin-top:1rem">
        Réservez des services de qualité autour de vous. Plombiers, coiffeurs, électriciens — tous vérifiés.
      </p>
    </div>

    <div class="panel-features">
      <div class="feature-item">
        <div class="feature-icon">🔧</div>
        <div class="feature-text">
          <strong>Prestataires vérifiés</strong>
          Profils contrôlés et notés par la communauté
        </div>
      </div>
      <div class="feature-item">
        <div class="feature-icon">📅</div>
        <div class="feature-text">
          <strong>Réservation en ligne</strong>
          Choisissez votre créneau en quelques clics
        </div>
      </div>
      <div class="feature-item">
        <div class="feature-icon">📡</div>
        <div class="feature-text">
          <strong>Module IoT intelligent</strong>
          Détection de pannes et alertes automatiques
        </div>
      </div>
    </div>

    <div class="panel-stats">
      <div>
        <span class="panel-stat-num">10+</span>
        <span class="panel-stat-lbl">Prestataires</span>
      </div>
      <div>
        <span class="panel-stat-num">98%</span>
        <span class="panel-stat-lbl">Satisfaction</span>
      </div>
      <div>
        <span class="panel-stat-num">3</span>
        <span class="panel-stat-lbl">Rôles</span>
      </div>
    </div>

  </div>

  <div class="panel-footer">
    © <?= date('Y') ?> ServiLocal · Université Mundiapolis
  </div>
</div>

<!-- ══════════════════════
     PANNEAU DROIT
     ══════════════════════ -->
<div class="panel-right">
  <div class="auth-card">

    <div class="auth-header">
      <h1 class="auth-title">Bon retour 👋</h1>
      <p class="auth-sub">Connectez-vous pour accéder à votre espace personnel.</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger">
        ⚠️ <?= e($error) ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="" id="loginForm" novalidate>
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

      <!-- Email -->
      <div class="form-group">
        <label class="form-label" for="email">Adresse email</label>
        <div class="input-wrap">
          <span class="input-icon">✉️</span>
          <input
            type="email"
            id="email"
            name="email"
            class="form-control"
            placeholder="votre@email.com"
            value="<?= e($_POST['email'] ?? '') ?>"
            required
            autofocus
            autocomplete="email"
          >
        </div>
      </div>

      <!-- Mot de passe -->
      <div class="form-group">
        <label class="form-label" for="password">Mot de passe</label>
        <div class="input-wrap password-wrap">
          <span class="input-icon">🔒</span>
          <input
            type="password"
            id="password"
            name="password"
            class="form-control"
            placeholder="••••••••"
            required
            autocomplete="current-password"
          >
          <button type="button" class="toggle-pw" id="togglePw" title="Afficher le mot de passe">
            👁
          </button>
        </div>
      </div>

      <a href="#" class="forgot-link">Mot de passe oublié ?</a>

      <button type="submit" class="btn-submit" id="submitBtn">
        <span id="btnLabel">Se connecter</span>
        <span class="btn-arrow" id="btnArrow">→</span>
      </button>
    </form>

    <div class="divider">Comptes de démonstration</div>

    <div class="demo-box">
      <div class="demo-title">🔑 Tester l'application</div>
      <div class="demo-accounts">

        <div class="demo-account" onclick="fillDemo('client@demo.com')" title="Connexion rapide client">
          <span class="demo-badge badge-client">Client</span>
          <span class="demo-email">client@demo.com</span>
          <span class="demo-pw">password123</span>
        </div>

        <div class="demo-account" onclick="fillDemo('amine@demo.com')" title="Connexion rapide prestataire">
          <span class="demo-badge badge-provider">Prestataire</span>
          <span class="demo-email">amine@demo.com</span>
          <span class="demo-pw">password123</span>
        </div>

        <div class="demo-account" onclick="fillDemo('admin@servilocal.com')" title="Connexion rapide admin">
          <span class="demo-badge badge-admin">Admin</span>
          <span class="demo-email">admin@servilocal.com</span>
          <span class="demo-pw">password123</span>
        </div>

      </div>
    </div>

    <div class="auth-footer">
      Pas encore de compte ?
      <a href="/servilocal/register.php">Créer un compte gratuitement</a>
    </div>

  </div>
</div>

<script>
// ── Toggle visibilité mot de passe ──
document.getElementById('togglePw').addEventListener('click', function () {
  const input = document.getElementById('password');
  const isHidden = input.type === 'password';
  input.type = isHidden ? 'text' : 'password';
  this.textContent = isHidden ? '🙈' : '👁';
});

// ── Remplissage automatique des démos ──
function fillDemo(email) {
  const emailInput = document.getElementById('email');
  const pwInput    = document.getElementById('password');
  emailInput.value = email;
  pwInput.value    = 'password123';
  // Petit flash visuel sur le bouton
  const btn = document.getElementById('submitBtn');
  btn.style.background = 'var(--sage)';
  setTimeout(() => btn.style.background = '', 300);
  emailInput.focus();
}

// ── Feedback visuel pendant la soumission ──
document.getElementById('loginForm').addEventListener('submit', function (e) {
  // Validation côté client rapide
  const email = document.getElementById('email').value.trim();
  const pw    = document.getElementById('password').value.trim();
  if (!email || !pw) {
    e.preventDefault();
    document.getElementById('email').focus();
    return;
  }
  const btn   = document.getElementById('submitBtn');
  const label = document.getElementById('btnLabel');
  const arrow = document.getElementById('btnArrow');
  label.textContent = 'Connexion en cours…';
  arrow.textContent = '⏳';
  btn.disabled = true;
  btn.style.opacity = '0.8';
});
</script>

</body>
</html>