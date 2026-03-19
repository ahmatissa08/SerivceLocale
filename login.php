<?php
// ============================================================
// login.php — Connexion utilisateur
// ============================================================
require_once 'db.php';
require_once 'includes/functions.php';

// Rediriger si déjà connecté
if (isLoggedIn()) {
    header('Location: /servilocal/dashboard.php');
    exit;
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    // ── Validation ───────────────────────────────────────
    if (empty($email) || empty($password)) {
        $error = 'Veuillez remplir tous les champs.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Adresse email invalide.';
    } else {
        // ── Recherche de l'utilisateur (prepared statement) ──
        $stmt = $pdo->prepare('SELECT id, name, email, password, role FROM users WHERE email = ? AND is_active = 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
    session_regenerate_id(true);
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_role'] = $user['role'];

    // ── Redirection selon le rôle ──────────────────────
    if ($user['role'] === 'admin') {
        $redirect = '/servilocal/admin.php';
    } else {
        $redirect = $_GET['redirect'] ?? '/servilocal/dashboard.php';
    }

    header('Location: ' . $redirect . '?toast=' . urlencode('👋 Bienvenue, ' . $user['name'] . ' !'));
    exit;
        } else {
            $error = 'Email ou mot de passe incorrect.';
        }
    }
}

$pageTitle = 'Connexion';
include 'includes/functions.php'; // déjà inclus, pas de doublon grâce au check session
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Connexion — ServiLocal</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700;900&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/servilocal/assets/css/style.css">
</head>
<body>

<!-- Header simplifié -->
<header class="site-header">
  <nav class="nav-inner">
    <a href="/servilocal/index.php" class="logo">Servi<span class="logo-dot">•</span>Local</a>
    <ul class="nav-links">
      <li><a href="/servilocal/register.php" class="btn-nav">Créer un compte</a></li>
    </ul>
  </nav>
</header>

<main>
<div class="auth-wrap">
  <div class="auth-card">
    <div class="auth-logo">
      <a href="/servilocal/index.php" class="logo">Servi<span class="logo-dot">•</span>Local</a>
    </div>

    <h1 class="auth-title">Bon retour ! 👋</h1>
    <p class="auth-sub">Connectez-vous pour accéder à votre espace personnel.</p>

    <?php if ($error): ?>
      <div class="alert alert-danger">⚠️ <?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

      <div class="form-group">
        <label for="email">Adresse email</label>
        <input type="email" id="email" name="email" class="form-control"
               placeholder="votre@email.com"
               value="<?= e($_POST['email'] ?? '') ?>" required autofocus>
      </div>

      <div class="form-group">
        <label for="password">Mot de passe</label>
        <input type="password" id="password" name="password" class="form-control"
               placeholder="••••••••" required>
      </div>

      <button type="submit" class="btn btn-primary btn-block" style="margin-top:.5rem">
        Se connecter →
      </button>
    </form>

    <!-- Comptes démo -->
    <div style="margin-top:1.5rem;padding:1rem;background:var(--cream);border-radius:var(--radius-sm);font-size:.82rem;color:var(--muted)">
      <strong style="color:var(--text)">Comptes démo (mot de passe : password123)</strong><br>
      Client : client@demo.com &nbsp;|&nbsp; Prestataire : amine@demo.com
    </div>

    <div class="auth-footer">
      Pas encore de compte ? <a href="/servilocal/register.php">Créer un compte</a>
    </div>
  </div>
</div>
</main>

<div class="toast" id="toastMsg"></div>
<script src="/servilocal/assets/js/main.js"></script>
</body>
</html>
