<?php
// includes/footer.php — Pied de page commun
?>
</main>

<footer class="site-footer">
  <div class="footer-inner">
    <div class="footer-grid">
      <div class="footer-brand">
        <a href="/servilocal/index.php" class="logo">Servi<span class="logo-dot">•</span>Local</a>
        <p>La plateforme qui connecte les habitants avec les meilleurs prestataires locaux.</p>
      </div>
      <div class="footer-col">
        <h4>Services</h4>
        <ul>
          <li><a href="/servilocal/index.php?cat=plomberie">Plomberie</a></li>
          <li><a href="/servilocal/index.php?cat=electricite">Électricité</a></li>
          <li><a href="/servilocal/index.php?cat=informatique">Informatique</a></li>
          <li><a href="/servilocal/index.php?cat=menage">Ménage</a></li>
        </ul>
      </div>
      <div class="footer-col">
        <h4>Compte</h4>
        <ul>
          <?php if (isLoggedIn()): ?>
            <li><a href="/servilocal/dashboard.php">Mon tableau de bord</a></li>
            <li><a href="/servilocal/profile.php">Mon profil</a></li>
            <li><a href="/servilocal/logout.php">Déconnexion</a></li>
          <?php else: ?>
            <li><a href="/servilocal/login.php">Connexion</a></li>
            <li><a href="/servilocal/register.php">Inscription</a></li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <span>© <?= date('Y') ?> ServiLocal. Tous droits réservés.</span>
      <span>Fait avec ♥ au Maroc</span>
    </div>
  </div>
</footer>

<div class="toast" id="toastMsg"></div>
<button class="scrolltop" id="scrollTopBtn" onclick="window.scrollTo({top:0,behavior:'smooth'})" title="Retour en haut">↑</button>

<script src="/servilocal/assets/js/main.js"></script>
</body>
</html>
