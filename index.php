<?php
// ============================================================
// index.php — EGES Technologies / ServiLocal
// Rapport Final Entrepreneurial — Université Mundiapolis 2025
// ============================================================
require_once 'db.php';
require_once __DIR__ . '/includes/functions.php';

// ── Paramètres GET ────────────────────────────────────────
$searchName = trim($_GET['name'] ?? '');
$searchCity = trim($_GET['city'] ?? '');
$searchCat  = $_GET['cat']    ?? 'tous';
$filter     = $_GET['filter'] ?? 'tous';
$sort       = $_GET['sort']   ?? 'rating';
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = 9;

// ── Requête dynamique PDO ─────────────────────────────────
$where  = ['1=1'];
$params = [];
if ($searchName !== '') {
    $where[]  = '(u.name LIKE ? OR p.category LIKE ?)';
    $params[] = "%{$searchName}%";
    $params[] = "%{$searchName}%";
}
if ($searchCity !== '') { $where[] = 'p.city LIKE ?'; $params[] = "%{$searchCity}%"; }
if ($searchCat  !== 'tous') { $where[] = 'p.category = ?'; $params[] = $searchCat; }
if ($filter === 'disponible') { $where[] = 'p.is_available = 1'; }
if ($filter === 'verified')   { $where[] = 'p.is_verified = 1'; }
$sortMap = ['rating'=>'p.rating DESC','name'=>'u.name ASC','price_asc'=>'CAST(p.price AS UNSIGNED) ASC','price_desc'=>'CAST(p.price AS UNSIGNED) DESC','reviews'=>'p.review_count DESC'];
$orderBy  = $sortMap[$sort] ?? 'p.rating DESC';
$whereStr = implode(' AND ', $where);
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM providers p JOIN users u ON u.id=p.user_id WHERE {$whereStr}");
$countStmt->execute($params);
$totalProviders = (int)$countStmt->fetchColumn();
$totalPages     = max(1, (int)ceil($totalProviders / $perPage));
$stmt = $pdo->prepare("SELECT p.*,u.name,u.avatar FROM providers p JOIN users u ON u.id=p.user_id WHERE {$whereStr} ORDER BY {$orderBy} LIMIT {$perPage} OFFSET " . (($page-1)*$perPage));
$stmt->execute($params);
$providers = $stmt->fetchAll();

// ── Catégories IoT (rapport : gaz, eau, electricite) ─────
$categories = ['gaz','eau','electricite'];
$catStmt = $pdo->query('SELECT category,COUNT(*) AS cnt FROM providers GROUP BY category');
$catCounts = []; foreach($catStmt->fetchAll() as $r) $catCounts[$r['category']] = $r['cnt'];
$totalAll = array_sum($catCounts);

// ── Stats globales ────────────────────────────────────────
$g = $pdo->query('SELECT (SELECT COUNT(*) FROM providers) AS p,(SELECT COUNT(*) FROM bookings) AS b,(SELECT ROUND(AVG(rating)*20) FROM providers) AS s')->fetch();

// ── Derniers avis ─────────────────────────────────────────
$revs = $pdo->query('SELECT r.rating,r.comment,u.name AS cn,pu.name AS pn,p.category FROM reviews r JOIN users u ON u.id=r.client_id JOIN providers p ON p.id=r.provider_id JOIN users pu ON pu.id=p.user_id ORDER BY r.created_at DESC LIMIT 3')->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EGES Technologies — Smart Energy, Smart Living</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
<style>
/* ============================================================
   EGES Technologies — Design "Rapport Présenté"
   Palette rapport : vert forêt + bleu tech
   ============================================================ */
:root{
  --f:#0B2518;--g:#1A4731;--s:#27694A;--m:#52B788;
  --ll:#D8F3DC;--lb:#B7E4C7;
  --b:#1255A1;--bd:#0C3D7A;--bb:#EBF4FF;
  --am:#F59E0B;--re:#DC2626;--rb:#FEF2F2;
  --cr:#F4FAF6;--wh:#FFFFFF;--tx:#0A1A12;--mu:#4B6358;
  --bo:rgba(11,37,24,.1);--r:16px;--rs:9px;
  --sh:0 2px 16px rgba(11,37,24,.08);--shl:0 10px 40px rgba(11,37,24,.14);
}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
html{scroll-behavior:smooth}
body{font-family:'DM Sans',sans-serif;background:var(--cr);color:var(--tx);overflow-x:hidden;line-height:1.6}
::-webkit-scrollbar{width:4px}::-webkit-scrollbar-thumb{background:var(--m);border-radius:2px}

/* ── HEADER ── */
.hdr{background:var(--f);position:sticky;top:0;z-index:900;border-bottom:1px solid rgba(255,255,255,.06)}
.hdr-in{max-width:1280px;margin:0 auto;padding:.85rem 2rem;display:flex;align-items:center;justify-content:space-between;gap:1rem}
.logo{font-family:'Syne',sans-serif;font-size:1.5rem;font-weight:800;color:#fff;text-decoration:none;display:flex;flex-direction:column;line-height:1.1}
.logo span{color:var(--m)}
.logo small{font-size:.6rem;font-weight:500;color:rgba(255,255,255,.45);text-transform:uppercase;letter-spacing:.1em;font-family:'DM Sans',sans-serif}
.nav{display:flex;align-items:center;gap:.3rem;list-style:none}
.nav a{color:rgba(255,255,255,.6);text-decoration:none;font-size:.87rem;font-weight:500;padding:.4rem .85rem;border-radius:50px;transition:all .2s}
.nav a:hover{color:#fff;background:rgba(255,255,255,.08)}
.nav .cta{background:var(--m);color:var(--f)!important;font-weight:700}
.nav .cta:hover{background:var(--lb)}

/* ── HERO PRESENTATION ── */
.hero{background:var(--f);overflow:hidden;position:relative}
.hero::before{content:'';position:absolute;width:700px;height:700px;border-radius:50%;background:rgba(82,183,136,.04);top:-200px;right:-200px;pointer-events:none}
.hero-in{max-width:1280px;margin:0 auto;padding:4.5rem 2rem 0;display:grid;grid-template-columns:1fr 420px;gap:3.5rem;align-items:end}

/* Left — présentation rapport */
.hero-kicker{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.12em;color:var(--m);margin-bottom:1rem;display:flex;align-items:center;gap:.5rem}
.hero-kicker::before{content:'';width:24px;height:2px;background:var(--m)}
.hero-h1{font-family:'Syne',sans-serif;font-size:clamp(2.4rem,4vw,3.6rem);font-weight:800;color:#fff;line-height:1.08;margin-bottom:1.25rem}
.hero-h1 em{color:var(--m);font-style:italic}
.hero-slogan{font-size:.78rem;font-weight:700;color:rgba(255,255,255,.35);text-transform:uppercase;letter-spacing:.14em;margin-bottom:.6rem}
.hero-desc{font-size:1rem;color:rgba(255,255,255,.6);line-height:1.75;max-width:500px;margin-bottom:2rem}

/* Métriques rapport en ligne */
.hero-metrics{display:flex;gap:0;margin-bottom:2.5rem;border:1px solid rgba(255,255,255,.1);border-radius:var(--r);overflow:hidden}
.metric{flex:1;padding:1.2rem 1rem;text-align:center;border-right:1px solid rgba(255,255,255,.1);position:relative}
.metric:last-child{border-right:none}
.metric-val{font-family:'Syne',sans-serif;font-size:1.75rem;font-weight:800;color:#fff;line-height:1;display:block}
.metric-val.accent{color:var(--m)}
.metric-lbl{font-size:.68rem;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.08em;margin-top:.25rem;display:block}
.hero-btns{display:flex;gap:.75rem;flex-wrap:wrap;padding-bottom:3rem}

/* Right — carte rapport */
.hero-card{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:var(--r) var(--r) 0 0;padding:1.75rem;align-self:stretch;display:flex;flex-direction:column;gap:1rem}
.hcard-title{font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:rgba(255,255,255,.4);margin-bottom:.25rem}
.hcard-badge{display:inline-flex;align-items:center;gap:.4rem;background:rgba(82,183,136,.15);border:1px solid rgba(82,183,136,.3);color:var(--m);font-size:.75rem;font-weight:700;padding:.3rem .8rem;border-radius:50px}
.hcard-section{border-top:1px solid rgba(255,255,255,.07);padding-top:.9rem}
.hcard-row{display:flex;justify-content:space-between;align-items:center;font-size:.83rem;color:rgba(255,255,255,.55);padding:.25rem 0}
.hcard-row strong{color:#fff;font-weight:600}
.iot-live{display:flex;flex-direction:column;gap:.45rem}
.iot-row{display:flex;align-items:center;gap:.65rem;padding:.5rem .75rem;border-radius:var(--rs);background:rgba(255,255,255,.04)}
.iot-dot{width:7px;height:7px;border-radius:50%;flex-shrink:0}
.iot-dot.red{background:var(--re);animation:blink .8s infinite}
.iot-dot.am{background:var(--am);animation:blink 1.2s infinite}
.iot-dot.gn{background:var(--m);animation:blink 2s infinite}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.25}}
.iot-label{font-size:.78rem;color:rgba(255,255,255,.7);flex:1}
.iot-val{font-size:.78rem;font-weight:700}
.iot-val.danger{color:var(--re)}.iot-val.warn{color:var(--am)}.iot-val.ok{color:var(--m)}

/* ── BOUTONS ── */
.btn{display:inline-flex;align-items:center;gap:.4rem;padding:.78rem 1.6rem;border-radius:50px;font-family:'DM Sans',sans-serif;font-size:.9rem;font-weight:700;cursor:pointer;text-decoration:none;border:none;transition:all .25s}
.btn-p{background:var(--m);color:var(--f)}.btn-p:hover{background:var(--lb);transform:translateY(-2px);box-shadow:0 6px 20px rgba(82,183,136,.35)}
.btn-o{background:transparent;color:#fff;border:1.5px solid rgba(255,255,255,.3)}.btn-o:hover{background:rgba(255,255,255,.07);border-color:rgba(255,255,255,.6)}
.btn-b{background:var(--b);color:#fff}.btn-b:hover{background:var(--bd);transform:translateY(-2px)}

/* ── BANDE RAPPORT (section marqueur) ── */
.rapport-band{background:var(--g);padding:.65rem 2rem;overflow:hidden}
.rapport-band-in{max-width:1280px;margin:0 auto;display:flex;align-items:center;gap:1.5rem;flex-wrap:wrap}
.rb-tag{font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--m);white-space:nowrap}
.rb-sep{color:rgba(255,255,255,.15);font-size:.9rem}
.rb-item{font-size:.78rem;color:rgba(255,255,255,.5);white-space:nowrap}
.rb-item strong{color:rgba(255,255,255,.8)}

/* ── SECTIONS ── */
.wrap{max-width:1280px;margin:0 auto;padding:3.5rem 2rem}
.sec-head{display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:2rem;flex-wrap:wrap;gap:1rem}
.sec-title{font-family:'Syne',sans-serif;font-size:1.8rem;font-weight:800;color:var(--f);display:flex;align-items:center;gap:.6rem}
.sec-title::before{content:'';width:4px;height:1.8rem;background:var(--m);border-radius:2px;flex-shrink:0}
.sec-sup{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--m);margin-bottom:.4rem}
.sec-link{color:var(--s);font-weight:600;font-size:.86rem;text-decoration:none}
.sec-link:hover{color:var(--f)}

/* ── SEARCH ── */
.search-outer{max-width:1280px;margin:0 auto;padding:2rem}
.search-card{background:var(--wh);border-radius:var(--r);box-shadow:var(--shl);padding:1.75rem;border:1px solid var(--bo)}
.s-lbl{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--mu);margin-bottom:.9rem}
.s-grid{display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:.75rem}
.s-wrap{position:relative}
.s-ico{position:absolute;left:.9rem;top:50%;transform:translateY(-50%);font-size:.9rem;color:var(--mu);pointer-events:none}
.fc{width:100%;padding:.8rem 1rem .8rem 2.5rem;border:1.5px solid var(--bo);border-radius:var(--rs);font-family:'DM Sans',sans-serif;font-size:.9rem;background:var(--cr);color:var(--tx);transition:all .2s;outline:none}
.fc:focus{border-color:var(--s);background:var(--wh);box-shadow:0 0 0 3px rgba(39,105,74,.08)}
.fc::placeholder{color:#AAB5AE}
.s-btn{background:var(--g);color:#fff;border:none;padding:.8rem 1.6rem;border-radius:var(--rs);font-family:'DM Sans',sans-serif;font-size:.88rem;font-weight:700;cursor:pointer;transition:background .2s;white-space:nowrap}
.s-btn:hover{background:var(--s)}
.ftags{display:flex;gap:.45rem;margin-top:.9rem;flex-wrap:wrap}
.ftag{padding:.3rem .82rem;border-radius:50px;font-size:.78rem;font-weight:600;border:1.5px solid var(--bo);background:var(--wh);color:var(--mu);text-decoration:none;transition:all .2s}
.ftag:hover,.ftag.act{background:var(--g);color:#fff;border-color:var(--g)}

/* ── CATS IOT ── */
.cat-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(155px,1fr));gap:1rem}
.cat-card{background:var(--wh);border:1.5px solid var(--bo);border-radius:var(--r);padding:1.35rem 1rem;text-align:center;text-decoration:none;color:var(--tx);transition:all .25s;display:block;position:relative;overflow:hidden}
.cat-card::after{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--cc,var(--m));border-radius:2px 2px 0 0}
.cat-card:hover,.cat-card.act{border-color:var(--cc,var(--m));background:var(--g);color:#fff;transform:translateY(-4px);box-shadow:var(--sh)}
.cat-ico{font-size:1.8rem;margin-bottom:.4rem;display:block}
.cat-nm{font-size:.88rem;font-weight:700}
.cat-cnt{font-size:.73rem;color:var(--mu);margin-top:.15rem}
.cat-card:hover .cat-cnt,.cat-card.act .cat-cnt{color:var(--lb)}
.cat-seuil{font-size:.67rem;font-weight:700;color:var(--mu);margin-top:.3rem;padding:.18rem .5rem;background:rgba(0,0,0,.04);border-radius:50px;display:inline-block}
.cat-card:hover .cat-seuil,.cat-card.act .cat-seuil{background:rgba(255,255,255,.12);color:var(--m)}

/* ── PROVIDERS ── */
.sort-bar{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:.75rem}
.res-cnt{font-size:.88rem;color:var(--mu)}
.res-cnt strong{color:var(--f)}
.pg{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:1.5rem}
.pc{background:var(--wh);border-radius:var(--r);overflow:hidden;border:1px solid var(--bo);transition:all .3s}
.pc:hover{transform:translateY(-5px);box-shadow:var(--shl);border-color:rgba(82,183,136,.3)}
.pc-banner{height:80px;position:relative;overflow:hidden}
.pc-badge{background:var(--wh);color:var(--f);font-size:.68rem;font-weight:700;padding:.22rem .6rem;border-radius:50px;position:absolute;right:.7rem;top:.7rem}
.pc-badge.top{background:var(--am);color:#fff}
.pc-body{padding:1.1rem 1.3rem}
.pc-hd{display:flex;gap:.85rem;margin-top:-1.9rem;margin-bottom:.9rem;position:relative}
.av{width:58px;height:58px;border-radius:50%;border:3px solid #fff;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:1.3rem;flex-shrink:0;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.14)}
.av img{width:100%;height:100%;object-fit:cover;border-radius:50%}
.vb{position:absolute;bottom:0;right:0;background:var(--s);color:#fff;width:17px;height:17px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.57rem;border:2px solid #fff}
.ci{padding-top:1.8rem;flex:1}
.pc-nm{font-weight:700;font-size:.97rem}
.pc-cat{font-size:.73rem;font-weight:700;color:var(--s);text-transform:uppercase;letter-spacing:.05em}
.pc-loc{font-size:.78rem;color:var(--mu);margin-top:.1rem}
.pc-desc{font-size:.85rem;color:var(--mu);line-height:1.6;margin-bottom:.85rem;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.pc-meta{display:flex;gap:.75rem;margin-bottom:1rem;font-size:.84rem;flex-wrap:wrap;align-items:center}
.pc-ft{display:flex;gap:.6rem}
.bb{flex:1;background:var(--g);color:#fff;border:none;padding:.68rem;border-radius:var(--rs);font-family:'DM Sans',sans-serif;font-weight:700;font-size:.86rem;cursor:pointer;transition:all .25s;display:flex;align-items:center;justify-content:center;gap:.35rem;text-decoration:none}
.bb:hover{background:var(--s);transform:translateY(-1px)}
.bi{width:38px;height:38px;border-radius:var(--rs);border:1.5px solid var(--bo);background:var(--wh);display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .2s;font-size:.9rem;text-decoration:none;color:var(--tx)}
.bi:hover{border-color:var(--s)}

/* ── SEUILS IOT — Tableau rapport section 5 ── */
.iot-sec{background:var(--f);padding:4rem 2rem}
.iot-in{max-width:1280px;margin:0 auto}
.iot-title{font-family:'Syne',sans-serif;font-size:1.7rem;font-weight:800;color:#fff;margin-bottom:.35rem}
.iot-sub{color:var(--lb);font-size:.88rem;margin-bottom:2.5rem}
.iot-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1.1rem}
.iot-card{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.09);border-radius:var(--r);padding:1.5rem;position:relative;overflow:hidden;transition:all .25s}
.iot-card:hover{background:rgba(255,255,255,.09);transform:translateY(-3px)}
.iot-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--ic,var(--m))}
.iot-cap{font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:rgba(255,255,255,.4);margin-bottom:.3rem}
.iot-capteur{font-size:.8rem;color:rgba(255,255,255,.55);margin-bottom:.6rem}
.iot-ico{font-size:1.7rem;margin-bottom:.7rem;display:block}
.iot-seuil{font-family:'Syne',sans-serif;font-size:1.5rem;font-weight:800;color:#fff;line-height:1.1}
.iot-action{display:inline-flex;align-items:center;gap:.35rem;font-size:.74rem;font-weight:600;padding:.28rem .72rem;border-radius:50px;margin-top:.85rem;background:var(--ic-bg,rgba(82,183,136,.2));color:#fff}

/* ── PLAN FREEMIUM — Rapport section 3 ── */
.pricing-sec{padding:4.5rem 2rem;background:var(--ll)}
.pricing-in{max-width:1280px;margin:0 auto}
.pricing-head{text-align:center;margin-bottom:3rem}
.pricing-title{font-family:'Syne',sans-serif;font-size:2rem;font-weight:800;color:var(--f);margin-bottom:.4rem}
.pricing-sub{color:var(--mu);font-size:.92rem}
.pricing-survey{display:flex;gap:1rem;justify-content:center;margin-top:1rem;flex-wrap:wrap}
.survey-item{background:var(--wh);border-radius:50px;padding:.35rem 1rem;font-size:.78rem;font-weight:600;color:var(--s);border:1px solid rgba(39,105,74,.2)}
.pricing-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1.5rem}
.plan{background:var(--wh);border-radius:var(--r);overflow:hidden;border:1.5px solid var(--bo);transition:all .25s}
.plan:hover{transform:translateY(-5px);box-shadow:var(--shl)}
.plan.featured{border-color:var(--b);box-shadow:0 0 0 3px rgba(18,85,161,.1)}
.plan-top{background:var(--f);padding:1.75rem 1.5rem}
.plan.featured .plan-top{background:var(--b)}
.plan-badge{font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:rgba(255,255,255,.5);margin-bottom:.6rem;display:block}
.plan-price{font-family:'Syne',sans-serif;font-size:2.2rem;font-weight:800;color:#fff;line-height:1}
.plan-price small{font-size:1rem;font-weight:400;color:rgba(255,255,255,.5)}
.plan-name{font-size:.85rem;color:rgba(255,255,255,.65);margin-top:.25rem}
.plan-body{padding:1.5rem}
.plan-feats{list-style:none;display:flex;flex-direction:column;gap:.6rem;margin-bottom:1.5rem}
.plan-feats li{font-size:.86rem;color:var(--mu);display:flex;align-items:flex-start;gap:.5rem}
.plan-feats li::before{content:'✓';color:var(--m);font-weight:700;flex-shrink:0;margin-top:.05rem}
.plan-cta{display:block;text-align:center;padding:.78rem;border-radius:var(--rs);font-weight:700;font-size:.88rem;text-decoration:none;transition:all .2s}
.plan-cta-free{background:var(--ll);color:var(--g)}.plan-cta-free:hover{background:var(--lb)}
.plan-cta-prem{background:var(--b);color:#fff}.plan-cta-prem:hover{background:var(--bd)}
.plan-cta-vip{background:var(--am);color:#fff}.plan-cta-vip:hover{background:#D97706}

/* ── BUDGET PRÉVISIONNEL — Rapport section 6 ── */
.budget-sec{padding:4rem 2rem;background:var(--wh)}
.budget-in{max-width:1280px;margin:0 auto}
.budget-table{width:100%;border-collapse:collapse;margin-top:1.75rem;border-radius:var(--r);overflow:hidden;box-shadow:var(--sh)}
.budget-table th{background:var(--f);color:#fff;padding:.85rem 1.2rem;font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;text-align:left}
.budget-table td{padding:.82rem 1.2rem;font-size:.88rem;border-bottom:1px solid var(--bo)}
.budget-table tr:last-child td{border-bottom:none}
.budget-table tr:nth-child(even) td{background:var(--cr)}
.budget-table .green{color:var(--s);font-weight:700}
.budget-table .bold{font-weight:700;color:var(--f)}
.pm-box{background:var(--ll);border:1.5px solid rgba(39,105,74,.25);border-radius:var(--r);padding:1.5rem 2rem;margin-top:2rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1.5rem}
.pm-num{font-family:'Syne',sans-serif;font-size:2.4rem;font-weight:800;color:var(--f);line-height:1}
.pm-lbl{font-size:.8rem;color:var(--mu);margin-top:.2rem}
.pm-desc{font-size:.88rem;color:var(--mu);max-width:500px;line-height:1.7}

/* ── SWOT — Rapport section 2 ── */
.swot-sec{padding:4rem 2rem;background:var(--cr)}
.swot-grid{display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;margin-top:1.75rem}
.swot-card{border-radius:var(--r);padding:1.5rem;border:1px solid var(--bo)}
.swot-card h3{font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.09em;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem}
.swot-card ul{list-style:none;display:flex;flex-direction:column;gap:.5rem}
.swot-card li{font-size:.87rem;line-height:1.55;display:flex;align-items:flex-start;gap:.5rem}
.swot-card li::before{flex-shrink:0;margin-top:.1rem}
.sw-f{background:rgba(39,105,74,.07);border-color:rgba(39,105,74,.2)}
.sw-f h3{color:var(--s)}.sw-f li::before{content:'●';color:var(--m)}
.sw-w{background:rgba(220,38,38,.04);border-color:rgba(220,38,38,.15)}
.sw-w h3{color:var(--re)}.sw-w li::before{content:'●';color:var(--re)}
.sw-o{background:rgba(18,85,161,.04);border-color:rgba(18,85,161,.15)}
.sw-o h3{color:var(--b)}.sw-o li::before{content:'●';color:var(--b)}
.sw-t{background:rgba(245,158,11,.05);border-color:rgba(245,158,11,.2)}
.sw-t h3{color:#B45309}.sw-t li::before{content:'●';color:var(--am)}

/* ── HOW ── */
.how-sec{background:var(--g);padding:4.5rem 2rem}
.how-in{max-width:1280px;margin:0 auto}
.how-title{font-family:'Syne',sans-serif;font-size:1.9rem;font-weight:800;color:#fff;text-align:center;margin-bottom:.5rem}
.how-sub{color:var(--lb);text-align:center;margin-bottom:3rem;font-size:.92rem}
.steps{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:2rem}
.step{text-align:center;padding:1.25rem}
.step-ico{font-size:2.2rem;margin-bottom:.7rem;display:block}
.step-n{width:40px;height:40px;border-radius:50%;border:2px solid rgba(82,183,136,.5);color:var(--m);font-family:'Syne',sans-serif;font-size:1.2rem;font-weight:800;display:flex;align-items:center;justify-content:center;margin:0 auto .85rem}
.step h3{font-size:.9rem;font-weight:700;color:#fff;margin-bottom:.35rem}
.step p{font-size:.83rem;color:rgba(255,255,255,.55);line-height:1.65}

/* ── AVIS ── */
.rev-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1.5rem;margin-top:.5rem}
.rv{background:var(--wh);border-radius:var(--r);padding:1.4rem;border:1px solid var(--bo);transition:all .25s}
.rv:hover{box-shadow:var(--sh);transform:translateY(-2px)}
.rv-au{display:flex;align-items:center;gap:.7rem;margin-top:1rem}
.av-sm{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.86rem;flex-shrink:0}

/* ── CTA FINAL ── */
.cta-wrap{max-width:1280px;margin:3rem auto;padding:0 2rem}
.cta-box{background:var(--f);border-radius:calc(var(--r)*1.5);padding:3.5rem;display:grid;grid-template-columns:1fr auto;gap:2rem;align-items:center;position:relative;overflow:hidden}
.cta-box::before{content:'';position:absolute;top:-80px;right:160px;width:260px;height:260px;border-radius:50%;background:rgba(255,255,255,.03)}
.cta-title{font-family:'Syne',sans-serif;font-size:1.75rem;font-weight:800;color:#fff;margin-bottom:.5rem}
.cta-sub{color:var(--lb);font-size:.9rem;line-height:1.7}

/* ── FOOTER ── */
.ft{background:var(--f);padding:3.5rem 2rem 2rem;margin-top:3.5rem}
.ft-in{max-width:1280px;margin:0 auto}
.ft-grid{display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:2.5rem;margin-bottom:2.5rem}
.ft-brand{font-family:'Syne',sans-serif;font-size:1.4rem;font-weight:800;color:#fff;margin-bottom:.75rem;display:block}
.ft-brand span{color:var(--m)}
.ft-desc{font-size:.83rem;color:rgba(255,255,255,.38);line-height:1.7}
.ft-col h4{color:#fff;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;margin-bottom:.9rem}
.ft-col ul{list-style:none}
.ft-col li{margin-bottom:.4rem}
.ft-col a{color:rgba(255,255,255,.38);text-decoration:none;font-size:.83rem;transition:color .2s}
.ft-col a:hover{color:var(--m)}
.ft-bot{padding-top:1.5rem;border-top:1px solid rgba(255,255,255,.07);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;font-size:.78rem;color:rgba(255,255,255,.28)}
.ft-tag{background:rgba(82,183,136,.12);color:var(--m);padding:.2rem .6rem;border-radius:50px;font-size:.7rem;font-weight:700}

/* ── MISC ── */
.toast{position:fixed;bottom:2rem;right:2rem;background:var(--g);color:#fff;padding:.9rem 1.4rem;border-radius:var(--rs);box-shadow:var(--shl);z-index:9999;font-weight:600;font-size:.86rem;transform:translateY(100px);opacity:0;transition:all .3s cubic-bezier(.34,1.56,.64,1);pointer-events:none}
.toast.show{transform:translateY(0);opacity:1}
.stb{position:fixed;bottom:2rem;left:2rem;width:40px;height:40px;border-radius:50%;background:var(--g);color:#fff;border:none;cursor:pointer;z-index:800;display:flex;align-items:center;justify-content:center;box-shadow:var(--sh);transition:all .3s;opacity:0;transform:translateY(15px);pointer-events:none;font-size:1rem}
.stb.vis{opacity:1;transform:translateY(0);pointer-events:auto}
.stb:hover{background:var(--s)}
.empty{text-align:center;padding:4rem;color:var(--mu)}
.empty .ico{font-size:3rem;margin-bottom:1rem}

/* ── RESPONSIVE ── */
@media(max-width:1024px){.hero-in{grid-template-columns:1fr}.hero-card{display:none}}
@media(max-width:860px){.s-grid{grid-template-columns:1fr 1fr}.s-grid .s-btn{grid-column:span 2}.ft-grid{grid-template-columns:1fr 1fr}.cta-box{grid-template-columns:1fr}.pricing-grid{grid-template-columns:1fr}.swot-grid{grid-template-columns:1fr}}
@media(max-width:600px){.nav .nav-hide{display:none}.hero{padding:3rem 1.5rem 0}.s-grid{grid-template-columns:1fr}.ft-grid{grid-template-columns:1fr}}
</style>
</head>
<body>

<!-- ═══════════════════════════════════════════════════
     HEADER
     ═══════════════════════════════════════════════════ -->
<header class="hdr">
  <div class="hdr-in">
    <a href="/servilocal/" class="logo">
      EGES<span> Technologies</span>
      <small>Smart Energy, Smart Living</small>
    </a>
    <ul class="nav">
      <li class="nav-hide"><a href="#seuils">Capteurs IoT</a></li>
      <li class="nav-hide"><a href="#pricing">Tarifs</a></li>
      <li class="nav-hide"><a href="#providers">Prestataires</a></li>
      <?php if (isLoggedIn()): ?>
        <li><a href="/servilocal/dashboard.php">Mon espace</a></li>
        <li><a href="/servilocal/logout.php">Déconnexion</a></li>
      <?php else: ?>
        <li><a href="/servilocal/login.php">Connexion</a></li>
        <li><a href="/servilocal/register.php" class="cta">Rejoindre →</a></li>
      <?php endif; ?>
    </ul>
  </div>
</header>

<!-- ═══════════════════════════════════════════════════
     HERO — Présentation rapport
     Rapport §1 : Vision · §3 : Slogan · §6 : Métriques
     ═══════════════════════════════════════════════════ -->
<section class="hero">
  <div class="hero-in">

    <!-- Gauche -->
    <div>
      <div class="hero-slogan"> Université Mundiapolis 2025</div>
      <div class="hero-kicker">Solution IoT de Gestion Énergétique au Maroc</div>
      <h1 class="hero-h1">
        Sécurité &amp;<br><em>Domotique</em><br>Intelligente
      </h1>
      <p class="hero-desc">
        Solution écosystémique combinant l'IoT et une plateforme full-stack dédiée au monitoring granulaire
        de l'électricité, de l'eau et du gaz. Détection prédictive · Alertes automatiques · Mise en relation.
      </p>

      <!-- Métriques rapport §6 Finances -->
      <div class="hero-metrics">
        <div class="metric">
          <span class="metric-val accent"><?= (int)($g['p'] ?? 0) ?>+</span>
          <span class="metric-lbl">Prestataires</span>
        </div>
        <div class="metric">
          <span class="metric-val accent"><?= (int)($g['b'] ?? 0) ?>+</span>
          <span class="metric-lbl">Réservations</span>
        </div>
        <div class="metric">
          <span class="metric-val accent">15-25%</span>
          <span class="metric-lbl">Économies facture</span>
        </div>
        <div class="metric">
          <span class="metric-val accent">100</span>
          <span class="metric-lbl">Point mort (abonnés)</span>
        </div>
      </div>

      <div class="hero-btns">
        <a href="#search" class="btn btn-p">🔍 Explorer les prestataires</a>
        <a href="/servilocal/register.php" class="btn btn-o">✦ Rejoindre le réseau</a>
      </div>
    </div>

    <!-- Droite — carte rapport IoT live -->
    <div class="hero-card">
      <div>
        <div class="hcard-title">Statut Capteurs IoT — Rapport §5</div>
        <div class="hcard-badge">● Monitoring en temps réel</div>
      </div>

      <div class="iot-live">
        <div class="iot-row">
          <span class="iot-dot red"></span>
          <span class="iot-label">🔥 Gaz — Cuisine</span>
          <span class="iot-val danger">0.89 m³/h</span>
        </div>
        <div class="iot-row">
          <span class="iot-dot am"></span>
          <span class="iot-label">⚡ Électricité — Salon</span>
          <span class="iot-val warn">4.2 kW</span>
        </div>
        <div class="iot-row">
          <span class="iot-dot gn"></span>
          <span class="iot-label">💧 Eau — Cuisine</span>
          <span class="iot-val ok">0.42 m³/h</span>
        </div>
        <div class="iot-row">
          <span class="iot-dot gn"></span>
          <span class="iot-label">🌡️ Température — Salon</span>
          <span class="iot-val ok">22.4 °C</span>
        </div>
      </div>

      <div class="hcard-section">
        <div class="hcard-title">Budget mensuel estimé</div>
        <?php foreach([['⚡','Électricité','370 DH'],['🔥','Gaz','210 DH'],['💧','Eau','90 DH']] as $r): ?>
          <div class="hcard-row"><span><?= $r[0] ?> <?= $r[1] ?></span><strong><?= $r[2] ?></strong></div>
        <?php endforeach; ?>
        <div class="hcard-row" style="border-top:1px solid rgba(255,255,255,.1);margin-top:.4rem;padding-top:.6rem">
          <span style="color:#fff;font-size:.8rem;font-weight:600">Total estimé / mois</span>
          <strong style="color:var(--m);font-size:.95rem">670 DH</strong>
        </div>
      </div>

      <div class="hcard-section">
        <div class="hcard-title">Modèle Freemium — Rapport §3</div>
        <?php foreach([['Free','0 MAD'],['Premium','20-50 MAD'],['VIP CHR','50-100 MAD']] as $p): ?>
          <div class="hcard-row"><span><?= $p[0] ?></span><strong><?= $p[1] ?>/mois</strong></div>
        <?php endforeach; ?>
      </div>
    </div>

  </div>
</section>

<!-- ═══════════════════════════════════════════════════
     RECHERCHE
     ═══════════════════════════════════════════════════ -->
<div id="search" class="search-outer">
  <form method="GET" id="sForm">
    <div class="search-card">
      <div class="s-lbl">Trouver un prestataire certifié EGES — axe Casablanca · Rabat</div>
      <div class="s-grid">
        <div class="s-wrap"><span class="s-ico">🔍</span>
          <input type="text" name="name" class="fc" placeholder="Plombier, électricien…" value="<?= e($searchName) ?>">
        </div>
        <div class="s-wrap"><span class="s-ico">📍</span>
          <input type="text" name="city" class="fc" placeholder="Casablanca, Rabat…" value="<?= e($searchCity) ?>">
        </div>
        <div class="s-wrap"><span class="s-ico">📋</span>
          <select name="cat" class="fc" style="padding-left:2.4rem">
            <option value="tous" <?= $searchCat==='tous'?'selected':'' ?>>Toutes catégories IoT</option>
            <?php foreach($categories as $cat): ?>
              <option value="<?= e($cat) ?>" <?= $searchCat===$cat?'selected':'' ?>><?= e(categoryName($cat)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="s-btn">Rechercher →</button>
      </div>
      <div class="ftags">
        <a href="?filter=tous&cat=<?= e($searchCat) ?>&name=<?= urlencode($searchName) ?>" class="ftag <?= $filter==='tous'?'act':'' ?>">Tous</a>
        <a href="?filter=disponible&cat=<?= e($searchCat) ?>&name=<?= urlencode($searchName) ?>" class="ftag <?= $filter==='disponible'?'act':'' ?>">🟢 Disponibles</a>
        <a href="?filter=verified&cat=<?= e($searchCat) ?>&name=<?= urlencode($searchName) ?>" class="ftag <?= $filter==='verified'?'act':'' ?>">✓ Certifiés EGES</a>
      </div>
    </div>
  </form>
</div>

<!-- ═══════════════════════════════════════════════════
     CATÉGORIES IoT — Rapport §5 tableau seuils
     ═══════════════════════════════════════════════════ -->
<div class="wrap" style="padding-top:0">
  <div class="sec-head">
    <div>
      
      <h2 class="sec-title">Services de proximité</h2>
    </div>
    <a href="?" class="sec-link">Voir tout →</a>
  </div>
  <div class="cat-grid">
    <a href="?" class="cat-card <?= $searchCat==='tous'?'act':'' ?>" style="--cc:var(--m)">
      <span class="cat-ico">✦</span>
      <div class="cat-nm">Tous</div>
      <div class="cat-cnt"><?= $totalAll ?> pros</div>
      <span class="cat-seuil">3 capteurs IoT</span>
    </a>
    <a href="?cat=gaz" class="cat-card <?= $searchCat==='gaz'?'act':'' ?>" style="--cc:#DC2626">
      <span class="cat-ico">🔥</span>
      <div class="cat-nm">Gaz</div>
      <div class="cat-cnt"><?= $catCounts['gaz']??0 ?> pro<?= ($catCounts['gaz']??0)>1?'s':'' ?></div>
      <span class="cat-seuil">Seuil &gt; 0.30 m³/h</span>
    </a>
    <a href="?cat=eau" class="cat-card <?= $searchCat==='eau'?'act':'' ?>" style="--cc:#1255A1">
      <span class="cat-ico">💧</span>
      <div class="cat-nm">Eau</div>
      <div class="cat-cnt"><?= $catCounts['eau']??0 ?> pro<?= ($catCounts['eau']??0)>1?'s':'' ?></div>
      <span class="cat-seuil">Seuil &gt; 0.50 m³/h</span>
    </a>
    <a href="?cat=electricite" class="cat-card <?= $searchCat==='electricite'?'act':'' ?>" style="--cc:#F59E0B">
      <span class="cat-ico">⚡</span>
      <div class="cat-nm">Électricité</div>
      <div class="cat-cnt"><?= $catCounts['electricite']??0 ?> pro<?= ($catCounts['electricite']??0)>1?'s':'' ?></div>
      <span class="cat-seuil">Seuil &gt; 2.50 kW</span>
    </a>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════
     PRESTATAIRES
     ═══════════════════════════════════════════════════ -->
<div class="wrap" id="providers" style="padding-top:0">
  <div class="sec-head">
    <div>
      
      <h2 class="sec-title">Prestataires certifiés</h2>
    </div>
  </div>

  <div class="sort-bar">
    <div class="res-cnt"><strong><?= $totalProviders ?></strong> prestataire<?= $totalProviders>1?'s':'' ?> trouvé<?= $totalProviders>1?'s':'' ?>
      <?php if($searchName||$searchCity||$searchCat!=='tous'): ?>
        <a href="?" style="color:var(--s);font-size:.8rem;text-decoration:none;margin-left:.5rem">✕ Effacer</a>
      <?php endif; ?>
    </div>
    <form method="GET" style="display:flex;align-items:center;gap:.5rem;font-size:.83rem">
      <?php foreach(['name'=>$searchName,'city'=>$searchCity,'cat'=>$searchCat,'filter'=>$filter] as $k=>$v): ?>
        <input type="hidden" name="<?= $k ?>" value="<?= e($v) ?>">
      <?php endforeach; ?>
      <label style="color:var(--mu)">Trier :</label>
      <select name="sort" class="fc" style="width:auto;padding:.36rem 1.3rem .36rem .6rem;font-size:.82rem" onchange="this.form.submit()">
        <option value="rating"    <?= $sort==='rating'    ?'selected':'' ?>>Note</option>
        <option value="name"      <?= $sort==='name'      ?'selected':'' ?>>Nom</option>
        <option value="reviews"   <?= $sort==='reviews'   ?'selected':'' ?>>Avis</option>
        <option value="price_asc" <?= $sort==='price_asc' ?'selected':'' ?>>Prix ↑</option>
        <option value="price_desc"<?= $sort==='price_desc'?'selected':'' ?>>Prix ↓</option>
      </select>
    </form>
  </div>

  <?php if(empty($providers)): ?>
    <div class="empty"><div class="ico">🔍</div><h3>Aucun prestataire trouvé</h3><p><a href="?" style="color:var(--s)">Effacer les filtres</a></p></div>
  <?php else: ?>
    <div class="pg">
      <?php foreach($providers as $p):
        $rgb = sscanf(ltrim($p['avatar_color'],'#'),"%02x%02x%02x");
        $bg  = 'rgba('.implode(',',$rgb).',.1)';
        $st  = str_repeat('★',(int)floor($p['rating'])).str_repeat('☆',5-(int)floor($p['rating']));
      ?>
        <div class="pc">
          <div class="pc-banner" style="background:<?= $bg ?>">
            <span style="position:absolute;left:1rem;top:50%;transform:translateY(-50%);font-size:2.2rem;opacity:.22"><?= categoryIcon($p['category']) ?></span>
            <?php if((float)$p['rating']>=4.8): ?><span class="pc-badge top">⭐ Top</span>
            <?php elseif(!$p['is_verified']): ?><span class="pc-badge">🆕 Nouveau</span>
            <?php endif; ?>
          </div>
          <div class="pc-body">
            <div class="pc-hd">
              <div style="position:relative">
                <div class="av" style="background:<?= e($p['avatar_color']) ?>">
                  <?php if($p['avatar']): ?><img src="/servilocal/uploads/profiles/<?= e($p['avatar']) ?>" alt="">
                  <?php else: ?><?= strtoupper(substr($p['name'],0,1)) ?>
                  <?php endif; ?>
                </div>
                <?php if($p['is_verified']): ?><div class="vb" title="Certifié EGES">✓</div><?php endif; ?>
              </div>
              <div class="ci">
                <div class="pc-nm"><?= e($p['name']) ?></div>
                <div class="pc-cat"><?= categoryIcon($p['category']) ?> <?= categoryName($p['category']) ?></div>
                <div class="pc-loc">📍 <?= e($p['city']) ?> · <?= $p['is_available'] ? '<span style="color:var(--m)">● Disponible</span>' : '<span style="color:var(--re)">● Indisponible</span>' ?></div>
              </div>
            </div>
            <p class="pc-desc"><?= e($p['description']??'') ?></p>
            <div class="pc-meta">
              <span style="color:var(--am)"><?= $st ?></span>
              <strong><?= number_format((float)$p['rating'],1) ?></strong>
              <span style="color:var(--mu);font-size:.8rem">(<?= $p['review_count'] ?> avis)</span>
              <span style="color:var(--s);font-weight:600;margin-left:.4rem">💰 <?= e($p['price']) ?></span>
            </div>
            <div class="pc-ft">
              <a href="/servilocal/booking.php?provider=<?= (int)$p['id'] ?>" class="bb">📅 Réserver</a>
              <a href="/servilocal/provider.php?id=<?= (int)$p['id'] ?>" class="bi" title="Voir le profil">👁</a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if($totalPages>1): ?>
      <div style="display:flex;justify-content:center;gap:.5rem;margin-top:2.5rem;flex-wrap:wrap">
        <?php for($i=1;$i<=$totalPages;$i++): ?>
          <a href="?<?= http_build_query(array_merge($_GET,['page'=>$i])) ?>"
             style="width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.86rem;font-weight:700;text-decoration:none;<?= $i===$page?'background:var(--g);color:#fff;':'background:var(--wh);color:var(--tx);border:1.5px solid var(--bo);' ?>">
            <?= $i ?>
          </a>
        <?php endfor; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<!-- ═══════════════════════════════════════════════════
     SEUILS IoT — Rapport §5 Tableau exact
     ═══════════════════════════════════════════════════ -->
<div class="iot-sec" id="seuils">
  <div class="iot-in">
   <h2 class="iot-title">Intelligence de détection automatique</h2>
    <p class="iot-sub">Le module IoT déclenche des alertes et connecte instantanément l'usager au prestataire qualifié</p>
    <div class="iot-grid">
      <div class="iot-card" style="--ic:#1255A1;--ic-bg:rgba(18,85,161,.3)">
        <span class="iot-ico">💧</span>
        <div class="iot-cap">Eau — Débit</div>
        <div class="iot-capteur">Capteur : Compteur d'eau</div>
        <div class="iot-seuil">&gt; 0.50 m³/h</div>
        <span class="iot-action">Alerte fuite + Suggestion Plombier</span>
      </div>
      <div class="iot-card" style="--ic:#DC2626;--ic-bg:rgba(220,38,38,.3)">
        <span class="iot-ico">🔥</span>
        <div class="iot-cap">Gaz — Débit / Niveau</div>
        <div class="iot-capteur">Capteur : Détecteur gaz cuisine</div>
        <div class="iot-seuil">&gt; 0.30 m³/h</div>
        <span class="iot-action">Alerte Critique + Coupure Gaz</span>
      </div>
      <div class="iot-card" style="--ic:#F59E0B;--ic-bg:rgba(245,158,11,.3)">
        <span class="iot-ico">⚡</span>
        <div class="iot-cap">Électricité — Charge</div>
        <div class="iot-capteur">Capteur : Tableau électrique</div>
        <div class="iot-seuil">&gt; 2.50 kW</div>
        <span class="iot-action">Alerte Pic de tension</span>
      </div>
      <div class="iot-card" style="--ic:#52B788;--ic-bg:rgba(82,183,136,.2)">
        <span class="iot-ico">🌡️</span>
        <div class="iot-cap">Température — Thermique</div>
        <div class="iot-capteur">Capteur : Thermostat salon</div>
        <div class="iot-seuil">&gt; 30 °C</div>
        <span class="iot-action">Alerte surchauffe</span>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════
     PLAN FREEMIUM — Rapport §3 Marketing Mix Prix
     Données questionnaire : 13 répondants
     ═══════════════════════════════════════════════════ -->
<div class="pricing-sec" id="pricing">
  <div class="pricing-in">
    <div class="pricing-head">
       <h2 class="pricing-title">Modèle tarifaire disruptif</h2>
      <p class="pricing-sub">Segmentation issue du questionnaire terrain — échantillon de 13 répondants</p>
      <div class="pricing-survey">
        <span class="survey-item">30.8% → Gratuit</span>
        <span class="survey-item">23.1% → 20-50 MAD</span>
        <span class="survey-item">30.8% → 50-100 MAD</span>
        <span class="survey-item">15.4% → +100 MAD</span>
      </div>
    </div>
    <div class="pricing-grid">

      <!-- Free -->
      <div class="plan">
        <div class="plan-top">
          <span class="plan-badge">Levier d'acquisition — 30.8%</span>
          <div class="plan-price">0 <small>MAD/mois</small></div>
          <div class="plan-name">Free — Suivi basique via App</div>
        </div>
        <div class="plan-body">
          <ul class="plan-feats">
            <li>Suivi basique via application mobile</li>
            <li>1 capteur connecté inclus</li>
            <li>Alertes email standard</li>
            <li>Accès plateforme web ServiLocal</li>
            <li>Mise en relation prestataires</li>
          </ul>
          <a href="/servilocal/register.php" class="plan-cta plan-cta-free">Commencer gratuitement</a>
        </div>
      </div>

      <!-- Premium -->
      <div class="plan featured">
        <div class="plan-top">
          <span class="plan-badge">⭐ Populaire — 23.1% + 30.8%</span>
          <div class="plan-price">20-50 <small>MAD/mois</small></div>
          <div class="plan-name">Premium — Alertes &amp; Monitoring entreprises</div>
        </div>
        <div class="plan-body">
          <ul class="plan-feats">
            <li>Alertes IoT temps réel (gaz, eau, élec)</li>
            <li>Monitoring entreprises B2B</li>
            <li>3 capteurs connectés</li>
            <li>Prestataires suggérés automatiquement</li>
            <li>Dashboard analytique Chart.js</li>
            <li>Rapport mensuel consommation</li>
          </ul>
          <a href="/servilocal/register.php" class="plan-cta plan-cta-prem">Choisir Premium</a>
        </div>
      </div>

      <!-- VIP -->
      <div class="plan">
        <div class="plan-top">
          <span class="plan-badge">VIP — Secteur CHR · 15.4%</span>
          <div class="plan-price">50-100 <small>MAD/mois</small></div>
          <div class="plan-name">VIP — Cafés · Hôtels · Restaurants</div>
        </div>
        <div class="plan-body">
          <ul class="plan-feats">
            <li>Monitoring complet 360° + maintenance</li>
            <li>Priorité intervention prestataires</li>
            <li>Secteur CHR (Cafés, Hôtels, Restaurants)</li>
            <li>Capteurs illimités</li>
            <li>ROI énergétique estimé : 15-25%</li>
            <li>Support dédié EGES Technologies</li>
          </ul>
          <a href="/servilocal/register.php" class="plan-cta plan-cta-vip">Contacter l'équipe VIP</a>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════
     COMMENT ÇA MARCHE
     ═══════════════════════════════════════════════════ -->
<div class="how-sec">
  <div class="how-in">
    <h2 class="how-title">Comment ça fonctionne ?</h2>
    <p class="how-sub">De la détection IoT à l'intervention prestataire en 4 étapes</p>
    <div class="steps">
      <div class="step"><span class="step-ico">📡</span><div class="step-n">1</div><h3>Capteur détecte</h3><p>Mesure continue. Comparaison aux seuils définis dans le rapport (§5).</p></div>
      <div class="step"><span class="step-ico">🚨</span><div class="step-n">2</div><h3>Alerte automatique</h3><p>Déclenchement instantané. Notification dashboard et email client.</p></div>
      <div class="step"><span class="step-ico">🔧</span><div class="step-n">3</div><h3>Prestataire suggéré</h3><p>Connexion instantanée au réseau certifié EGES (§4 Faisabilité Commerciale).</p></div>
      <div class="step"><span class="step-ico">⭐</span><div class="step-n">4</div><h3>Intervention &amp; Avis</h3><p>Commissionnement EGES + évaluation client pour enrichir le réseau.</p></div>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════
     AVIS CLIENTS
     ═══════════════════════════════════════════════════ -->
<?php if(!empty($revs)): ?>
<div class="wrap">
  <div class="sec-head">
    <div>
      <div class="sec-sup">Témoignages clients</div>
      <h2 class="sec-title">Ce que disent nos clients</h2>
    </div>
  </div>
  <div class="rev-grid">
    <?php foreach($revs as $r): ?>
      <div class="rv">
        <div style="color:var(--am);font-size:.92rem;margin-bottom:.45rem"><?= str_repeat('★',$r['rating']).str_repeat('☆',5-$r['rating']) ?></div>
        <p style="font-size:.87rem;color:var(--mu);line-height:1.7;font-style:italic">"<?= e(mb_substr($r['comment'],0,150)) ?><?= mb_strlen($r['comment'])>150?'…':'' ?>"</p>
        <div class="rv-au">
          <div class="av-sm" style="background:var(--g)"><?= strtoupper(substr($r['cn'],0,1)) ?></div>
          <div>
            <div style="font-weight:700;font-size:.85rem"><?= e($r['cn']) ?></div>
            <div style="font-size:.73rem;color:var(--mu)"><?= e($r['pn']) ?> · <?= categoryName($r['category']) ?></div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════════
     CTA — Rapport §7 Recommandations
     "20 plombiers/électriciens certifiés dès le lancement"
     ═══════════════════════════════════════════════════ -->
<div class="cta-wrap">
  <div class="cta-box">
    <div>
      <h2 class="cta-title">Rejoignez le réseau EGES Technologies !</h2>
      <p class="cta-sub">
        Objectif rapport : contractualiser <strong style="color:var(--m)">20 plombiers/électriciens certifiés</strong>
        dès le lancement sur l'axe <strong style="color:var(--m)">Casablanca–Rabat</strong>.<br>
        Développez votre clientèle et validez le modèle de commissionnement.
      </p>
    </div>
    <a href="/servilocal/register.php" class="btn btn-p" style="white-space:nowrap;padding:1.1rem 2.5rem;font-size:.95rem">
      Créer mon profil gratuit →
    </a>
  </div>
</div>

<?php include 'includes/footer.php'; ?>

<div class="toast" id="toastMsg"></div>
<button class="stb" id="stbBtn" onclick="window.scrollTo({top:0,behavior:'smooth'})">↑</button>

<script src="/servilocal/assets/js/main.js"></script>
<script>
window.addEventListener('scroll',()=>{
  document.getElementById('stbBtn').classList.toggle('vis',window.scrollY>400);
});
(function(){
  const p=new URLSearchParams(window.location.search);
  const m=p.get('toast');
  if(m){
    const t=document.getElementById('toastMsg');
    t.textContent=decodeURIComponent(m);
    t.classList.add('show');
    setTimeout(()=>t.classList.remove('show'),3500);
    window.history.replaceState({},'',window.location.pathname);
  }
})();
</script>
</body>
</html>