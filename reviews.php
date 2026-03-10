<?php
// ============================================================
// reviews.php — Laisser un avis sur un prestataire
// ============================================================
require_once 'db.php';
require_once 'includes/functions.php';
requireLogin();

if (isProvider()) {
    header('Location: /servilocal/dashboard.php?toast=' . urlencode('⚠️ Les prestataires ne peuvent pas laisser d\'avis.'));
    exit;
}

$providerId = (int)($_GET['provider'] ?? 0);
$bookingId  = (int)($_GET['booking']  ?? 0) ?: null;
if (!$providerId) { header('Location: /servilocal/index.php'); exit; }

// Charger le prestataire
$stmt = $pdo->prepare('SELECT p.*, u.name FROM providers p JOIN users u ON u.id = p.user_id WHERE p.id = ?');
$stmt->execute([$providerId]);
$provider = $stmt->fetch();
if (!$provider) { header('Location: /servilocal/index.php'); exit; }

$userId  = $_SESSION['user_id'];
$errors  = [];
$success = false;

// Vérifier si l'utilisateur a déjà laissé un avis pour cette réservation
if ($bookingId) {
    $stmt = $pdo->prepare('SELECT id FROM reviews WHERE client_id = ? AND provider_id = ? AND booking_id = ?');
    $stmt->execute([$userId, $providerId, $bookingId]);
    if ($stmt->fetch()) {
        header('Location: /servilocal/provider.php?id=' . $providerId . '&toast=' . urlencode('⚠️ Vous avez déjà laissé un avis pour cette réservation.'));
        exit;
    }
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $rating  = (int)($_POST['rating']  ?? 0);
    $comment = trim($_POST['comment']  ?? '');

    if ($rating < 1 || $rating > 5) $errors[] = 'Veuillez choisir une note entre 1 et 5 étoiles.';

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare('INSERT INTO reviews (client_id, provider_id, booking_id, rating, comment) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$userId, $providerId, $bookingId ?: null, $rating, $comment]);

            // Le trigger MySQL met à jour la note — mais on peut aussi forcer ici
            $stmt = $pdo->prepare('UPDATE providers SET rating = (SELECT AVG(rating) FROM reviews WHERE provider_id = ?), review_count = (SELECT COUNT(*) FROM reviews WHERE provider_id = ?) WHERE id = ?');
            $stmt->execute([$providerId, $providerId, $providerId]);

            $success = true;
        } catch (PDOException $e) {
            $errors[] = 'Vous avez peut-être déjà laissé un avis pour ce prestataire.';
        }
    }
}

$pageTitle = 'Laisser un avis — ' . $provider['name'];
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

<div class="section-sm">
  <a href="/servilocal/provider.php?id=<?= $provider['id'] ?>" style="color:var(--sage);text-decoration:none;font-size:.9rem;display:inline-flex;align-items:center;gap:.3rem;margin-bottom:1.5rem">← Retour au profil</a>

  <?php if ($success): ?>
    <!-- Confirmation -->
    <div class="card card-body text-center" style="padding:3rem">
      <div style="font-size:4rem;margin-bottom:1rem">⭐</div>
      <h2 style="font-family:'Playfair Display',serif;font-size:1.75rem;color:var(--forest);margin-bottom:.75rem">Merci pour votre avis !</h2>
      <p style="color:var(--muted);margin-bottom:2rem">Votre évaluation de <strong><?= e($provider['name']) ?></strong> a bien été publiée.</p>
      <div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap">
        <a href="/servilocal/provider.php?id=<?= $provider['id'] ?>" class="btn btn-primary">Voir le profil →</a>
        <a href="/servilocal/dashboard.php" class="btn btn-outline">Mon tableau de bord</a>
      </div>
    </div>

  <?php else: ?>

    <div class="card card-body">
      <!-- Mini profil -->
      <div class="provider-mini" style="margin-bottom:2rem">
        <div class="avatar-lg" style="background:<?= e($provider['avatar_color']) ?>;width:56px;height:56px;font-size:1.3rem">
          <?= strtoupper(substr($provider['name'], 0, 1)) ?>
        </div>
        <div>
          <div style="font-weight:700"><?= e($provider['name']) ?></div>
          <div style="font-size:.82rem;color:var(--sage);font-weight:600">
            <?= categoryIcon($provider['category']) ?> <?= categoryName($provider['category']) ?>
          </div>
          <div style="color:var(--amber);font-size:.9rem;margin-top:.2rem">
            <?= str_repeat('★', (int)round($provider['rating'])) ?>
            <span style="color:var(--muted);font-size:.82rem"> (<?= $provider['review_count'] ?> avis)</span>
          </div>
        </div>
      </div>

      <h1 style="font-family:'Playfair Display',serif;font-size:1.5rem;font-weight:700;color:var(--forest);margin-bottom:1.5rem">
        Votre avis
      </h1>

      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><?php foreach ($errors as $e_): echo '⚠️ '.e($e_).'<br>'; endforeach; ?></div>
      <?php endif; ?>

      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

        <!-- Étoiles interactives -->
        <div class="form-group">
          <label>Votre note *</label>
          <div class="star-picker" id="starPicker">
            <span>★</span><span>★</span><span>★</span><span>★</span><span>★</span>
          </div>
          <input type="hidden" name="rating" id="ratingInput" value="<?= (int)($_POST['rating'] ?? 0) ?>">
          <div id="ratingLabel" style="font-size:.85rem;color:var(--muted);margin-top:.3rem"></div>
        </div>

        <div class="form-group">
          <label for="comment">Votre commentaire</label>
          <textarea id="comment" name="comment" class="form-control" style="min-height:130px"
                    placeholder="Décrivez votre expérience : ponctualité, qualité du travail, communication…"><?= e($_POST['comment'] ?? '') ?></textarea>
        </div>

        <div style="display:flex;gap:1rem;margin-top.5rem">
          <button type="submit" class="btn btn-primary" style="flex:1">⭐ Publier mon avis</button>
          <a href="/servilocal/provider.php?id=<?= $provider['id'] ?>" class="btn btn-outline">Annuler</a>
        </div>
      </form>
    </div>
  <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
<script>
// Étoiles interactives
const labels = ['','Très mauvais 😞','Mauvais 😕','Moyen 😐','Bien 😊','Excellent ! 🌟'];
initStarPicker('starPicker', 'ratingInput');

// Mettre à jour le label selon la note
document.getElementById('starPicker').addEventListener('click', function() {
  const val = parseInt(document.getElementById('ratingInput').value);
  document.getElementById('ratingLabel').textContent = labels[val] || '';
});

// Init si valeur déjà sélectionnée (erreur de form)
(function() {
  const val = parseInt(document.getElementById('ratingInput').value);
  if (val > 0) {
    const stars = document.querySelectorAll('#starPicker span');
    stars.forEach((s, i) => s.classList.toggle('lit', i < val));
    document.getElementById('ratingLabel').textContent = labels[val] || '';
  }
})();
</script>
</body>
</html>
