<?php
// ============================================================
// dashboard_iot_section.php — Module IoT Client
// A inclure dans dashboard.php quand role === 'client'
// En production : remplacer getSensorData() par requete SQL
// sur la table sensor_readings (voir database_iot.sql)
// ============================================================
require_once __DIR__ . '/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$userId = $_SESSION['user_id'] ?? null;
if (!$userId) { echo '<p>Session expiree.</p>'; return; }
if (!function_exists('e')) {
    function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}

// Donnees capteurs (simulation — remplacer en production)
function getSensorData(PDO $pdo, int $userId): array {
    return [
        'water_flow'    => 0.42,
        'gas_flow'      => 0.89,
        'electricity'   => 4.2,
        'temperature'   => 22.4,
        'water_ok'      => true,
        'gas_alert'     => true,
        'elec_alert'    => true,
        'temp_ok'       => true,
        'budget_elec'   => 370,
        'budget_gas'    => 210,
        'budget_water'  => 90,
        'history_elec'  => [1.8,2.1,1.9,2.4,3.1,2.8,2.2,2.5,3.8,4.2,3.6,2.9],
        'history_gas'   => [0.20,0.30,0.25,0.28,0.31,0.29,0.35,0.30,0.41,0.89,0.78,0.60],
        'history_water' => [0.30,0.38,0.35,0.40,0.42,0.38,0.36,0.41,0.43,0.42,0.38,0.40],
        'monthly_2024'  => [520,490,560,500,480,510],
        'monthly_2025'  => [490,510,540,580,610,670],
    ];
}
function getSuggestedProviders(PDO $pdo, string $category): array {
    $stmt = $pdo->prepare('
        SELECT p.id, p.rating, p.price, p.review_count,
               u.name, u.phone
        FROM providers p
        JOIN users u ON u.id = p.user_id
        WHERE p.category = ? AND p.is_available = 1
        ORDER BY p.rating DESC
        LIMIT 3
    ');
    $stmt->execute([$category]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
$sensors       = getSensorData($pdo, $userId);
$gasProviders  = $sensors['gas_alert']  ? getSuggestedProviders($pdo, 'plomberie')   : [];
$elecProviders = $sensors['elec_alert'] ? getSuggestedProviders($pdo, 'electricite') : [];
$totalAlerts   = (int)$sensors['gas_alert'] + (int)$sensors['elec_alert'];
$GAS_THRESHOLD  = 0.30;
$ELEC_THRESHOLD = 2.5;
$totalBudget    = $sensors['budget_elec'] + $sensors['budget_gas'] + $sensors['budget_water'];

?>
<!-- ============================================================
     STYLES IoT
     ============================================================ -->
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
.iot-wrap {
  --ig:#1C3D2E;--is:#3A6B4A;--im:#7EB89A;--il:#B5D99C;--ilbg:#EAF3DE;
  --ia:#E8A838;--iabg:#FEF3DC;--ir:#DC2626;--irbg:#FEF2F2;--irb:rgba(220,38,38,.2);
  --ib:#185FA5;--ibbg:#EFF6FF;--icr:#F7F4EF;--iw:#FFFFFF;
  --it:#1A1A1A;--imu:#6B6860;--ibr:rgba(28,61,46,.1);
  --irad:16px;--irads:10px;
  --ish:0 2px 14px rgba(28,61,46,.07);--ishl:0 8px 32px rgba(28,61,46,.12);
  font-family:"DM Sans",sans-serif;
}
.iot-wrap *{box-sizing:border-box}
/* Header */
.iot-header{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-bottom:1.75rem}
.iot-htitle{font-size:1.4rem;font-weight:700;color:var(--ig);margin-bottom:.25rem}
.iot-hsub{font-size:.84rem;color:var(--imu);display:flex;align-items:center;gap:.4rem}
.lpulse{width:8px;height:8px;border-radius:50%;display:inline-block;flex-shrink:0}
.lpulse-g{background:#16A34A;animation:lp 2s ease-in-out infinite}
.lpulse-r{background:var(--ir);animation:lp .8s ease-in-out infinite}
@keyframes lp{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.4;transform:scale(.85)}}
.iot-badges{display:flex;gap:.5rem;flex-wrap:wrap}
.ibadge{display:inline-flex;align-items:center;gap:.3rem;font-size:.74rem;font-weight:700;padding:.28rem .82rem;border-radius:50px}
.ibadge-danger{background:var(--irbg);color:#991B1B;border:1px solid var(--irb);animation:pb 2s infinite}
.ibadge-ok{background:var(--ilbg);color:var(--is);border:1px solid rgba(126,184,154,.4)}
@keyframes pb{0%,100%{box-shadow:0 0 0 0 rgba(220,38,38,.3)}50%{box-shadow:0 0 0 5px rgba(220,38,38,0)}}
/* KPI */
.iot-kpi-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem}
.iot-kpi{background:var(--iw);border:1px solid var(--ibr);border-radius:var(--irad);padding:1.2rem 1.1rem 1rem;position:relative;overflow:hidden;transition:transform .25s,box-shadow .25s;animation:fu .4s ease both}
.iot-kpi:nth-child(2){animation-delay:.07s}.iot-kpi:nth-child(3){animation-delay:.14s}.iot-kpi:nth-child(4){animation-delay:.21s}
@keyframes fu{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
.iot-kpi:hover{transform:translateY(-3px);box-shadow:var(--ishl)}
.iot-kpi::before{content:"";position:absolute;top:0;left:0;right:0;height:3px;background:var(--kac,var(--im));border-radius:2px 2px 0 0}
.kpi-danger{border-color:var(--irb);background:var(--irbg);--kac:var(--ir)}
.kpi-warn{border-color:rgba(232,168,56,.25);background:var(--iabg);--kac:var(--ia)}
.kpi-blue{--kac:var(--ib)}
.kpi-top{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:.85rem}
.kpi-lbl{font-size:.72rem;font-weight:600;color:var(--imu);text-transform:uppercase;letter-spacing:.07em}
.kpi-ico{width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0}
.kpi-val{font-size:1.7rem;font-weight:700;color:var(--it);line-height:1.1;margin-bottom:.28rem}
.kpi-val small{font-size:.88rem;font-weight:400;color:var(--imu);margin-left:.12rem}
.val-danger{color:var(--ir)}.val-warn{color:#92400E}
.kpi-status{font-size:.77rem;font-weight:600;display:flex;align-items:center;gap:.32rem}
.st-ok{color:#16A34A}.st-danger{color:var(--ir)}.st-warn{color:#B45309}
/* Alerts */
.iot-alerts{display:flex;flex-direction:column;gap:1rem;margin-bottom:1.5rem}
.iot-alert{border-radius:var(--irad);padding:1.2rem 1.4rem;display:grid;grid-template-columns:auto 1fr;gap:1rem;animation:sa .4s ease both}
@keyframes sa{from{opacity:0;transform:translateX(-10px)}to{opacity:1;transform:translateX(0)}}
.iot-alert-d{background:var(--irbg);border:1.5px solid var(--irb)}
.iot-alert-w{background:var(--iabg);border:1.5px solid rgba(232,168,56,.3)}
.aico{width:42px;height:42px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:1.25rem;flex-shrink:0;align-self:flex-start}
.aico-d{background:rgba(220,38,38,.1)}.aico-w{background:rgba(232,168,56,.15)}
.atitle{font-size:.97rem;font-weight:700;color:#991B1B;margin-bottom:.28rem}
.atitle-w{color:#92400E}
.adesc{font-size:.87rem;color:#7F1D1D;line-height:1.6;margin-bottom:.85rem}
.adesc-w{color:#78350F}
.athresh{display:inline-flex;align-items:center;gap:.4rem;background:rgba(220,38,38,.07);border:1px solid rgba(220,38,38,.14);border-radius:50px;padding:.24rem .72rem;font-size:.77rem;font-weight:600;color:#991B1B;margin-bottom:.85rem}
.athresh-w{background:rgba(232,168,56,.1);border-color:rgba(232,168,56,.25);color:#92400E}
.slabel{font-size:.72rem;font-weight:700;color:#991B1B;text-transform:uppercase;letter-spacing:.07em;margin-bottom:.6rem}
.slabel-w{color:#92400E}
.slist{display:flex;flex-direction:column;gap:.5rem}
.scard{background:var(--iw);border:1px solid rgba(220,38,38,.1);border-radius:var(--irads);padding:.82rem 1rem;display:flex;align-items:center;justify-content:space-between;gap:.75rem;transition:border-color .2s,box-shadow .2s}
.scard:hover{border-color:rgba(220,38,38,.25);box-shadow:0 2px 10px rgba(220,38,38,.08)}
.scard-w{border-color:rgba(232,168,56,.14)}.scard-w:hover{border-color:rgba(232,168,56,.3)}
.sav{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:.9rem;flex-shrink:0}
.sinfo{flex:1;min-width:0}
.sname{font-size:.88rem;font-weight:600;color:var(--it)}
.smeta{font-size:.77rem;color:var(--imu);margin-top:.1rem}
.smeta .stars{color:var(--ia)}
.sactions{display:flex;gap:.4rem;flex-shrink:0}
.btn-call,.btn-bk{padding:.38rem .85rem;border-radius:50px;font-size:.78rem;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:.3rem;transition:all .2s;white-space:nowrap}
.btn-call{background:var(--iw);border:1px solid var(--ibr);color:var(--it)}
.btn-call:hover{border-color:var(--is);color:var(--is)}
.btn-bk{background:var(--ig);border:1px solid var(--ig);color:white}
.btn-bk:hover{background:var(--is);transform:translateY(-1px)}
.btn-bk-w{background:var(--ia)!important;border-color:var(--ia)!important}
.btn-bk-w:hover{background:#d09328!important}
/* Charts */
.iot-charts-row{display:grid;grid-template-columns:1.5fr 1fr;gap:1.25rem;margin-bottom:1.25rem}
.iot-card{background:var(--iw);border:1px solid var(--ibr);border-radius:var(--irad);padding:1.2rem 1.4rem;box-shadow:var(--ish)}
.icard-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;flex-wrap:wrap;gap:.5rem}
.icard-title{font-size:.9rem;font-weight:700;color:var(--ig)}
.iot-tabs{display:flex;gap:2px;background:var(--icr);border-radius:8px;padding:3px}
.iot-tab{padding:.28rem .78rem;border-radius:6px;font-size:.77rem;font-weight:600;color:var(--imu);border:none;background:transparent;cursor:pointer;transition:all .18s;font-family:inherit}
.iot-tab.active{background:var(--iw);color:var(--ig);box-shadow:0 1px 4px rgba(28,61,46,.1)}
.cw{position:relative;width:100%;height:170px}
/* Budget */
.bdg-items{display:flex;flex-direction:column;gap:.85rem}
.bdg-row{display:flex;align-items:center;gap:.8rem;font-size:.87rem}
.bdg-lbl{min-width:95px;color:var(--it);font-weight:500}
.bdg-bar{flex:1;background:var(--icr);border-radius:50px;height:8px;overflow:hidden}
.bdg-fill{height:100%;border-radius:50px;transition:width .8s cubic-bezier(.16,1,.3,1)}
.bdg-amt{font-weight:700;color:var(--it);min-width:52px;text-align:right;font-size:.84rem}
.bdg-footer{display:flex;justify-content:space-between;align-items:center;padding-top:.85rem;margin-top:.85rem;border-top:1px solid var(--ibr);font-size:.87rem}
.bdg-flbl{color:var(--imu)}.bdg-fval{font-weight:700;color:var(--ig);font-size:.93rem}
.bdg-delta{color:#B45309;font-weight:500;font-size:.78rem}
/* Sensors */
.iot-sensors-row{display:grid;grid-template-columns:1fr 1fr;gap:1.25rem}
.slist2{display:flex;flex-direction:column}
.sitem{display:flex;align-items:center;justify-content:space-between;padding:.88rem 0;border-bottom:1px solid var(--ibr);gap:1rem}
.sitem:last-child{border-bottom:none;padding-bottom:0}
.sitem-left{display:flex;align-items:center;gap:.72rem}
.sico-wrap{width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0}
.sitem-name{font-size:.88rem;font-weight:600;color:var(--it)}
.sitem-loc{font-size:.76rem;color:var(--imu);margin-top:.08rem}
.sitem-right{display:flex;align-items:center;gap:.7rem;flex-shrink:0}
.sbarm{width:78px;background:var(--icr);border-radius:50px;height:6px;overflow:hidden}
.sbarf{height:100%;border-radius:50px}
.sbadge{font-size:.69rem;font-weight:700;padding:.2rem .62rem;border-radius:50px;white-space:nowrap}
.sb-ok{background:var(--ilbg);color:var(--is)}.sb-d{background:var(--irbg);color:#991B1B}.sb-w{background:var(--iabg);color:#92400E}
.legend-row{display:flex;gap:.75rem;font-size:.74rem;color:var(--imu)}
.leg-dot{width:10px;height:10px;border-radius:2px;display:inline-block}
/* Responsive */
@media(max-width:1024px){.iot-kpi-grid{grid-template-columns:repeat(2,1fr)}.iot-charts-row,.iot-sensors-row{grid-template-columns:1fr}}
@media(max-width:600px){.iot-kpi-grid{grid-template-columns:1fr 1fr}.iot-alert{grid-template-columns:1fr}.scard{flex-wrap:wrap}.sactions{width:100%;justify-content:flex-end}}
</style>

<!-- MODULE IoT -->
<div class="iot-wrap">

  <!-- Header -->
  <div class="iot-header">
    <div>
      <div class="iot-htitle">&#127968; Mon espace connecte</div>
      <div class="iot-hsub">
        <span class="lpulse <?php echo $totalAlerts > 0 ? 'lpulse-r' : 'lpulse-g'; ?>"></span>
        <?php if($totalAlerts>0): ?><?php echo $totalAlerts; ?> alerte<?php echo $totalAlerts>1?'s':''; ?> active<?php echo $totalAlerts>1?'s':''; ?> &mdash;<?php endif; ?>
        Capteurs mis a jour a <?php echo date('H:i:s'); ?>
      </div>
    </div>
    <div class="iot-badges">
      <?php if($totalAlerts>0): ?><span class="ibadge ibadge-danger">&#9888;&#65039; <?php echo $totalAlerts; ?> ALERTE<?php echo $totalAlerts>1?'S':''; ?></span><?php endif; ?>
      <span class="ibadge ibadge-ok">&#9679; 4 capteurs actifs</span>
    </div>
  </div>

  <!-- KPI Grid -->
  <div class="iot-kpi-grid">
    <div class="iot-kpi kpi-blue">
      <div class="kpi-top"><span class="kpi-lbl">Eau</span><div class="kpi-ico" style="background:var(--ibbg)">&#128167;</div></div>
      <div class="kpi-val"><?php echo $sensors['water_flow']; ?><small>m&sup3;/h</small></div>
      <div class="kpi-status st-ok">&#9679; Debit normal</div>
    </div>
    <div class="iot-kpi <?php echo $sensors['gas_alert']?'kpi-danger':''; ?>">
      <div class="kpi-top"><span class="kpi-lbl">Gaz</span><div class="kpi-ico" style="background:<?php echo $sensors['gas_alert']?'rgba(220,38,38,.1)':'var(--ilbg)'; ?>">&#128293;</div></div>
      <div class="kpi-val <?php echo $sensors['gas_alert']?'val-danger':''; ?>"><?php echo $sensors['gas_flow']; ?><small>m&sup3;/h</small></div>
      <div class="kpi-status <?php echo $sensors['gas_alert']?'st-danger':'st-ok'; ?>"><?php echo $sensors['gas_alert']?'&#9888;&#65039; Fuite detectee':'&#9679; Securise'; ?></div>
    </div>
    <div class="iot-kpi <?php echo $sensors['elec_alert']?'kpi-warn':''; ?>">
      <div class="kpi-top"><span class="kpi-lbl">Electricite</span><div class="kpi-ico" style="background:<?php echo $sensors['elec_alert']?'rgba(232,168,56,.12)':'var(--ilbg)'; ?>">&#9889;</div></div>
      <div class="kpi-val <?php echo $sensors['elec_alert']?'val-warn':''; ?>"><?php echo $sensors['electricity']; ?><small>kW</small></div>
      <div class="kpi-status <?php echo $sensors['elec_alert']?'st-warn':'st-ok'; ?>"><?php echo $sensors['elec_alert']?'&#9888;&#65039; Pic anormal':'&#9679; Stable'; ?></div>
    </div>
    <div class="iot-kpi">
      <div class="kpi-top"><span class="kpi-lbl">Temperature</span><div class="kpi-ico" style="background:var(--ilbg)">&#127777;&#65039;</div></div>
      <div class="kpi-val"><?php echo $sensors['temperature']; ?><small>&deg;C</small></div>
      <div class="kpi-status st-ok">&#9679; Confortable</div>
    </div>
  </div>

  <!-- Alertes -->
  <?php if($sensors['gas_alert']||$sensors['elec_alert']): ?>
  <div class="iot-alerts">

    <?php if($sensors['gas_alert']): ?>
    <div class="iot-alert iot-alert-d">
      <div class="aico aico-d">&#128680;</div>
      <div>
        <div class="atitle">Fuite de gaz detectee &mdash; Cuisine</div>
        <div class="adesc">Debit anormal de <strong><?php echo $sensors['gas_flow']; ?> m&sup3;/h</strong> detecte a <?php echo date('H:i'); ?>. Intervention urgente recommandee.</div>
        <span class="athresh">Mesure : <?php echo $sensors['gas_flow']; ?> m&sup3;/h &nbsp;&middot;&nbsp; Seuil : <?php echo $GAS_THRESHOLD; ?> m&sup3;/h</span>
        <?php if(!empty($gasProviders)): ?>
          <div class="slabel">&#128296; Plombiers disponibles pres de vous</div>
          <div class="slist">
            <?php foreach($gasProviders as $p): ?>
            <div class="scard">
              <div class="sav" style="background:var(--ig)"><?php echo strtoupper(substr($p['name'],0,1)); ?></div>
              <div class="sinfo">
                <div class="sname"><?php echo e($p['name']); ?></div>
                <div class="smeta">Plomberie &middot; <span class="stars">&#9733; <?php echo number_format((float)$p['rating'],1); ?></span> (<?php echo $p['review_count']; ?> avis) &middot; <?php echo e($p['price']); ?></div>
              </div>
              <div class="sactions">
                <?php if($p['phone']): ?><a href="tel:<?php echo e($p['phone']); ?>" class="btn-call">&#128222; Appeler</a><?php endif; ?>
                <a href="/servilocal/booking.php?provider=<?php echo (int)$p['id']; ?>" class="btn-bk">Reserver &rarr;</a>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <a href="/servilocal/index.php?cat=plomberie" class="btn-bk" style="display:inline-flex;margin-top:.5rem">Voir les plombiers &rarr;</a>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if($sensors['elec_alert']): ?>
    <div class="iot-alert iot-alert-w">
      <div class="aico aico-w">&#9889;</div>
      <div>
        <div class="atitle atitle-w">Pic electrique anormal &mdash; Salon</div>
        <div class="adesc adesc-w">Consommation de <strong><?php echo $sensors['electricity']; ?> kW</strong> detectee a <?php echo date('H:i'); ?>. Possible court-circuit ou appareil defectueux.</div>
        <span class="athresh athresh-w">Mesure : <?php echo $sensors['electricity']; ?> kW &nbsp;&middot;&nbsp; Seuil : <?php echo $ELEC_THRESHOLD; ?> kW</span>
        <?php if(!empty($elecProviders)): ?>
          <div class="slabel slabel-w">&#9889; Electriciens disponibles pres de vous</div>
          <div class="slist">
            <?php foreach($elecProviders as $p): ?>
            <div class="scard scard-w">
              <div class="sav" style="background:var(--ia)"><?php echo strtoupper(substr($p['name'],0,1)); ?></div>
              <div class="sinfo">
                <div class="sname"><?php echo e($p['name']); ?></div>
                <div class="smeta">Electricite &middot; <span class="stars">&#9733; <?php echo number_format((float)$p['rating'],1); ?></span> (<?php echo $p['review_count']; ?> avis) &middot; <?php echo e($p['price']); ?></div>
              </div>
              <div class="sactions">
                <?php if($p['phone']): ?><a href="tel:<?php echo e($p['phone']); ?>" class="btn-call">&#128222; Appeler</a><?php endif; ?>
                <a href="/servilocal/booking.php?provider=<?php echo (int)$p['id']; ?>" class="btn-bk btn-bk-w">Reserver &rarr;</a>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <a href="/servilocal/index.php?cat=electricite" class="btn-bk btn-bk-w" style="display:inline-flex;margin-top:.5rem">Voir les electriciens &rarr;</a>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

  </div>
  <?php endif; ?>

  <!-- Charts -->
  <div class="iot-charts-row">
    <div class="iot-card">
      <div class="icard-header">
        <span class="icard-title">Consommation &mdash; 24 dernieres heures</span>
        <div class="iot-tabs">
          <button class="iot-tab active" onclick="switchChart(this,'elec')">Electricite</button>
          <button class="iot-tab" onclick="switchChart(this,'gas')">Gaz</button>
          <button class="iot-tab" onclick="switchChart(this,'water')">Eau</button>
        </div>
      </div>
      <div class="cw"><canvas id="iotLineChart"></canvas></div>
      <div style="font-size:.73rem;color:var(--imu);text-align:center;margin-top:.45rem">Releves toutes les 2 heures</div>
    </div>
    <div class="iot-card">
      <div class="icard-header"><span class="icard-title">Budget mensuel en cours</span></div>
      <div class="bdg-items">
        <div class="bdg-row"><span class="bdg-lbl">&#9889; Electricite</span><div class="bdg-bar"><div class="bdg-fill" style="width:<?php echo min(100,round($sensors['budget_elec']/500*100)); ?>%;background:var(--ia)"></div></div><span class="bdg-amt"><?php echo $sensors['budget_elec']; ?> DH</span></div>
        <div class="bdg-row"><span class="bdg-lbl">&#128293; Gaz</span><div class="bdg-bar"><div class="bdg-fill" style="width:<?php echo min(100,round($sensors['budget_gas']/400*100)); ?>%;background:var(--ir)"></div></div><span class="bdg-amt"><?php echo $sensors['budget_gas']; ?> DH</span></div>
        <div class="bdg-row"><span class="bdg-lbl">&#128167; Eau</span><div class="bdg-bar"><div class="bdg-fill" style="width:<?php echo min(100,round($sensors['budget_water']/300*100)); ?>%;background:var(--ib)"></div></div><span class="bdg-amt"><?php echo $sensors['budget_water']; ?> DH</span></div>
      </div>
      <div class="bdg-footer">
        <span class="bdg-flbl">Total estime fin de mois</span>
        <div><span class="bdg-fval"><?php echo $totalBudget; ?> DH</span><span class="bdg-delta"> +18%</span></div>
      </div>
    </div>
  </div>

  <!-- Sensors + Compare -->
  <div class="iot-sensors-row">
    <div class="iot-card">
      <div class="icard-header"><span class="icard-title">Etat des capteurs installes</span></div>
      <div class="slist2">
        <div class="sitem"><div class="sitem-left"><div class="sico-wrap" style="background:var(--ibbg)">&#128167;</div><div><div class="sitem-name">Compteur eau</div><div class="sitem-loc">Couloir technique</div></div></div><div class="sitem-right"><div class="sbarm"><div class="sbarf" style="width:32%;background:var(--ib)"></div></div><span class="sbadge sb-ok">Normal</span></div></div>
        <div class="sitem"><div class="sitem-left"><div class="sico-wrap" style="background:var(--irbg)">&#128293;</div><div><div class="sitem-name">Detecteur gaz</div><div class="sitem-loc">Cuisine</div></div></div><div class="sitem-right"><div class="sbarm"><div class="sbarf" style="width:89%;background:var(--ir)"></div></div><span class="sbadge sb-d">Alerte</span></div></div>
        <div class="sitem"><div class="sitem-left"><div class="sico-wrap" style="background:var(--iabg)">&#9889;</div><div><div class="sitem-name">Tableau electrique</div><div class="sitem-loc">Entree</div></div></div><div class="sitem-right"><div class="sbarm"><div class="sbarf" style="width:67%;background:var(--ia)"></div></div><span class="sbadge sb-w">Eleve</span></div></div>
        <div class="sitem"><div class="sitem-left"><div class="sico-wrap" style="background:var(--ilbg)">&#127777;</div><div><div class="sitem-name">Thermostat</div><div class="sitem-loc">Salon</div></div></div><div class="sitem-right"><div class="sbarm"><div class="sbarf" style="width:45%;background:var(--im)"></div></div><span class="sbadge sb-ok">Normal</span></div></div>
      </div>
    </div>
    <div class="iot-card">
      <div class="icard-header">
        <span class="icard-title">Comparatif annuel (DH)</span>
        <div class="legend-row">
          <span style="display:flex;align-items:center;gap:.3rem"><span class="leg-dot" style="background:rgba(28,61,46,.3)"></span>2024</span>
          <span style="display:flex;align-items:center;gap:.3rem"><span class="leg-dot" style="background:var(--ia)"></span>2025</span>
        </div>
      </div>
      <div class="cw"><canvas id="iotBarChart"></canvas></div>
    </div>
  </div>

</div><!-- /.iot-wrap -->

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function(){
  var isDark=matchMedia("(prefers-color-scheme:dark)").matches;
  var grid=isDark?"rgba(255,255,255,.06)":"rgba(0,0,0,.05)";
  var tick="#9A958E";
  var sd={
    elec:{label:"Electricite (kW)",color:"#E8A838",bg:"rgba(232,168,56,.1)",data:[1.8, 2.1, 1.9, 2.4, 3.1, 2.8, 2.2, 2.5, 3.8, 4.2, 3.6, 2.9]},
    gas:{label:"Gaz (m3/h)",color:"#DC2626",bg:"rgba(220,38,38,.08)",data:[0.2, 0.3, 0.25, 0.28, 0.31, 0.29, 0.35, 0.3, 0.41, 0.89, 0.78, 0.6]},
    water:{label:"Eau (m3/h)",color:"#185FA5",bg:"rgba(24,95,165,.08)",data:[0.3, 0.38, 0.35, 0.4, 0.42, 0.38, 0.36, 0.41, 0.43, 0.42, 0.38, 0.4]},
  };
  var lbl=["0h","2h","4h","6h","8h","10h","12h","14h","16h","18h","20h","22h"];
  var baseOpts={responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{grid:{color:grid},ticks:{color:tick,font:{size:10}}},y:{grid:{color:grid},ticks:{color:tick,font:{size:10}}}}};
  var lc;
  function buildLine(t){
    if(lc)lc.destroy();
    var d=sd[t];
    lc=new Chart(document.getElementById("iotLineChart"),{type:"line",data:{labels:lbl,datasets:[{label:d.label,data:d.data,borderColor:d.color,backgroundColor:d.bg,borderWidth:2,fill:true,tension:.45,pointRadius:3,pointBackgroundColor:d.color,pointHoverRadius:5}]},options:Object.assign({},baseOpts,{interaction:{intersect:false,mode:"index"}})});
  }
  buildLine("elec");
  window.switchChart=function(btn,t){document.querySelectorAll(".iot-tab").forEach(function(x){x.classList.remove("active")});btn.classList.add("active");buildLine(t);};
  new Chart(document.getElementById("iotBarChart"),{type:"bar",data:{labels:["Jan","Fev","Mar","Avr","Mai","Jun"],datasets:[{label:"2024",data:[520, 490, 560, 500, 480, 510],backgroundColor:"rgba(28,61,46,.2)",borderColor:"rgba(28,61,46,.4)",borderWidth:1,borderRadius:4},{label:"2025",data:[490, 510, 540, 580, 610, 670],backgroundColor:"rgba(232,168,56,.65)",borderColor:"rgba(232,168,56,.9)",borderWidth:1,borderRadius:4}]},options:Object.assign({},baseOpts,{scales:{x:{grid:{display:false},ticks:{color:tick,font:{size:10}}},y:{grid:{color:grid},ticks:{color:tick,font:{size:10},callback:function(v){return v+" DH"}}}}})});
})();
</script>