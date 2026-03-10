<?php
// ============================================================
// dashboard.php — Tableau de bord (client + prestataire)
// ============================================================
require_once 'db.php';
require_once 'includes/functions.php';
requireLogin();

$userId = $_SESSION['user_id'];
$role   = userRole();

// ── Actions sur les réservations (prestataire) ────────────
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action    = $_GET['action'];
    $bookingId = (int)$_GET['id'];
    $allowed   = ['accept' => 'accepted', 'refuse' => 'refused', 'cancel' => 'cancelled'];

    if (array_key_exists($action, $allowed)) {
        if ($role === 'provider') {
            // Le prestataire peut accept/refuse ses propres réservations
            $stmt = $pdo->prepare('UPDATE bookings SET status = ? WHERE id = ? AND provider_id = (SELECT id FROM providers WHERE user_id = ?)');
            $stmt->execute([$allowed[$action], $bookingId, $userId]);
        } elseif ($action === 'cancel') {
            // Le client peut annuler ses propres réservations
            $stmt = $pdo->prepare('UPDATE bookings SET status = "cancelled" WHERE id = ? AND client_id = ? AND status = "pending"');
            $stmt->execute([$bookingId, $userId]);
        }
    }
    header('Location: /servilocal/dashboard.php?toast=' . urlencode('✅ Réservation mise à jour.'));
    exit;
}

// ── Charger les données ───────────────────────────────────
if ($role === 'client') {
    // Réservations du client
    $stmt = $pdo->prepare('
        SELECT b.*, u.name AS provider_name, p.category, p.city, p.id AS provider_id
        FROM bookings b
        JOIN providers p ON p.id = b.provider_id
        JOIN users u ON u.id = p.user_id
        WHERE b.client_id = ?
        ORDER BY b.booking_date DESC, b.created_at DESC
    ');
    $stmt->execute([$userId]);
    $bookings = $stmt->fetchAll();

    // Avis du client
    $stmt = $pdo->prepare('
        SELECT r.*, u.name AS provider_name, p.category
        FROM reviews r
        JOIN providers p ON p.id = r.provider_id
        JOIN users u ON u.id = p.user_id
        WHERE r.client_id = ?
        ORDER BY r.created_at DESC
    ');
    $stmt->execute([$userId]);
    $myReviews = $stmt->fetchAll();

    // Stats client
    $stats = [
        'total'     => count($bookings),
        'pending'   => count(array_filter($bookings, fn($b) => $b['status'] === 'pending')),
        'accepted'  => count(array_filter($bookings, fn($b) => $b['status'] === 'accepted')),
        'completed' => count(array_filter($bookings, fn($b) => $b['status'] === 'completed')),
    ];

} else {
    // Profil prestataire
    $stmt = $pdo->prepare('SELECT * FROM providers WHERE user_id = ?');
    $stmt->execute([$userId]);
    $providerProfile = $stmt->fetch();

    if (!$providerProfile) {
        header('Location: /servilocal/profile.php?toast=' . urlencode('⚠️ Créez votre profil prestataire.'));
        exit;
    }

    $provId = $providerProfile['id'];

    // Réservations reçues
    $stmt = $pdo->prepare('
        SELECT b.*, u.name AS client_name, u.phone AS client_phone, u.email AS client_email
        FROM bookings b
        JOIN users u ON u.id = b.client_id
        WHERE b.provider_id = ?
        ORDER BY b.booking_date ASC, b.created_at DESC
    ');
    $stmt->execute([$provId]);
    $bookings = $stmt->fetchAll();

    // Avis reçus
    $stmt = $pdo->prepare('
        SELECT r.*, u.name AS client_name
        FROM reviews r
        JOIN users u ON u.id = r.client_id
        WHERE r.provider_id = ?
        ORDER BY r.created_at DESC
    ');
    $stmt->execute([$provId]);
    $myReviews = $stmt->fetchAll();

    // Stats prestataire
    $stats = [
        'total'    => count($bookings),
        'pending'  => count(array_filter($bookings, fn($b) => $b['status'] === 'pending')),
        'accepted' => count(array_filter($bookings, fn($b) => $b['status'] === 'accepted')),
        'rating'   => $providerProfile['rating'],
    ];
}

$pageTitle = 'Mon Tableau de bord';
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

<div class="dash-wrap">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:2rem">
    <h1 class="dash-title" style="margin:0">
      Bonjour, <?= e($_SESSION['user_name']) ?> 👋
    </h1>
    <?php if ($role === 'client'): ?>
      <a href="/servilocal/index.php" class="btn btn-primary btn-sm">🔍 Trouver un service</a>
    <?php else: ?>
      <a href="/servilocal/profile.php" class="btn btn-outline btn-sm">⚙️ Mon profil pro</a>
    <?php endif; ?>
  </div>

  <!-- Stat cards -->
  <div class="stat-cards">
    <div class="stat-card">
      <div class="stat-icon" style="background:rgba(28,61,46,.1)">📅</div>
      <div>
        <span class="stat-val"><?= $stats['total'] ?></span>
        <span class="stat-lbl">Réservations total</span>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#fffbeb">🕐</div>
      <div>
        <span class="stat-val"><?= $stats['pending'] ?></span>
        <span class="stat-lbl">En attente</span>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#ecfdf5">✅</div>
      <div>
        <span class="stat-val"><?= $stats['accepted'] ?></span>
        <span class="stat-lbl">Acceptées</span>
      </div>
    </div>
    <?php if ($role === 'client'): ?>
      <div class="stat-card">
        <div class="stat-icon" style="background:#f0fdf4">🏁</div>
        <div>
          <span class="stat-val"><?= $stats['completed'] ?></span>
          <span class="stat-lbl">Terminées</span>
        </div>
      </div>
    <?php else: ?>
      <div class="stat-card">
        <div class="stat-icon" style="background:#fffbeb">⭐</div>
        <div>
          <span class="stat-val"><?= number_format($stats['rating'], 1) ?></span>
          <span class="stat-lbl">Note moyenne</span>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <!-- Onglets -->
  <div class="profile-tabs" style="margin-bottom:1.75rem">
    <button class="profile-tab active" onclick="switchDashTab(this,'tabBookings')">
      <?= $role === 'client' ? 'Mes réservations' : 'Demandes reçues' ?>
    </button>
    <button class="profile-tab" onclick="switchDashTab(this,'tabReviews')">
      <?= $role === 'client' ? 'Mes avis' : 'Avis reçus' ?>
    </button>
  </div>

  <!-- Réservations -->
  <div id="tabBookings">
    <div class="card">
      <div style="padding:1.25rem 1.5rem;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
        <h2 style="font-size:1.1rem;font-weight:700;color:var(--forest)">
          <?= $role === 'client' ? 'Mes réservations' : 'Demandes de réservation' ?>
        </h2>
        <span style="font-size:.85rem;color:var(--muted)"><?= count($bookings) ?> au total</span>
      </div>
      <?php if (empty($bookings)): ?>
        <div class="empty-state">
          <div class="icon">📅</div>
          <h3>Aucune réservation</h3>
          <?php if ($role === 'client'): ?>
            <p><a href="/servilocal/index.php" style="color:var(--sage)">Trouver un prestataire →</a></p>
          <?php else: ?>
            <p>Votre profil est en ligne, les demandes arriveront bientôt !</p>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div style="overflow-x:auto">
          <table class="dash-table">
            <thead>
              <tr>
                <?php if ($role === 'client'): ?>
                  <th>Prestataire</th><th>Service</th>
                <?php else: ?>
                  <th>Client</th><th>Contact</th>
                <?php endif; ?>
                <th>Date</th><th>Heure</th><th>Adresse</th><th>Statut</th><th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($bookings as $b): ?>
                <tr>
                  <?php if ($role === 'client'): ?>
                    <td>
                      <a href="/servilocal/provider.php?id=<?= $b['provider_id'] ?>" style="color:var(--forest);font-weight:600;text-decoration:none">
                        <?= e($b['provider_name']) ?>
                      </a>
                    </td>
                    <td><span class="tag"><?= categoryName($b['category']) ?></span></td>
                  <?php else: ?>
                    <td><strong><?= e($b['client_name']) ?></strong></td>
                    <td style="font-size:.82rem">
                      <?= e($b['client_phone'] ?? '') ?><br>
                      <span style="color:var(--muted)"><?= e($b['client_email'] ?? '') ?></span>
                    </td>
                  <?php endif; ?>
                  <td><?= dateFr($b['booking_date']) ?></td>
                  <td><?= substr($b['booking_time'], 0, 5) ?></td>
                  <td style="max-width:150px;font-size:.85rem;color:var(--muted)"><?= e($b['address'] ?? '—') ?></td>
                  <td><?= statusBadge($b['status']) ?></td>
                  <td>
                    <div style="display:flex;gap:.4rem;flex-wrap:wrap">
                      <?php if ($role === 'provider' && $b['status'] === 'pending'): ?>
                        <button class="btn btn-sm" style="background:var(--green);color:white;padding:.35rem .8rem;border-radius:50px;border:none;cursor:pointer;font-size:.78rem"
                          data-action="accept" data-id="<?= $b['id'] ?>">✓ Accepter</button>
                        <button class="btn btn-sm" style="background:var(--red);color:white;padding:.35rem .8rem;border-radius:50px;border:none;cursor:pointer;font-size:.78rem"
                          data-action="refuse" data-id="<?= $b['id'] ?>">✗ Refuser</button>
                      <?php elseif ($role === 'client' && $b['status'] === 'pending'): ?>
                        <button class="btn btn-sm" style="background:var(--border);color:var(--muted);padding:.35rem .8rem;border-radius:50px;border:none;cursor:pointer;font-size:.78rem"
                          data-action="cancel" data-id="<?= $b['id'] ?>">Annuler</button>
                      <?php elseif ($role === 'client' && $b['status'] === 'completed'): ?>
                        <a href="/servilocal/reviews.php?provider=<?= $b['provider_id'] ?>&booking=<?= $b['id'] ?>"
                           class="btn btn-sm" style="background:var(--amber);color:white;padding:.35rem .8rem;border-radius:50px;font-size:.78rem;text-decoration:none">
                          ⭐ Avis
                        </a>
                      <?php else: ?>
                        <span style="font-size:.78rem;color:var(--muted)">—</span>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Avis -->
  <div id="tabReviews" style="display:none">
    <div class="card card-body">
      <h2 style="font-size:1.1rem;font-weight:700;color:var(--forest);margin-bottom:1.5rem">
        <?= $role === 'client' ? 'Mes avis publiés' : 'Avis reçus' ?>
      </h2>

      <?php if (empty($myReviews)): ?>
        <div class="empty-state" style="padding:2rem">
          <div class="icon">⭐</div>
          <h3>Aucun avis</h3>
          <?php if ($role === 'client'): ?>
            <p>Vos avis apparaîtront ici après vos réservations.</p>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <?php foreach ($myReviews as $rev): ?>
          <div class="review-card">
            <div class="review-author" style="justify-content:space-between">
              <div style="display:flex;gap:.75rem;align-items:center">
                <div class="avatar-sm" style="background:var(--forest)"><?= strtoupper(substr($role === 'client' ? $rev['provider_name'] : $rev['client_name'], 0, 1)) ?></div>
                <div>
                  <div style="font-weight:700;font-size:.92rem">
                    <?= $role === 'client' ? e($rev['provider_name']) : e($rev['client_name']) ?>
                    <?php if ($role === 'client'): ?>
                      <span style="font-size:.78rem;color:var(--sage)">· <?= categoryName($rev['category']) ?></span>
                    <?php endif; ?>
                  </div>
                  <div style="color:var(--amber);font-size:.88rem"><?= str_repeat('★', $rev['rating']) . str_repeat('☆', 5 - $rev['rating']) ?></div>
                </div>
              </div>
              <span class="review-date"><?= dateFr($rev['created_at']) ?></span>
            </div>
            <?php if ($rev['comment']): ?>
              <p class="review-text"><?= e($rev['comment']) ?></p>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

</div><!-- /.dash-wrap -->

<?php include 'includes/footer.php'; ?>
<script>
function switchDashTab(btn, tabId) {
  document.querySelectorAll('.profile-tab').forEach(t => t.classList.remove('active'));
  btn.classList.add('active');
  ['tabBookings','tabReviews'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.style.display = id === tabId ? 'block' : 'none';
  });
}
</script>
</body>
</html>
