-- ============================================================
-- ServiLocal - Base de données MySQL
-- Compatible XAMPP / phpMyAdmin
-- ============================================================

CREATE DATABASE IF NOT EXISTS servilocal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE servilocal;

-- ============================================================
-- TABLE : users
-- Stocke tous les utilisateurs (clients + prestataires)
-- ============================================================
CREATE TABLE users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100)        NOT NULL,
    email       VARCHAR(150)        NOT NULL UNIQUE,
    password    VARCHAR(255)        NOT NULL,          -- password_hash bcrypt
    phone       VARCHAR(20)         DEFAULT NULL,
    role        ENUM('client','provider') NOT NULL DEFAULT 'client',
    avatar      VARCHAR(255)        DEFAULT NULL,      -- chemin vers l'image
    is_active   TINYINT(1)          NOT NULL DEFAULT 1,
    created_at  TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE : providers
-- Profil professionnel des prestataires
-- ============================================================
CREATE TABLE providers (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT                 NOT NULL,
    category    VARCHAR(80)         NOT NULL,          -- plomberie, coiffure, etc.
    city        VARCHAR(100)        NOT NULL,
    price       VARCHAR(80)         DEFAULT 'Sur devis',
    description TEXT                DEFAULT NULL,
    avatar_color VARCHAR(10)        DEFAULT '#2C5F2D',
    is_available TINYINT(1)         NOT NULL DEFAULT 1,
    is_verified  TINYINT(1)         NOT NULL DEFAULT 0,
    rating      DECIMAL(3,2)        NOT NULL DEFAULT 0.00,
    review_count INT                NOT NULL DEFAULT 0,
    created_at  TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_providers_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE : bookings
-- Réservations de services
-- ============================================================
CREATE TABLE bookings (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    client_id       INT             NOT NULL,
    provider_id     INT             NOT NULL,
    booking_date    DATE            NOT NULL,
    booking_time    TIME            NOT NULL,
    address         VARCHAR(255)    DEFAULT NULL,
    description     TEXT            DEFAULT NULL,
    status          ENUM('pending','accepted','refused','completed','cancelled')
                                    NOT NULL DEFAULT 'pending',
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_bookings_client   FOREIGN KEY (client_id)   REFERENCES users(id)      ON DELETE CASCADE,
    CONSTRAINT fk_bookings_provider FOREIGN KEY (provider_id) REFERENCES providers(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE : reviews
-- Avis et notes laissés par les clients
-- ============================================================
CREATE TABLE reviews (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    client_id   INT             NOT NULL,
    provider_id INT             NOT NULL,
    booking_id  INT             DEFAULT NULL,
    rating      TINYINT         NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment     TEXT            DEFAULT NULL,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reviews_client   FOREIGN KEY (client_id)   REFERENCES users(id)      ON DELETE CASCADE,
    CONSTRAINT fk_reviews_provider FOREIGN KEY (provider_id) REFERENCES providers(id)  ON DELETE CASCADE,
    CONSTRAINT fk_reviews_booking  FOREIGN KEY (booking_id)  REFERENCES bookings(id)   ON DELETE SET NULL,
    UNIQUE KEY unique_review (client_id, provider_id, booking_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- DONNÉES DE DÉMO
-- ============================================================

-- Utilisateurs démo (mot de passe = "password123" pour tous)
INSERT INTO users (name, email, password, phone, role) VALUES
('Amine Cherkaoui',  'amine@demo.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0600111222', 'provider'),
('Sara Moukhliss',   'sara@demo.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0600333444', 'provider'),
('Karim Ziani',      'karim@demo.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0600555666', 'provider'),
('Hassan El Fassi',  'hassan@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0600777888', 'provider'),
('Fatima Benali',    'fatima@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0600999000', 'provider'),
('Youssef Idrissi',  'youssef@demo.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0611222333', 'provider'),
('Mariam Client',    'client@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0622444555', 'client');

-- Profils prestataires
INSERT INTO providers (user_id, category, city, price, description, avatar_color, is_available, is_verified, rating, review_count) VALUES
(1, 'plomberie',    'Casablanca', '150 DH/h',  'Plombier certifié avec 12 ans d\'expérience. Dépannage urgent, installation sanitaire, rénovation complète.', '#1C3D2E', 1, 1, 4.90, 87),
(2, 'coiffure',     'Rabat',      '80 DH',     'Coiffeuse professionnelle spécialisée en colorations et coupes modernes. Salon à domicile disponible.',       '#7C3AED', 1, 1, 4.80, 134),
(3, 'informatique', 'Casablanca', '200 DH/h',  'Expert en dépannage informatique, réseaux et cybersécurité. Interventions rapides et solutions durables.',     '#059669', 0, 1, 4.70, 62),
(4, 'electricite',  'Fès',        '120 DH/h',  'Électricien agréé, travaux neufs et rénovation. Mise aux normes, domotique, panneaux solaires.',               '#D97706', 1, 1, 4.60, 45),
(5, 'menage',       'Marrakech',  '60 DH/h',   'Service de ménage professionnel, repassage, garde d\'enfants. Disponible en semaine et week-end.',             '#DC2626', 1, 1, 4.90, 203),
(6, 'jardinage',    'Casablanca', '100 DH/h',  'Paysagiste et jardinier expérimenté. Création, entretien de jardins, taille d\'arbres, gazon.',                '#16A34A', 1, 0, 4.50, 38);

-- Réservations démo
INSERT INTO bookings (client_id, provider_id, booking_date, booking_time, address, description, status) VALUES
(7, 1, DATE_ADD(CURDATE(), INTERVAL 2 DAY),  '09:00:00', '23 Rue Ibn Batouta, Casablanca', 'Fuite sous l\'évier de cuisine', 'pending'),
(7, 2, DATE_ADD(CURDATE(), INTERVAL 5 DAY),  '14:30:00', 'À domicile - Rabat', 'Coupe et coloration', 'accepted'),
(7, 5, DATE_SUB(CURDATE(), INTERVAL 7 DAY),  '10:00:00', '5 Bd Zerktouni, Casablanca', 'Ménage complet appartement 3 pièces', 'completed');

-- Avis démo
INSERT INTO reviews (client_id, provider_id, booking_id, rating, comment) VALUES
(7, 5, 3, 5, 'Fatima est incroyable, mon appartement n\'a jamais été aussi propre. Très professionnelle et fiable.');

-- Trigger : recalcul automatique de la note du prestataire après un avis
DELIMITER $$
CREATE TRIGGER update_provider_rating AFTER INSERT ON reviews
FOR EACH ROW
BEGIN
    UPDATE providers
    SET rating       = (SELECT AVG(rating)  FROM reviews WHERE provider_id = NEW.provider_id),
        review_count = (SELECT COUNT(*)     FROM reviews WHERE provider_id = NEW.provider_id)
    WHERE id = NEW.provider_id;
END$$
DELIMITER ;
