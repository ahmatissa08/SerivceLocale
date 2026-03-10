<?php
// includes/header.php — En-tête HTML commun à toutes les pages
// Utilisation : include 'includes/header.php';
// Variables attendues : $pageTitle (string)
$pageTitle = $pageTitle ?? 'ServiLocal';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> — ServiLocal</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700;900&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/servilocal/assets/css/style.css">
</head>
<body>

<header class="site-header">
  <nav class="nav-inner">
    <a href="/servilocal/index.php" class="logo">Servi<span class="logo-dot">•</span>Local</a>

    <ul class="nav-links">
      <li><a href="/servilocal/index.php">Accueil</a></li>
      <li><a href="/servilocal/index.php#providers">Prestataires</a></li>
      <?php if (isLoggedIn()): ?>
        <li><a href="/servilocal/dashboard.php">Mon espace</a></li>
        <li><a href="/servilocal/profile.php">Mon profil</a></li>
        <li>
          <a href="/servilocal/logout.php" class="btn-nav btn-outline-sm">
            Déconnexion
          </a>
        </li>
      <?php else: ?>
        <li><a href="/servilocal/login.php">Se connecter</a></li>
        <li><a href="/servilocal/register.php" class="btn-nav">Rejoindre</a></li>
      <?php endif; ?>
    </ul>

    <!-- Mobile burger -->
    <button class="burger" id="burgerBtn" aria-label="Menu">
      <span></span><span></span><span></span>
    </button>
  </nav>
</header>

<!-- Mobile nav -->
<div class="mobile-nav" id="mobileNav">
  <a href="/servilocal/index.php">Accueil</a>
  <a href="/servilocal/index.php#providers">Prestataires</a>
  <?php if (isLoggedIn()): ?>
    <a href="/servilocal/dashboard.php">Mon espace</a>
    <a href="/servilocal/profile.php">Mon profil</a>
    <a href="/servilocal/logout.php">Déconnexion</a>
  <?php else: ?>
    <a href="/servilocal/login.php">Se connecter</a>
    <a href="/servilocal/register.php">Rejoindre gratuitement</a>
  <?php endif; ?>
</div>

<main>
