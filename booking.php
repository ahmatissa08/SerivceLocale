<?php
// ============================================================
// booking.php — Formulaire de réservation
// ============================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

// Seuls les clients peuvent réserver
if (isProvider()) {
    header('Location: /servilocal/dashboard.php?toast=' . urlencode('⚠️ Les prestataires ne peuvent pas réserver.'));
    exit;
}

$providerId = (int)($_GET['provider'] ?? 0);
if (!$providerId) { header('Location: /servilocal/index.php'); exit; }

// Charger le prestataire
$stmt = $pdo->prepare('SELECT p.*, u.name, u.avatar FROM providers p JOIN users u ON u.id = p.user_id WHERE p.id = ?');
$stmt->execute([$providerId]);
$provider = $stmt->fetch();
if (!$provider) { header('Location: /servilocal/index.php'); exit; }

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $date     = $_POST['booking_date'] ?? '';
    $time     = $_POST['booking_time'] ?? '';
    $address  = trim($_POST['address']     ?? '');
    $details  = trim($_POST['description'] ?? '');

    // Validation
    if (empty($date))               $errors[] = 'La date est requise.';
    elseif ($date < date('Y-m-d'))  $errors[] = 'La date ne peut pas être dans le passé.';
    if (empty($time))               $errors[] = 'L\'heure est requise.';

    // Vérifier si le prestataire n'a pas déjà une réservation acceptée ce créneau
    if (empty($errors)) {
        $stmt = $pdo->prepare('SELECT id FROM bookings WHERE provider_id = ? AND booking_date = ? AND booking_time = ? AND status IN ("pending","accepted")');
        $stmt->execute([$providerId, $date, $time]);
        if ($stmt->fetch()) $errors[] = 'Ce créneau est déjà réservé. Choisissez un autre horaire.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare('INSERT INTO bookings (client_id, provider_id, booking_date, booking_time, address, description) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$_SESSION['user_id'], $providerId, $date, $time, $address, $details]);
        $success = true;
    }
}

$pageTitle = 'Réserver ' . $provider['name'];
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

<div class="booking-wrap">
  <a href="/servilocal/provider.php?id=<?= $provider['id'] ?>" style="color:var(--sage);text-decoration:none;font-size:.9rem;display:inline-flex;align-items:center;gap:.3rem;margin-bottom:1.5rem">← Retour au profil</a>

  <h1 class="page-title">Réserver un service</h1>

  <?php if ($success): ?>
    <!-- Confirmation -->
    <div class="card card-body text-center" style="padding:3rem">
      <div style="font-size:4rem;margin-bottom:1rem">✅</div>
      <h2 style="font-family:'Playfair Display',serif;font-size:1.75rem;color:var(--forest);margin-bottom:.75rem">Réservation confirmée !</h2>
      <p style="color:var(--muted);margin-bottom:2rem">Votre demande a été envoyée à <strong><?= e($provider['name']) ?></strong>. Vous serez contacté(e) pour confirmer le rendez-vous.</p>
      <div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap">
        <a href="/servilocal/dashboard.php" class="btn btn-primary">Voir mes réservations →</a>
        <a href="/servilocal/index.php" class="btn btn-outline">Retour à l'accueil</a>
      </div>
    </div>

  <?php else: ?>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-danger"><?php foreach ($errors as $e_): echo '⚠️ '.e($e_).'<br>'; endforeach; ?></div>
    <?php endif; ?>

    <div class="booking-card">
      <!-- Mini profil prestataire -->
      <div class="provider-mini">
        <div class="avatar-lg" style="background:<?= e($provider['avatar_color']) ?>;width:56px;height:56px;font-size:1.3rem">
          <?= strtoupper(substr($provider['name'], 0, 1)) ?>
        </div>
        <div>
          <div style="font-weight:700"><?= e($provider['name']) ?></div>
          <div style="font-size:.82rem;color:var(--sage);font-weight:600;text-transform:uppercase"><?= categoryIcon($provider['category']) ?> <?= categoryName($provider['category']) ?></div>
          <div style="font-size:.82rem;color:var(--muted)">📍 <?= e($provider['city']) ?> &nbsp;·&nbsp; 💰 <?= e($provider['price']) ?></div>
        </div>
      </div>

      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

        <div class="form-row">
          <div class="form-group">
            <label>Date souhaitée *</label>
            <input type="date" name="booking_date" class="form-control"
                   min="<?= date('Y-m-d') ?>"
                   value="<?= e($_POST['booking_date'] ?? '') ?>" required>
          </div>
          <div class="form-group">
            <label>Heure souhaitée *</label>
            <input type="time" name="booking_time" class="form-control"
                   value="<?= e($_POST['booking_time'] ?? '') ?>" required>
          </div>
        </div>

        <div class="form-group">
          <label>Adresse d'intervention</label>
          <input type="text" name="address" class="form-control"
                 placeholder="Votre adresse complète"
                 value="<?= e($_POST['address'] ?? '') ?>">
        </div>

        <div class="form-group">
          <label>Description du problème / besoin</label>
          <textarea name="description" class="form-control" style="min-height:120px"
                    placeholder="Décrivez votre besoin en détail…"><?= e($_POST['description'] ?? '') ?></textarea>
        </div>

        <div style="background:var(--cream);border-radius:var(--radius-sm);padding:1rem;margin-bottom:1.5rem;font-size:.88rem;color:var(--muted)">
          ℹ️ Votre demande sera envoyée au prestataire qui vous contactera pour confirmer le rendez-vous.
        </div>

        <div style="display:flex;gap:1rem">
          <button type="submit" class="btn btn-primary" style="flex:1;padding:1rem">
            📅 Confirmer la réservation
          </button>
          <a href="/servilocal/provider.php?id=<?= $provider['id'] ?>" class="btn btn-outline">Annuler</a>
        </div>
      </form>
    </div>

  <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
</body>
</html>
