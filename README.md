# 🏠 ServiLocal — Plateforme de Services de Proximité

> Connectez les habitants avec les meilleurs prestataires locaux : plombiers, électriciens, coiffeurs, informaticiens et bien plus.

![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![HTML5](https://img.shields.io/badge/HTML5-E34F26?style=for-the-badge&logo=html5&logoColor=white)
![CSS3](https://img.shields.io/badge/CSS3-1572B6?style=for-the-badge&logo=css3&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)
![XAMPP](https://img.shields.io/badge/XAMPP-Compatible-FB7A24?style=for-the-badge&logo=xampp&logoColor=white)

---

## 📋 Table des matières

- [Aperçu](#-aperçu)
- [Fonctionnalités](#-fonctionnalités)
- [Stack technique](#-stack-technique)
- [Structure du projet](#-structure-du-projet)
- [Installation](#-installation)
- [Base de données](#-base-de-données)
- [Comptes de démo](#-comptes-de-démo)
- [Sécurité](#-sécurité)
- [Captures d'écran](#-captures-décran)
- [Contribuer](#-contribuer)

---

## 🌟 Aperçu

ServiLocal est une application web complète qui met en relation des clients avec des prestataires de services locaux. Les utilisateurs peuvent rechercher, comparer, réserver et évaluer des professionnels près de chez eux.

---

## ✨ Fonctionnalités

### 👤 Authentification
- Inscription avec choix du rôle : **Client** ou **Prestataire**
- Connexion sécurisée avec `password_hash` / `password_verify` (bcrypt)
- Gestion des sessions PHP avec régénération d'ID
- Protection CSRF sur tous les formulaires
- Déconnexion avec destruction complète de session

### 🔍 Recherche & Découverte
- Recherche multi-critères : nom/service, ville, catégorie
- Filtres rapides : disponibles, vérifiés
- Tri : par note, nom, prix, nombre d'avis
- Pagination des résultats
- Navigation par catégorie (8 catégories)

### 📅 Réservations
- Formulaire de réservation avec date, heure, adresse et description
- Vérification des conflits de créneaux
- Statuts : `En attente` → `Acceptée` / `Refusée` → `Terminée` / `Annulée`
- Le client peut annuler ses réservations en attente

### 📊 Tableau de bord
| Rôle | Fonctionnalités |
|------|-----------------|
| **Client** | Voir toutes ses réservations, suivre les statuts, laisser des avis |
| **Prestataire** | Voir les demandes reçues, accepter/refuser en un clic, consulter ses avis |

### ⭐ Avis & Évaluations
- Notation interactive de 1 à 5 étoiles
- Commentaire libre
- Recalcul automatique de la note moyenne (trigger SQL)
- Affichage de la distribution des notes par étoile

### 👤 Profil utilisateur
- Modifier nom, email, téléphone
- Upload de photo de profil (JPG, PNG, GIF, WEBP — max 2 Mo)
- Changement de mot de passe avec vérification de l'ancien
- Prestataires : modifier catégorie, ville, tarif, description, disponibilité

---

## 🛠 Stack technique

| Couche | Technologie |
|--------|-------------|
| Backend | PHP 8.0+ |
| Base de données | MySQL 8 via PDO |
| Frontend | HTML5, CSS3 (variables CSS, Grid, Flexbox) |
| JavaScript | Vanilla JS (ES6+) |
| Polices | Google Fonts (Playfair Display + Outfit) |
| Serveur local | XAMPP (Apache + MySQL) |

---

## 📁 Structure du projet

```
servilocal/
│
├── 📄 index.php              # Page d'accueil, recherche, liste prestataires
├── 📄 login.php              # Connexion utilisateur
├── 📄 register.php           # Inscription (client ou prestataire)
├── 📄 logout.php             # Déconnexion
├── 📄 provider.php           # Fiche détaillée d'un prestataire
├── 📄 booking.php            # Formulaire de réservation
├── 📄 dashboard.php          # Tableau de bord (client & prestataire)
├── 📄 profile.php            # Gestion du profil utilisateur
├── 📄 reviews.php            # Formulaire d'avis
├── 📄 db.php                 # Connexion PDO à MySQL
│
├── 📁 includes/
│   ├── functions.php         # Helpers : CSRF, sessions, formatage, sécurité
│   ├── header.php            # En-tête HTML commun
│   └── footer.php            # Pied de page HTML commun
│
├── 📁 assets/
│   ├── css/style.css         # Feuille de style complète (responsive)
│   └── js/main.js            # Scripts : étoiles interactives, toast, burger menu
│
├── 📁 uploads/
│   └── profiles/             # Photos de profil uploadées (auto-créé)
│
└── 📄 database.sql           # Script SQL complet (tables + données de démo)
```

---

## 🚀 Installation

### Prérequis
- [XAMPP](https://www.apachefriends.org/) (ou tout serveur Apache + PHP 8+ + MySQL)
- Un navigateur moderne

### Étapes

**1. Cloner le dépôt**
```bash
git clone https://github.com/votre-username/servilocal.git
```

**2. Copier dans le dossier XAMPP**
```bash
# Windows
cp -r servilocal C:/xampp/htdocs/servilocal

# macOS
cp -r servilocal /Applications/XAMPP/htdocs/servilocal
```

**3. Démarrer XAMPP**

Lancer **Apache** et **MySQL** depuis le panneau de contrôle XAMPP.

**4. Importer la base de données**

- Ouvrir [http://localhost/phpmyadmin](http://localhost/phpmyadmin)
- Créer une base de données nommée `servilocal` (ou laisser le script le faire)
- Cliquer sur **Importer** → sélectionner `database.sql` → **Exécuter**

**5. Configurer la connexion** *(si nécessaire)*

Ouvrir `db.php` et adapter les paramètres :
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'servilocal');
define('DB_USER', 'root');
define('DB_PASS', '');   // Vide par défaut sur XAMPP
```

**6. Lancer l'application**

Ouvrir [http://localhost/servilocal](http://localhost/servilocal) 🎉

---

## 🗄 Base de données

### Schéma

```
users ─────────────────────────────────────────────────
  id, name, email, password, phone, role, avatar, is_active, created_at

providers ─────────────────────────────────────────────
  id, user_id (FK→users), category, city, price,
  description, avatar_color, is_available, is_verified,
  rating, review_count, created_at

bookings ──────────────────────────────────────────────
  id, client_id (FK→users), provider_id (FK→providers),
  booking_date, booking_time, address, description,
  status (pending|accepted|refused|completed|cancelled), created_at

reviews ────────────────────────────────────────────────
  id, client_id (FK→users), provider_id (FK→providers),
  booking_id (FK→bookings), rating (1-5), comment, created_at
```

### Relations
- Un `user` peut avoir un profil `provider` (relation 1:1)
- Un `client` peut avoir plusieurs `bookings`
- Un `booking` peut avoir un `review`
- Un `trigger` MySQL recalcule automatiquement `providers.rating` après chaque avis

---

## 🔑 Comptes de démo

Tous les comptes ont le mot de passe : **`password123`**

| Rôle | Email | Nom |
|------|-------|-----|
| Client | `client@demo.com` | Mariam Client |
| Prestataire | `amine@demo.com` | Amine Cherkaoui (Plomberie) |
| Prestataire | `sara@demo.com` | Sara Moukhliss (Coiffure) |
| Prestataire | `karim@demo.com` | Karim Ziani (Informatique) |
| Prestataire | `hassan@demo.com` | Hassan El Fassi (Électricité) |
| Prestataire | `fatima@demo.com` | Fatima Benali (Ménage) |

---

## 🔒 Sécurité

| Mesure | Implémentation |
|--------|----------------|
| Mots de passe | `password_hash()` / `password_verify()` (bcrypt) |
| Injections SQL | PDO avec **prepared statements** sur toutes les requêtes |
| XSS | `htmlspecialchars()` sur tout l'output via la fonction `e()` |
| CSRF | Token de session sur tous les formulaires POST |
| Fixation de session | `session_regenerate_id(true)` à la connexion |
| Uploads | Validation de l'extension et de la taille, nom de fichier aléatoire |
| Accès restreint | `requireLogin()` sur les pages protégées, vérification du rôle |

---

## 🗺 Pages & Routes

| URL | Description | Accès |
|-----|-------------|-------|
| `/servilocal/` | Page d'accueil | Public |
| `/servilocal/login.php` | Connexion | Invité |
| `/servilocal/register.php` | Inscription | Invité |
| `/servilocal/logout.php` | Déconnexion | Connecté |
| `/servilocal/provider.php?id=X` | Fiche prestataire | Public |
| `/servilocal/booking.php?provider=X` | Réserver | Client |
| `/servilocal/dashboard.php` | Tableau de bord | Connecté |
| `/servilocal/profile.php` | Mon profil | Connecté |
| `/servilocal/reviews.php?provider=X` | Laisser un avis | Client |

---

## 🤝 Contribuer

Les contributions sont les bienvenues !

1. Forker le projet
2. Créer une branche : `git checkout -b feature/ma-fonctionnalite`
3. Committer : `git commit -m 'feat: ajout de ma fonctionnalité'`
4. Pusher : `git push origin feature/ma-fonctionnalite`
5. Ouvrir une **Pull Request**

### Idées d'améliorations futures
- [ ] Messagerie interne client ↔ prestataire
- [ ] Notifications par email (PHPMailer)
- [ ] Carte interactive des prestataires (Leaflet.js)
- [ ] Système de paiement en ligne
- [ ] Panel d'administration
- [ ] API REST pour une application mobile
- [ ] Export des réservations en PDF

---

## 📄 Licence

Ce projet est sous licence **MIT**. Voir le fichier [LICENSE](LICENSE) pour plus de détails.

---

<div align="center">

Fait avec ♥ au Maroc · [ServiLocal](http://localhost/servilocal)

</div>