<?php
// ============================================================
// register.php — Inscription utilisateur
// ============================================================
require_once 'db.php';
require_once 'includes/functions.php';

if (isLoggedIn()) {
    header('Location: /servilocal/dashboard.php');
    exit;
}

$errors = [];
$data   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    // ── Récupération et nettoyage ─────────────────────────
    $data = [
        'name'     => trim($_POST['name']     ?? ''),
        'email'    => trim($_POST['email']    ?? ''),
        'phone'    => trim($_POST['phone']    ?? ''),
        'password' => trim($_POST['password'] ?? ''),
        'confirm'  => trim($_POST['confirm']  ?? ''),
        'role'     => $_POST['role'] ?? 'client',
        // Champs prestataire
        'category'    => trim($_POST['category']    ?? ''),
        'city'        => trim($_POST['city']        ?? ''),
        'price'       => trim($_POST['price']       ?? ''),
        'description' => trim($_POST['description'] ?? ''),
    ];

    // ── Validation ───────────────────────────────────────
    if (empty($data['name']))                         $errors[] = 'Le nom est requis.';
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Email invalide.';
    if (strlen($data['password']) < 8)               $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
    if ($data['password'] !== $data['confirm'])       $errors[] = 'Les mots de passe ne correspondent pas.';
    if (!in_array($data['role'], ['client','provider'])) $errors[] = 'Rôle invalide.';
    if ($data['role'] === 'provider') {
        if (empty($data['category']))  $errors[] = 'La catégorie est requise.';
        if (empty($data['city']))      $errors[] = 'La ville est requise.';
        if (empty($data['description'])) $errors[] = 'La description est requise.';
    }

    // Vérifier email unique
    if (empty($errors)) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$data['email']]);
        if ($stmt->fetch()) $errors[] = 'Cet email est déjà utilisé.';
    }

    // ── Enregistrement ───────────────────────────────────
    if (empty($errors)) {
        $pdo->beginTransaction();
        try {
            // Insérer l'utilisateur
            $hash = password_hash($data['password'], PASSWORD_BCRYPT);
            $stmt = $pdo->prepare('INSERT INTO users (name, email, password, phone, role) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$data['name'], $data['email'], $hash, $data['phone'], $data['role']]);
            $userId = $pdo->lastInsertId();

            // Si prestataire, créer le profil
            if ($data['role'] === 'provider') {
                $colors = ['#1C3D2E','#7C3AED','#059669','#D97706','#DC2626','#0284C7','#EA580C','#0891B2'];
                $color  = $colors[array_rand($colors)];
                $stmt2  = $pdo->prepare('INSERT INTO providers (user_id, category, city, price, description, avatar_color) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt2->execute([$userId, $data['category'], $data['city'], $data['price'] ?: 'Sur devis', $data['description'], $color]);
            }

            $pdo->commit();

            // Auto-login après inscription
            session_regenerate_id(true);
            $_SESSION['user_id']   = $userId;
            $_SESSION['user_name'] = $data['name'];
            $_SESSION['user_role'] = $data['role'];

            header('Location: /servilocal/dashboard.php?toast=' . urlencode('🎉 Bienvenue sur ServiLocal, ' . $data['name'] . ' !'));
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Erreur lors de l\'inscription. Réessayez.';
        }
    }
}

$categories = ['plomberie','electricite','gaz'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Inscription — ServiLocal</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700;900&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/servilocal/assets/css/style.css">
</head>
<body>

<header class="site-header">
  <nav class="nav-inner">
    <a href="/servilocal/index.php" class="logo">Servi<span class="logo-dot">•</span>Local</a>
    <ul class="nav-links">
      <li><a href="/servilocal/login.php">Se connecter</a></li>
    </ul>
  </nav>
</header>

<main>
<div class="auth-wrap" style="padding:3rem 1rem">
  <div class="auth-card" style="max-width:560px">
    <div class="auth-logo">
      <a href="/servilocal/index.php" class="logo">Servi<span class="logo-dot">•</span>Local</a>
    </div>

    <h1 class="auth-title">Créer un compte</h1>
    <p class="auth-sub">Rejoignez la communauté ServiLocal !</p>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-danger">
        <?php foreach ($errors as $err): ?>
          <div>⚠️ <?= e($err) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="" id="registerForm">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

      <!-- Sélecteur de rôle -->
      <div class="form-group">
        <label>Je suis…</label>
        <div class="role-selector">
          <label class="role-option">
            <input type="radio" name="role" value="client" <?= ($data['role'] ?? 'client') === 'client' ? 'checked' : '' ?> onchange="toggleProviderFields()">
            <span class="icon">👤</span>
            <div class="label">Client</div>
            <div style="font-size:.75rem;color:var(--muted)">Je cherche des services</div>
          </label>
          <label class="role-option">
            <input type="radio" name="role" value="provider" <?= ($data['role'] ?? '') === 'provider' ? 'checked' : '' ?> onchange="toggleProviderFields()">
            <span class="icon">🔧</span>
            <div class="label">Prestataire</div>
            <div style="font-size:.75rem;color:var(--muted)">Je propose des services</div>
          </label>
        </div>
      </div>

      <!-- Champs communs -->
      <div class="form-row">
        <div class="form-group">
          <label for="name">Nom complet *</label>
          <input type="text" id="name" name="name" class="form-control" placeholder="Prénom Nom" value="<?= e($data['name'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label for="phone">Téléphone</label>
          <input type="tel" id="phone" name="phone" class="form-control" placeholder="0600000000" value="<?= e($data['phone'] ?? '') ?>">
        </div>
      </div>

      <div class="form-group">
        <label for="email">Email *</label>
        <input type="email" id="email" name="email" class="form-control" placeholder="votre@email.com" value="<?= e($data['email'] ?? '') ?>" required>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="password">Mot de passe *</label>
          <input type="password" id="password" name="password" class="form-control" placeholder="Min. 8 caractères" required>
        </div>
        <div class="form-group">
          <label for="confirm">Confirmer *</label>
          <input type="password" id="confirm" name="confirm" class="form-control" placeholder="••••••••" required>
        </div>
      </div>

      <!-- Champs prestataire (affichés si role=provider) -->
      <div id="providerFields" style="display:<?= ($data['role'] ?? '') === 'provider' ? 'block' : 'none' ?>">
        <hr class="divider">
        <p style="font-size:.88rem;font-weight:600;color:var(--forest);margin-bottom:1rem">✦ Informations professionnelles</p>

        <div class="form-row">
          <div class="form-group">
            <label for="category">Catégorie *</label>
            <select id="category" name="category" class="form-control">
              <option value="">Choisir…</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat ?>" <?= ($data['category'] ?? '') === $cat ? 'selected' : '' ?>>
                  <?= categoryName($cat) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="city">Ville *</label>
            <input type="text" id="city" name="city" class="form-control" placeholder="Casablanca" value="<?= e($data['city'] ?? '') ?>">
          </div>
        </div>

        <div class="form-group">
          <label for="price">Tarif</label>
          <input type="text" id="price" name="price" class="form-control" placeholder="Ex: 150 DH/h" value="<?= e($data['price'] ?? '') ?>">
        </div>

        <div class="form-group">
          <label for="description">Description de vos services *</label>
          <textarea id="description" name="description" class="form-control" placeholder="Décrivez votre expertise, vos années d'expérience…"><?= e($data['description'] ?? '') ?></textarea>
        </div>
      </div>

      <button type="submit" class="btn btn-primary btn-block" style="margin-top:.75rem">
        Créer mon compte →
      </button>
    </form>

    <div class="auth-footer">
      Déjà un compte ? <a href="/servilocal/login.php">Se connecter</a>
    </div>
  </div>
</div>
</main>

<div class="toast" id="toastMsg"></div>
<script src="/servilocal/assets/js/main.js"></script>
<script>
function toggleProviderFields() {
  const role   = document.querySelector('input[name="role"]:checked').value;
  const fields = document.getElementById('providerFields');
  fields.style.display = role === 'provider' ? 'block' : 'none';
  // required sur les champs prestataire
  ['category','city','description'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.required = (role === 'provider');
  });
}
// Init
toggleProviderFields();
</script>
</body>
</html>
