<?php
// ============================================================
// profile.php — Profil utilisateur
// ============================================================
require_once 'db.php';
require_once 'includes/functions.php';
requireLogin();

$userId  = $_SESSION['user_id'];
$errors  = [];
$success = '';

// ── Charger les données actuelles ────────────────────────
$stmt = $pdo->prepare('SELECT u.*, p.id AS provider_id, p.category, p.city, p.price, p.description, p.is_available
                        FROM users u
                        LEFT JOIN providers p ON p.user_id = u.id
                        WHERE u.id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();

// ── Traitement formulaire profil ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    verifyCsrf();

    $name  = trim($_POST['name']  ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if (empty($name))                              $errors[] = 'Le nom est requis.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email invalide.';

    // Vérifier unicité email (excluant l'utilisateur actuel)
    if (empty($errors)) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
        $stmt->execute([$email, $userId]);
        if ($stmt->fetch()) $errors[] = 'Cet email est déjà utilisé par un autre compte.';
    }

    // Upload photo de profil
    $avatarPath = $user['avatar'];
    if (!empty($_FILES['avatar']['name'])) {
        $file     = $_FILES['avatar'];
        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed  = ['jpg','jpeg','png','gif','webp'];
        $maxSize  = 2 * 1024 * 1024; // 2 Mo

        if (!in_array($ext, $allowed))      $errors[] = 'Format d\'image non supporté (jpg, png, gif, webp).';
        elseif ($file['size'] > $maxSize)   $errors[] = 'L\'image ne doit pas dépasser 2 Mo.';
        else {
            $filename   = 'avatar_' . $userId . '_' . time() . '.' . $ext;
            $uploadDir  = __DIR__ . '/uploads/profiles/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                // Supprimer l'ancienne photo
                if ($user['avatar'] && file_exists($uploadDir . $user['avatar'])) {
                    unlink($uploadDir . $user['avatar']);
                }
                $avatarPath = $filename;
            } else {
                $errors[] = 'Erreur lors du téléchargement de l\'image.';
            }
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare('UPDATE users SET name = ?, phone = ?, email = ?, avatar = ? WHERE id = ?');
        $stmt->execute([$name, $phone, $email, $avatarPath, $userId]);
        $_SESSION['user_name'] = $name;
        $success = 'Profil mis à jour avec succès !';
        // Recharger les données
        $stmt = $pdo->prepare('SELECT u.*, p.id AS provider_id, p.category, p.city, p.price, p.description, p.is_available
                                FROM users u LEFT JOIN providers p ON p.user_id = u.id WHERE u.id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
    }
}

// ── Traitement formulaire prestataire ─────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_provider'])) {
    verifyCsrf();
    if ($user['provider_id']) {
        $stmt = $pdo->prepare('UPDATE providers SET category=?, city=?, price=?, description=?, is_available=? WHERE user_id=?');
        $stmt->execute([
            $_POST['category'], trim($_POST['city']), trim($_POST['price']),
            trim($_POST['description']), isset($_POST['is_available']) ? 1 : 0,
            $userId
        ]);
        $success = 'Profil prestataire mis à jour !';
        // Recharger
        $stmt = $pdo->prepare('SELECT u.*, p.id AS provider_id, p.category, p.city, p.price, p.description, p.is_available
                                FROM users u LEFT JOIN providers p ON p.user_id = u.id WHERE u.id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
    }
}

// ── Traitement changement de mot de passe ─────────────────
$pwErrors  = [];
$pwSuccess = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    verifyCsrf();
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password']     ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();

    if (!password_verify($current, $row['password'])) $pwErrors[] = 'Mot de passe actuel incorrect.';
    elseif (strlen($new) < 8)                          $pwErrors[] = 'Le nouveau mot de passe doit contenir au moins 8 caractères.';
    elseif ($new !== $confirm)                         $pwErrors[] = 'Les mots de passe ne correspondent pas.';

    if (empty($pwErrors)) {
        $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
        $stmt->execute([password_hash($new, PASSWORD_BCRYPT), $userId]);
        $pwSuccess = 'Mot de passe modifié avec succès !';
    }
}

$categories = ['plomberie','coiffure','informatique','electricite','jardinage','menage','transport','peinture'];
$pageTitle  = 'Mon Profil';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> — ServiLocal</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700;900&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/servilocal/assets/css/style.css">
</head>
<body>
<?php include 'includes/header.php'; ?>

<div class="profile-wrap">

  <!-- Header carte profil -->
  <div class="profile-header-card">
    <div class="profile-avatar-big" style="background:<?= e($user['avatar_color'] ?? '#2C5F2D') ?>">
      <?php if ($user['avatar']): ?>
        <img src="/servilocal/uploads/profiles/<?= e($user['avatar']) ?>" alt="Avatar" id="avatarPreview">
      <?php else: ?>
        <span id="avatarPlaceholder"><?= strtoupper(substr($user['name'], 0, 1)) ?></span>
        <img src="" alt="Avatar" id="avatarPreview" style="display:none">
      <?php endif; ?>
      <label class="avatar-edit" for="avatarInputHeader" title="Changer la photo">✏️</label>
    </div>
    <div>
      <h1 style="font-family:'Playfair Display',serif;font-size:1.5rem;font-weight:700;color:var(--forest)"><?= e($user['name']) ?></h1>
      <p style="color:var(--muted)"><?= e($user['email']) ?></p>
      <p style="margin-top:.3rem">
        <?php if ($user['role'] === 'provider'): ?>
          <span style="background:rgba(58,107,74,.12);color:var(--sage);padding:.25rem .75rem;border-radius:50px;font-size:.8rem;font-weight:700">
            🔧 Prestataire · <?= categoryName($user['category'] ?? '') ?>
          </span>
        <?php else: ?>
          <span style="background:rgba(28,61,46,.1);color:var(--forest);padding:.25rem .75rem;border-radius:50px;font-size:.8rem;font-weight:700">👤 Client</span>
        <?php endif; ?>
      </p>
    </div>
  </div>

  <!-- Onglets -->
  <div class="profile-tabs" id="profileTabs">
    <button class="profile-tab active" onclick="switchProfileTab(this,'tabInfos')">Informations</button>
    <?php if ($user['role'] === 'provider'): ?>
      <button class="profile-tab" onclick="switchProfileTab(this,'tabProvider')">Profil pro</button>
    <?php endif; ?>
    <button class="profile-tab" onclick="switchProfileTab(this,'tabPassword')">Sécurité</button>
  </div>

  <!-- Tab : Informations personnelles -->
  <div id="tabInfos" class="card card-body" style="margin-bottom:1.5rem">
    <h2 class="card-title" style="margin-bottom:1.5rem">Informations personnelles</h2>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-danger"><?php foreach ($errors as $e_): echo '⚠️ '.e($e_).'<br>'; endforeach; ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="alert alert-success" data-auto-dismiss>✅ <?= e($success) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <input type="hidden" name="update_profile" value="1">
      <!-- Avatar input caché lié au bouton dans le header -->
      <input type="file" id="avatarInputHeader" name="avatar" accept="image/*" style="display:none">

      <div class="form-row">
        <div class="form-group">
          <label for="p_name">Nom complet</label>
          <input type="text" id="p_name" name="name" class="form-control" value="<?= e($user['name']) ?>" required>
        </div>
        <div class="form-group">
          <label for="p_phone">Téléphone</label>
          <input type="tel" id="p_phone" name="phone" class="form-control" value="<?= e($user['phone'] ?? '') ?>">
        </div>
      </div>
      <div class="form-group">
        <label for="p_email">Email</label>
        <input type="email" id="p_email" name="email" class="form-control" value="<?= e($user['email']) ?>" required>
      </div>
      <div class="form-group">
        <label>Photo de profil</label>
        <input type="file" id="avatarInput" name="avatar" accept="image/*" class="form-control">
        <span style="font-size:.78rem;color:var(--muted)">Formats acceptés : JPG, PNG, GIF, WEBP — Max 2 Mo</span>
      </div>
      <button type="submit" class="btn btn-primary">💾 Enregistrer</button>
    </form>
  </div>

  <!-- Tab : Profil prestataire -->
  <?php if ($user['role'] === 'provider'): ?>
  <div id="tabProvider" class="card card-body" style="display:none;margin-bottom:1.5rem">
    <h2 class="card-title" style="margin-bottom:1.5rem">Mon profil professionnel</h2>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <input type="hidden" name="update_provider" value="1">
      <div class="form-row">
        <div class="form-group">
          <label>Catégorie</label>
          <select name="category" class="form-control">
            <?php foreach ($categories as $cat): ?>
              <option value="<?= $cat ?>" <?= ($user['category'] ?? '') === $cat ? 'selected' : '' ?>><?= categoryName($cat) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Ville</label>
          <input type="text" name="city" class="form-control" value="<?= e($user['city'] ?? '') ?>" required>
        </div>
      </div>
      <div class="form-group">
        <label>Tarif</label>
        <input type="text" name="price" class="form-control" value="<?= e($user['price'] ?? '') ?>" placeholder="Ex: 150 DH/h">
      </div>
      <div class="form-group">
        <label>Description</label>
        <textarea name="description" class="form-control" style="min-height:120px"><?= e($user['description'] ?? '') ?></textarea>
      </div>
      <div class="form-group" style="display:flex;align-items:center;gap:.75rem">
        <input type="checkbox" id="is_available" name="is_available" value="1" <?= ($user['is_available'] ?? 1) ? 'checked' : '' ?>>
        <label for="is_available" style="margin:0;cursor:pointer">🟢 Disponible pour de nouvelles réservations</label>
      </div>
      <button type="submit" class="btn btn-primary">💾 Mettre à jour</button>
    </form>
  </div>
  <?php endif; ?>

  <!-- Tab : Sécurité -->
  <div id="tabPassword" class="card card-body" style="display:none;margin-bottom:1.5rem">
    <h2 class="card-title" style="margin-bottom:1.5rem">Changer le mot de passe</h2>

    <?php if (!empty($pwErrors)): ?>
      <div class="alert alert-danger"><?php foreach ($pwErrors as $e_): echo '⚠️ '.e($e_).'<br>'; endforeach; ?></div>
    <?php endif; ?>
    <?php if ($pwSuccess): ?>
      <div class="alert alert-success" data-auto-dismiss>✅ <?= e($pwSuccess) ?></div>
    <?php endif; ?>

    <form method="POST" style="max-width:420px">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <input type="hidden" name="change_password" value="1">
      <div class="form-group">
        <label>Mot de passe actuel</label>
        <input type="password" name="current_password" class="form-control" required>
      </div>
      <div class="form-group">
        <label>Nouveau mot de passe</label>
        <input type="password" name="new_password" class="form-control" placeholder="Min. 8 caractères" required>
      </div>
      <div class="form-group">
        <label>Confirmer le nouveau mot de passe</label>
        <input type="password" name="confirm_password" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-primary">🔒 Changer le mot de passe</button>
    </form>
  </div>

</div><!-- /.profile-wrap -->

<?php include 'includes/footer.php'; ?>
<script>
// Synchroniser le second input avatar avec la preview
document.getElementById('avatarInputHeader').addEventListener('change', function() {
  document.getElementById('avatarInput').files = this.files;
  const r = new FileReader();
  r.onload = e => {
    const prev = document.getElementById('avatarPreview');
    const ph   = document.getElementById('avatarPlaceholder');
    prev.src = e.target.result; prev.style.display = 'block';
    if (ph) ph.style.display = 'none';
  };
  r.readAsDataURL(this.files[0]);
});

function switchProfileTab(btn, tabId) {
  document.querySelectorAll('.profile-tab').forEach(t => t.classList.remove('active'));
  btn.classList.add('active');
  ['tabInfos','tabProvider','tabPassword'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.style.display = id === tabId ? 'block' : 'none';
  });
}
</script>
</body>
</html>
