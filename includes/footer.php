<?php
// includes/footer.php — Pied de page commun EGES Technologies
// Styles auto-contenus — fonctionne sur toutes les pages
?>
</main>

<style>
/* ── Footer EGES — styles propres, ne dépendent d'aucune page ── */
.eges-footer {
  background: #0B2518;
  padding: 4rem 2rem 0;
  margin-top: 4rem;
  font-family: 'DM Sans', 'Outfit', sans-serif;
}
.eges-footer * { box-sizing: border-box; }

.eges-footer-in {
  max-width: 1280px;
  margin: 0 auto;
}

/* Grille 4 colonnes */
.eges-footer-grid {
  display: grid;
  grid-template-columns: 2fr 1fr 1fr 1fr;
  gap: 3rem;
  padding-bottom: 3rem;
  border-bottom: 1px solid rgba(255,255,255,.08);
}

/* Colonne marque */
.eges-ft-brand { display: flex; flex-direction: column; gap: .9rem; }
.eges-ft-logo {
  font-family: 'Syne', 'DM Sans', sans-serif;
  font-size: 1.5rem;
  font-weight: 800;
  color: #fff;
  text-decoration: none;
  display: flex;
  flex-direction: column;
  line-height: 1.15;
}
.eges-ft-logo .accent { color: #52B788; }
.eges-ft-logo .tagline {
  font-size: .6rem;
  font-weight: 500;
  color: rgba(255,255,255,.38);
  text-transform: uppercase;
  letter-spacing: .12em;
  font-family: 'DM Sans', sans-serif;
  margin-top: .15rem;
}
.eges-ft-desc {
  font-size: .84rem;
  color: rgba(255,255,255,.38);
  line-height: 1.75;
  max-width: 280px;
}
.eges-ft-badges {
  display: flex;
  gap: .5rem;
  flex-wrap: wrap;
  margin-top: .25rem;
}
.eges-ft-badge {
  background: rgba(82,183,136,.12);
  color: #95D5B2;
  font-size: .68rem;
  font-weight: 700;
  padding: .22rem .65rem;
  border-radius: 50px;
  border: 1px solid rgba(82,183,136,.2);
}

/* Colonnes nav */
.eges-ft-col h4 {
  color: #fff;
  font-size: .7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .12em;
  margin-bottom: 1.1rem;
  font-family: 'DM Sans', sans-serif;
}
.eges-ft-col ul { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: .45rem; }
.eges-ft-col li a {
  color: rgba(255,255,255,.38);
  text-decoration: none;
  font-size: .84rem;
  transition: color .2s;
  display: flex;
  align-items: center;
  gap: .4rem;
}
.eges-ft-col li a:hover { color: #52B788; }
.eges-ft-col li a .ico { font-size: .8rem; flex-shrink: 0; }

/* Barre du bas */
.eges-ft-bot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 1rem;
  padding: 1.5rem 0 2rem;
  font-size: .78rem;
  color: rgba(255,255,255,.25);
}
.eges-ft-bot a { color: rgba(255,255,255,.35); text-decoration: none; transition: color .2s; }
.eges-ft-bot a:hover { color: #52B788; }
.eges-ft-right { display: flex; align-items: center; gap: 1.25rem; }
.eges-ompic-tag {
  background: rgba(245,158,11,.12);
  color: #FCD34D;
  font-size: .67rem;
  font-weight: 700;
  padding: .2rem .6rem;
  border-radius: 50px;
  border: 1px solid rgba(245,158,11,.2);
}

/* Responsive */
@media (max-width: 900px) {
  .eges-footer-grid { grid-template-columns: 1fr 1fr; gap: 2rem; }
  .eges-ft-brand { grid-column: span 2; }
}
@media (max-width: 560px) {
  .eges-footer-grid { grid-template-columns: 1fr; }
  .eges-ft-brand { grid-column: span 1; }
  .eges-ft-bot { flex-direction: column; align-items: flex-start; gap: .6rem; }
}
</style>

<footer class="eges-footer">
  <div class="eges-footer-in">
    <div class="eges-footer-grid">

      <!-- Marque -->
      <div class="eges-ft-brand">
        <a href="/servilocal/index.php" class="eges-ft-logo">
          EGES<span class="accent"> Technologies</span>
          <span class="tagline">Smart Energy, Smart Living</span>
        </a>
        <p class="eges-ft-desc">
          Solution IoT de monitoring énergétique. Détection prédictive des fuites gaz, surconsommation
          eau et pics électriques. Mise en relation instantanée avec des prestataires certifiés.
        </p>
        <div class="eges-ft-badges">
          <span class="eges-ft-badge">PHP 8</span>
          <span class="eges-ft-badge">MySQL 8</span>
          <span class="eges-ft-badge">IoT</span>
          <span class="eges-ft-badge">SARL</span>
        </div>
      </div>

      <!-- Services IoT (catégories rapport) -->
      <div class="eges-ft-col">
        <h4>Services IoT</h4>
        <ul>
          <li><a href="/servilocal/index.php?cat=gaz"><span class="ico">🔥</span>Gaz — seuil 0.30 m³/h</a></li>
          <li><a href="/servilocal/index.php?cat=eau"><span class="ico">💧</span>Eau — seuil 0.50 m³/h</a></li>
          <li><a href="/servilocal/index.php?cat=electricite"><span class="ico">⚡</span>Électricité — 2.50 kW</a></li>
          <li><a href="/servilocal/index.php"><span class="ico">✦</span>Tous les prestataires</a></li>
        </ul>
      </div>

      <!-- Plateforme -->
      <div class="eges-ft-col">
        <h4>Plateforme</h4>
        <ul>
          <li><a href="/servilocal/index.php"><span class="ico">🏠</span>Accueil</a></li>
          <li><a href="/servilocal/register.php?role=provider"><span class="ico">🔧</span>Devenir prestataire</a></li>
          <?php if (function_exists('isLoggedIn') && isLoggedIn()): ?>
            <li><a href="/servilocal/dashboard.php"><span class="ico">📊</span>Mon tableau de bord</a></li>
            <li><a href="/servilocal/profile.php"><span class="ico">👤</span>Mon profil</a></li>
            <li><a href="/servilocal/logout.php"><span class="ico">🚪</span>Déconnexion</a></li>
          <?php else: ?>
            <li><a href="/servilocal/login.php"><span class="ico">🔑</span>Connexion</a></li>
            <li><a href="/servilocal/register.php"><span class="ico">✨</span>Inscription gratuite</a></li>
          <?php endif; ?>
        </ul>
      </div>

      <!-- Rapport & Financement -->
      <div class="eges-ft-col">
        <h4>Projet · Rapport</h4>
        <ul>
          <li><a href="#pricing"><span class="ico">💰</span>Tarifs Freemium</a></li>
          <li><a href="#seuils"><span class="ico">📡</span>Seuils IoT</a></li>
          <li><a href="#"><span class="ico">🏛️</span>Innov Invest — 200k MAD</a></li>
          <li><a href="#"><span class="ico">📋</span>Rapport Mundiapolis 2025</a></li>
        </ul>
      </div>

    </div>

    <!-- Bas de page -->
    <div class="eges-ft-bot">
      <span>© <?= date('Y') ?> EGES Technologies / ServiLocal. Tous droits réservés.</span>
      <div class="eges-ft-right">
        <span class="eges-ompic-tag">OMPIC · SARL en cours</span>
        <span>Fait avec ♥ au Maroc · Casablanca–Rabat</span>
      </div>
    </div>
  </div>
</footer>

<div class="toast" id="toastMsg"></div>
<button class="scrolltop" id="scrollTopBtn" onclick="window.scrollTo({top:0,behavior:'smooth'})" title="Retour en haut" style="position:fixed;bottom:2rem;left:2rem;width:40px;height:40px;border-radius:50%;background:#1A4731;color:#fff;border:none;cursor:pointer;z-index:800;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 16px rgba(0,0,0,.2);font-size:1rem;transition:all .3s;opacity:0;transform:translateY(15px);pointer-events:none">↑</button>

<script src="/servilocal/assets/js/main.js"></script>
<script>
// Scroll to top visibility
window.addEventListener('scroll', function () {
  var btn = document.getElementById('scrollTopBtn');
  if (btn) {
    btn.style.opacity    = window.scrollY > 400 ? '1' : '0';
    btn.style.transform  = window.scrollY > 400 ? 'translateY(0)' : 'translateY(15px)';
    btn.style.pointerEvents = window.scrollY > 400 ? 'auto' : 'none';
  }
});
</script>
</body>
</html>