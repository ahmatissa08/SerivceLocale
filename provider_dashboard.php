<?php
// ============================================================
// provider_dashboard.php — Tableau de bord prestataire complet
// Accès : rôle provider uniquement
// ============================================================
require_once 'db.php';
require_once 'includes/functions.php';
requireLogin();

// ── Vérification du rôle ─────────────────────────────────
if (userRole() !== 'provider') {
    header('Location: /servilocal/dashboard.php');
    exit;
}

$userId = $_SESSION['user_id'];

// ── Actions rapides sur les réservations ─────────────────
if (isset($_GET['action']) && isset($_GET['id'])) {
    verifyCsrf(); // protection via token dans l'URL n'est pas idéal — voir note bas
    $action    = $_GET['action'];
    $bookingId = (int)$_GET['id'];
    $allowed   = ['accept' => 'accepted', 'refuse' => 'refused', 'complete' => 'completed'];

    if (array_key_exists($action, $allowed)) {
        $stmt = $pdo->prepare(
            'UPDATE bookings SET status = ?
             WHERE id = ? AND provider_id = (SELECT id FROM providers WHERE user_id = ?)'
        );
        $stmt->execute([$allowed[$action], $bookingId, $userId]);
    }
    header('Location: /servilocal/provider_dashboard.php?toast=' . urlencode('✅ Réservation mise à jour.'));
    exit;
}

// ── Charger le profil prestataire ────────────────────────
$stmt = $pdo->prepare(
    'SELECT p.*, u.name, u.email, u.phone, u.avatar, u.created_at AS member_since
     FROM providers p
     JOIN users u ON u.id = p.user_id
     WHERE p.user_id = ?'
);
$stmt->execute([$userId]);
$provider = $stmt->fetch();

if (!$provider) {
    header('Location: /servilocal/profile.php?toast=' . urlencode('⚠️ Complétez votre profil prestataire.'));
    exit;
}

$provId = $provider['id'];

// ── Réservations (toutes, avec infos client) ─────────────
$statusFilter = $_GET['status'] ?? '';
$search       = trim($_GET['q'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 8;

$whereExtra = ''; $params = [$provId];
if ($statusFilter && in_array($statusFilter, ['pending','accepted','refused','completed','cancelled'])) {
    $whereExtra .= ' AND b.status = ?'; $params[] = $statusFilter;
}
if ($search) {
    $whereExtra .= ' AND (u.name LIKE ? OR b.address LIKE ?)';
    $params[] = "%$search%"; $params[] = "%$search%";
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM bookings b JOIN users u ON u.id=b.client_id WHERE b.provider_id=? $whereExtra");
$countStmt->execute($params);
$totalBookings = (int)$countStmt->fetchColumn();
$totalPages    = ceil($totalBookings / $perPage);

$params[] = $perPage;
$params[] = ($page - 1) * $perPage;
$stmt = $pdo->prepare(
    "SELECT b.*, u.name AS client_name, u.phone AS client_phone, u.email AS client_email, u.avatar AS client_avatar
     FROM bookings b
     JOIN users u ON u.id = b.client_id
     WHERE b.provider_id = ? $whereExtra
     ORDER BY
       CASE b.status WHEN 'pending' THEN 0 WHEN 'accepted' THEN 1 ELSE 2 END,
       b.booking_date ASC
     LIMIT ? OFFSET ?"
);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

// ── Avis reçus ───────────────────────────────────────────
$stmt = $pdo->prepare(
    'SELECT r.*, u.name AS client_name, u.avatar AS client_avatar
     FROM reviews r
     JOIN users u ON u.id = r.client_id
     WHERE r.provider_id = ?
     ORDER BY r.created_at DESC
     LIMIT 10'
);
$stmt->execute([$provId]);
$reviews = $stmt->fetchAll();

// Distribution des notes
$stmt = $pdo->prepare('SELECT rating, COUNT(*) AS cnt FROM reviews WHERE provider_id = ? GROUP BY rating ORDER BY rating DESC');
$stmt->execute([$provId]);
$ratingDist = [];
foreach ($stmt->fetchAll() as $row) $ratingDist[$row['rating']] = (int)$row['cnt'];

// ── Stats globales ────────────────────────────────────────
$stmt = $pdo->prepare('SELECT status, COUNT(*) AS cnt FROM bookings WHERE provider_id = ? GROUP BY status');
$stmt->execute([$provId]);
$statusCounts = [];
foreach ($stmt->fetchAll() as $row) $statusCounts[$row['status']] = (int)$row['cnt'];

$totalAll       = array_sum($statusCounts);
$totalPending   = $statusCounts['pending']   ?? 0;
$totalAccepted  = $statusCounts['accepted']  ?? 0;
$totalCompleted = $statusCounts['completed'] ?? 0;
$totalRevenue   = $totalCompleted * 150; // estimation

// Activité des 7 derniers jours
$stmt = $pdo->prepare(
    "SELECT DATE(created_at) AS d, COUNT(*) AS cnt
     FROM bookings WHERE provider_id = ?
     AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
     GROUP BY DATE(created_at) ORDER BY d"
);
$stmt->execute([$provId]);
$weekActivity = [];
for ($i = 6; $i >= 0; $i--) $weekActivity[date('Y-m-d', strtotime("-$i days"))] = 0;
foreach ($stmt->fetchAll() as $row) $weekActivity[$row['d']] = (int)$row['cnt'];

// ── Helpers ──────────────────────────────────────────────
function statusLabel(string $s): array {
    return match($s) {
        'pending'   => ['🕐 En attente', 'badge-pending'],
        'accepted'  => ['✅ Acceptée',   'badge-accepted'],
        'refused'   => ['❌ Refusée',    'badge-refused'],
        'completed' => ['🏁 Terminée',   'badge-completed'],
        'cancelled' => ['🚫 Annulée',    'badge-cancelled'],
        default     => ['—', 'badge-muted'],
    };
}

function avatarInitial(string $name): string {
    return strtoupper(mb_substr($name, 0, 1));
}

function categoryIcon(string $cat): string {
    return match($cat) {
        'plomberie'    => '🔧',
        'electricite'  => '⚡',
        'informatique' => '💻',
        'coiffure'     => '💇',
        'jardinage'    => '🌿',
        'menage'       => '🏠',
        'transport'    => '🚗',
        'peinture'     => '🎨',
        default        => '✦',
    };
}

// Toast depuis redirect
$toast = '';
if (isset($_GET['toast'])) $toast = urldecode($_GET['toast']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mon Tableau de Bord — ServiLocal</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
/* ============================================================
   PROVIDER DASHBOARD — Dark + nature theme
   Esthétique : slate dark / accent vert / données-forward
   ============================================================ */
:root {
  --bg:        #0E1117;
  --bg2:       #161B22;
  --bg3:       #1C2230;
  --border:    #21262D;
  --border2:   #30363D;
  --text:      #E6EDF3;
  --muted:     #8B949E;
  --accent:    #4ADE80;
  --accent2:   #22C55E;
  --teal:      #06B6D4;
  --amber:     #F59E0B;
  --red:       #F87171;
  --purple:    #A78BFA;
  --sidebar-w: 256px;
  --header-h:  60px;
  --radius:    12px;
  --radius-sm: 8px;
  --shadow:    0 4px 20px rgba(0,0,0,.35);
}

*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
html { scroll-behavior:smooth; }
body {
  font-family:'DM Sans',sans-serif;
  background:var(--bg); color:var(--text);
  min-height:100vh; display:flex;
  font-size:14px; line-height:1.5;
}
::-webkit-scrollbar { width:5px; }
::-webkit-scrollbar-track { background:var(--bg2); }
::-webkit-scrollbar-thumb { background:var(--border2); border-radius:3px; }
a { text-decoration:none; color:inherit; }
img { max-width:100%; }

/* ══════════════════════════════
   SIDEBAR
   ══════════════════════════════ */
.sidebar {
  width:var(--sidebar-w); background:var(--bg2);
  border-right:1px solid var(--border);
  position:fixed; top:0; left:0; height:100vh;
  display:flex; flex-direction:column;
  z-index:100; overflow-y:auto;
  transition:transform .3s;
}

.sidebar-logo {
  padding:1.25rem 1.25rem 1rem;
  border-bottom:1px solid var(--border);
  display:flex; align-items:center; gap:.65rem;
}
.logo-icon {
  width:36px; height:36px; border-radius:10px;
  background:var(--accent); display:flex;
  align-items:center; justify-content:center;
  font-family:'Syne',sans-serif; font-size:1rem;
  font-weight:800; color:#000; flex-shrink:0;
}
.logo-text {
  font-family:'Syne',sans-serif; font-size:1.1rem;
  font-weight:800; color:var(--text);
}
.logo-text span { color:var(--accent); }

/* Status badge */
.sidebar-status {
  margin:.75rem 1.25rem;
  display:flex; align-items:center; gap:.6rem;
  background:var(--bg3); border:1px solid var(--border);
  border-radius:var(--radius-sm); padding:.6rem .8rem;
}
.status-dot {
  width:8px; height:8px; border-radius:50%;
  flex-shrink:0; animation:pulse 2s infinite;
}
.status-dot.online  { background:var(--accent); }
.status-dot.offline { background:var(--muted); animation:none; }
@keyframes pulse {
  0%,100%{opacity:1;transform:scale(1)}
  50%{opacity:.6;transform:scale(1.2)}
}
.status-text { font-size:.78rem; }
.status-text strong { color:var(--text); display:block; font-size:.8rem; }
.status-text span { color:var(--muted); }

/* Nav */
.nav-section { padding:.6rem .75rem; }
.nav-label {
  font-size:.68rem; color:var(--muted);
  text-transform:uppercase; letter-spacing:.1em;
  font-weight:600; padding:.4rem .5rem .2rem;
}
.nav-item {
  display:flex; align-items:center; gap:.7rem;
  padding:.58rem .75rem; border-radius:var(--radius-sm);
  color:var(--muted); font-size:.88rem; font-weight:500;
  cursor:pointer; transition:all .2s; margin-bottom:2px;
}
.nav-item:hover { background:var(--bg3); color:var(--text); }
.nav-item.active {
  background:rgba(74,222,128,.1);
  color:var(--accent);
  border:1px solid rgba(74,222,128,.18);
}
.nav-icon { width:18px; text-align:center; font-size:.95rem; flex-shrink:0; }
.nav-badge {
  margin-left:auto; background:var(--red);
  color:white; font-size:.65rem; font-weight:700;
  padding:.12rem .48rem; border-radius:50px;
  min-width:18px; text-align:center;
}
.nav-badge.green { background:var(--accent); color:#000; }

/* Sidebar footer */
.sidebar-footer {
  margin-top:auto; padding:1rem 1.25rem;
  border-top:1px solid var(--border);
}
.prov-mini {
  display:flex; align-items:center; gap:.7rem;
  padding:.6rem; border-radius:var(--radius-sm);
  border:1px solid var(--border); background:var(--bg3);
  margin-bottom:.75rem;
}
.prov-av {
  width:34px; height:34px; border-radius:50%;
  display:flex; align-items:center; justify-content:center;
  font-weight:700; font-size:.9rem; flex-shrink:0;
  color:#000;
}
.prov-name { font-size:.83rem; font-weight:600; color:var(--text); }
.prov-cat  { font-size:.72rem; color:var(--muted); }
.sidebar-links { display:flex; gap:.5rem; }
.sidebar-link {
  flex:1; padding:.42rem; border-radius:var(--radius-sm);
  border:1px solid var(--border); background:transparent;
  color:var(--muted); font-size:.75rem; font-weight:500;
  cursor:pointer; text-align:center; transition:all .2s;
  font-family:'DM Sans',sans-serif;
}
.sidebar-link:hover { background:var(--bg3); color:var(--text); }
.sidebar-link.danger:hover { border-color:rgba(248,113,113,.3); color:var(--red); }

/* ══════════════════════════════
   MAIN
   ══════════════════════════════ */
.main {
  margin-left:var(--sidebar-w); flex:1;
  display:flex; flex-direction:column; min-height:100vh;
}

/* Topbar */
.topbar {
  height:var(--header-h); background:var(--bg2);
  border-bottom:1px solid var(--border);
  display:flex; align-items:center; justify-content:space-between;
  padding:0 1.75rem; position:sticky; top:0; z-index:90; gap:1rem;
}
.topbar-left { display:flex; align-items:center; gap:.75rem; }
.topbar-greeting { font-family:'Syne',sans-serif; font-size:1rem; font-weight:700; }
.topbar-date { font-size:.78rem; color:var(--muted); }
.topbar-right { display:flex; align-items:center; gap:.6rem; }
.topbar-btn {
  background:var(--bg3); border:1px solid var(--border);
  color:var(--muted); padding:.42rem .9rem; border-radius:var(--radius-sm);
  font-family:'DM Sans',sans-serif; font-size:.8rem; font-weight:500;
  cursor:pointer; transition:all .2s; display:flex; align-items:center; gap:.4rem;
  text-decoration:none;
}
.topbar-btn:hover { border-color:var(--border2); color:var(--text); }
.topbar-btn.primary {
  background:var(--accent); color:#000;
  border-color:var(--accent); font-weight:600;
}
.topbar-btn.primary:hover { background:var(--accent2); }

/* Burger (mobile) */
.burger-btn {
  display:none; background:none; border:none;
  color:var(--text); font-size:1.25rem; cursor:pointer; padding:0;
}

/* Content */
.content { padding:1.75rem; flex:1; }

/* ══════════════════════════════
   STAT CARDS
   ══════════════════════════════ */
.stat-grid {
  display:grid; grid-template-columns:repeat(auto-fill,minmax(190px,1fr));
  gap:1rem; margin-bottom:1.75rem;
}
.stat-card {
  background:var(--bg2); border:1px solid var(--border);
  border-radius:var(--radius); padding:1.25rem 1.4rem;
  position:relative; overflow:hidden; transition:border-color .2s;
  animation:fadeUp .4s ease both;
}
.stat-card:nth-child(2){animation-delay:.05s}
.stat-card:nth-child(3){animation-delay:.1s}
.stat-card:nth-child(4){animation-delay:.15s}
.stat-card:hover { border-color:var(--border2); }
.stat-card::before {
  content:''; position:absolute; top:0; left:0; right:0; height:2px;
  background:var(--stat-accent, var(--accent));
}
@keyframes fadeUp {
  from{opacity:0;transform:translateY(16px)}
  to{opacity:1;transform:translateY(0)}
}
.stat-icon { font-size:1.6rem; margin-bottom:.6rem; display:block; }
.stat-val {
  font-family:'Syne',sans-serif; font-size:1.9rem;
  font-weight:800; color:var(--text); display:block; line-height:1;
}
.stat-lbl { font-size:.73rem; color:var(--muted); text-transform:uppercase; letter-spacing:.06em; margin-top:.3rem; }
.stat-trend {
  position:absolute; top:.9rem; right:.9rem;
  font-size:.7rem; font-weight:600; padding:.18rem .5rem; border-radius:50px;
}
.trend-up   { background:rgba(74,222,128,.1);  color:var(--accent); }
.trend-warn { background:rgba(245,158,11,.1);  color:var(--amber); }

/* ══════════════════════════════
   GRID LAYOUT
   ══════════════════════════════ */
.grid-main { display:grid; grid-template-columns:1fr 360px; gap:1.25rem; }
.grid-full  { grid-column:1/-1; }

/* ══════════════════════════════
   CARDS
   ══════════════════════════════ */
.card {
  background:var(--bg2); border:1px solid var(--border);
  border-radius:var(--radius); overflow:hidden; margin-bottom:1.25rem;
}
.card-header {
  padding:1rem 1.4rem; border-bottom:1px solid var(--border);
  display:flex; align-items:center; justify-content:space-between;
  gap:1rem; flex-wrap:wrap;
}
.card-title {
  font-family:'Syne',sans-serif; font-size:.95rem;
  font-weight:700; color:var(--text);
  display:flex; align-items:center; gap:.5rem;
}
.card-body { padding:1.4rem; }

/* ══════════════════════════════
   FILTRES & RECHERCHE
   ══════════════════════════════ */
.filter-bar {
  display:flex; align-items:center; gap:.6rem;
  flex-wrap:wrap; padding:.9rem 1.4rem;
  border-bottom:1px solid var(--border);
  background:var(--bg3);
}
.search-input-wrap { position:relative; flex:1; min-width:180px; }
.search-ico {
  position:absolute; left:.7rem; top:50%;
  transform:translateY(-50%); color:var(--muted);
  font-size:.85rem; pointer-events:none;
}
.search-input {
  width:100%; background:var(--bg2); border:1px solid var(--border);
  border-radius:var(--radius-sm); padding:.5rem .8rem .5rem 2.2rem;
  color:var(--text); font-family:'DM Sans',sans-serif; font-size:.85rem;
  transition:border-color .2s; outline:none;
}
.search-input:focus { border-color:var(--accent); }
.filter-select {
  background:var(--bg2); border:1px solid var(--border);
  border-radius:var(--radius-sm); padding:.5rem .8rem;
  color:var(--text); font-family:'DM Sans',sans-serif; font-size:.82rem;
  cursor:pointer; outline:none;
}
.filter-chip {
  padding:.32rem .8rem; border-radius:50px; font-size:.76rem;
  font-weight:500; border:1px solid var(--border);
  background:transparent; color:var(--muted); cursor:pointer;
  transition:all .2s; text-decoration:none;
}
.filter-chip:hover, .filter-chip.active-chip {
  background:var(--accent); color:#000; border-color:var(--accent);
}

/* ══════════════════════════════
   TABLE
   ══════════════════════════════ */
.table-wrap { overflow-x:auto; }
table { width:100%; border-collapse:collapse; font-size:.84rem; }
th {
  background:var(--bg3); padding:.7rem 1rem;
  text-align:left; font-size:.7rem; text-transform:uppercase;
  letter-spacing:.07em; color:var(--muted);
  border-bottom:1px solid var(--border); white-space:nowrap;
  font-weight:600;
}
td { padding:.78rem 1rem; border-bottom:1px solid var(--border); vertical-align:middle; }
tr:last-child td { border-bottom:none; }
tr:hover td { background:rgba(255,255,255,.015); }

/* ══════════════════════════════
   BADGES STATUT
   ══════════════════════════════ */
.badge {
  display:inline-flex; align-items:center; gap:.3rem;
  padding:.2rem .65rem; border-radius:50px;
  font-size:.72rem; font-weight:700;
}
.badge-pending   { background:rgba(245,158,11,.12);  color:var(--amber);  border:1px solid rgba(245,158,11,.25); }
.badge-accepted  { background:rgba(6,182,212,.12);   color:var(--teal);   border:1px solid rgba(6,182,212,.25); }
.badge-completed { background:rgba(74,222,128,.12);  color:var(--accent); border:1px solid rgba(74,222,128,.25); }
.badge-refused   { background:rgba(248,113,113,.12); color:var(--red);    border:1px solid rgba(248,113,113,.25); }
.badge-cancelled { background:rgba(139,148,158,.1);  color:var(--muted);  border:1px solid rgba(139,148,158,.2); }

/* ══════════════════════════════
   BOUTONS D'ACTION TABLE
   ══════════════════════════════ */
.btn-action {
  padding:.28rem .65rem; border-radius:6px; border:1px solid;
  font-family:'DM Sans',sans-serif; font-size:.73rem; font-weight:600;
  cursor:pointer; transition:all .2s; display:inline-flex;
  align-items:center; gap:.25rem; text-decoration:none;
}
.btn-accept  { border-color:rgba(74,222,128,.3);  color:var(--accent); background:rgba(74,222,128,.06); }
.btn-accept:hover  { background:rgba(74,222,128,.16); }
.btn-refuse  { border-color:rgba(248,113,113,.3); color:var(--red);    background:rgba(248,113,113,.06); }
.btn-refuse:hover  { background:rgba(248,113,113,.16); }
.btn-complete { border-color:rgba(6,182,212,.3);  color:var(--teal);   background:rgba(6,182,212,.06); }
.btn-complete:hover { background:rgba(6,182,212,.16); }
.btn-muted   { border-color:var(--border); color:var(--muted); background:transparent; }
.btn-muted:hover   { color:var(--text); background:var(--bg3); }

/* ══════════════════════════════
   AVATAR
   ══════════════════════════════ */
.av {
  width:32px; height:32px; border-radius:50%;
  display:flex; align-items:center; justify-content:center;
  font-weight:700; font-size:.82rem; flex-shrink:0;
  color:#000;
}
.av-provider {
  width:40px; height:40px; font-size:1rem;
}

/* ══════════════════════════════
   AVIS
   ══════════════════════════════ */
.review-item {
  padding:1rem; border-radius:var(--radius-sm);
  border:1px solid var(--border); background:var(--bg3);
  margin-bottom:.75rem; transition:border-color .2s;
}
.review-item:hover { border-color:var(--border2); }
.review-item:last-child { margin-bottom:0; }
.review-header { display:flex; align-items:center; gap:.75rem; margin-bottom:.5rem; }
.review-stars { color:var(--amber); font-size:.85rem; letter-spacing:1px; }
.review-text { font-size:.86rem; color:var(--muted); line-height:1.6; }
.review-date { font-size:.72rem; color:var(--muted); margin-left:auto; }

/* Rating bar */
.rating-bars { display:flex; flex-direction:column; gap:.4rem; margin-top:.75rem; }
.rating-bar-row { display:flex; align-items:center; gap:.6rem; font-size:.78rem; }
.rb-label { min-width:12px; color:var(--muted); }
.rb-star  { color:var(--amber); }
.rb-track { flex:1; background:var(--border); border-radius:4px; height:5px; overflow:hidden; }
.rb-fill  { height:100%; border-radius:4px; background:var(--amber); }
.rb-pct   { min-width:28px; text-align:right; color:var(--muted); }

/* ══════════════════════════════
   MINI CHART (barres CSS)
   ══════════════════════════════ */
.chart-bars {
  display:flex; align-items:flex-end; gap:4px;
  height:52px; margin-top:.6rem;
}
.chart-col { flex:1; display:flex; flex-direction:column; align-items:center; gap:3px; }
.chart-bar {
  width:100%; border-radius:3px 3px 0 0;
  background:rgba(74,222,128,.3); min-height:2px;
  transition:background .2s;
}
.chart-bar:hover { background:var(--accent); }
.chart-bar.today { background:var(--accent); }
.chart-lbl { font-size:.6rem; color:var(--muted); }

/* ══════════════════════════════
   EMPTY STATE
   ══════════════════════════════ */
.empty-state {
  text-align:center; padding:3rem 2rem; color:var(--muted);
}
.empty-icon { font-size:3rem; margin-bottom:.75rem; display:block; opacity:.5; }
.empty-title { font-size:.95rem; font-weight:600; color:var(--text); margin-bottom:.4rem; }
.empty-sub  { font-size:.85rem; }

/* ══════════════════════════════
   PAGINATION
   ══════════════════════════════ */
.pagination {
  display:flex; align-items:center; justify-content:space-between;
  padding:.9rem 1.4rem; border-top:1px solid var(--border);
  font-size:.82rem; color:var(--muted); flex-wrap:wrap; gap:.5rem;
}
.page-btns { display:flex; gap:.35rem; }
.page-btn {
  width:30px; height:30px; border-radius:6px;
  background:var(--bg3); border:1px solid var(--border);
  color:var(--muted); font-size:.82rem; font-weight:500;
  cursor:pointer; display:flex; align-items:center; justify-content:center;
  transition:all .2s; text-decoration:none;
}
.page-btn:hover, .page-btn.active { background:var(--accent); color:#000; border-color:var(--accent); }

/* ══════════════════════════════
   TOAST
   ══════════════════════════════ */
.toast {
  position:fixed; bottom:1.5rem; right:1.5rem;
  background:var(--bg2); color:var(--text);
  border:1px solid var(--border);
  padding:.85rem 1.25rem; border-radius:var(--radius-sm);
  box-shadow:var(--shadow); z-index:9999;
  font-size:.88rem; font-weight:500;
  transform:translateY(80px); opacity:0;
  transition:all .3s cubic-bezier(.34,1.56,.64,1);
  display:flex; align-items:center; gap:.5rem; max-width:320px;
}
.toast.show { transform:translateY(0); opacity:1; }
.toast-accent { width:3px; height:100%; background:var(--accent); border-radius:2px; flex-shrink:0; align-self:stretch; }

/* ══════════════════════════════
   RESPONSIVE
   ══════════════════════════════ */
@media(max-width:1100px) { .grid-main { grid-template-columns:1fr; } }
@media(max-width:900px) {
  .sidebar { transform:translateX(-100%); }
  .sidebar.open { transform:translateX(0); }
  .main { margin-left:0; }
  .burger-btn { display:block; }
  .stat-grid { grid-template-columns:repeat(2,1fr); }
}
@media(max-width:540px) {
  .stat-grid { grid-template-columns:1fr 1fr; }
  .content { padding:1rem; }
  .topbar { padding:0 1rem; }
}
</style>
</head>
<body>

<!-- ══════════════════════════════════════════════════════════
     SIDEBAR
     ══════════════════════════════════════════════════════════ -->
<aside class="sidebar" id="sidebar">

  <div class="sidebar-logo">
    <div class="logo-icon">SL</div>
    <div>
      <div class="logo-text">Servi<span>•</span>Local</div>
    </div>
  </div>

  <!-- Statut disponibilité -->
  <div class="sidebar-status">
    <div class="status-dot <?= $provider['is_available'] ? 'online' : 'offline' ?>"></div>
    <div class="status-text">
      <strong><?= $provider['is_available'] ? 'Disponible' : 'Indisponible' ?></strong>
      <span><?= $provider['is_available'] ? 'Vous acceptez des demandes' : 'Demandes suspendues' ?></span>
    </div>
  </div>

  <nav class="nav-section">
    <div class="nav-label">Navigation</div>

    <a href="#overview" class="nav-item active" onclick="showSection('overview',this)">
      <span class="nav-icon">📊</span> Vue d'ensemble
    </a>
    <a href="#bookings" class="nav-item" onclick="showSection('bookings',this)">
      <span class="nav-icon">📅</span> Réservations
      <?php if ($totalPending > 0): ?>
        <span class="nav-badge"><?= $totalPending ?></span>
      <?php endif; ?>
    </a>
    <a href="#reviews" class="nav-item" onclick="showSection('reviews',this)">
      <span class="nav-icon">⭐</span> Mes avis
      <span class="nav-badge green"><?= count($reviews) ?></span>
    </a>

    <div class="nav-label" style="margin-top:.75rem">Compte</div>
    <a href="/servilocal/profile.php" class="nav-item">
      <span class="nav-icon">👤</span> Mon profil
    </a>
    <a href="/servilocal/provider.php?id=<?= $provId ?>" class="nav-item" target="_blank">
      <span class="nav-icon">👁</span> Ma fiche publique
    </a>
    <a href="/servilocal/index.php" class="nav-item">
      <span class="nav-icon">🏠</span> Retour au site
    </a>
  </nav>

  <div class="sidebar-footer">
    <div class="prov-mini">
      <div class="prov-av" style="background:<?= e($provider['avatar_color']) ?>">
        <?= avatarInitial($provider['name']) ?>
      </div>
      <div>
        <div class="prov-name"><?= e($provider['name']) ?></div>
        <div class="prov-cat"><?= categoryIcon($provider['category']) ?> <?= e(ucfirst($provider['category'])) ?></div>
      </div>
    </div>
    <div class="sidebar-links">
      <a href="/servilocal/profile.php" class="sidebar-link">⚙️ Paramètres</a>
      <a href="/servilocal/logout.php" class="sidebar-link danger">🚪 Quitter</a>
    </div>
  </div>

</aside>

<!-- ══════════════════════════════════════════════════════════
     MAIN CONTENT
     ══════════════════════════════════════════════════════════ -->
<div class="main">

  <!-- Topbar -->
  <div class="topbar">
    <div class="topbar-left">
      <button class="burger-btn" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button>
      <div>
        <div class="topbar-greeting">
          Bonjour, <?= e(explode(' ', $provider['name'])[0]) ?> <?= categoryIcon($provider['category']) ?>
        </div>
        <div class="topbar-date"><?= strftime('%A %d %B %Y') ?? date('l d F Y') ?></div>
      </div>
    </div>
    <div class="topbar-right">
      <?php if ($totalPending > 0): ?>
        <span style="background:rgba(245,158,11,.12);color:var(--amber);border:1px solid rgba(245,158,11,.25);padding:.35rem .8rem;border-radius:50px;font-size:.76rem;font-weight:600">
          🕐 <?= $totalPending ?> en attente
        </span>
      <?php endif; ?>
      <a href="/servilocal/profile.php" class="topbar-btn">⚙️ Profil</a>
      <a href="/servilocal/logout.php" class="topbar-btn">🚪 Déconnexion</a>
    </div>
  </div>

  <!-- Contenu principal -->
  <div class="content">

    <!-- ══════════════════════════════
         SECTION : VUE D'ENSEMBLE
         ══════════════════════════════ -->
    <section id="overview">

      <!-- Stat cards -->
      <div class="stat-grid">
        <div class="stat-card" style="--stat-accent:var(--accent)">
          <span class="stat-icon">📅</span>
          <span class="stat-val"><?= $totalAll ?></span>
          <div class="stat-lbl">Réservations total</div>
        </div>
        <div class="stat-card" style="--stat-accent:var(--amber)">
          <span class="stat-icon">🕐</span>
          <span class="stat-val"><?= $totalPending ?></span>
          <div class="stat-lbl">En attente</div>
          <?php if ($totalPending > 0): ?>
            <span class="stat-trend trend-warn">Action requise</span>
          <?php endif; ?>
        </div>
        <div class="stat-card" style="--stat-accent:var(--teal)">
          <span class="stat-icon">✅</span>
          <span class="stat-val"><?= $totalAccepted ?></span>
          <div class="stat-lbl">Acceptées</div>
        </div>
        <div class="stat-card" style="--stat-accent:var(--purple)">
          <span class="stat-icon">⭐</span>
          <span class="stat-val"><?= number_format((float)($provider['rating'] ?? 0), 1) ?></span>
          <div class="stat-lbl">Note moyenne</div>
          <span class="stat-trend trend-up"><?= $provider['review_count'] ?> avis</span>
        </div>
      </div>

      <!-- Grid principal -->
      <div class="grid-main">

        <!-- Colonne gauche -->
        <div>
          <!-- Réservations en attente urgentes -->
          <?php
          $urgentBookings = array_filter($bookings, fn($b) => $b['status'] === 'pending');
          if (!empty($urgentBookings)):
          ?>
          <div class="card" style="border-color:rgba(245,158,11,.3)">
            <div class="card-header">
              <div class="card-title">
                <span>🔔</span> Demandes en attente
                <span style="background:rgba(245,158,11,.15);color:var(--amber);font-size:.7rem;padding:.15rem .55rem;border-radius:50px"><?= count($urgentBookings) ?></span>
              </div>
              <a href="#bookings" class="topbar-btn" onclick="showSection('bookings',null)" style="font-size:.75rem">Voir toutes →</a>
            </div>
            <div class="table-wrap">
              <table>
                <thead>
                  <tr>
                    <th>Client</th>
                    <th>Date</th>
                    <th>Heure</th>
                    <th>Description</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach (array_slice($urgentBookings, 0, 4) as $b): ?>
                    <tr>
                      <td>
                        <div style="display:flex;align-items:center;gap:.6rem">
                          <div class="av" style="background:var(--teal)"><?= avatarInitial($b['client_name']) ?></div>
                          <div>
                            <div style="font-weight:600"><?= e($b['client_name']) ?></div>
                            <div style="font-size:.72rem;color:var(--muted)"><?= e($b['client_phone'] ?? '—') ?></div>
                          </div>
                        </div>
                      </td>
                      <td style="font-size:.82rem">
                        <?= date('d/m/Y', strtotime($b['booking_date'])) ?>
                        <?php
                        $days = (new DateTime($b['booking_date']))->diff(new DateTime())->days;
                        $inFuture = $b['booking_date'] >= date('Y-m-d');
                        if ($inFuture && $days <= 2):
                        ?>
                          <span style="font-size:.68rem;background:rgba(248,113,113,.15);color:var(--red);padding:.1rem .4rem;border-radius:4px;margin-left:.3rem">
                            <?= $days === 0 ? 'Aujourd\'hui' : "J-$days" ?>
                          </span>
                        <?php endif; ?>
                      </td>
                      <td style="font-size:.82rem;color:var(--muted)"><?= substr($b['booking_time'], 0, 5) ?></td>
                      <td style="max-width:200px;font-size:.8rem;color:var(--muted)">
                        <?= e(mb_substr($b['description'] ?? '—', 0, 50)) ?><?= mb_strlen($b['description'] ?? '') > 50 ? '…' : '' ?>
                      </td>
                      <td>
                        <div style="display:flex;gap:.35rem">
                          <a href="?action=accept&id=<?= $b['id'] ?>&csrf_token=<?= csrfToken() ?>"
                             class="btn-action btn-accept"
                             onclick="return confirm('Accepter cette réservation ?')">✓ Accepter</a>
                          <a href="?action=refuse&id=<?= $b['id'] ?>&csrf_token=<?= csrfToken() ?>"
                             class="btn-action btn-refuse"
                             onclick="return confirm('Refuser cette réservation ?')">✗ Refuser</a>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
          <?php endif; ?>

          <!-- Activité hebdo -->
          <div class="card">
            <div class="card-header">
              <div class="card-title"><span>📈</span> Activité — 7 derniers jours</div>
              <span style="font-size:.78rem;color:var(--muted)"><?= array_sum($weekActivity) ?> réservations</span>
            </div>
            <div class="card-body">
              <?php $maxAct = max(array_values($weekActivity)) ?: 1; ?>
              <div class="chart-bars">
                <?php foreach ($weekActivity as $date => $cnt): ?>
                  <?php $isToday = $date === date('Y-m-d'); ?>
                  <div class="chart-col" title="<?= date('d/m', strtotime($date)) ?> : <?= $cnt ?> réservation<?= $cnt>1?'s':'' ?>">
                    <div class="chart-bar <?= $isToday ? 'today' : '' ?>"
                         style="height:<?= max(4, round($cnt / $maxAct * 44)) ?>px"></div>
                    <div class="chart-lbl"><?= date('D', strtotime($date)) ?></div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <!-- Dernières réservations (aperçu) -->
          <div class="card">
            <div class="card-header">
              <div class="card-title"><span>📋</span> Dernières réservations</div>
              <a href="#bookings" class="topbar-btn" onclick="showSection('bookings',null)" style="font-size:.75rem">Voir tout →</a>
            </div>
            <?php $recent = array_slice($bookings, 0, 5); ?>
            <?php if (empty($recent)): ?>
              <div class="empty-state">
                <span class="empty-icon">📅</span>
                <div class="empty-title">Aucune réservation pour l'instant</div>
                <div class="empty-sub">Votre profil est en ligne. Les demandes arriveront bientôt !</div>
              </div>
            <?php else: ?>
              <div class="table-wrap">
                <table>
                  <thead>
                    <tr><th>Client</th><th>Date</th><th>Statut</th><th>Actions</th></tr>
                  </thead>
                  <tbody>
                    <?php foreach ($recent as $b):
                      [$label, $cls] = statusLabel($b['status']);
                    ?>
                      <tr>
                        <td>
                          <div style="display:flex;align-items:center;gap:.6rem">
                            <div class="av" style="background:var(--teal)"><?= avatarInitial($b['client_name']) ?></div>
                            <span style="font-weight:500"><?= e($b['client_name']) ?></span>
                          </div>
                        </td>
                        <td style="font-size:.82rem;color:var(--muted)"><?= date('d/m/Y', strtotime($b['booking_date'])) ?></td>
                        <td><span class="badge <?= $cls ?>"><?= $label ?></span></td>
                        <td>
                          <?php if ($b['status'] === 'pending'): ?>
                            <div style="display:flex;gap:.35rem">
                              <a href="?action=accept&id=<?= $b['id'] ?>&csrf_token=<?= csrfToken() ?>"
                                 class="btn-action btn-accept" onclick="return confirm('Accepter ?')">✓</a>
                              <a href="?action=refuse&id=<?= $b['id'] ?>&csrf_token=<?= csrfToken() ?>"
                                 class="btn-action btn-refuse" onclick="return confirm('Refuser ?')">✗</a>
                            </div>
                          <?php elseif ($b['status'] === 'accepted'): ?>
                            <a href="?action=complete&id=<?= $b['id'] ?>&csrf_token=<?= csrfToken() ?>"
                               class="btn-action btn-complete" onclick="return confirm('Marquer comme terminée ?')">🏁 Terminer</a>
                          <?php else: ?>
                            <span style="font-size:.75rem;color:var(--muted)">—</span>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Colonne droite -->
        <div>

          <!-- Profil public -->
          <div class="card">
            <div class="card-header">
              <div class="card-title"><span>👤</span> Mon profil</div>
              <a href="/servilocal/profile.php" class="topbar-btn" style="font-size:.75rem">Modifier</a>
            </div>
            <div class="card-body">
              <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.1rem">
                <div class="av av-provider" style="background:<?= e($provider['avatar_color']) ?>">
                  <?= avatarInitial($provider['name']) ?>
                </div>
                <div>
                  <div style="font-weight:600;font-size:.95rem"><?= e($provider['name']) ?></div>
                  <div style="font-size:.78rem;color:var(--muted)"><?= categoryIcon($provider['category']) ?> <?= e(ucfirst($provider['category'])) ?> · <?= e($provider['city']) ?></div>
                  <?php if ($provider['is_verified']): ?>
                    <div style="font-size:.72rem;color:var(--accent);margin-top:.2rem">✓ Profil vérifié</div>
                  <?php endif; ?>
                </div>
              </div>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem;font-size:.8rem">
                <div style="background:var(--bg3);border-radius:var(--radius-sm);padding:.7rem;text-align:center">
                  <div style="font-family:'Syne',sans-serif;font-size:1.3rem;font-weight:700;color:var(--amber)"><?= number_format((float)$provider['rating'], 1) ?></div>
                  <div style="color:var(--muted)">Note</div>
                </div>
                <div style="background:var(--bg3);border-radius:var(--radius-sm);padding:.7rem;text-align:center">
                  <div style="font-family:'Syne',sans-serif;font-size:1.3rem;font-weight:700;color:var(--text)"><?= $provider['review_count'] ?></div>
                  <div style="color:var(--muted)">Avis</div>
                </div>
              </div>
              <div style="margin-top:.85rem;font-size:.8rem;color:var(--muted)">
                💰 <?= e($provider['price']) ?><br>
                <span style="margin-top:.3rem;display:block">
                  <?= $provider['is_available']
                    ? '<span style="color:var(--accent)">● Disponible</span>'
                    : '<span style="color:var(--red)">● Indisponible</span>' ?>
                </span>
              </div>
              <a href="/servilocal/provider.php?id=<?= $provId ?>"
                 target="_blank"
                 style="display:flex;align-items:center;justify-content:center;gap:.4rem;margin-top:1rem;padding:.6rem;border-radius:var(--radius-sm);border:1px solid var(--border);background:var(--bg3);color:var(--muted);font-size:.8rem;transition:all .2s;text-decoration:none"
                 onmouseover="this.style.color='var(--text)'"
                 onmouseout="this.style.color='var(--muted)'">
                👁 Voir ma fiche publique
              </a>
            </div>
          </div>

          <!-- Aperçu avis -->
          <div class="card">
            <div class="card-header">
              <div class="card-title"><span>⭐</span> Derniers avis</div>
              <a href="#reviews" class="topbar-btn" onclick="showSection('reviews',null)" style="font-size:.75rem">Voir tout →</a>
            </div>
            <div class="card-body" style="padding-bottom:.75rem">
              <!-- Barre de notes -->
              <?php if ($provider['review_count'] > 0): ?>
                <div style="text-align:center;margin-bottom:1rem">
                  <div style="font-family:'Syne',sans-serif;font-size:2rem;font-weight:800;color:var(--amber)"><?= number_format((float)$provider['rating'], 1) ?></div>
                  <div style="color:var(--amber);font-size:1rem;letter-spacing:2px">
                    <?= str_repeat('★', (int)round($provider['rating'])) ?>
                    <?= str_repeat('☆', 5 - (int)round($provider['rating'])) ?>
                  </div>
                </div>
                <div class="rating-bars">
                  <?php for ($s = 5; $s >= 1; $s--): ?>
                    <?php
                    $cnt = $ratingDist[$s] ?? 0;
                    $pct = $provider['review_count'] ? round($cnt / $provider['review_count'] * 100) : 0;
                    ?>
                    <div class="rating-bar-row">
                      <span class="rb-label"><?= $s ?></span>
                      <span class="rb-star">★</span>
                      <div class="rb-track"><div class="rb-fill" style="width:<?= $pct ?>%"></div></div>
                      <span class="rb-pct"><?= $pct ?>%</span>
                    </div>
                  <?php endfor; ?>
                </div>
                <div style="border-top:1px solid var(--border);margin-top:1rem;padding-top:1rem">
              <?php endif; ?>

              <?php if (empty($reviews)): ?>
                <div class="empty-state" style="padding:1.5rem">
                  <span class="empty-icon" style="font-size:2rem">⭐</span>
                  <div class="empty-title">Aucun avis</div>
                  <div class="empty-sub">Vos premiers avis apparaîtront ici.</div>
                </div>
              <?php else: ?>
                <?php foreach (array_slice($reviews, 0, 3) as $rev): ?>
                  <div class="review-item" style="<?= $provider['review_count'] > 0 ? '' : '' ?>">
                    <div class="review-header">
                      <div class="av" style="background:var(--teal);color:#fff"><?= avatarInitial($rev['client_name']) ?></div>
                      <div>
                        <div style="font-weight:600;font-size:.84rem"><?= e($rev['client_name']) ?></div>
                        <div class="review-stars"><?= str_repeat('★', $rev['rating']) . str_repeat('☆', 5 - $rev['rating']) ?></div>
                      </div>
                      <span class="review-date"><?= date('d/m/Y', strtotime($rev['created_at'])) ?></span>
                    </div>
                    <?php if ($rev['comment']): ?>
                      <div class="review-text"><?= e($rev['comment']) ?></div>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>

              <?php if ($provider['review_count'] > 0): ?>
                </div>
              <?php endif; ?>
            </div>
          </div>

        </div><!-- /col droite -->
      </div><!-- /grid-main -->

    </section>

    <!-- ══════════════════════════════
         SECTION : TOUTES LES RÉSERVATIONS
         ══════════════════════════════ -->
    <section id="bookings" style="display:none">
      <div class="card">
        <div class="card-header">
          <div class="card-title"><span>📅</span> Toutes les réservations</div>
          <span style="font-size:.8rem;color:var(--muted)"><strong style="color:var(--text)"><?= $totalBookings ?></strong> au total</span>
        </div>

        <!-- Filtres -->
        <form method="GET" id="filterForm">
          <input type="hidden" name="section" value="bookings">
          <div class="filter-bar">
            <div class="search-input-wrap">
              <span class="search-ico">🔍</span>
              <input type="text" name="q" class="search-input"
                     placeholder="Rechercher un client…"
                     value="<?= e($search) ?>">
            </div>
            <select name="status" class="filter-select" onchange="this.form.submit()">
              <option value="">Tous les statuts</option>
              <option value="pending"   <?= $statusFilter==='pending'   ?'selected':'' ?>>🕐 En attente</option>
              <option value="accepted"  <?= $statusFilter==='accepted'  ?'selected':'' ?>>✅ Acceptées</option>
              <option value="completed" <?= $statusFilter==='completed' ?'selected':'' ?>>🏁 Terminées</option>
              <option value="refused"   <?= $statusFilter==='refused'   ?'selected':'' ?>>❌ Refusées</option>
              <option value="cancelled" <?= $statusFilter==='cancelled' ?'selected':'' ?>>🚫 Annulées</option>
            </select>
            <button type="submit" class="topbar-btn primary">Filtrer</button>
            <a href="?section=bookings" class="topbar-btn">Réinitialiser</a>
          </div>
        </form>

        <?php if (empty($bookings)): ?>
          <div class="empty-state">
            <span class="empty-icon">📅</span>
            <div class="empty-title">Aucune réservation trouvée</div>
            <div class="empty-sub">Modifiez les filtres ou attendez de nouvelles demandes.</div>
          </div>
        <?php else: ?>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>#</th>
                  <th>Client</th>
                  <th>Date</th>
                  <th>Heure</th>
                  <th>Adresse</th>
                  <th>Description</th>
                  <th>Statut</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($bookings as $b):
                  [$label, $cls] = statusLabel($b['status']);
                ?>
                  <tr>
                    <td style="color:var(--muted);font-size:.75rem">#<?= $b['id'] ?></td>
                    <td>
                      <div style="display:flex;align-items:center;gap:.6rem">
                        <div class="av" style="background:var(--teal);color:#fff"><?= avatarInitial($b['client_name']) ?></div>
                        <div>
                          <div style="font-weight:600;font-size:.86rem"><?= e($b['client_name']) ?></div>
                          <div style="font-size:.72rem;color:var(--muted)"><?= e($b['client_phone'] ?? '') ?></div>
                          <div style="font-size:.72rem;color:var(--muted)"><?= e($b['client_email'] ?? '') ?></div>
                        </div>
                      </div>
                    </td>
                    <td style="font-size:.82rem">
                      <?= date('d/m/Y', strtotime($b['booking_date'])) ?>
                      <?php
                      $diff = (new DateTime($b['booking_date']))->diff(new DateTime());
                      $isFuture = $b['booking_date'] >= date('Y-m-d');
                      if ($isFuture && $diff->days <= 2 && $b['status'] === 'accepted'):
                      ?>
                        <span style="font-size:.66rem;background:rgba(248,113,113,.15);color:var(--red);padding:.1rem .38rem;border-radius:4px;display:block;margin-top:2px">
                          <?= $diff->days === 0 ? 'Aujourd\'hui !' : 'Dans ' . $diff->days . 'j' ?>
                        </span>
                      <?php endif; ?>
                    </td>
                    <td style="font-size:.82rem;color:var(--muted)"><?= substr($b['booking_time'], 0, 5) ?></td>
                    <td style="font-size:.8rem;color:var(--muted);max-width:130px">
                      <?= e(mb_substr($b['address'] ?? '—', 0, 35)) ?><?= mb_strlen($b['address'] ?? '') > 35 ? '…' : '' ?>
                    </td>
                    <td style="font-size:.8rem;color:var(--muted);max-width:160px">
                      <?= e(mb_substr($b['description'] ?? '—', 0, 50)) ?><?= mb_strlen($b['description'] ?? '') > 50 ? '…' : '' ?>
                    </td>
                    <td><span class="badge <?= $cls ?>"><?= $label ?></span></td>
                    <td>
                      <div style="display:flex;gap:.3rem;flex-wrap:wrap">
                        <?php if ($b['status'] === 'pending'): ?>
                          <a href="?action=accept&id=<?= $b['id'] ?>&csrf_token=<?= csrfToken() ?>"
                             class="btn-action btn-accept"
                             onclick="return confirm('Accepter cette réservation de ' + '<?= e($b['client_name']) ?>' + ' ?')">
                            ✓ Accepter
                          </a>
                          <a href="?action=refuse&id=<?= $b['id'] ?>&csrf_token=<?= csrfToken() ?>"
                             class="btn-action btn-refuse"
                             onclick="return confirm('Refuser cette réservation ?')">
                            ✗ Refuser
                          </a>
                        <?php elseif ($b['status'] === 'accepted'): ?>
                          <a href="?action=complete&id=<?= $b['id'] ?>&csrf_token=<?= csrfToken() ?>"
                             class="btn-action btn-complete"
                             onclick="return confirm('Marquer comme terminée ?')">
                            🏁 Terminer
                          </a>
                        <?php else: ?>
                          <span style="font-size:.75rem;color:var(--muted)">—</span>
                        <?php endif; ?>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <?php if ($totalPages > 1): ?>
            <div class="pagination">
              <span>Page <?= $page ?> / <?= $totalPages ?> — <?= $totalBookings ?> résultats</span>
              <div class="page-btns">
                <?php if ($page > 1): ?>
                  <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>"
                     class="page-btn">‹</a>
                <?php endif; ?>
                <?php for ($i = max(1,$page-2); $i <= min($totalPages,$page+2); $i++): ?>
                  <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
                     class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                  <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>"
                     class="page-btn">›</a>
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>

        <?php endif; ?>
      </div>
    </section>

    <!-- ══════════════════════════════
         SECTION : MES AVIS
         ══════════════════════════════ -->
    <section id="reviews" style="display:none">
      <div style="display:grid;grid-template-columns:300px 1fr;gap:1.25rem;align-items:start">

        <!-- Résumé notes -->
        <div class="card">
          <div class="card-header"><div class="card-title"><span>📊</span> Résumé</div></div>
          <div class="card-body" style="text-align:center">
            <div style="font-family:'Syne',sans-serif;font-size:3rem;font-weight:800;color:var(--amber);line-height:1">
              <?= number_format((float)$provider['rating'], 1) ?>
            </div>
            <div style="color:var(--amber);font-size:1.3rem;letter-spacing:3px;margin:.3rem 0">
              <?= str_repeat('★', (int)round($provider['rating'])) ?>
              <?= str_repeat('☆', 5 - (int)round($provider['rating'])) ?>
            </div>
            <div style="font-size:.82rem;color:var(--muted);margin-bottom:1.25rem"><?= $provider['review_count'] ?> avis</div>
            <div class="rating-bars">
              <?php for ($s = 5; $s >= 1; $s--): ?>
                <?php
                $cnt = $ratingDist[$s] ?? 0;
                $pct = $provider['review_count'] ? round($cnt / $provider['review_count'] * 100) : 0;
                ?>
                <div class="rating-bar-row">
                  <span class="rb-label"><?= $s ?></span>
                  <span class="rb-star">★</span>
                  <div class="rb-track"><div class="rb-fill" style="width:<?= $pct ?>%"></div></div>
                  <span style="font-size:.76rem;color:var(--muted);min-width:28px;text-align:right"><?= $cnt ?></span>
                </div>
              <?php endfor; ?>
            </div>
          </div>
        </div>

        <!-- Liste avis -->
        <div class="card">
          <div class="card-header">
            <div class="card-title"><span>⭐</span> Tous les avis</div>
            <span style="font-size:.8rem;color:var(--muted)"><?= count($reviews) ?> avis</span>
          </div>
          <div class="card-body">
            <?php if (empty($reviews)): ?>
              <div class="empty-state">
                <span class="empty-icon">⭐</span>
                <div class="empty-title">Aucun avis reçu</div>
                <div class="empty-sub">Vos clients pourront vous noter après leurs réservations.</div>
              </div>
            <?php else: ?>
              <?php foreach ($reviews as $rev): ?>
                <div class="review-item">
                  <div class="review-header">
                    <div class="av" style="background:var(--teal);color:#fff"><?= avatarInitial($rev['client_name']) ?></div>
                    <div>
                      <div style="font-weight:600;font-size:.88rem"><?= e($rev['client_name']) ?></div>
                      <div class="review-stars"><?= str_repeat('★', $rev['rating']) . str_repeat('☆', 5 - $rev['rating']) ?></div>
                    </div>
                    <span class="review-date"><?= date('d/m/Y', strtotime($rev['created_at'])) ?></span>
                  </div>
                  <?php if ($rev['comment']): ?>
                    <div class="review-text"><?= e($rev['comment']) ?></div>
                  <?php else: ?>
                    <div class="review-text" style="opacity:.4;font-style:italic">Aucun commentaire</div>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

      </div>
    </section>

  </div><!-- /.content -->
</div><!-- /.main -->

<!-- Toast -->
<div class="toast" id="toast">
  <div class="toast-accent"></div>
  <span id="toast-msg"></span>
</div>

<script>
// ── Navigation sections ────────────────────────────────────
function showSection(id, navEl) {
  document.querySelectorAll('section').forEach(s => s.style.display = 'none');
  const el = document.getElementById(id);
  if (el) el.style.display = 'block';

  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  if (navEl) navEl.classList.add('active');

  document.getElementById('sidebar').classList.remove('open');
}

// Initialiser la bonne section selon l'URL
(function() {
  const hash = window.location.hash.replace('#','') || 'overview';
  const el = document.getElementById(hash);
  if (el) {
    document.querySelectorAll('section').forEach(s => s.style.display = 'none');
    el.style.display = 'block';
    document.querySelectorAll('.nav-item').forEach(n => {
      if (n.getAttribute('href') === '#' + hash) n.classList.add('active');
      else n.classList.remove('active');
    });
  }
})();

// ── Toast ─────────────────────────────────────────────────
function showToast(msg) {
  const toast = document.getElementById('toast');
  document.getElementById('toast-msg').textContent = msg;
  toast.classList.add('show');
  setTimeout(() => toast.classList.remove('show'), 3500);
}

<?php if ($toast): ?>
  setTimeout(() => showToast(<?= json_encode($toast) ?>), 300);
<?php endif; ?>

// ── Fermer sidebar au clic extérieur (mobile) ──────────────
document.addEventListener('click', function(e) {
  const sidebar = document.getElementById('sidebar');
  if (sidebar.classList.contains('open') && !sidebar.contains(e.target) && !e.target.closest('.burger-btn')) {
    sidebar.classList.remove('open');
  }
});
</script>
</body>
</html>