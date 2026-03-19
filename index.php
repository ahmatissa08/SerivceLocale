<?php
// ============================================================
// index.php — Page d'accueil ServiLocal
// ============================================================
require_once 'db.php';
require_once __DIR__ . '/includes/functions.php';

// ── Paramètres de recherche (GET) ─────────────────────────
$searchName = trim($_GET['name'] ?? '');
$searchCity = trim($_GET['city'] ?? '');
$searchCat  = $_GET['cat']    ?? 'tous';
$filter     = $_GET['filter'] ?? 'tous';
$sort       = $_GET['sort']   ?? 'rating';
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = 9;

// ── Construction de la requête dynamique ──────────────────
$where  = ['1=1'];
$params = [];

if ($searchName !== '') {
    $where[]  = '(u.name LIKE ? OR p.category LIKE ?)';
    $params[] = "%{$searchName}%";
    $params[] = "%{$searchName}%";
}
if ($searchCity !== '') {
    $where[]  = 'p.city LIKE ?';
    $params[] = "%{$searchCity}%";
}
if ($searchCat !== 'tous') {
    $where[]  = 'p.category = ?';
    $params[] = $searchCat;
}
if ($filter === 'disponible') { $where[] = 'p.is_available = 1'; }
if ($filter === 'verified')   { $where[] = 'p.is_verified = 1'; }

$sortMap = [
    'rating'     => 'p.rating DESC',
    'name'       => 'u.name ASC',
    'price_asc'  => 'CAST(p.price AS UNSIGNED) ASC',
    'price_desc' => 'CAST(p.price AS UNSIGNED) DESC',
    'reviews'    => 'p.review_count DESC',
];
$orderBy = $sortMap[$sort] ?? 'p.rating DESC';

$whereStr = implode(' AND ', $where);

// Compter le total
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM providers p JOIN users u ON u.id = p.user_id WHERE {$whereStr}");
$countStmt->execute($params);
$totalProviders = (int)$countStmt->fetchColumn();
$totalPages     = ceil($totalProviders / $perPage);

// Récupérer les prestataires (paginés)
$offset = ($page - 1) * $perPage;
$stmt   = $pdo->prepare("
    SELECT p.*, u.name, u.avatar
    FROM providers p
    JOIN users u ON u.id = p.user_id
    WHERE {$whereStr}
    ORDER BY {$orderBy}
    LIMIT {$perPage} OFFSET {$offset}
");
$stmt->execute($params);
$providers = $stmt->fetchAll();

// ── Catégories avec compteurs ─────────────────────────────
$catStmt = $pdo->query('SELECT category, COUNT(*) AS cnt FROM providers GROUP BY category');
$catCounts = [];
foreach ($catStmt->fetchAll() as $row) $catCounts[$row['category']] = $row['cnt'];
$totalAll = array_sum($catCounts);

// ── Stats globales ────────────────────────────────────────
$statsStmt  = $pdo->query('SELECT (SELECT COUNT(*) FROM providers) AS p, (SELECT COUNT(*) FROM bookings) AS b, (SELECT ROUND(AVG(rating)*20) FROM providers) AS s');
$globalStats = $statsStmt->fetch();

$categories  = ['plomberie','gaz','electricite'];
$pageTitle   = 'Accueil';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EGES Technologies — Services de Proximité</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700;900&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/ServiLocal/assets/css/style.css">
</head>
<body>
<?php include 'includes/header.php'; ?>

<!-- ── HERO ──────────────────────────────────────────────── -->
<section class="hero">
  <div>
    <div class="hero-badge">✦ Réseau de confiance local</div>
    <h1>Trouvez l'expert local <em>idéal</em> en un instant.</h1>
    <p>Réservez des services de qualité autour de vous. Plombiers,electriciens — tous vérifiés.</p>
    <div class="hero-actions">
      <a href="#search" class="btn btn-primary">🔍 Explorer les services</a>
      <a href="/servilocal/register.php" class="btn btn-outline">✦ Devenir prestataire</a>
    </div>
    <div class="hero-stats">
      <div><span class="hero-stat-num"><?= $globalStats['p'] ?>+</span><span class="hero-stat-lbl">Prestataires</span></div>
      <div><span class="hero-stat-num"><?= $globalStats['b'] ?>+</span><span class="hero-stat-lbl">Réservations</span></div>
      <div><span class="hero-stat-num"><?= $globalStats['s'] ?>%</span><span class="hero-stat-lbl">Satisfaction</span></div>
    </div>
  </div>
  <div class="hero-visual">🏠</div>
</section>

<!-- ── SEARCH ─────────────────────────────────────────────── -->
<div id="search" style="max-width:1280px;margin:0 auto;padding:0 2rem 2rem">
  <form method="GET" id="searchForm">
    <div class="search-wrap">
      <div style="font-size:.82rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.08em;margin-bottom:1rem">Rechercher un service</div>
      <div class="search-bar">
        <div class="search-input-wrap">
          <span class="ico">🔍</span>
          <input type="text" id="searchName" name="name" class="form-control" placeholder="Nom ou service…" value="<?= e($searchName) ?>">
        </div>
        <div class="search-input-wrap">
          <span class="ico">📍</span>
          <input type="text" name="city" class="form-control" placeholder="Ville…" value="<?= e($searchCity) ?>">
        </div>
        <div class="search-input-wrap">
          <span class="ico">📋</span>
          <select name="cat" class="form-control" style="padding-left:2.4rem">
            <option value="tous" <?= $searchCat === 'tous' ? 'selected' : '' ?>>Toutes les catégories</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= $cat ?>" <?= $searchCat === $cat ? 'selected' : '' ?>><?= categoryName($cat) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="search-btn">Rechercher →</button>
      </div>
      <div class="filter-tags">
        <a href="?filter=tous&cat=<?= e($searchCat) ?>&name=<?= urlencode($searchName) ?>" class="filter-tag <?= $filter === 'tous' ? 'active' : '' ?>">Tous</a>
        <a href="?filter=disponible&cat=<?= e($searchCat) ?>&name=<?= urlencode($searchName) ?>" class="filter-tag <?= $filter === 'disponible' ? 'active' : '' ?>">🟢 Disponibles</a>
        <a href="?filter=verified&cat=<?= e($searchCat) ?>&name=<?= urlencode($searchName) ?>" class="filter-tag <?= $filter === 'verified' ? 'active' : '' ?>">✓ Vérifiés</a>
      </div>
    </div>
  </form>
</div>

<!-- ── CATEGORIES ─────────────────────────────────────────── -->
<div class="section" id="categories" style="padding-top:0">
  <div class="section-header">
    <h2 class="section-title">Explorer par catégorie</h2>
    <a href="?" class="section-link">Voir tout →</a>
  </div>
  <div class="cat-grid">
    <a href="?" class="cat-card <?= $searchCat === 'tous' ? 'active-cat' : '' ?>" data-cat="tous">
      <span class="cat-icon">✦</span>
      <div class="cat-name">Tous</div>
      <div class="cat-count"><?= $totalAll ?> pros</div>
    </a>
    <?php foreach ($categories as $cat): ?>
      <a href="?cat=<?= $cat ?>" class="cat-card <?= $searchCat === $cat ? 'active-cat' : '' ?>" data-cat="<?= $cat ?>">
        <span class="cat-icon"><?= categoryIcon($cat) ?></span>
        <div class="cat-name"><?= categoryName($cat) ?></div>
        <div class="cat-count"><?= $catCounts[$cat] ?? 0 ?> pro<?= ($catCounts[$cat] ?? 0) > 1 ? 's' : '' ?></div>
      </a>
    <?php endforeach; ?>
  </div>
</div>

<!-- ── PROVIDERS ──────────────────────────────────────────── -->
<div class="section" id="providers" style="padding-top:0">
  <div class="section-header">
    <h2 class="section-title">Prestataires disponibles</h2>
  </div>

  <div class="sort-bar">
    <div class="results-count">
      <strong><?= $totalProviders ?></strong> prestataire<?= $totalProviders > 1 ? 's' : '' ?> trouvé<?= $totalProviders > 1 ? 's' : '' ?>
      <?php if ($searchName || $searchCity || $searchCat !== 'tous'): ?>
        <a href="?" style="color:var(--sage);font-size:.82rem;text-decoration:none;margin-left:.5rem">✕ Effacer les filtres</a>
      <?php endif; ?>
    </div>
    <form method="GET" style="display:flex;align-items:center;gap:.5rem;font-size:.85rem">
      <?php foreach (['name' => $searchName, 'city' => $searchCity, 'cat' => $searchCat, 'filter' => $filter] as $k => $v): ?>
        <input type="hidden" name="<?= $k ?>" value="<?= e($v) ?>">
      <?php endforeach; ?>
      <label for="sort" style="color:var(--muted)">Trier par :</label>
      <select id="sort" name="sort" class="form-control" style="width:auto;padding:.4rem 1.5rem .4rem .6rem;font-size:.85rem" onchange="this.form.submit()">
        <option value="rating"     <?= $sort === 'rating'     ? 'selected' : '' ?>>Note</option>
        <option value="name"       <?= $sort === 'name'       ? 'selected' : '' ?>>Nom</option>
        <option value="reviews"    <?= $sort === 'reviews'    ? 'selected' : '' ?>>Nombre d'avis</option>
        <option value="price_asc"  <?= $sort === 'price_asc'  ? 'selected' : '' ?>>Prix ↑</option>
        <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Prix ↓</option>
      </select>
    </form>
  </div>

  <?php if (empty($providers)): ?>
    <div class="empty-state">
      <div class="icon">🔍</div>
      <h3>Aucun prestataire trouvé</h3>
      <p>Essayez d'autres mots-clés ou <a href="?" style="color:var(--sage)">effacez les filtres</a>.</p>
    </div>
  <?php else: ?>
    <div class="provider-grid">
      <?php foreach ($providers as $p): ?>
        <?php
          $bannerBg = 'rgba(' . implode(',', sscanf(ltrim($p['avatar_color'], '#'), "%02x%02x%02x")) . ',.1)';
          $stars    = str_repeat('★', (int)floor($p['rating'])) . str_repeat('☆', 5 - (int)floor($p['rating']));
        ?>
        <div class="provider-card">
          <div class="card-banner" style="background:<?= $bannerBg ?>">
            <span style="position:absolute;left:1.25rem;top:50%;transform:translateY(-50%);font-size:2.5rem;opacity:.35"><?= categoryIcon($p['category']) ?></span>
            <?php if ($p['rating'] >= 4.8): ?>
              <span class="card-badge top">⭐ Top Prestataire</span>
            <?php elseif (!$p['is_verified']): ?>
              <span class="card-badge">🆕 Nouveau</span>
            <?php endif; ?>
          </div>
          <div class="pcard-body">
            <div class="pcard-header">
              <div style="position:relative">
                <div class="avatar-lg" style="background:<?= e($p['avatar_color']) ?>">
                  <?php if ($p['avatar']): ?>
                    <img src="/servilocal/uploads/profiles/<?= e($p['avatar']) ?>" style="width:100%;height:100%;border-radius:50%;object-fit:cover">
                  <?php else: ?>
                    <?= strtoupper(substr($p['name'], 0, 1)) ?>
                  <?php endif; ?>
                </div>
                <?php if ($p['is_verified']): ?>
                  <div class="verified-badge" title="Prestataire vérifié">✓</div>
                <?php endif; ?>
              </div>
              <div class="card-info" style="padding-top:2rem">
                <div class="pcard-name"><?= e($p['name']) ?></div>
                <div class="pcard-cat"><?= categoryIcon($p['category']) ?> <?= categoryName($p['category']) ?></div>
                <div class="pcard-location">
                  📍 <?= e($p['city']) ?> ·
                  <?= $p['is_available']
                    ? '<span style="color:var(--green)">● Disponible</span>'
                    : '<span style="color:var(--red)">● Indisponible</span>' ?>
                </div>
              </div>
            </div>
            <p class="pcard-desc"><?= e($p['description'] ?? '') ?></p>
            <div class="pcard-meta">
              <div class="meta-item">
                <span style="color:var(--amber)"><?= $stars ?></span>
                <strong><?= number_format($p['rating'], 1) ?></strong>
                <span style="color:var(--muted)">(<?= $p['review_count'] ?> avis)</span>
              </div>
              <div class="meta-item">
                <span style="color:var(--sage);font-weight:600">💰 <?= e($p['price']) ?></span>
              </div>
            </div>
            <div class="pcard-footer">
              <a href="/servilocal/booking.php?provider=<?= $p['id'] ?>" class="btn-book">📅 Réserver</a>
              <a href="/servilocal/provider.php?id=<?= $p['id'] ?>" class="btn-icon" title="Voir le profil">👁</a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
      <div style="display:flex;justify-content:center;gap:.5rem;margin-top:2.5rem;flex-wrap:wrap">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
          <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
             style="width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.9rem;font-weight:600;text-decoration:none;
             <?= $i === $page ? 'background:var(--forest);color:white;' : 'background:white;color:var(--text);border:1.5px solid var(--border);' ?>">
            <?= $i ?>
          </a>
        <?php endfor; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<!-- ── HOW IT WORKS ────────────────────────────────────────── -->
<div class="how-section">
  <div class="how-inner">
    <h2 class="how-title">Comment ça fonctionne ?</h2>
    <p class="how-sub">En 4 étapes simples, trouvez et réservez le service dont vous avez besoin</p>
    <div class="steps-grid">
      <div class="step"><span class="step-icon">🔍</span><div class="step-num">1</div><h3>Recherchez</h3><p>Entrez le service et votre ville.</p></div>
      <div class="step"><span class="step-icon">👤</span><div class="step-num">2</div><h3>Choisissez</h3><p>Comparez les avis et tarifs.</p></div>
      <div class="step"><span class="step-icon">📅</span><div class="step-num">3</div><h3>Réservez</h3><p>Choisissez votre créneau horaire.</p></div>
      <div class="step"><span class="step-icon">⭐</span><div class="step-num">4</div><h3>Évaluez</h3><p>Laissez un avis pour la communauté.</p></div>
    </div>
  </div>
</div>

<!-- ── AVIS RÉCENTS ────────────────────────────────────────── -->
<?php
$revStmt = $pdo->query('
    SELECT r.rating, r.comment, r.created_at, u.name AS client_name, pu.name AS provider_name, p.category
    FROM reviews r
    JOIN users u ON u.id = r.client_id
    JOIN providers p ON p.id = r.provider_id
    JOIN users pu ON pu.id = p.user_id
    ORDER BY r.created_at DESC LIMIT 6
');
$latestReviews = $revStmt->fetchAll();
?>
<?php if (!empty($latestReviews)): ?>
<div class="section">
  <div class="section-header">
    <h2 class="section-title">Ce que disent nos clients</h2>
  </div>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1.5rem">
    <?php foreach ($latestReviews as $rev): ?>
      <div class="review-card" style="background:white">
        <div class="testi-stars" style="color:var(--amber);font-size:1rem;margin-bottom:.5rem"><?= str_repeat('★', $rev['rating']) . str_repeat('☆', 5 - $rev['rating']) ?></div>
        <div style="font-family:'Playfair Display',serif;font-size:2.5rem;line-height:1;color:var(--lime);margin-bottom:-.3rem">"</div>
        <p style="font-size:.9rem;color:var(--muted);line-height:1.7;margin-bottom:1rem;font-style:italic"><?= e(mb_substr($rev['comment'], 0, 160)) ?><?= mb_strlen($rev['comment']) > 160 ? '…' : '' ?></p>
        <div class="review-author">
          <div class="avatar-sm" style="background:var(--forest)"><?= strtoupper(substr($rev['client_name'], 0, 1)) ?></div>
          <div>
            <div style="font-weight:700;font-size:.88rem"><?= e($rev['client_name']) ?></div>
            <div style="font-size:.75rem;color:var(--muted)">A évalué <?= e($rev['provider_name']) ?> · <?= categoryName($rev['category']) ?></div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- ── CTA ────────────────────────────────────────────────── -->
<div class="cta-section">
  <div class="cta-inner">
    <div>
      <h2 class="cta-title">Vous êtes un professionnel ?<br>Rejoignez notre réseau !</h2>
      <p class="cta-sub">Développez votre clientèle, gérez vos réservations en ligne.</p>
    </div>
    <a href="/servilocal/register.php" class="btn btn-secondary" style="white-space:nowrap;padding:1rem 2.5rem">
      Créer mon profil gratuit →
    </a>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
</body>
</html>
