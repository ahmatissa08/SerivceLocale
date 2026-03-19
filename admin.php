<?php
// ============================================================
// admin.php — Panneau d'administration ServiLocal
// Accès : role = 'admin' uniquement
// Pour créer un admin : UPDATE users SET role='admin' WHERE id=X;
// ============================================================
require_once 'db.php';
require_once 'includes/functions.php';

// ── Sécurité : admin uniquement ───────────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isLoggedIn() || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: /servilocal/login.php?toast=' . urlencode('⛔ Accès réservé aux administrateurs.'));
    exit;
}

$adminName = $_SESSION['user_name'];
$action    = $_GET['action'] ?? 'dashboard';
$id        = (int)($_GET['id'] ?? 0);
$msg       = '';
$msgType   = 'success';

// ============================================================
// TRAITEMENT DES ACTIONS POST
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    // ── Modifier un utilisateur ───────────────────────────
    if (isset($_POST['edit_user'])) {
        $stmt = $pdo->prepare('UPDATE users SET name=?, email=?, phone=?, role=?, is_active=? WHERE id=?');
        $stmt->execute([
            trim($_POST['name']), trim($_POST['email']), trim($_POST['phone']),
            $_POST['role'], isset($_POST['is_active']) ? 1 : 0, (int)$_POST['uid']
        ]);
        $msg = '✅ Utilisateur mis à jour.';
    }

    // ── Modifier un prestataire ───────────────────────────
    if (isset($_POST['edit_provider'])) {
        $stmt = $pdo->prepare('UPDATE providers SET category=?, city=?, price=?, description=?, is_available=?, is_verified=? WHERE id=?');
        $stmt->execute([
            trim($_POST['category']), trim($_POST['city']), trim($_POST['price']),
            trim($_POST['description']),
            isset($_POST['is_available']) ? 1 : 0,
            isset($_POST['is_verified'])  ? 1 : 0,
            (int)$_POST['pid']
        ]);
        $msg = '✅ Prestataire mis à jour.';
    }

    // ── Modifier statut réservation ───────────────────────
    if (isset($_POST['edit_booking'])) {
        $stmt = $pdo->prepare('UPDATE bookings SET status=? WHERE id=?');
        $stmt->execute([$_POST['status'], (int)$_POST['bid']]);
        $msg = '✅ Réservation mise à jour.';
    }

    // ── Supprimer un avis ─────────────────────────────────
    if (isset($_POST['delete_review'])) {
        $stmt = $pdo->prepare('DELETE FROM reviews WHERE id=?');
        $stmt->execute([(int)$_POST['rid']]);
        // Recalculer la note du prestataire
        $stmt2 = $pdo->prepare('UPDATE providers SET rating=COALESCE((SELECT AVG(rating) FROM reviews WHERE provider_id=?),0), review_count=(SELECT COUNT(*) FROM reviews WHERE provider_id=?) WHERE id=?');
        $pid = (int)$_POST['provider_id'];
        $stmt2->execute([$pid,$pid,$pid]);
        $msg = '🗑️ Avis supprimé.';
    }

    // ── Ajouter un utilisateur admin ─────────────────────
    if (isset($_POST['add_user'])) {
        $errors = [];
        $email = trim($_POST['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email invalide.';
        if (strlen($_POST['password'] ?? '') < 6) $errors[] = 'Mot de passe trop court.';
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email=?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) $errors[] = 'Email déjà utilisé.';

        if (empty($errors)) {
            $stmt = $pdo->prepare('INSERT INTO users (name,email,password,phone,role) VALUES (?,?,?,?,?)');
            $stmt->execute([
                trim($_POST['name']), $email,
                password_hash($_POST['password'], PASSWORD_BCRYPT),
                trim($_POST['phone'] ?? ''), $_POST['role']
            ]);
            $msg = '✅ Utilisateur créé.';
        } else {
            $msg = '⚠️ ' . implode(' | ', $errors);
            $msgType = 'error';
        }
    }
}

// ── Actions GET ───────────────────────────────────────────
if ($_GET['action'] ?? '' === 'toggle_user' && $id) {
    $stmt = $pdo->prepare('UPDATE users SET is_active = 1 - is_active WHERE id=? AND id != ?');
    $stmt->execute([$id, $_SESSION['user_id']]);
    header('Location: /servilocal/admin.php?action=users&msg=' . urlencode('✅ Statut utilisateur modifié.'));
    exit;
}
if ($_GET['action'] ?? '' === 'toggle_verify' && $id) {
    $stmt = $pdo->prepare('UPDATE providers SET is_verified = 1 - is_verified WHERE id=?');
    $stmt->execute([$id]);
    header('Location: /servilocal/admin.php?action=providers&msg=' . urlencode('✅ Vérification modifiée.'));
    exit;
}
if ($_GET['action'] ?? '' === 'delete_user' && $id) {
    $stmt = $pdo->prepare('DELETE FROM users WHERE id=? AND id != ?');
    $stmt->execute([$id, $_SESSION['user_id']]);
    header('Location: /servilocal/admin.php?action=users&msg=' . urlencode('🗑️ Utilisateur supprimé.'));
    exit;
}
if ($_GET['action'] ?? '' === 'delete_booking' && $id) {
    $stmt = $pdo->prepare('DELETE FROM bookings WHERE id=?');
    $stmt->execute([$id]);
    header('Location: /servilocal/admin.php?action=bookings&msg=' . urlencode('🗑️ Réservation supprimée.'));
    exit;
}

// ── Message depuis redirect ────────────────────────────────
if (!$msg && isset($_GET['msg'])) $msg = urldecode($_GET['msg']);

// ============================================================
// DONNÉES PAR SECTION
// ============================================================
$search = trim($_GET['q'] ?? '');

// ── Dashboard : stats globales ────────────────────────────
$stats = [];
if ($action === 'dashboard' || true) {
    $stats['users']            = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $stats['clients']          = $pdo->query('SELECT COUNT(*) FROM users WHERE role="client"')->fetchColumn();
    $stats['providers_count']  = $pdo->query('SELECT COUNT(*) FROM providers')->fetchColumn();
    $stats['bookings']         = $pdo->query('SELECT COUNT(*) FROM bookings')->fetchColumn();
    $stats['pending']          = $pdo->query('SELECT COUNT(*) FROM bookings WHERE status="pending"')->fetchColumn();
    $stats['accepted']         = $pdo->query('SELECT COUNT(*) FROM bookings WHERE status="accepted"')->fetchColumn();
    $stats['completed']        = $pdo->query('SELECT COUNT(*) FROM bookings WHERE status="completed"')->fetchColumn();
    $stats['reviews']          = $pdo->query('SELECT COUNT(*) FROM reviews')->fetchColumn();
    $stats['avg_rating']       = round($pdo->query('SELECT AVG(rating) FROM reviews')->fetchColumn(), 1);
    $stats['verified']         = $pdo->query('SELECT COUNT(*) FROM providers WHERE is_verified=1')->fetchColumn();
    $stats['revenue_est']      = $pdo->query('SELECT COUNT(*) FROM bookings WHERE status IN ("accepted","completed")')->fetchColumn() * 150;

    // Activité des 7 derniers jours
    $stmt = $pdo->query('SELECT DATE(created_at) as d, COUNT(*) as cnt FROM bookings WHERE created_at >= DATE_SUB(NOW(),INTERVAL 7 DAY) GROUP BY DATE(created_at) ORDER BY d');
    $stats['weekly'] = $stmt->fetchAll();

    // Top prestataires
    $stmt = $pdo->query('SELECT u.name, p.category, p.rating, p.review_count, p.city FROM providers p JOIN users u ON u.id=p.user_id ORDER BY p.rating DESC LIMIT 5');
    $stats['top_providers'] = $stmt->fetchAll();

    // Dernières réservations
    $stmt = $pdo->query('SELECT b.id, b.status, b.booking_date, cu.name as client, pu.name as provider FROM bookings b JOIN users cu ON cu.id=b.client_id JOIN providers p ON p.id=b.provider_id JOIN users pu ON pu.id=p.user_id ORDER BY b.created_at DESC LIMIT 8');
    $stats['recent_bookings'] = $stmt->fetchAll();
}

// ── Users ─────────────────────────────────────────────────
$users = [];
if ($action === 'users') {
    $roleFilter = $_GET['role'] ?? '';
    $q = "SELECT u.*, (SELECT COUNT(*) FROM bookings WHERE client_id=u.id) AS booking_cnt FROM users u WHERE 1=1";
    $p = [];
    if ($search) { $q .= ' AND (u.name LIKE ? OR u.email LIKE ?)'; $p[] = "%$search%"; $p[] = "%$search%"; }
    if ($roleFilter) { $q .= ' AND u.role=?'; $p[] = $roleFilter; }
    $q .= ' ORDER BY u.created_at DESC';
    $stmt = $pdo->prepare($q);
    $stmt->execute($p);
    $users = $stmt->fetchAll();
}

// ── Providers ─────────────────────────────────────────────
$providers = [];
if ($action === 'providers') {
    $catFilter = $_GET['cat'] ?? '';
    $verFilter = $_GET['ver'] ?? '';
    $q = "SELECT p.*, u.name, u.email, u.phone FROM providers p JOIN users u ON u.id=p.user_id WHERE 1=1";
    $p = [];
    if ($search) { $q .= ' AND (u.name LIKE ? OR p.city LIKE ?)'; $p[] = "%$search%"; $p[] = "%$search%"; }
    if ($catFilter) { $q .= ' AND p.category=?'; $p[] = $catFilter; }
    if ($verFilter !== '') { $q .= ' AND p.is_verified=?'; $p[] = (int)$verFilter; }
    $q .= ' ORDER BY p.rating DESC';
    $stmt = $pdo->prepare($q);
    $stmt->execute($p);
    $providers = $stmt->fetchAll();
}

// ── Bookings ──────────────────────────────────────────────
$bookings = [];
if ($action === 'bookings') {
    $statusFilter = $_GET['status'] ?? '';
    $q = "SELECT b.*, cu.name AS client_name, pu.name AS provider_name, p.category
          FROM bookings b
          JOIN users cu ON cu.id=b.client_id
          JOIN providers p ON p.id=b.provider_id
          JOIN users pu ON pu.id=p.user_id
          WHERE 1=1";
    $params = [];
    if ($search) { $q .= ' AND (cu.name LIKE ? OR pu.name LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
    if ($statusFilter) { $q .= ' AND b.status=?'; $params[] = $statusFilter; }
    $q .= ' ORDER BY b.created_at DESC';
    $stmt = $pdo->prepare($q);
    $stmt->execute($params);
    $bookings = $stmt->fetchAll();
}

// ── Reviews ───────────────────────────────────────────────
$reviews = [];
if ($action === 'reviews') {
    $q = "SELECT r.*, cu.name AS client_name, pu.name AS provider_name, p.id AS pid, p.category
          FROM reviews r
          JOIN users cu ON cu.id=r.client_id
          JOIN providers p ON p.id=r.provider_id
          JOIN users pu ON pu.id=p.user_id
          WHERE 1=1";
    $params = [];
    if ($search) { $q .= ' AND (cu.name LIKE ? OR pu.name LIKE ? OR r.comment LIKE ?)'; $params = array_fill(0, 3, "%$search%"); }
    $q .= ' ORDER BY r.created_at DESC';
    $stmt = $pdo->prepare($q);
    $stmt->execute($params);
    $reviews = $stmt->fetchAll();
}

// ── Catégories ────────────────────────────────────────────
$categories_list = ['plomberie','coiffure','informatique','electricite','jardinage','menage','transport','peinture'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Administration — ServiLocal</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
/* ============================================================
   ADMIN PANEL — Dark editorial theme
   ============================================================ */
:root {
  --bg:        #0A0C10;
  --bg2:       #111318;
  --bg3:       #181C23;
  --border:    #232830;
  --border2:   #2E3440;
  --text:      #E8EAF0;
  --muted:     #7A8099;
  --accent:    #4ADE80;
  --accent2:   #22D3EE;
  --amber:     #FBBF24;
  --red:       #F87171;
  --purple:    #A78BFA;
  --sidebar-w: 260px;
  --header-h:  64px;
  --radius:    12px;
  --radius-sm: 8px;
  --shadow:    0 4px 24px rgba(0,0,0,.4);
  --shadow-lg: 0 8px 40px rgba(0,0,0,.6);
}

*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
html { scroll-behavior:smooth; }
body { font-family:'DM Sans',sans-serif; background:var(--bg); color:var(--text); line-height:1.6; min-height:100vh; display:flex; }
::-webkit-scrollbar { width:5px; }
::-webkit-scrollbar-track { background:var(--bg2); }
::-webkit-scrollbar-thumb { background:var(--border2); border-radius:3px; }
a { color:inherit; text-decoration:none; }
img { max-width:100%; }

/* ── Sidebar ─────────────────────────────────────────────── */
.sidebar {
  width:var(--sidebar-w); background:var(--bg2);
  border-right:1px solid var(--border);
  position:fixed; top:0; left:0; height:100vh;
  display:flex; flex-direction:column;
  z-index:100; transition:transform .3s;
  overflow-y:auto;
}
.sidebar-logo {
  padding:1.5rem 1.5rem 1rem;
  border-bottom:1px solid var(--border);
  display:flex; align-items:center; gap:.75rem;
}
.logo-icon {
  width:38px; height:38px; border-radius:10px;
  background:linear-gradient(135deg, var(--accent) 0%, var(--accent2) 100%);
  display:flex; align-items:center; justify-content:center;
  font-size:1.1rem; font-weight:800; color:#000;
  font-family:'Syne',sans-serif;
}
.logo-text { font-family:'Syne',sans-serif; font-size:1.1rem; font-weight:800; }
.logo-text span { color:var(--accent); }
.logo-sub  { font-size:.68rem; color:var(--muted); text-transform:uppercase; letter-spacing:.1em; }

.nav-section { padding:.75rem 1rem; }
.nav-section-label { font-size:.68rem; color:var(--muted); text-transform:uppercase; letter-spacing:.12em; font-weight:600; padding:.5rem .5rem .25rem; }
.nav-item {
  display:flex; align-items:center; gap:.75rem;
  padding:.6rem .75rem; border-radius:var(--radius-sm);
  color:var(--muted); font-size:.88rem; font-weight:500;
  cursor:pointer; transition:all .2s; margin-bottom:2px;
  text-decoration:none;
}
.nav-item:hover { background:var(--bg3); color:var(--text); }
.nav-item.active { background:rgba(74,222,128,.1); color:var(--accent); border:1px solid rgba(74,222,128,.2); }
.nav-item .nav-icon { width:20px; text-align:center; font-size:1rem; flex-shrink:0; }
.nav-badge { margin-left:auto; background:var(--red); color:white; font-size:.65rem; font-weight:700; padding:.15rem .5rem; border-radius:50px; min-width:20px; text-align:center; }
.nav-badge.green { background:var(--accent); color:#000; }

.sidebar-footer {
  margin-top:auto; padding:1rem 1.25rem;
  border-top:1px solid var(--border);
}
.admin-info { display:flex; align-items:center; gap:.75rem; }
.admin-avatar {
  width:36px; height:36px; border-radius:50%;
  background:linear-gradient(135deg,var(--accent),var(--accent2));
  display:flex; align-items:center; justify-content:center;
  font-weight:800; font-size:.88rem; color:#000; flex-shrink:0;
}
.admin-name  { font-size:.85rem; font-weight:600; }
.admin-role  { font-size:.72rem; color:var(--accent); text-transform:uppercase; letter-spacing:.08em; }

/* ── Main layout ─────────────────────────────────────────── */
.main { margin-left:var(--sidebar-w); flex:1; min-height:100vh; display:flex; flex-direction:column; }

.topbar {
  height:var(--header-h); background:var(--bg2);
  border-bottom:1px solid var(--border);
  display:flex; align-items:center; justify-content:space-between;
  padding:0 2rem; position:sticky; top:0; z-index:90;
  gap:1rem;
}
.topbar-title { font-family:'Syne',sans-serif; font-size:1.1rem; font-weight:700; }
.topbar-right { display:flex; align-items:center; gap:1rem; }
.topbar-btn {
  background:var(--bg3); border:1px solid var(--border);
  color:var(--muted); padding:.45rem .9rem; border-radius:var(--radius-sm);
  font-family:'DM Sans',sans-serif; font-size:.82rem; font-weight:500;
  cursor:pointer; transition:all .2s; display:flex; align-items:center; gap:.4rem;
  text-decoration:none;
}
.topbar-btn:hover { border-color:var(--border2); color:var(--text); }
.topbar-btn.primary { background:var(--accent); color:#000; border-color:var(--accent); font-weight:600; }
.topbar-btn.primary:hover { background:#22c55e; }

.content { padding:2rem; flex:1; }

/* ── Alert ───────────────────────────────────────────────── */
.alert {
  padding:.85rem 1.25rem; border-radius:var(--radius-sm);
  margin-bottom:1.5rem; font-size:.88rem; font-weight:500;
  display:flex; align-items:center; gap:.6rem; animation:slideIn .3s;
}
.alert-success { background:rgba(74,222,128,.08); border:1px solid rgba(74,222,128,.25); color:var(--accent); }
.alert-error   { background:rgba(248,113,113,.08); border:1px solid rgba(248,113,113,.25); color:var(--red); }
@keyframes slideIn { from{opacity:0;transform:translateY(-8px)} to{opacity:1;transform:translateY(0)} }

/* ── Stat cards ──────────────────────────────────────────── */
.stat-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:1rem; margin-bottom:2rem; }
.stat-card {
  background:var(--bg2); border:1px solid var(--border);
  border-radius:var(--radius); padding:1.25rem 1.5rem;
  position:relative; overflow:hidden; transition:border-color .2s;
}
.stat-card:hover { border-color:var(--border2); }
.stat-card::before {
  content:''; position:absolute; top:0; left:0; right:0; height:2px;
  background:var(--stat-color, var(--accent));
}
.stat-icon { font-size:1.75rem; margin-bottom:.5rem; display:block; }
.stat-val  { font-family:'Syne',sans-serif; font-size:2rem; font-weight:800; color:var(--text); display:block; line-height:1.1; }
.stat-lbl  { font-size:.78rem; color:var(--muted); text-transform:uppercase; letter-spacing:.06em; margin-top:.2rem; }
.stat-trend { position:absolute; top:1rem; right:1rem; font-size:.75rem; font-weight:600; padding:.2rem .5rem; border-radius:50px; }
.stat-trend.up   { background:rgba(74,222,128,.1);   color:var(--accent); }
.stat-trend.down { background:rgba(248,113,113,.1); color:var(--red); }

/* ── Cards ───────────────────────────────────────────────── */
.card {
  background:var(--bg2); border:1px solid var(--border);
  border-radius:var(--radius); overflow:hidden; margin-bottom:1.5rem;
}
.card-header {
  padding:1.1rem 1.5rem; border-bottom:1px solid var(--border);
  display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap;
}
.card-title { font-family:'Syne',sans-serif; font-size:1rem; font-weight:700; }
.card-body  { padding:1.5rem; }

/* ── Table ───────────────────────────────────────────────── */
.table-wrap { overflow-x:auto; }
table { width:100%; border-collapse:collapse; font-size:.85rem; }
th {
  background:var(--bg3); padding:.75rem 1rem; text-align:left;
  font-size:.72rem; text-transform:uppercase; letter-spacing:.08em;
  color:var(--muted); border-bottom:1px solid var(--border); white-space:nowrap;
  font-weight:600;
}
td { padding:.8rem 1rem; border-bottom:1px solid var(--border); vertical-align:middle; }
tr:last-child td { border-bottom:none; }
tr:hover td { background:rgba(255,255,255,.02); }

/* ── Badges ──────────────────────────────────────────────── */
.badge {
  display:inline-flex; align-items:center; gap:.3rem;
  padding:.2rem .65rem; border-radius:50px; font-size:.72rem; font-weight:700;
}
.badge-green   { background:rgba(74,222,128,.1);   color:var(--accent);  border:1px solid rgba(74,222,128,.2); }
.badge-red     { background:rgba(248,113,113,.1); color:var(--red);    border:1px solid rgba(248,113,113,.2); }
.badge-amber   { background:rgba(251,191,36,.1);  color:var(--amber);  border:1px solid rgba(251,191,36,.2); }
.badge-cyan    { background:rgba(34,211,238,.1);  color:var(--accent2);border:1px solid rgba(34,211,238,.2); }
.badge-purple  { background:rgba(167,139,250,.1); color:var(--purple); border:1px solid rgba(167,139,250,.2); }
.badge-muted   { background:rgba(122,128,153,.1); color:var(--muted);  border:1px solid rgba(122,128,153,.2); }

/* ── Buttons in table ────────────────────────────────────── */
.btn-t {
  padding:.3rem .7rem; border-radius:6px; border:1px solid;
  font-family:'DM Sans',sans-serif; font-size:.75rem; font-weight:600;
  cursor:pointer; transition:all .2s; display:inline-flex; align-items:center; gap:.3rem;
  text-decoration:none;
}
.btn-t-green  { border-color:rgba(74,222,128,.3); color:var(--accent); background:rgba(74,222,128,.05); }
.btn-t-green:hover { background:rgba(74,222,128,.15); }
.btn-t-red    { border-color:rgba(248,113,113,.3); color:var(--red); background:rgba(248,113,113,.05); }
.btn-t-red:hover { background:rgba(248,113,113,.15); }
.btn-t-amber  { border-color:rgba(251,191,36,.3); color:var(--amber); background:rgba(251,191,36,.05); }
.btn-t-amber:hover { background:rgba(251,191,36,.15); }
.btn-t-cyan   { border-color:rgba(34,211,238,.3); color:var(--accent2); background:rgba(34,211,238,.05); }
.btn-t-cyan:hover { background:rgba(34,211,238,.15); }
.btn-t-muted  { border-color:var(--border); color:var(--muted); background:transparent; }
.btn-t-muted:hover { color:var(--text); background:var(--bg3); }

/* ── Avatar ──────────────────────────────────────────────── */
.av {
  width:34px; height:34px; border-radius:50%;
  display:inline-flex; align-items:center; justify-content:center;
  font-weight:700; font-size:.85rem; flex-shrink:0;
}

/* ── Search & filter bar ─────────────────────────────────── */
.filter-bar {
  display:flex; align-items:center; gap:.75rem; flex-wrap:wrap; margin-bottom:1.5rem;
}
.search-input {
  flex:1; min-width:200px; background:var(--bg3); border:1px solid var(--border);
  border-radius:var(--radius-sm); padding:.6rem 1rem .6rem 2.4rem;
  color:var(--text); font-family:'DM Sans',sans-serif; font-size:.88rem;
  transition:border-color .2s;
}
.search-input:focus { outline:none; border-color:var(--accent); }
.search-wrap-inner { position:relative; flex:1; }
.search-ico { position:absolute; left:.8rem; top:50%; transform:translateY(-50%); font-size:.9rem; color:var(--muted); pointer-events:none; }
.filter-select {
  background:var(--bg3); border:1px solid var(--border);
  border-radius:var(--radius-sm); padding:.6rem .9rem; color:var(--text);
  font-family:'DM Sans',sans-serif; font-size:.85rem; cursor:pointer;
}
.filter-select:focus { outline:none; border-color:var(--accent); }

/* ── Modal ───────────────────────────────────────────────── */
.overlay {
  display:none; position:fixed; inset:0;
  background:rgba(0,0,0,.7); z-index:999;
  align-items:center; justify-content:center; padding:1rem;
  backdrop-filter:blur(4px);
}
.overlay.open { display:flex; animation:fadeIn .2s; }
.modal {
  background:var(--bg2); border:1px solid var(--border);
  border-radius:calc(var(--radius)*1.3); width:100%; max-width:520px;
  max-height:90vh; overflow-y:auto;
  animation:modalUp .25s ease; box-shadow:var(--shadow-lg);
}
.modal-header {
  padding:1.5rem 1.75rem 1.1rem; border-bottom:1px solid var(--border);
  display:flex; justify-content:space-between; align-items:center;
}
.modal-title { font-family:'Syne',sans-serif; font-size:1.1rem; font-weight:700; }
.modal-close { background:var(--bg3); border:1px solid var(--border); color:var(--muted); width:32px; height:32px; border-radius:8px; cursor:pointer; font-size:1rem; display:flex; align-items:center; justify-content:center; transition:all .2s; }
.modal-close:hover { color:var(--text); }
.modal-body { padding:1.75rem; }
@keyframes fadeIn  { from{opacity:0} to{opacity:1} }
@keyframes modalUp { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }

/* ── Form elements ───────────────────────────────────────── */
.form-group { margin-bottom:1.1rem; }
.form-label { display:block; font-size:.8rem; font-weight:600; color:var(--muted); text-transform:uppercase; letter-spacing:.06em; margin-bottom:.4rem; }
.form-control {
  width:100%; padding:.7rem 1rem; background:var(--bg3);
  border:1px solid var(--border); border-radius:var(--radius-sm);
  color:var(--text); font-family:'DM Sans',sans-serif; font-size:.9rem;
  transition:border-color .2s;
}
.form-control:focus { outline:none; border-color:var(--accent); box-shadow:0 0 0 2px rgba(74,222,128,.1); }
textarea.form-control { resize:vertical; min-height:90px; }
.form-row { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
.form-check { display:flex; align-items:center; gap:.6rem; cursor:pointer; font-size:.88rem; color:var(--text); }
.form-check input { width:16px; height:16px; accent-color:var(--accent); cursor:pointer; }

/* ── Submit button ───────────────────────────────────────── */
.btn-submit {
  width:100%; background:var(--accent); color:#000; border:none;
  padding:.8rem; border-radius:var(--radius-sm); font-family:'DM Sans',sans-serif;
  font-size:.95rem; font-weight:700; cursor:pointer; transition:all .2s; margin-top:.5rem;
}
.btn-submit:hover { background:#22c55e; }

/* ── Charts ──────────────────────────────────────────────── */
.chart-bar { display:flex; align-items:flex-end; gap:.4rem; height:80px; margin-top:.75rem; }
.chart-col { flex:1; display:flex; flex-direction:column; align-items:center; gap:.25rem; }
.chart-bar-fill { width:100%; border-radius:4px 4px 0 0; background:var(--accent); opacity:.7; transition:opacity .2s; min-height:2px; }
.chart-bar-fill:hover { opacity:1; }
.chart-bar-lbl { font-size:.6rem; color:var(--muted); }

/* ── Stars ───────────────────────────────────────────────── */
.stars { color:var(--amber); letter-spacing:1px; }

/* ── Toggle ──────────────────────────────────────────────── */
.toggle { position:relative; display:inline-flex; width:36px; height:20px; }
.toggle input { opacity:0; width:0; height:0; }
.toggle-slider { position:absolute; cursor:pointer; inset:0; background:var(--border2); border-radius:10px; transition:.3s; }
.toggle-slider::before { content:''; position:absolute; height:14px; width:14px; left:3px; bottom:3px; background:var(--muted); border-radius:50%; transition:.3s; }
.toggle input:checked + .toggle-slider { background:rgba(74,222,128,.3); }
.toggle input:checked + .toggle-slider::before { transform:translateX(16px); background:var(--accent); }

/* ── Pagination ──────────────────────────────────────────── */
.pagination { display:flex; justify-content:center; gap:.5rem; margin-top:1.5rem; flex-wrap:wrap; }
.page-btn { width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:.85rem; font-weight:600; border:1px solid var(--border); background:var(--bg3); color:var(--muted); cursor:pointer; transition:all .2s; text-decoration:none; }
.page-btn:hover, .page-btn.active { background:var(--accent); color:#000; border-color:var(--accent); }

/* ── Grid layout ─────────────────────────────────────────── */
.grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:1.5rem; }
.grid-3 { display:grid; grid-template-columns:repeat(3,1fr); gap:1rem; }

/* ── Mini bar chart ──────────────────────────────────────── */
.mini-bar { display:flex; gap:2px; align-items:flex-end; height:40px; }
.mini-bar-col { flex:1; background:rgba(74,222,128,.3); border-radius:2px; min-height:2px; }

/* ── Responsive ──────────────────────────────────────────── */
@media (max-width:900px) {
  .sidebar { transform:translateX(-100%); }
  .sidebar.open { transform:translateX(0); }
  .main { margin-left:0; }
  .grid-2 { grid-template-columns:1fr; }
  .form-row { grid-template-columns:1fr; }
}
</style>
</head>
<body>

<!-- ============================================================
     SIDEBAR
     ============================================================ -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <div class="logo-icon">SL</div>
    <div>
      <div class="logo-text">Servi<span>•</span>Local</div>
      <div class="logo-sub">Administration</div>
    </div>
  </div>

  <nav class="nav-section">
    <div class="nav-section-label">Principal</div>
    <a href="?action=dashboard" class="nav-item <?= $action==='dashboard'?'active':'' ?>">
      <span class="nav-icon">📊</span> Tableau de bord
    </a>
    <a href="?action=users" class="nav-item <?= $action==='users'?'active':'' ?>">
      <span class="nav-icon">👥</span> Utilisateurs
      <span class="nav-badge green"><?= $stats['users'] ?></span>
    </a>
    <a href="?action=providers" class="nav-item <?= $action==='providers'?'active':'' ?>">
      <span class="nav-icon">🔧</span> Prestataires
      <span class="nav-badge"><?= $stats['providers_count'] ?></span>
    </a>
    <a href="?action=bookings" class="nav-item <?= $action==='bookings'?'active':'' ?>">
      <span class="nav-icon">📅</span> Réservations
      <?php if ($stats['pending'] > 0): ?>
        <span class="nav-badge"><?= $stats['pending'] ?></span>
      <?php endif; ?>
    </a>
    <a href="?action=reviews" class="nav-item <?= $action==='reviews'?'active':'' ?>">
      <span class="nav-icon">⭐</span> Avis
      <span class="nav-badge green"><?= $stats['reviews'] ?></span>
    </a>

    <div class="nav-section-label" style="margin-top:1rem">Autres</div>
    <a href="?action=add_user" class="nav-item <?= $action==='add_user'?'active':'' ?>">
      <span class="nav-icon">➕</span> Ajouter utilisateur
    </a>
    <a href="/servilocal/index.php" class="nav-item" target="_blank">
      <span class="nav-icon">🌐</span> Voir le site
    </a>
  </nav>

  <div class="sidebar-footer">
    <div class="admin-info">
      <div class="admin-avatar"><?= strtoupper(substr($adminName, 0, 1)) ?></div>
      <div>
        <div class="admin-name"><?= htmlspecialchars($adminName) ?></div>
        <div class="admin-role">Administrateur</div>
      </div>
    </div>
    <a href="/servilocal/logout.php" style="display:flex;align-items:center;gap:.5rem;color:var(--muted);font-size:.8rem;margin-top:.75rem;padding:.4rem 0;transition:color .2s" onmouseover="this.style.color='var(--red)'" onmouseout="this.style.color='var(--muted)'">
      🚪 Déconnexion
    </a>
  </div>
</aside>

<!-- ============================================================
     MAIN CONTENT
     ============================================================ -->
<div class="main">

  <!-- Topbar -->
  <div class="topbar">
    <div style="display:flex;align-items:center;gap:1rem">
      <button onclick="document.getElementById('sidebar').classList.toggle('open')" style="display:none;background:none;border:none;color:var(--text);font-size:1.3rem;cursor:pointer" id="burgerAdmin">☰</button>
      <div class="topbar-title">
        <?php
        $titles = ['dashboard'=>'Tableau de bord','users'=>'Gestion des Utilisateurs','providers'=>'Gestion des Prestataires','bookings'=>'Gestion des Réservations','reviews'=>'Gestion des Avis','add_user'=>'Ajouter un Utilisateur'];
        echo $titles[$action] ?? 'Administration';
        ?>
      </div>
    </div>
    <div class="topbar-right">
      <span style="font-size:.8rem;color:var(--muted)">
        <?= date('l d F Y') ?>
      </span>
      <a href="?action=add_user" class="topbar-btn primary">➕ Ajouter</a>
    </div>
  </div>

  <!-- Content -->
  <div class="content">

    <?php if ($msg): ?>
      <div class="alert alert-<?= $msgType === 'error' ? 'error' : 'success' ?>"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <!-- ========================================================
         DASHBOARD
         ======================================================== -->
    <?php if ($action === 'dashboard'): ?>

      <!-- Stat cards -->
      <div class="stat-grid">
        <div class="stat-card" style="--stat-color:var(--accent)">
          <span class="stat-icon">👥</span>
          <span class="stat-val"><?= $stats['users'] ?></span>
          <div class="stat-lbl">Utilisateurs total</div>
        </div>
        <div class="stat-card" style="--stat-color:var(--accent2)">
          <span class="stat-icon">🔧</span>
          <span class="stat-val"><?= $stats['providers_count'] ?></span>
          <div class="stat-lbl">Prestataires</div>
          <span class="stat-trend up">✓ <?= $stats['verified'] ?> vérifiés</span>
        </div>
        <div class="stat-card" style="--stat-color:var(--amber)">
          <span class="stat-icon">📅</span>
          <span class="stat-val"><?= $stats['bookings'] ?></span>
          <div class="stat-lbl">Réservations</div>
          <span class="stat-trend up"><?= $stats['pending'] ?> en attente</span>
        </div>
        <div class="stat-card" style="--stat-color:var(--purple)">
          <span class="stat-icon">⭐</span>
          <span class="stat-val"><?= $stats['avg_rating'] ?></span>
          <div class="stat-lbl">Note moyenne</div>
          <span class="stat-trend up"><?= $stats['reviews'] ?> avis</span>
        </div>
        <div class="stat-card" style="--stat-color:var(--accent)">
          <span class="stat-icon">✅</span>
          <span class="stat-val"><?= $stats['completed'] ?></span>
          <div class="stat-lbl">Services complétés</div>
        </div>
        <div class="stat-card" style="--stat-color:var(--amber)">
          <span class="stat-icon">💰</span>
          <span class="stat-val"><?= number_format($stats['revenue_est']) ?></span>
          <div class="stat-lbl">Volume DH (estimé)</div>
        </div>
      </div>

      <div class="grid-2">
        <!-- Activité hebdomadaire -->
        <div class="card">
          <div class="card-header">
            <div class="card-title">📈 Activité — 7 derniers jours</div>
          </div>
          <div class="card-body">
            <?php
            $days7 = [];
            for ($i = 6; $i >= 0; $i--) {
              $d = date('Y-m-d', strtotime("-{$i} days"));
              $days7[$d] = 0;
            }
            foreach ($stats['weekly'] as $w) $days7[$w['d']] = (int)$w['cnt'];
            $maxVal = max(array_values($days7)) ?: 1;
            ?>
            <div class="chart-bar">
              <?php foreach ($days7 as $date => $cnt): ?>
                <div class="chart-col" title="<?= date('D d', strtotime($date)) ?> : <?= $cnt ?> résa">
                  <div style="font-size:.6rem;color:var(--accent);font-weight:700;margin-bottom:2px"><?= $cnt ?: '' ?></div>
                  <div class="chart-bar-fill" style="height:<?= round($cnt/$maxVal*60)+4 ?>px"></div>
                  <div class="chart-bar-lbl"><?= date('D', strtotime($date)) ?></div>
                </div>
              <?php endforeach; ?>
            </div>
            <div style="margin-top:1rem;display:flex;gap:1.5rem;font-size:.82rem;color:var(--muted)">
              <span>Total période : <strong style="color:var(--text)"><?= array_sum($days7) ?></strong> réservations</span>
            </div>
          </div>
        </div>

        <!-- Répartition statuts -->
        <div class="card">
          <div class="card-header"><div class="card-title">🎯 Répartition des statuts</div></div>
          <div class="card-body">
            <?php
            $statuses = [
              ['En attente', $stats['pending'],   'var(--amber)', '🕐'],
              ['Acceptées',  $stats['accepted'],   'var(--accent2)','✅'],
              ['Terminées',  $stats['completed'],  'var(--accent)',  '🏁'],
              ['Refusées/Annulées', $stats['bookings'] - $stats['pending'] - $stats['accepted'] - $stats['completed'], 'var(--red)', '❌'],
            ];
            $total = $stats['bookings'] ?: 1;
            ?>
            <?php foreach ($statuses as [$label, $cnt, $color, $icon]): ?>
              <?php $pct = round($cnt/$total*100); ?>
              <div style="margin-bottom:1rem">
                <div style="display:flex;justify-content:space-between;font-size:.85rem;margin-bottom:.3rem">
                  <span><?= $icon ?> <?= $label ?></span>
                  <span style="font-weight:700;color:var(--text)"><?= $cnt ?> <span style="color:var(--muted);font-weight:400">(<?= $pct ?>%)</span></span>
                </div>
                <div style="background:var(--bg3);border-radius:50px;height:6px">
                  <div style="width:<?= $pct ?>%;background:<?= $color ?>;border-radius:50px;height:100%;transition:width .5s"></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div class="grid-2">
        <!-- Top prestataires -->
        <div class="card">
          <div class="card-header">
            <div class="card-title">🏆 Top Prestataires</div>
            <a href="?action=providers" style="font-size:.8rem;color:var(--accent)">Voir tout →</a>
          </div>
          <div class="table-wrap">
            <table>
              <thead><tr><th>#</th><th>Nom</th><th>Catégorie</th><th>Note</th><th>Avis</th></tr></thead>
              <tbody>
                <?php foreach ($stats['top_providers'] as $i => $p): ?>
                  <tr>
                    <td style="color:var(--amber);font-weight:700"><?= $i+1 ?>.</td>
                    <td><strong><?= htmlspecialchars($p['name']) ?></strong><br><span style="font-size:.75rem;color:var(--muted)"><?= htmlspecialchars($p['city']) ?></span></td>
                    <td><span class="badge badge-cyan"><?= htmlspecialchars($p['category']) ?></span></td>
                    <td><span class="stars">★</span> <?= number_format($p['rating'],1) ?></td>
                    <td><?= $p['review_count'] ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Dernières réservations -->
        <div class="card">
          <div class="card-header">
            <div class="card-title">🕐 Dernières réservations</div>
            <a href="?action=bookings" style="font-size:.8rem;color:var(--accent)">Voir tout →</a>
          </div>
          <div class="table-wrap">
            <table>
              <thead><tr><th>Client</th><th>Prestataire</th><th>Date</th><th>Statut</th></tr></thead>
              <tbody>
                <?php foreach ($stats['recent_bookings'] as $b): ?>
                  <?php
                  $sc = ['pending'=>'badge-amber','accepted'=>'badge-cyan','completed'=>'badge-green','refused'=>'badge-red','cancelled'=>'badge-muted'];
                  $sl = ['pending'=>'En attente','accepted'=>'Acceptée','completed'=>'Terminée','refused'=>'Refusée','cancelled'=>'Annulée'];
                  ?>
                  <tr>
                    <td style="font-size:.82rem"><?= htmlspecialchars($b['client']) ?></td>
                    <td style="font-size:.82rem"><?= htmlspecialchars($b['provider']) ?></td>
                    <td style="font-size:.78rem;color:var(--muted)"><?= date('d/m', strtotime($b['booking_date'])) ?></td>
                    <td><span class="badge <?= $sc[$b['status']] ?? 'badge-muted' ?>"><?= $sl[$b['status']] ?? $b['status'] ?></span></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    <!-- ========================================================
         USERS
         ======================================================== -->
    <?php elseif ($action === 'users'): ?>

      <form method="GET">
        <input type="hidden" name="action" value="users">
        <div class="filter-bar">
          <div class="search-wrap-inner">
            <span class="search-ico">🔍</span>
            <input type="text" name="q" class="search-input" placeholder="Rechercher par nom ou email…" value="<?= htmlspecialchars($search) ?>">
          </div>
          <select name="role" class="filter-select" onchange="this.form.submit()">
            <option value="">Tous les rôles</option>
            <option value="client"   <?= ($_GET['role']??'')=='client'   ?'selected':'' ?>>Clients</option>
            <option value="provider" <?= ($_GET['role']??'')=='provider' ?'selected':'' ?>>Prestataires</option>
            <option value="admin"    <?= ($_GET['role']??'')=='admin'    ?'selected':'' ?>>Admins</option>
          </select>
          <button type="submit" class="topbar-btn primary">Filtrer</button>
          <a href="?action=users" class="topbar-btn">Réinitialiser</a>
        </div>
      </form>

      <div class="card">
        <div class="card-header">
          <div class="card-title">👥 Utilisateurs <span style="color:var(--muted);font-weight:400">(<?= count($users) ?>)</span></div>
          <a href="?action=add_user" class="topbar-btn primary">➕ Ajouter</a>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>#</th><th>Utilisateur</th><th>Email</th><th>Téléphone</th>
                <th>Rôle</th><th>Réservations</th><th>Statut</th><th>Inscrit le</th><th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($users as $u): ?>
                <?php
                $roleColors = ['client'=>'badge-cyan','provider'=>'badge-green','admin'=>'badge-purple'];
                $roleIcons  = ['client'=>'👤','provider'=>'🔧','admin'=>'👑'];
                ?>
                <tr>
                  <td style="color:var(--muted);font-size:.78rem">#<?= $u['id'] ?></td>
                  <td>
                    <div style="display:flex;align-items:center;gap:.75rem">
                      <div class="av" style="background:<?= $u['role']==='admin' ? 'var(--purple)' : ($u['role']==='provider' ? 'var(--accent)' : 'var(--accent2)') ?>;color:#000">
                        <?= strtoupper(substr($u['name'],0,1)) ?>
                      </div>
                      <div>
                        <div style="font-weight:600;font-size:.88rem"><?= htmlspecialchars($u['name']) ?></div>
                      </div>
                    </div>
                  </td>
                  <td style="font-size:.83rem;color:var(--muted)"><?= htmlspecialchars($u['email']) ?></td>
                  <td style="font-size:.83rem"><?= htmlspecialchars($u['phone'] ?? '—') ?></td>
                  <td><span class="badge <?= $roleColors[$u['role']] ?? 'badge-muted' ?>"><?= $roleIcons[$u['role']] ?? '' ?> <?= $u['role'] ?></span></td>
                  <td style="text-align:center"><?= $u['booking_cnt'] ?></td>
                  <td>
                    <span class="badge <?= $u['is_active'] ? 'badge-green' : 'badge-red' ?>">
                      <?= $u['is_active'] ? '● Actif' : '● Inactif' ?>
                    </span>
                  </td>
                  <td style="font-size:.78rem;color:var(--muted)"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                  <td>
                    <div style="display:flex;gap:.4rem;flex-wrap:wrap">
                      <button class="btn-t btn-t-amber" onclick='openEditUser(<?= json_encode($u) ?>)'>✏️ Modifier</button>
                      <a href="?action=toggle_user&id=<?= $u['id'] ?>" class="btn-t btn-t-cyan"
                         onclick="return confirm('Changer le statut de cet utilisateur ?')">
                        <?= $u['is_active'] ? '🔒 Désactiver' : '🔓 Activer' ?>
                      </a>
                      <?php if ($u['id'] != $_SESSION['user_id']): ?>
                        <a href="?action=delete_user&id=<?= $u['id'] ?>" class="btn-t btn-t-red"
                           onclick="return confirm('Supprimer définitivement cet utilisateur ?')">🗑️</a>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    <!-- ========================================================
         PROVIDERS
         ======================================================== -->
    <?php elseif ($action === 'providers'): ?>

      <form method="GET">
        <input type="hidden" name="action" value="providers">
        <div class="filter-bar">
          <div class="search-wrap-inner">
            <span class="search-ico">🔍</span>
            <input type="text" name="q" class="search-input" placeholder="Rechercher par nom ou ville…" value="<?= htmlspecialchars($search) ?>">
          </div>
          <select name="cat" class="filter-select">
            <option value="">Toutes catégories</option>
            <?php foreach ($categories_list as $cat): ?>
              <option value="<?= $cat ?>" <?= ($_GET['cat']??'')===$cat?'selected':'' ?>><?= ucfirst($cat) ?></option>
            <?php endforeach; ?>
          </select>
          <select name="ver" class="filter-select">
            <option value="">Tous</option>
            <option value="1" <?= ($_GET['ver']??'')==='1'?'selected':'' ?>>✓ Vérifiés</option>
            <option value="0" <?= ($_GET['ver']??'')==='0'?'selected':'' ?>>⏳ Non vérifiés</option>
          </select>
          <button type="submit" class="topbar-btn primary">Filtrer</button>
          <a href="?action=providers" class="topbar-btn">Réinitialiser</a>
        </div>
      </form>

      <div class="card">
        <div class="card-header">
          <div class="card-title">🔧 Prestataires <span style="color:var(--muted);font-weight:400">(<?= count($providers) ?>)</span></div>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>#</th><th>Prestataire</th><th>Catégorie</th><th>Ville</th>
                <th>Tarif</th><th>Note</th><th>Avis</th><th>Vérifié</th><th>Dispo</th><th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($providers as $p): ?>
                <tr>
                  <td style="color:var(--muted);font-size:.78rem">#<?= $p['id'] ?></td>
                  <td>
                    <div style="display:flex;align-items:center;gap:.7rem">
                      <div class="av" style="background:<?= htmlspecialchars($p['avatar_color']) ?>;color:white">
                        <?= strtoupper(substr($p['name'],0,1)) ?>
                      </div>
                      <div>
                        <div style="font-weight:600;font-size:.85rem"><?= htmlspecialchars($p['name']) ?></div>
                        <div style="font-size:.73rem;color:var(--muted)"><?= htmlspecialchars($p['email']) ?></div>
                      </div>
                    </div>
                  </td>
                  <td><span class="badge badge-cyan"><?= htmlspecialchars($p['category']) ?></span></td>
                  <td style="font-size:.83rem">📍 <?= htmlspecialchars($p['city']) ?></td>
                  <td style="font-size:.83rem;color:var(--accent)"><?= htmlspecialchars($p['price']) ?></td>
                  <td>
                    <span class="stars">★</span>
                    <strong><?= number_format($p['rating'],1) ?></strong>
                  </td>
                  <td><?= $p['review_count'] ?></td>
                  <td>
                    <span class="badge <?= $p['is_verified'] ? 'badge-green' : 'badge-amber' ?>">
                      <?= $p['is_verified'] ? '✓ Oui' : '⏳ Non' ?>
                    </span>
                  </td>
                  <td>
                    <span class="badge <?= $p['is_available'] ? 'badge-green' : 'badge-red' ?>">
                      <?= $p['is_available'] ? '● Oui' : '● Non' ?>
                    </span>
                  </td>
                  <td>
                    <div style="display:flex;gap:.4rem;flex-wrap:wrap">
                      <button class="btn-t btn-t-amber" onclick='openEditProvider(<?= json_encode($p) ?>)'>✏️ Modifier</button>
                      <a href="?action=toggle_verify&id=<?= $p['id'] ?>" class="btn-t btn-t-green"
                         onclick="return confirm('Changer le statut de vérification ?')">
                        <?= $p['is_verified'] ? '✗ Dévérifier' : '✓ Vérifier' ?>
                      </a>
                      <a href="/servilocal/provider.php?id=<?= $p['id'] ?>" class="btn-t btn-t-cyan" target="_blank">👁</a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    <!-- ========================================================
         BOOKINGS
         ======================================================== -->
    <?php elseif ($action === 'bookings'): ?>

      <form method="GET">
        <input type="hidden" name="action" value="bookings">
        <div class="filter-bar">
          <div class="search-wrap-inner">
            <span class="search-ico">🔍</span>
            <input type="text" name="q" class="search-input" placeholder="Rechercher client ou prestataire…" value="<?= htmlspecialchars($search) ?>">
          </div>
          <select name="status" class="filter-select">
            <option value="">Tous les statuts</option>
            <option value="pending"   <?= ($_GET['status']??'')==='pending'   ?'selected':'' ?>>🕐 En attente</option>
            <option value="accepted"  <?= ($_GET['status']??'')==='accepted'  ?'selected':'' ?>>✅ Acceptées</option>
            <option value="refused"   <?= ($_GET['status']??'')==='refused'   ?'selected':'' ?>>❌ Refusées</option>
            <option value="completed" <?= ($_GET['status']??'')==='completed' ?'selected':'' ?>>🏁 Terminées</option>
            <option value="cancelled" <?= ($_GET['status']??'')==='cancelled' ?'selected':'' ?>>🚫 Annulées</option>
          </select>
          <button type="submit" class="topbar-btn primary">Filtrer</button>
          <a href="?action=bookings" class="topbar-btn">Réinitialiser</a>
        </div>
      </form>

      <div class="card">
        <div class="card-header">
          <div class="card-title">📅 Réservations <span style="color:var(--muted);font-weight:400">(<?= count($bookings) ?>)</span></div>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>#</th><th>Client</th><th>Prestataire</th><th>Service</th>
                <th>Date</th><th>Heure</th><th>Adresse</th><th>Statut</th><th>Créé le</th><th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $sc = ['pending'=>'badge-amber','accepted'=>'badge-cyan','completed'=>'badge-green','refused'=>'badge-red','cancelled'=>'badge-muted'];
              $sl = ['pending'=>'🕐 En attente','accepted'=>'✅ Acceptée','completed'=>'🏁 Terminée','refused'=>'❌ Refusée','cancelled'=>'🚫 Annulée'];
              ?>
              <?php foreach ($bookings as $b): ?>
                <tr>
                  <td style="color:var(--muted);font-size:.78rem">#<?= $b['id'] ?></td>
                  <td style="font-size:.83rem"><strong><?= htmlspecialchars($b['client_name']) ?></strong></td>
                  <td style="font-size:.83rem"><?= htmlspecialchars($b['provider_name']) ?></td>
                  <td><span class="badge badge-cyan" style="font-size:.68rem"><?= htmlspecialchars($b['category']) ?></span></td>
                  <td style="font-size:.82rem"><?= date('d/m/Y', strtotime($b['booking_date'])) ?></td>
                  <td style="font-size:.82rem"><?= substr($b['booking_time'],0,5) ?></td>
                  <td style="font-size:.78rem;color:var(--muted);max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= htmlspecialchars($b['address']??'') ?>"><?= htmlspecialchars(mb_substr($b['address']??'—',0,30)) ?></td>
                  <td><span class="badge <?= $sc[$b['status']] ?? 'badge-muted' ?>"><?= $sl[$b['status']] ?? $b['status'] ?></span></td>
                  <td style="font-size:.75rem;color:var(--muted)"><?= date('d/m/Y', strtotime($b['created_at'])) ?></td>
                  <td>
                    <div style="display:flex;gap:.4rem">
                      <button class="btn-t btn-t-amber" onclick='openEditBooking(<?= json_encode($b) ?>)'>✏️ Statut</button>
                      <a href="?action=delete_booking&id=<?= $b['id'] ?>" class="btn-t btn-t-red"
                         onclick="return confirm('Supprimer cette réservation ?')">🗑️</a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    <!-- ========================================================
         REVIEWS
         ======================================================== -->
    <?php elseif ($action === 'reviews'): ?>

      <form method="GET">
        <input type="hidden" name="action" value="reviews">
        <div class="filter-bar">
          <div class="search-wrap-inner">
            <span class="search-ico">🔍</span>
            <input type="text" name="q" class="search-input" placeholder="Rechercher dans les avis…" value="<?= htmlspecialchars($search) ?>">
          </div>
          <button type="submit" class="topbar-btn primary">Rechercher</button>
          <a href="?action=reviews" class="topbar-btn">Réinitialiser</a>
        </div>
      </form>

      <div class="card">
        <div class="card-header">
          <div class="card-title">⭐ Avis <span style="color:var(--muted);font-weight:400">(<?= count($reviews) ?>)</span></div>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>#</th><th>Client</th><th>Prestataire</th><th>Catégorie</th>
                <th>Note</th><th>Commentaire</th><th>Date</th><th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($reviews as $r): ?>
                <tr>
                  <td style="color:var(--muted);font-size:.78rem">#<?= $r['id'] ?></td>
                  <td style="font-size:.83rem"><strong><?= htmlspecialchars($r['client_name']) ?></strong></td>
                  <td style="font-size:.83rem"><?= htmlspecialchars($r['provider_name']) ?></td>
                  <td><span class="badge badge-cyan" style="font-size:.68rem"><?= htmlspecialchars($r['category']) ?></span></td>
                  <td>
                    <span class="stars"><?= str_repeat('★', $r['rating']) ?></span>
                    <span style="color:var(--muted);font-size:.75rem"><?= str_repeat('☆', 5-$r['rating']) ?></span>
                    <strong style="font-size:.85rem;margin-left:.3rem"><?= $r['rating'] ?>/5</strong>
                  </td>
                  <td style="font-size:.82rem;color:var(--muted);max-width:220px">
                    <?= htmlspecialchars(mb_substr($r['comment'] ?? '—', 0, 80)) ?><?= mb_strlen($r['comment']??'') > 80 ? '…' : '' ?>
                  </td>
                  <td style="font-size:.75rem;color:var(--muted)"><?= date('d/m/Y', strtotime($r['created_at'])) ?></td>
                  <td>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer cet avis ?')">
                      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                      <input type="hidden" name="rid" value="<?= $r['id'] ?>">
                      <input type="hidden" name="provider_id" value="<?= $r['pid'] ?>">
                      <input type="hidden" name="delete_review" value="1">
                      <button type="submit" class="btn-t btn-t-red">🗑️ Supprimer</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    <!-- ========================================================
         ADD USER
         ======================================================== -->
    <?php elseif ($action === 'add_user'): ?>

      <div style="max-width:540px">
        <div class="card">
          <div class="card-header"><div class="card-title">➕ Créer un nouvel utilisateur</div></div>
          <div class="card-body">
            <form method="POST">
              <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
              <input type="hidden" name="add_user" value="1">
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">Nom complet *</label>
                  <input type="text" name="name" class="form-control" required placeholder="Prénom Nom">
                </div>
                <div class="form-group">
                  <label class="form-label">Téléphone</label>
                  <input type="tel" name="phone" class="form-control" placeholder="0600000000">
                </div>
              </div>
              <div class="form-group">
                <label class="form-label">Email *</label>
                <input type="email" name="email" class="form-control" required placeholder="exemple@email.com">
              </div>
              <div class="form-group">
                <label class="form-label">Mot de passe *</label>
                <input type="password" name="password" class="form-control" required placeholder="Min. 6 caractères">
              </div>
              <div class="form-group">
                <label class="form-label">Rôle *</label>
                <select name="role" class="form-control" required>
                  <option value="client">👤 Client</option>
                  <option value="provider">🔧 Prestataire</option>
                  <option value="admin">👑 Administrateur</option>
                </select>
              </div>
              <button type="submit" class="btn-submit">✅ Créer l'utilisateur</button>
            </form>
          </div>
        </div>

        <!-- Info admin SQL -->
        <div class="card" style="border-color:rgba(251,191,36,.2)">
          <div class="card-header"><div class="card-title" style="color:var(--amber)">💡 Créer un admin via SQL</div></div>
          <div class="card-body">
            <p style="font-size:.85rem;color:var(--muted);margin-bottom:.75rem">Pour promouvoir un utilisateur existant :</p>
            <pre style="background:var(--bg3);border:1px solid var(--border);border-radius:8px;padding:1rem;font-size:.8rem;color:var(--accent2);overflow-x:auto">UPDATE users SET role='admin' WHERE email='votre@email.com';</pre>
          </div>
        </div>
      </div>

    <?php endif; ?>

  </div><!-- /.content -->
</div><!-- /.main -->

<!-- ============================================================
     MODALS
     ============================================================ -->

<!-- Edit User Modal -->
<div class="overlay" id="editUserModal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">✏️ Modifier l'utilisateur</div>
      <button class="modal-close" onclick="closeModal('editUserModal')">✕</button>
    </div>
    <div class="modal-body">
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="edit_user" value="1">
        <input type="hidden" name="uid" id="edit_uid">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Nom</label>
            <input type="text" name="name" id="edit_name" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Téléphone</label>
            <input type="tel" name="phone" id="edit_phone" class="form-control">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Email</label>
          <input type="email" name="email" id="edit_email" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">Rôle</label>
          <select name="role" id="edit_role" class="form-control">
            <option value="client">👤 Client</option>
            <option value="provider">🔧 Prestataire</option>
            <option value="admin">👑 Administrateur</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-check">
            <input type="checkbox" name="is_active" id="edit_is_active" value="1">
            Compte actif
          </label>
        </div>
        <button type="submit" class="btn-submit">💾 Enregistrer</button>
      </form>
    </div>
  </div>
</div>

<!-- Edit Provider Modal -->
<div class="overlay" id="editProviderModal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">✏️ Modifier le prestataire</div>
      <button class="modal-close" onclick="closeModal('editProviderModal')">✕</button>
    </div>
    <div class="modal-body">
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="edit_provider" value="1">
        <input type="hidden" name="pid" id="edit_pid">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Catégorie</label>
            <select name="category" id="edit_pcat" class="form-control">
              <?php foreach ($categories_list as $cat): ?>
                <option value="<?= $cat ?>"><?= ucfirst($cat) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Ville</label>
            <input type="text" name="city" id="edit_pcity" class="form-control" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Tarif</label>
          <input type="text" name="price" id="edit_pprice" class="form-control">
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea name="description" id="edit_pdesc" class="form-control"></textarea>
        </div>
        <div class="form-row" style="margin-bottom:1rem">
          <label class="form-check">
            <input type="checkbox" name="is_available" id="edit_pavail" value="1">
            Disponible
          </label>
          <label class="form-check">
            <input type="checkbox" name="is_verified" id="edit_pverif" value="1">
            Vérifié ✓
          </label>
        </div>
        <button type="submit" class="btn-submit">💾 Enregistrer</button>
      </form>
    </div>
  </div>
</div>

<!-- Edit Booking Modal -->
<div class="overlay" id="editBookingModal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">📅 Modifier le statut</div>
      <button class="modal-close" onclick="closeModal('editBookingModal')">✕</button>
    </div>
    <div class="modal-body">
      <div id="booking_info" style="background:var(--bg3);border-radius:8px;padding:1rem;margin-bottom:1.25rem;font-size:.85rem;color:var(--muted)"></div>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="edit_booking" value="1">
        <input type="hidden" name="bid" id="edit_bid">
        <div class="form-group">
          <label class="form-label">Nouveau statut</label>
          <select name="status" id="edit_bstatus" class="form-control">
            <option value="pending">🕐 En attente</option>
            <option value="accepted">✅ Acceptée</option>
            <option value="refused">❌ Refusée</option>
            <option value="completed">🏁 Terminée</option>
            <option value="cancelled">🚫 Annulée</option>
          </select>
        </div>
        <button type="submit" class="btn-submit">💾 Mettre à jour</button>
      </form>
    </div>
  </div>
</div>

<script>
// ── Modal helpers ─────────────────────────────────────────
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.overlay').forEach(o => {
  o.addEventListener('click', e => { if (e.target === o) o.classList.remove('open'); });
});
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') document.querySelectorAll('.overlay.open').forEach(o => o.classList.remove('open'));
});

// ── Edit User ─────────────────────────────────────────────
function openEditUser(u) {
  document.getElementById('edit_uid').value      = u.id;
  document.getElementById('edit_name').value     = u.name;
  document.getElementById('edit_email').value    = u.email;
  document.getElementById('edit_phone').value    = u.phone || '';
  document.getElementById('edit_role').value     = u.role;
  document.getElementById('edit_is_active').checked = u.is_active == 1;
  openModal('editUserModal');
}

// ── Edit Provider ─────────────────────────────────────────
function openEditProvider(p) {
  document.getElementById('edit_pid').value     = p.id;
  document.getElementById('edit_pcat').value    = p.category;
  document.getElementById('edit_pcity').value   = p.city;
  document.getElementById('edit_pprice').value  = p.price || '';
  document.getElementById('edit_pdesc').value   = p.description || '';
  document.getElementById('edit_pavail').checked = p.is_available == 1;
  document.getElementById('edit_pverif').checked = p.is_verified  == 1;
  openModal('editProviderModal');
}

// ── Edit Booking ──────────────────────────────────────────
function openEditBooking(b) {
  document.getElementById('edit_bid').value     = b.id;
  document.getElementById('edit_bstatus').value = b.status;
  document.getElementById('booking_info').innerHTML =
    `<strong style="color:var(--text)">#${b.id}</strong> · 
     ${b.client_name} → ${b.provider_name}<br>
     📅 ${b.booking_date} à ${b.booking_time.substring(0,5)}
     ${b.address ? '<br>📍 '+b.address : ''}`;
  openModal('editBookingModal');
}

// ── Responsive burger ─────────────────────────────────────
const burgerAdmin = document.getElementById('burgerAdmin');
if (window.innerWidth <= 900) {
  burgerAdmin.style.display = 'flex';
}
window.addEventListener('resize', () => {
  burgerAdmin.style.display = window.innerWidth <= 900 ? 'flex' : 'none';
});

// ── Auto-dismiss alerts ───────────────────────────────────
document.querySelectorAll('.alert').forEach(a => {
  setTimeout(() => { a.style.opacity='0'; a.style.transition='.4s'; setTimeout(()=>a.remove(),400); }, 4000);
});
</script>
</body>
</html>