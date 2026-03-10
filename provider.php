<?php
// ============================================================
// provider.php?id= — Fiche détaillée d'un prestataire
// ============================================================
require_once 'db.php';
require_once 'includes/functions.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /servilocal/index.php'); exit; }

// ── Charger le prestataire ────────────────────────────────
$stmt = $pdo->prepare('
    SELECT p.*, u.name, u.email, u.phone, u.avatar, u.created_at AS member_since
    FROM providers p
    JOIN users u ON u.id = p.user_id
    WHERE p.id = ?
');
$stmt->execute([$id]);
$provider = $stmt->fetch();
if (!$provider) { header('Location: /servilocal/index.php'); exit; }

// ── Charger les avis ──────────────────────────────────────
$stmt = $pdo->prepare('
    SELECT r.*, u.name AS client_name, u.avatar AS client_avatar
    FROM reviews r
    JOIN users u ON u.id = r.client_id
    WHERE r.provider_id = ?
    ORDER BY r.created_at DESC
    LIMIT 20
');
$stmt->execute([$id]);
$reviews = $stmt->fetchAll();

// ── Distribution des notes ────────────────────────────────
$stmt = $pdo->prepare('SELECT rating, COUNT(*) AS cnt FROM reviews WHERE provider_id = ? GROUP BY rating ORDER BY rating DESC');
$stmt->execute([$id]);
$ratingDist = [];
foreach ($stmt->fetchAll() as $row) $ratingDist[$row['rating']] = $row['cnt'];

$pageTitle = $provider['name'];
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

<!-- Hero prestataire -->
<div class="provider-hero">
  <div class="provider-hero-inner" style="max-width:1100px;margin:0 auto;display:grid;grid-template-columns:auto 1fr;gap:2rem;align-items:start;padding:0 2rem">
    <div style="display:flex;align-items:flex-start;gap:1.5rem">
      <div class="avatar-lg" style="background:<?= e($provider['avatar_color']) ?>;width:90px;height:90px;font-size:2rem;flex-shrink:0;position:relative;border:4px solid white;box-shadow:var(--shadow-lg)">
        <?php if ($provider['avatar']): ?>
          <img src="/servilocal/uploads/profiles/<?= e($provider['avatar']) ?>" style="width:100%;height:100%;border-radius:50%;object-fit:cover">
        <?php else: ?>
          <?= strtoupper(substr($provider['name'], 0, 1)) ?>
        <?php endif; ?>
        <?php if ($provider['is_verified']): ?>
          <div class="verified-badge" style="width:26px;height:26px;font-size:.8rem" title="Prestataire vérifié">✓</div>
        <?php endif; ?>
      </div>
      <div>
        <div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap">
          <h1 style="font-family:'Playfair Display',serif;font-size:1.9rem;font-weight:700;color:var(--forest)"><?= e($provider['name']) ?></h1>
          <?php if ($provider['is_verified']): ?>
            <span style="background:rgba(58,107,74,.12);color:var(--sage);padding:.25rem .75rem;border-radius:50px;font-size:.78rem;font-weight:700">✓ Vérifié</span>
          <?php endif; ?>
        </div>
        <p style="color:var(--sage);font-weight:600;text-transform:uppercase;font-size:.85rem;letter-spacing:.05em;margin-top:.2rem">
          <?= categoryIcon($provider['category']) ?> <?= categoryName($provider['category']) ?>
        </p>
        <p style="color:var(--muted);margin-top:.3rem">📍 <?= e($provider['city']) ?> &nbsp;·&nbsp; 💰 <?= e($provider['price']) ?></p>
        <p style="margin-top:.3rem">
          <?= $provider['is_available']
            ? '<span style="color:var(--green);font-weight:600">● Disponible</span>'
            : '<span style="color:var(--red);font-weight:600">● Indisponible</span>' ?>
          &nbsp;·&nbsp; Membre depuis <?= dateFr($provider['member_since']) ?>
        </p>
        <div style="display:flex;gap:1rem;margin-top:1rem;flex-wrap:wrap">
          <?= stars($provider['rating'] ?? 0) ?>
          <strong style="color:var(--forest)"><?= number_format($provider['rating'] ?? 0, 1) ?></strong>
          <span style="color:var(--muted)">(<?= $provider['review_count'] ?> avis)</span>
        </div>
      </div>
    </div>
    <div style="text-align:right;display:flex;flex-direction:column;gap:.75rem;align-items:flex-end">
      <a href="/servilocal/booking.php?provider=<?= $provider['id'] ?>" class="btn btn-primary" style="padding:1rem 2.5rem;font-size:1.1rem">
        📅 Réserver maintenant
      </a>
      <?php if (isLoggedIn()): ?>
        <a href="/servilocal/reviews.php?provider=<?= $provider['id'] ?>" class="btn btn-outline btn-sm">⭐ Laisser un avis</a>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="section" style="max-width:1100px">
  <div style="display:grid;grid-template-columns:2fr 1fr;gap:2rem;align-items:start">

    <!-- Colonne principale -->
    <div>
      <!-- Description -->
      <div class="card card-body mb-3">
        <h2 class="card-title">À propos</h2>
        <p style="color:var(--muted);line-height:1.75"><?= nl2br(e($provider['description'])) ?></p>
      </div>

      <!-- Avis clients -->
      <div class="card card-body">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem">
          <h2 class="card-title" style="margin:0">Avis clients (<?= count($reviews) ?>)</h2>
          <?php if (isLoggedIn() && userRole() === 'client'): ?>
            <a href="/servilocal/reviews.php?provider=<?= $provider['id'] ?>" class="btn btn-secondary btn-sm">⭐ Donner un avis</a>
          <?php endif; ?>
        </div>

        <?php if (empty($reviews)): ?>
          <div class="empty-state" style="padding:2rem">
            <div class="icon">💬</div>
            <h3>Aucun avis pour l'instant</h3>
            <p>Soyez le premier à donner votre avis !</p>
          </div>
        <?php else: ?>
          <?php foreach ($reviews as $rev): ?>
            <div class="review-card">
              <div class="review-author">
                <div class="avatar-sm" style="background:var(--forest)"><?= strtoupper(substr($rev['client_name'], 0, 1)) ?></div>
                <div>
                  <div style="font-weight:700;font-size:.92rem"><?= e($rev['client_name']) ?></div>
                  <div style="color:var(--amber);font-size:.85rem"><?= str_repeat('★', $rev['rating']) . str_repeat('☆', 5 - $rev['rating']) ?></div>
                </div>
                <div class="review-date" style="margin-left:auto"><?= dateFr($rev['created_at']) ?></div>
              </div>
              <?php if ($rev['comment']): ?>
                <p class="review-text"><?= e($rev['comment']) ?></p>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Sidebar -->
    <div>
      <!-- Stats note -->
      <div class="card card-body mb-3">
        <h3 style="font-weight:700;margin-bottom:1.25rem;color:var(--forest)">Note globale</h3>
        <div style="text-align:center;margin-bottom:1.25rem">
          <div style="font-family:'Playfair Display',serif;font-size:3.5rem;font-weight:700;color:var(--forest);line-height:1"><?= number_format($provider['rating'] ?? 0, 1) ?></div>
          <div style="color:var(--amber);font-size:1.4rem;margin:.3rem 0"><?= str_repeat('★', (int)round($provider['rating'] ?? 0)) . str_repeat('☆', 5 - (int)round($provider['rating'] ?? 0)) ?></div>
          <div style="color:var(--muted);font-size:.85rem"><?= $provider['review_count'] ?> avis</div>
        </div>
        <?php for ($s = 5; $s >= 1; $s--): ?>
          <?php $cnt = $ratingDist[$s] ?? 0; $pct = $provider['review_count'] ? round($cnt / $provider['review_count'] * 100) : 0; ?>
          <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.5rem;font-size:.85rem">
            <span style="min-width:14px"><?= $s ?></span>
            <span style="color:var(--amber)">★</span>
            <div style="flex:1;background:var(--border);border-radius:50px;height:8px">
              <div style="width:<?= $pct ?>%;background:var(--amber);border-radius:50px;height:100%"></div>
            </div>
            <span style="min-width:28px;color:var(--muted)"><?= $pct ?>%</span>
          </div>
        <?php endfor; ?>
      </div>

      <!-- Contact rapide -->
      <div class="card card-body mb-3">
        <h3 style="font-weight:700;margin-bottom:1rem;color:var(--forest)">Contact</h3>
        <?php if ($provider['phone']): ?>
          <p style="margin-bottom:.6rem">📞 <a href="tel:<?= e($provider['phone']) ?>" style="color:var(--sage);text-decoration:none"><?= e($provider['phone']) ?></a></p>
        <?php endif; ?>
        <p>✉️ <a href="mailto:<?= e($provider['email']) ?>" style="color:var(--sage);text-decoration:none"><?= e($provider['email']) ?></a></p>
      </div>

      <!-- CTA réservation -->
      <a href="/servilocal/booking.php?provider=<?= $provider['id'] ?>" class="btn btn-primary btn-block">
        📅 Réserver ce prestataire
      </a>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
</body>
</html>
