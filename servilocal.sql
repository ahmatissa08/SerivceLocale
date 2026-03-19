-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : jeu. 19 mars 2026 à 16:07
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `servilocal`
--

-- --------------------------------------------------------

--
-- Structure de la table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `booking_date` date NOT NULL,
  `booking_time` time NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('pending','accepted','refused','completed','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `bookings`
--

INSERT INTO `bookings` (`id`, `client_id`, `provider_id`, `booking_date`, `booking_time`, `address`, `description`, `status`, `created_at`, `updated_at`) VALUES
(1, 7, 1, '2026-03-16', '09:00:00', '23 Rue Ibn Batouta, Casablanca', 'Fuite sous l\'évier de cuisine', 'refused', '2026-03-14 21:37:14', '2026-03-17 09:02:52'),
(4, 7, 1, '2026-03-20', '12:04:00', 'Sacré-Coeur 3', 'hv', 'accepted', '2026-03-17 09:01:19', '2026-03-17 09:02:46');

-- --------------------------------------------------------

--
-- Structure de la table `providers`
--

CREATE TABLE `providers` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `category` varchar(80) NOT NULL,
  `city` varchar(100) NOT NULL,
  `price` varchar(80) DEFAULT 'Sur devis',
  `description` text DEFAULT NULL,
  `avatar_color` varchar(10) DEFAULT '#2C5F2D',
  `is_available` tinyint(1) NOT NULL DEFAULT 1,
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `rating` decimal(3,2) NOT NULL DEFAULT 0.00,
  `review_count` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `providers`
--

INSERT INTO `providers` (`id`, `user_id`, `category`, `city`, `price`, `description`, `avatar_color`, `is_available`, `is_verified`, `rating`, `review_count`, `created_at`, `updated_at`) VALUES
(1, 1, 'plomberie', 'Casablanca', '150 DH/h', 'Plombier certifié avec 12 ans d\'expérience. Dépannage urgent, installation sanitaire, rénovation complète.', '#1C3D2E', 1, 1, 4.90, 87, '2026-03-14 21:37:14', '2026-03-14 21:37:14'),
(4, 4, 'electricite', 'Fès', '120 DH/h', 'Électricien agréé, travaux neufs et rénovation. Mise aux normes, domotique, panneaux solaires.', '#D97706', 1, 1, 4.60, 45, '2026-03-14 21:37:14', '2026-03-14 21:37:14'),
(7, 9, 'gaz', 'Casablanca', '200/H', '3 ans d\'experience dans le domaine du gaz', '#DC2626', 1, 0, 0.00, 0, '2026-03-17 08:49:07', '2026-03-17 08:49:07');

-- --------------------------------------------------------

--
-- Structure de la table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `rating` tinyint(4) NOT NULL CHECK (`rating` between 1 and 5),
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déclencheurs `reviews`
--
DELIMITER $$
CREATE TRIGGER `update_provider_rating` AFTER INSERT ON `reviews` FOR EACH ROW BEGIN
    UPDATE providers
    SET rating       = (SELECT AVG(rating)  FROM reviews WHERE provider_id = NEW.provider_id),
        review_count = (SELECT COUNT(*)     FROM reviews WHERE provider_id = NEW.provider_id)
    WHERE id = NEW.provider_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Structure de la table `sensor_alerts`
--

CREATE TABLE `sensor_alerts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `sensor_type` varchar(50) NOT NULL,
  `sensor_loc` varchar(100) DEFAULT NULL,
  `value` decimal(10,3) NOT NULL,
  `threshold` decimal(10,3) NOT NULL,
  `message` text DEFAULT NULL,
  `status` enum('active','resolved','dismissed') NOT NULL DEFAULT 'active',
  `triggered_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `resolved_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `sensor_alerts`
--

INSERT INTO `sensor_alerts` (`id`, `user_id`, `sensor_type`, `sensor_loc`, `value`, `threshold`, `message`, `status`, `triggered_at`, `resolved_at`) VALUES
(1, 7, 'gas', 'Cuisine', 0.890, 0.300, 'Débit gaz anormal : 0.89 m³/h (seuil : 0.30). Fuite probable.', 'active', '2026-03-19 14:56:35', NULL),
(2, 7, 'electricity', 'Salon', 4.200, 2.500, 'Pic électrique : 4.2 kW (seuil : 2.5). Court-circuit possible.', 'active', '2026-03-19 14:56:35', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `sensor_readings`
--

CREATE TABLE `sensor_readings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `sensor_type` enum('water','gas','electricity','temperature') NOT NULL,
  `sensor_loc` varchar(100) DEFAULT 'Maison',
  `value` decimal(10,3) NOT NULL,
  `unit` varchar(20) NOT NULL,
  `is_alert` tinyint(1) NOT NULL DEFAULT 0,
  `threshold` decimal(10,3) DEFAULT NULL,
  `read_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `sensor_readings`
--

INSERT INTO `sensor_readings` (`id`, `user_id`, `sensor_type`, `sensor_loc`, `value`, `unit`, `is_alert`, `threshold`, `read_at`) VALUES
(1, 7, 'electricity', 'Tableau général', 1.800, 'kW', 0, NULL, '2026-03-19 14:56:35'),
(2, 7, 'electricity', 'Tableau général', 2.100, 'kW', 0, NULL, '2026-03-19 14:56:35'),
(3, 7, 'electricity', 'Tableau général', 3.800, 'kW', 0, NULL, '2026-03-19 14:56:35'),
(4, 7, 'electricity', 'Salon', 4.200, 'kW', 1, NULL, '2026-03-19 14:56:35'),
(5, 7, 'gas', 'Cuisine', 0.280, 'm³/h', 0, NULL, '2026-03-19 14:56:35'),
(6, 7, 'gas', 'Cuisine', 0.310, 'm³/h', 0, NULL, '2026-03-19 14:56:35'),
(7, 7, 'gas', 'Cuisine', 0.890, 'm³/h', 1, NULL, '2026-03-19 14:56:35'),
(8, 7, 'water', 'Couloir tech.', 0.380, 'm³/h', 0, NULL, '2026-03-19 14:56:35'),
(9, 7, 'water', 'Couloir tech.', 0.420, 'm³/h', 0, NULL, '2026-03-19 14:56:35'),
(10, 7, 'temperature', 'Salon', 22.400, '°C', 0, NULL, '2026-03-19 14:56:35');

-- --------------------------------------------------------

--
-- Structure de la table `sensor_thresholds`
--

CREATE TABLE `sensor_thresholds` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `sensor_type` enum('water','gas','electricity','temperature') NOT NULL,
  `threshold` decimal(10,3) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `sensor_thresholds`
--

INSERT INTO `sensor_thresholds` (`id`, `user_id`, `sensor_type`, `threshold`, `updated_at`) VALUES
(1, 7, 'water', 0.500, '2026-03-19 14:56:35'),
(2, 7, 'gas', 0.300, '2026-03-19 14:56:35'),
(3, 7, 'electricity', 2.500, '2026-03-19 14:56:35'),
(4, 7, 'temperature', 30.000, '2026-03-19 14:56:35');

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('user','client','provider','admin') DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `phone`, `role`, `avatar`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Amine Cherkaoui', 'amine@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0600111222', 'provider', NULL, 1, '2026-03-14 21:37:14', '2026-03-14 21:37:14'),
(2, 'Sara Moukhliss', 'sara@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0600333444', 'provider', NULL, 1, '2026-03-14 21:37:14', '2026-03-14 21:37:14'),
(3, 'Karim Ziani', 'karim@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0600555666', 'provider', NULL, 1, '2026-03-14 21:37:14', '2026-03-14 21:37:14'),
(4, 'Hassan El Fassi', 'hassan@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0600777888', 'provider', NULL, 1, '2026-03-14 21:37:14', '2026-03-14 21:37:14'),
(5, 'Fatima Benali', 'fatima@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0600999000', 'provider', NULL, 1, '2026-03-14 21:37:14', '2026-03-14 21:37:14'),
(6, 'Youssef Idrissi', 'youssef@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0611222333', 'provider', NULL, 1, '2026-03-14 21:37:14', '2026-03-14 21:37:14'),
(7, 'Mariam Client', 'client@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0622444555', 'client', NULL, 1, '2026-03-14 21:37:14', '2026-03-14 21:37:14'),
(8, 'Admin ServiLocal', 'admin@servilocal.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0600000001', 'admin', NULL, 1, '2026-03-14 21:37:14', '2026-03-14 21:49:27'),
(9, 'Brahim Charka', 'brahim@icloud.com', '$2y$10$8Kxh9kZCY/xnrqJ1nOtMxO6MGmdya.GxzwJqDwh1KKx1o/HzXHKfG', '070293832', 'provider', NULL, 1, '2026-03-17 08:49:07', '2026-03-17 08:49:07');

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_bookings_client` (`client_id`),
  ADD KEY `fk_bookings_provider` (`provider_id`);

--
-- Index pour la table `providers`
--
ALTER TABLE `providers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_providers_user` (`user_id`);

--
-- Index pour la table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_review` (`client_id`,`provider_id`,`booking_id`),
  ADD KEY `fk_reviews_provider` (`provider_id`),
  ADD KEY `fk_reviews_booking` (`booking_id`);

--
-- Index pour la table `sensor_alerts`
--
ALTER TABLE `sensor_alerts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_alert_user` (`user_id`,`status`);

--
-- Index pour la table `sensor_readings`
--
ALTER TABLE `sensor_readings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_type` (`user_id`,`sensor_type`),
  ADD KEY `idx_read_at` (`read_at`);

--
-- Index pour la table `sensor_thresholds`
--
ALTER TABLE `sensor_thresholds`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_sensor` (`user_id`,`sensor_type`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `providers`
--
ALTER TABLE `providers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT pour la table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `sensor_alerts`
--
ALTER TABLE `sensor_alerts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `sensor_readings`
--
ALTER TABLE `sensor_readings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT pour la table `sensor_thresholds`
--
ALTER TABLE `sensor_thresholds`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `fk_bookings_client` FOREIGN KEY (`client_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_bookings_provider` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `providers`
--
ALTER TABLE `providers`
  ADD CONSTRAINT `fk_providers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `fk_reviews_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_reviews_client` FOREIGN KEY (`client_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_reviews_provider` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `sensor_alerts`
--
ALTER TABLE `sensor_alerts`
  ADD CONSTRAINT `fk_alert_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `sensor_readings`
--
ALTER TABLE `sensor_readings`
  ADD CONSTRAINT `fk_sensor_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `sensor_thresholds`
--
ALTER TABLE `sensor_thresholds`
  ADD CONSTRAINT `fk_threshold_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
