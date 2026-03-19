<?php
// ============================================================
// dashboard.php — Section IoT à intégrer dans le bloc client
// Remplace l'affichage classique quand $role === 'client'
// ============================================================
// En production, ces données viendraient de vos capteurs IoT
// via une API ou une table `sensor_readings` en base de données
// ============================================================
require_once 'db.php'; // fichier de connexion PDO
session_start();
$userId = $_SESSION['user_id'] ?? null;

// ── Simulation données capteurs (à remplacer par vraies données) ──
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}
function getSensorData(PDO $pdo, int $userId): array {
    // En production :
    // $stmt = $pdo->prepare('SELECT * FROM sensor_readings WHERE user_id = ? ORDER BY read_at DESC LIMIT 1');
    // $stmt->execute([$userId]);
    // return $stmt->fetch() ?: [];

    // Données simulées pour la démo
    return [
        'water_flow'   => 0.42,   // m³/h
        'gas_flow'     => 0.89,   // m³/h — ANORMAL (seuil : 0.30)
        'electricity'  => 4.2,    // kW    — ANORMAL (seuil : 2.5)
        'temperature'  => 22.4,   // °C
        'water_ok'     => true,
        'gas_alert'    => true,   // fuite détectée
        'elec_alert'   => true,   // pic anormal
        'temp_ok'      => true,
        // Budget mensuel
        'budget_elec'  => 370,
        'budget_gas'   => 210,
        'budget_water' => 90,
        // Historique 24h (12 points = toutes les 2h)
        'history_elec' => [1.8,2.1,1.9,2.4,3.1,2.8,2.2,2.5,3.8,4.2,3.6,2.9],
        'history_gas'  => [0.20,0.30,0.25,0.28,0.31,0.29,0.35,0.30,0.41,0.89,0.78,0.60],
        'history_water'=> [0.30,0.38,0.35,0.40,0.42,0.38,0.36,0.41,0.43,0.42,0.38,0.40],
        // Comparaison mensuelle (DH)
        'monthly_2024' => [520,490,560,500,480,510],
        'monthly_2025' => [490,510,540,580,610,670],
    ];
}

// ── Prestataires suggérés selon type d'alerte ────────────────
function getSuggestedProviders(PDO $pdo, string $category, string $city = ''): array {
    $stmt = $pdo->prepare('
        SELECT p.id, p.rating, p.price, p.review_count, p.is_available, u.name, u.phone
        FROM providers p
        JOIN users u ON u.id = p.user_id
        WHERE p.category = ?
          AND p.is_available = 1
        ORDER BY p.rating DESC
        LIMIT 3
    ');
    $stmt->execute([$category]);
    return $stmt->fetchAll();
}
if (!$pdo || !$userId) {
    die("Erreur : utilisateur non connecté ou base de données indisponible.");
}

$sensors = getSensorData($pdo, $userId);
// Récupérer les données
$sensors        = getSensorData($pdo, $userId);
$gasProviders   = $sensors['gas_alert']  ? getSuggestedProviders($pdo, 'plomberie') : [];
$elecProviders  = $sensors['elec_alert'] ? getSuggestedProviders($pdo, 'electricite') : [];
$totalAlerts    = (int)$sensors['gas_alert'] + (int)$sensors['elec_alert'];

// Seuils
$GAS_THRESHOLD  = 0.30;
$ELEC_THRESHOLD = 2.5;
?>

<!-- ============================================================
     DASHBOARD IoT — Section client connecté
     ============================================================ -->
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="iot-container">
    <div class="iot-header">
        <div class="iot-header-content">
            <h1 class="dash-title">Mon espace connecté <span>🏠</span></h1>
            <div class="status-bar">
                <span class="live-dot <?= $totalAlerts > 0 ? 'live-red' : 'live-green' ?>"></span>
                <span class="status-text">
                    <?= $totalAlerts ?> alerte<?= $totalAlerts > 1 ? 's' : '' ?> active<?= $totalAlerts > 1 ? 's' : '' ?> 
                    <span class="separator">•</span> Capteurs mis à jour à <?= date('H:i:s') ?>
                </span>
            </div>
        </div>
        <div class="iot-header-badges">
            <?php if ($totalAlerts > 0): ?>
                <span class="iot-badge badge-danger pulse-border"><?= $totalAlerts ?> ALERTE<?= $totalAlerts > 1 ? 'S' : '' ?></span>
            <?php endif; ?>
            <span class="iot-badge badge-success">4 CAPTEURS EN LIGNE</span>
        </div>
    </div>

    <div class="iot-kpi-grid">
        <div class="iot-card kpi-card">
            <div class="kpi-icon icon-water">💧</div>
            <div class="kpi-data">
                <span class="kpi-label">Consommation Eau</span>
                <span class="kpi-value"><?= $sensors['water_flow'] ?> <small>m³/h</small></span>
                <span class="kpi-status status-ok">● Débit normal</span>
            </div>
        </div>

        <div class="iot-card kpi-card <?= $sensors['gas_alert'] ? 'kpi-alert-danger' : '' ?>">
            <div class="kpi-icon icon-gas">🔥</div>
            <div class="kpi-data">
                <span class="kpi-label">Niveau de Gaz</span>
                <span class="kpi-value"><?= $sensors['gas_flow'] ?> <small>m³/h</small></span>
                <span class="kpi-status <?= $sensors['gas_alert'] ? 'status-danger' : 'status-ok' ?>">
                    <?= $sensors['gas_alert'] ? '⚠️ FUITE DÉTECTÉE' : '● Sécurisé' ?>
                </span>
            </div>
        </div>

        <div class="iot-card kpi-card <?= $sensors['elec_alert'] ? 'kpi-alert-warn' : '' ?>">
            <div class="kpi-icon icon-elec">⚡</div>
            <div class="kpi-data">
                <span class="kpi-label">Charge Élec.</span>
                <span class="kpi-value"><?= $sensors['electricity'] ?> <small>kW</small></span>
                <span class="kpi-status <?= $sensors['elec_alert'] ? 'status-warn' : 'status-ok' ?>">
                    <?= $sensors['elec_alert'] ? '⚠️ Pic de tension' : '● Stable' ?>
                </span>
            </div>
        </div>

        <div class="iot-card kpi-card">
            <div class="kpi-icon icon-temp">🌡️</div>
            <div class="kpi-data">
                <span class="kpi-label">Temp. Intérieure</span>
                <span class="kpi-value"><?= $sensors['temperature'] ?> <small>°C</small></span>
                <span class="kpi-status status-ok">● Confortable</span>
            </div>
        </div>
    </div>

    <div class="alerts-section">
        <?php if ($sensors['gas_alert']): ?>
            <div class="critical-alert">
                <div class="alert-main">
                    <div class="alert-icon-big">🧨</div>
                    <div class="alert-text">
                        <h3>Alerte Critique : Fuite de Gaz</h3>
                        <p>Une anomalie majeure (<?= $sensors['gas_flow'] ?> m³/h) a été isolée dans la zone <strong>Cuisine</strong>.</p>
                    </div>
                </div>
                <div class="provider-suggestions">
                    <h4>Intervention d'urgence disponible :</h4>
                    <div class="provider-grid">
                        <?php foreach ($gasProviders as $prov): ?>
                            <div class="provider-item">
                                <div class="prov-info">
                                    <div class="prov-avatar"><?= substr($prov['name'],0,1) ?></div>
                                    <div>
                                        <div class="prov-name"><?= e($prov['name']) ?></div>
                                        <div class="prov-meta">⭐ <?= $prov['rating'] ?> • <?= e($prov['price']) ?></div>
                                    </div>
                                </div>
                               <div class="prov-actions">
    <?php if ($prov['phone']): ?>
        <a href="tel:<?= e($prov['phone']) ?>" class="btn-icon" title="Appeler">📞</a>
    <?php endif; ?>
    <a href="/servilocal/booking.php?provider=<?= (int)$prov['id'] ?>" class="btn-action">
        Réserver →
    </a>
</div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="charts-row">
        <div class="iot-card chart-container">
            <div class="chart-header">
                <h3>Historique 24h</h3>
                <div class="tab-group">
                    <button class="tab-btn active" onclick="switchIotChart(this,'elec')">Élec</button>
                    <button class="tab-btn" onclick="switchIotChart(this,'gas')">Gaz</button>
                    <button class="tab-btn" onclick="switchIotChart(this,'water')">Eau</button>
                </div>
            </div>
            <div class="canvas-wrapper">
                <canvas id="iotLineChart"></canvas>
            </div>
        </div>

        <div class="iot-card chart-container">
            <div class="chart-header">
                <h3>Comparatif Annuel (DH)</h3>
            </div>
            <div class="canvas-wrapper">
                <canvas id="iotBarChart"></canvas>
            </div>
        </div>
    </div>

    <div class="iot-card budget-card">
        <h3>Prévisions Budgétaires Mensuelles</h3>
        <div class="budget-lines">
            <?php 
            $budgets = [
                ['Elec', $sensors['budget_elec'], 500, '#E8A838'],
                ['Gaz', $sensors['budget_gas'], 400, '#E05C3A'],
                ['Eau', $sensors['budget_water'], 300, '#4facfe']
            ];
            foreach($budgets as [$label, $current, $max, $color]): 
                $perc = min(100, ($current/$max)*100);
            ?>
            <div class="budget-item">
                <div class="budget-info">
                    <span><?= $label ?></span>
                    <strong><?= $current ?> DH <small>/ <?= $max ?> DH</small></strong>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?= $perc ?>%; background: <?= $color ?>;"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<style>
:root {
    --iot-bg: #f8fafc;
    --iot-card: #ffffff;
    --forest: #1e293b;
    --accent: #22c55e;
    --danger: #ef4444;
    --warning: #f59e0b;
    --muted: #64748b;
    --border: #e2e8f0;
    --radius: 16px;
    --shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
}

.iot-container {
    font-family: 'Outfit', sans-serif;
    background: var(--iot-bg);
    padding: 2rem;
    color: var(--forest);
    max-width: 1200px;
    margin: 0 auto;
}

/* Header */
.iot-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 2rem;
}
.dash-title {
    font-family: 'Playfair Display', serif;
    font-size: 2rem;
    margin: 0;
}
.status-bar {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 8px;
    font-size: 0.9rem;
    color: var(--muted);
}
.separator { opacity: 0.3; padding: 0 5px; }

/* Badges */
.iot-badge {
    padding: 6px 14px;
    border-radius: 99px;
    font-weight: 700;
    font-size: 0.75rem;
    letter-spacing: 0.5px;
}
.badge-danger { background: #fee2e2; color: var(--danger); }
.badge-success { background: #dcfce7; color: var(--accent); }

/* KPI Cards */
.iot-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}
.iot-card {
    background: var(--iot-card);
    border-radius: var(--radius);
    padding: 1.5rem;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
    transition: transform 0.2s;
}
.kpi-card {
    display: flex;
    align-items: center;
    gap: 1.25rem;
}
.kpi-icon {
    font-size: 2rem;
    width: 60px;
    height: 60px;
    background: var(--iot-bg);
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
}
.kpi-label { font-size: 0.85rem; color: var(--muted); display: block; }
.kpi-value { font-size: 1.5rem; font-weight: 700; display: block; margin: 4px 0; }
.kpi-status { font-size: 0.75rem; font-weight: 600; }
.status-ok { color: var(--accent); }
.status-danger { color: var(--danger); animation: blink 1s infinite; }
.status-warn { color: var(--warning); }

/* Alert Section */
.critical-alert {
    background: linear-gradient(135deg, #fff5f5 0%, #ffffff 100%);
    border: 2px solid #feb2b2;
    border-radius: var(--radius);
    padding: 1.5rem;
    margin-bottom: 2rem;
}
.alert-main { display: flex; gap: 1rem; align-items: center; margin-bottom: 1.5rem; }
.alert-icon-big { font-size: 2.5rem; }
.alert-text h3 { margin: 0; color: #c53030; }
.provider-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1rem;
}
.provider-item {
    background: white;
    padding: 1rem;
    border-radius: 12px;
    border: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.prov-info { display: flex; gap: 10px; align-items: center; }
.prov-avatar {
    width: 40px; height: 40px; background: var(--forest);
    color: white; border-radius: 50%; display: flex;
    align-items: center; justify-content: center; font-weight: bold;
}
.btn-action {
    background: var(--forest); color: white;
    padding: 6px 12px; border-radius: 8px; text-decoration: none; font-size: 0.8rem;
}

/* Charts */
.charts-row {
    display: grid;
    grid-template-columns: 1.5fr 1fr;
    gap: 1.5rem;
    margin-bottom: 2rem;
}
.chart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}
.canvas-wrapper { height: 220px; }
.tab-group { background: var(--iot-bg); padding: 4px; border-radius: 8px; }
.tab-btn {
    border: none; background: none; padding: 4px 12px;
    border-radius: 6px; font-size: 0.8rem; cursor: pointer;
}
.tab-btn.active { background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.05); font-weight: 600; }

/* Budget */
.budget-lines { margin-top: 1rem; }
.budget-item { margin-bottom: 1.2rem; }
.budget-info { display: flex; justify-content: space-between; font-size: 0.9rem; margin-bottom: 6px; }
.progress-bar { height: 8px; background: var(--iot-bg); border-radius: 10px; overflow: hidden; }
.progress-fill { height: 100%; border-radius: 10px; transition: width 1s ease-in-out; }

@keyframes blink { 50% { opacity: 0.5; } }
@keyframes pulse-border { 0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); } 70% { box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); } 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); } }
.pulse-border { animation: pulse-border 2s infinite; }

@media (max-width: 768px) {
    .charts-row { grid-template-columns: 1fr; }
    .iot-header { flex-direction: column; }
}
</style>

<script>
// Configuration des graphiques (Utilisation des données injectées par PHP)
const sensorData = {
    elec: { label: 'Électricité (kW)', color: '#E8A838', data: <?= json_encode($sensors['history_elec']) ?> },
    gas: { label: 'Gaz (m³/h)', color: '#E05C3A', data: <?= json_encode($sensors['history_gas']) ?> },
    water: { label: 'Eau (m³/h)', color: '#4facfe', data: <?= json_encode($sensors['history_water']) ?> }
};

let lineChart;
function buildLineChart(type) {
    const ctx = document.getElementById('iotLineChart').getContext('2d');
    if (lineChart) lineChart.destroy();
    
    const d = sensorData[type];
    lineChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['0h','2h','4h','6h','8h','10h','12h','14h','16h','18h','20h','22h'],
            datasets: [{
                label: d.label,
                data: d.data,
                borderColor: d.color,
                backgroundColor: d.color + '20',
                fill: true,
                tension: 0.4,
                borderWidth: 3,
                pointRadius: 0,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { display: false } },
                x: { grid: { color: '#f0f0f0' } }
            }
        }
    });
}

// Initialisation
buildLineChart('elec');

function switchIotChart(btn, type) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    buildLineChart(type);
}

// Graphique à barres
new Chart(document.getElementById('iotBarChart'), {
    type: 'bar',
    data: {
        labels: ['Jan','Fév','Mar','Avr','Mai','Juin'],
        datasets: [
            { label: '2024', data: <?= json_encode($sensors['monthly_2024']) ?>, backgroundColor: '#cbd5e1', borderRadius: 4 },
            { label: '2025', data: <?= json_encode($sensors['monthly_2025']) ?>, backgroundColor: '#E8A838', borderRadius: 4 }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, usePointStyle: true } } },
        scales: { y: { grid: { borderDash: [5,5] } } }
    }
});
</script>