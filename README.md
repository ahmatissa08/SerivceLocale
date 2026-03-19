<div align="center">

# 🏠 ServiLocal

**Plateforme intelligente de services de proximité avec monitoring IoT**

*Connectez les habitants avec les meilleurs prestataires locaux — plombiers, électriciens, coiffeurs et bien plus. Détectez les pannes avant qu'elles arrivent.*

---

[![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4?style=flat-square&logo=php&logoColor=white)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?style=flat-square&logo=mysql&logoColor=white)](https://mysql.com)
[![JavaScript](https://img.shields.io/badge/JavaScript-ES6+-F7DF1E?style=flat-square&logo=javascript&logoColor=black)](https://developer.mozilla.org/fr/docs/Web/JavaScript)
[![Chart.js](https://img.shields.io/badge/Chart.js-4.4-FF6384?style=flat-square&logo=chart.js&logoColor=white)](https://chartjs.org)
[![XAMPP](https://img.shields.io/badge/XAMPP-Compatible-FB7A24?style=flat-square&logo=xampp&logoColor=white)](https://apachefriends.org)
[![License](https://img.shields.io/badge/Licence-MIT-green?style=flat-square)](LICENSE)

---

[Aperçu](#-aperçu) · [Fonctionnalités](#-fonctionnalités) · [Installation](#-installation) · [Structure](#-structure-du-projet) · [Base de données](#-base-de-données) · [IoT](#-module-iot) · [Admin](#-panneau-dadministration) · [Sécurité](#-sécurité) · [Démo](#-comptes-de-démo)

</div>

---

## 📋 Aperçu

ServiLocal est une application web **full-stack PHP/MySQL** qui met en relation des clients avec des prestataires de services locaux. Elle intègre un module **IoT de monitoring intelligent** qui analyse la consommation en temps réel (eau, gaz, électricité, température) et déclenche automatiquement des alertes avec suggestion directe des prestataires compétents.

```
Client cherche un plombier         →  Recherche, compare, réserve en ligne
Capteur détecte une fuite de gaz   →  Alerte automatique + plombiers suggérés
Prestataire reçoit une demande     →  Accepte ou refuse depuis son dashboard
Administrateur surveille           →  Gestion complète depuis le panneau admin
```

---

## ✨ Fonctionnalités

### 🔐 Authentification & Rôles

| Fonctionnalité | Détail |
|---|---|
| Inscription | Choix du rôle : Client ou Prestataire |
| Connexion | `password_hash` bcrypt + `password_verify` |
| Sessions | Régénération d'ID anti-fixation |
| Protection | Tokens CSRF sur tous les formulaires POST |
| Rôles | `client` · `provider` · `admin` |
| Déconnexion | Destruction complète de session + cookie |

### 🔍 Recherche & Découverte

- Recherche **multi-critères** : nom/service, ville, catégorie en temps réel
- **8 catégories** de services avec compteurs dynamiques
- **Filtres** : disponibles, vérifiés, top notés, nouveaux
- **Tri** : par note, nom, prix croissant/décroissant, nombre d'avis
- **Pagination** côté serveur
- Navigation directe par catégorie

### 📅 Réservations

- Formulaire complet : date, heure, adresse, description
- Vérification des **conflits de créneaux** en base de données
- Workflow de statuts : `En attente` → `Acceptée / Refusée` → `Terminée / Annulée`
- Client : annulation possible des réservations en attente
- Prestataire : acceptation/refus en un clic depuis le dashboard

### 📊 Tableau de bord

**Vue Client**
- Suivi de toutes ses réservations avec statuts colorés
- Actions contextuelles (annuler, laisser un avis)
- Historique des avis publiés
- Module IoT intégré

**Vue Prestataire**
- Demandes reçues avec coordonnées du client
- Accepter / Refuser chaque réservation
- Avis reçus avec notes et commentaires
- Statistiques : total, en attente, acceptées, note moyenne

### 👤 Profil Utilisateur

- Modifier nom, email, téléphone
- **Upload photo de profil** (JPG/PNG/GIF/WEBP, max 2 Mo, nom aléatoire côté serveur)
- Changement de mot de passe avec vérification de l'ancien
- Prestataire : modifier catégorie, ville, tarif, description, toggle disponibilité

### ⭐ Avis & Évaluations

- Notation interactive **1 à 5 étoiles** en JavaScript
- Commentaire libre
- Protection anti-doublon par réservation
- Recalcul automatique de la **note moyenne** via trigger MySQL
- Distribution des notes avec barres de progression
- Affichage des avis sur la fiche prestataire

### 🏠 Module IoT — Monitoring Intelligent

- Dashboard consommations temps réel : eau, gaz, électricité, température
- **Détection automatique d'anomalies** avec seuils configurables par utilisateur
- Alertes visuelles prioritaires (rouge = danger, orange = avertissement)
- **Suggestion intelligente** des prestataires compétents lors d'une alerte
- Graphiques 24h par capteur (Chart.js), switcher Électricité / Gaz / Eau
- Comparaison mensuelle budgétaire 2024 vs 2025
- Historique des relevés en base (`sensor_readings`)
- Log des incidents avec statut actif/résolu (`sensor_alerts`)

### 👑 Panneau d'Administration

- Dashboard avec 6 KPIs globaux et graphiques d'activité
- CRUD complet : Utilisateurs, Prestataires, Réservations, Avis
- Modals d'édition inline sans rechargement de page
- Vérification des prestataires en un clic
- Thème dark professionnel avec sidebar fixe et navigation fluide

---

## 🛠 Stack Technique

| Couche | Technologie | Usage |
|---|---|---|
| **Backend** | PHP 8.0+ | Logique métier, sessions, sécurité |
| **Base de données** | MySQL 8 via PDO | Données, relations, triggers |
| **Frontend** | HTML5 / CSS3 | Interface responsive, design system |
| **Scripts** | Vanilla JavaScript ES6+ | Interactions, charts, live updates |
| **Graphiques** | Chart.js 4.4 | Visualisation IoT et statistiques |
| **Polices** | Google Fonts | Playfair Display + Outfit |
| **Serveur local** | XAMPP | Apache + PHP + MySQL |

---

## 📁 Structure du Projet

```
servilocal/
│
├── 📄 index.php                   # Accueil · Recherche · Prestataires · Pagination
├── 📄 login.php                   # Connexion sécurisée · Redirection par rôle
├── 📄 register.php                # Inscription client ou prestataire
├── 📄 logout.php                  # Déconnexion + destruction session
├── 📄 provider.php                # Fiche prestataire + avis + distribution notes
├── 📄 booking.php                 # Réservation avec vérification créneaux
├── 📄 dashboard.php               # Tableau de bord (client & prestataire)
├── 📄 dashboard_iot_section.php   # Module IoT capteurs · alertes · charts
├── 📄 profile.php                 # Profil · Upload photo · Mot de passe · Pro
├── 📄 reviews.php                 # Formulaire avis étoiles interactif
├── 📄 admin.php                   # Panneau administration (rôle admin)
├── 📄 db.php                      # Connexion PDO MySQL
│
├── 📁 includes/
│   ├── functions.php              # CSRF · Sessions · Helpers · Sécurité
│   ├── header.php                 # En-tête HTML commun + nav responsive
│   └── footer.php                 # Pied de page · Toast · ScrollTop
│
├── 📁 assets/
│   ├── css/style.css              # Design system (variables · composants · responsive)
│   └── js/main.js                 # Burger menu · Étoiles · Toast · Actions
│
├── 📁 uploads/profiles/           # Photos de profil uploadées (auto-créé)
│
├── 📄 database.sql                # Schéma + données démo + triggers MySQL
└── 📄 database_iot.sql            # Tables IoT : readings · alerts · thresholds
```

---

## 🚀 Installation

### Prérequis

- [XAMPP](https://www.apachefriends.org/) v8.0+ (Apache + PHP 8 + MySQL 8)
- Navigateur moderne (Chrome, Firefox, Edge, Safari)
- Connexion internet pour les polices Google Fonts

### Étape 1 — Cloner le projet

```bash
git clone https://github.com/votre-username/servilocal.git
```

### Étape 2 — Copier dans XAMPP

```bash
# Windows
xcopy servilocal C:\xampp\htdocs\servilocal /E /I

# macOS
cp -r servilocal /Applications/XAMPP/htdocs/servilocal

# Linux
cp -r servilocal /opt/lampp/htdocs/servilocal
```

### Étape 3 — Démarrer XAMPP

Lancer **Apache** et **MySQL** depuis le panneau de contrôle XAMPP.

### Étape 4 — Importer la base de données

1. Ouvrir [http://localhost/phpmyadmin](http://localhost/phpmyadmin)
2. Cliquer **Nouvelle base de données** → nommer `servilocal` → **Créer**
3. Sélectionner `servilocal` → onglet **Importer**
4. Choisir `database.sql` → **Exécuter**
5. Répéter avec `database_iot.sql` pour le module IoT

### Étape 5 — Configurer la connexion *(si nécessaire)*

Ouvrir `db.php` et adapter les paramètres :

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'servilocal');
define('DB_USER', 'root');
define('DB_PASS', '');   // Vide par défaut sur XAMPP
```

### Étape 6 — Corriger les mots de passe démo

Si les comptes démo refusent la connexion, créer `fix_passwords.php` à la racine :

```php
<?php
require_once 'db.php';
$hash = password_hash('password123', PASSWORD_BCRYPT);
$pdo->prepare("UPDATE users SET password = ?")->execute([$hash]);
echo "✅ Mots de passe mis à jour. Supprimez ce fichier immédiatement !";
```

Visiter `http://localhost/servilocal/fix_passwords.php` puis **supprimer ce fichier**.

### Étape 7 — Accéder à l'application 🎉

| URL | Description |
|---|---|
| `http://localhost/servilocal/` | Site principal |
| `http://localhost/servilocal/admin.php` | Panneau admin |
| `http://localhost/phpmyadmin` | Base de données |

---

## 🗄 Base de Données

### Schéma relationnel

```
users ──────────────────────────────────────────────────────
  id · name · email · password(bcrypt) · phone · role
  avatar · is_active · created_at · updated_at

          │ 1                              │ 1
          │                               │
          ▼ ∞                             ▼ ∞
providers ─────────────────    bookings ──────────────────────
  id · user_id (FK)              id · client_id (FK)
  category · city · price        provider_id (FK)
  description · avatar_color     booking_date · booking_time
  is_available · is_verified     address · description
  rating · review_count          status (ENUM 5 valeurs)
  created_at                     created_at · updated_at
          │ 1
          │
          ▼ ∞
reviews ────────────────────────────────────────────────────
  id · client_id (FK) · provider_id (FK) · booking_id (FK)
  rating (1-5) · comment · created_at
  UNIQUE (client_id, provider_id, booking_id)

Tables IoT ─────────────────────────────────────────────────
  sensor_readings    → historique des relevés capteurs
  sensor_thresholds  → seuils configurables par utilisateur
  sensor_alerts      → log des incidents avec statut
```

### Trigger automatique

La note d'un prestataire se recalcule à chaque nouvel avis :

```sql
CREATE TRIGGER update_provider_rating AFTER INSERT ON reviews
FOR EACH ROW
BEGIN
    UPDATE providers
    SET rating       = (SELECT AVG(rating) FROM reviews WHERE provider_id = NEW.provider_id),
        review_count = (SELECT COUNT(*)    FROM reviews WHERE provider_id = NEW.provider_id)
    WHERE id = NEW.provider_id;
END;
```

---

## 🌐 Pages & Routes

| URL | Description | Accès |
|---|---|---|
| `/servilocal/` | Accueil · Recherche · Prestataires | Public |
| `/servilocal/login.php` | Connexion (redirection selon rôle) | Invité |
| `/servilocal/register.php` | Inscription client ou prestataire | Invité |
| `/servilocal/logout.php` | Déconnexion sécurisée | Connecté |
| `/servilocal/provider.php?id=X` | Fiche complète du prestataire | Public |
| `/servilocal/booking.php?provider=X` | Réserver un service | Client |
| `/servilocal/dashboard.php` | Tableau de bord personnalisé | Connecté |
| `/servilocal/profile.php` | Modifier profil + photo + MDP | Connecté |
| `/servilocal/reviews.php?provider=X` | Laisser un avis étoiles | Client |
| `/servilocal/admin.php` | Panneau d'administration complet | Admin |

---

## 📡 Module IoT

Le module IoT transforme le dashboard client en **centre de contrôle domestique intelligent**.

### Capteurs gérés

| Capteur | Unité | Seuil par défaut | Type d'alerte |
|---|---|---|---|
| Débit eau | m³/h | 0.50 | Surconsommation |
| Détecteur gaz | m³/h | **0.30** | Fuite critique 🚨 |
| Électricité | kW | 2.50 | Pic anormal / court-circuit |
| Température | °C | 30.0 | Surchauffe |

### Flux d'alerte intelligent

```
Capteur gaz dépasse 0.30 m³/h
          ↓
Enregistrement dans sensor_alerts (status = 'active')
          ↓
Dashboard : bannière rouge + valeur mesurée vs seuil
          ↓
Requête automatique en base :
  SELECT providers WHERE category = 'plomberie'
  AND is_available = 1
  ORDER BY rating DESC
          ↓
Affichage des plombiers disponibles avec
boutons [📞 Appeler] et [Réserver →]
```

### Intégration de vrais capteurs

Remplacer `getSensorData()` dans `dashboard_iot_section.php` :

```php
function getSensorData(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare('
        SELECT sensor_type, value, unit, is_alert, sensor_loc, read_at
        FROM sensor_readings
        WHERE user_id = ?
        ORDER BY read_at DESC
        LIMIT 100
    ');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}
```

Les capteurs peuvent envoyer leurs données via :

- **HTTP POST** vers un endpoint PHP dédié (`api/sensor.php`)
- **MQTT** avec Mosquitto + bridge PHP
- **WebSocket** pour du temps réel strict
- Import depuis une passerelle IoT (Arduino, Raspberry Pi, ESP32)

---

## 👑 Panneau d'Administration

Accessible sur `/servilocal/admin.php` — **rôle `admin` requis**.

### Sections disponibles

| Section | Actions |
|---|---|
| **Dashboard** | KPIs · Graphique hebdomadaire · Top prestataires · Dernières réservations |
| **Utilisateurs** | Lister · Modifier · Activer/Désactiver · Supprimer · Filtrer |
| **Prestataires** | Modifier profil · Vérifier/Dévérifier · Filtrer par catégorie |
| **Réservations** | Modifier statut · Supprimer · Filtrer par statut |
| **Avis** | Lister · Supprimer (recalcul note auto) · Recherche texte |
| **Ajouter** | Créer un utilisateur avec n'importe quel rôle |

### Promouvoir un compte administrateur

```sql
-- Via phpMyAdmin → SQL
UPDATE users SET role = 'admin' WHERE email = 'votre@email.com';
```

---

## 🔒 Sécurité

| Mesure | Implémentation | Fichier |
|---|---|---|
| Mots de passe | `password_hash()` bcrypt · `password_verify()` | `login.php`, `register.php` |
| Injections SQL | PDO + **prepared statements** sur toutes les requêtes | Tous les `.php` |
| XSS | Fonction `e()` = `htmlspecialchars()` sur tout l'output | `includes/functions.php` |
| CSRF | Token `csrf_token` en session sur tous les POST | `includes/functions.php` |
| Fixation session | `session_regenerate_id(true)` à la connexion | `login.php` |
| Upload fichiers | Validation extension + taille max + nom aléatoire | `profile.php` |
| Contrôle d'accès | `requireLogin()` + vérification rôle sur pages protégées | `includes/functions.php` |
| Admin isolé | Double vérification `role === 'admin'` en tête de fichier | `admin.php` |

---

## 🔑 Comptes de Démo

> Mot de passe universel : **`password123`**

| Rôle | Email | Nom | Spécialité |
|---|---|---|---|
| 👑 Admin | `admin@servilocal.com` | Admin ServiLocal | — |
| 👤 Client | `client@demo.com` | Mariam Client | — |
| 🔧 Prestataire | `amine@demo.com` | Amine Cherkaoui | Plomberie · Casablanca |
| 🔧 Prestataire | `sara@demo.com` | Sara Moukhliss | Coiffure · Rabat |
| 🔧 Prestataire | `karim@demo.com` | Karim Ziani | Informatique · Casablanca |
| 🔧 Prestataire | `hassan@demo.com` | Hassan El Fassi | Électricité · Fès |
| 🔧 Prestataire | `fatima@demo.com` | Fatima Benali | Ménage · Marrakech |

---

## 🗺 Roadmap

### v1.0 — Actuelle ✅

- [x] Authentification multi-rôles sécurisée
- [x] Recherche et filtres dynamiques
- [x] Réservations avec gestion complète des statuts
- [x] Système d'avis étoiles
- [x] Dashboard client et prestataire
- [x] Module IoT monitoring + alertes intelligentes
- [x] Panneau d'administration complet
- [x] Design responsive mobile

### v2.0 — Prochaine version

- [ ] Messagerie interne client ↔ prestataire en temps réel
- [ ] Notifications email (PHPMailer / SendGrid)
- [ ] Carte interactive des prestataires (Leaflet.js + OpenStreetMap)
- [ ] Système de paiement en ligne (Stripe / CMI Maroc)
- [ ] WebSocket pour alertes IoT en temps réel strict
- [ ] Export PDF des réservations et factures

### v3.0 — Vision long terme

- [ ] Application mobile (React Native ou Flutter)
- [ ] API REST complète documentée (OpenAPI/Swagger)
- [ ] Vérification d'identité prestataires (KYC)
- [ ] Intelligence artificielle pour prédiction de pannes
- [ ] Programme de fidélité clients avec points
- [ ] Tableau de bord analytique avancé

---

## 🤝 Contribuer

Les contributions sont les bienvenues !

```bash
# 1. Forker le projet sur GitHub

# 2. Créer une branche descriptive
git checkout -b feature/nom-de-la-fonctionnalite

# 3. Committer avec un message clair
git commit -m "feat: ajout de la fonctionnalité X"

# 4. Pusher la branche
git push origin feature/nom-de-la-fonctionnalite

# 5. Ouvrir une Pull Request sur GitHub
```

### Convention de commits

| Préfixe | Usage |
|---|---|
| `feat:` | Nouvelle fonctionnalité |
| `fix:` | Correction de bug |
| `security:` | Correctif de sécurité |
| `style:` | Modifications CSS / design |
| `docs:` | Documentation uniquement |
| `refactor:` | Refactorisation sans changement fonctionnel |
| `perf:` | Optimisation des performances |

---

## 📄 Licence

Ce projet est distribué sous licence **MIT**. Voir le fichier [LICENSE](LICENSE) pour les détails complets.

---

<div align="center">

Développé avec ♥ au Maroc · Université Mundiapolis

**[Voir la démo](http://localhost/servilocal)** · **[Signaler un bug](../../issues)** · **[Proposer une fonctionnalité](../../issues)**

</div>