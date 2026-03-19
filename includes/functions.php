<?php
// ============================================================
// includes/functions.php — Fonctions utilitaires partagées
// ============================================================

// Démarrage sécurisé de la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Sécurité ────────────────────────────────────────────────

/** Échappe l'output HTML pour éviter XSS */
if (!function_exists('e')) {
    function e(string $s): string {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}

/** Vérifie si l'utilisateur est connecté */
if (!function_exists('isLoggedIn')) {
    function isLoggedIn(): bool {
        return isset($_SESSION['user_id']);
    }
}

/** Redirige si non connecté */
if (!function_exists('requireLogin')) {
    function requireLogin(): void {
        if (!isLoggedIn()) {
            header('Location: /servilocal/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
            exit;
        }
    }
}

/** Retourne le rôle de l'utilisateur connecté */
if (!function_exists('userRole')) {
    function userRole(): string {
        return $_SESSION['user_role'] ?? '';
    }
}

/** Vérifie si l'utilisateur connecté est un prestataire */
if (!function_exists('isProvider')) {
    function isProvider(): bool {
        return userRole() === 'provider';
    }
}

/** Génère un token CSRF et le stocke en session */
if (!function_exists('csrfToken')) {
    function csrfToken(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

/** Valide le token CSRF envoyé dans un formulaire */
if (!function_exists('verifyCsrf')) {
    function verifyCsrf(): void {
        $token = $_POST['csrf_token'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            http_response_code(403);
            die('Action non autorisée (CSRF).');
        }
    }
}

/** Formate une date MySQL en français */
if (!function_exists('dateFr')) {
    function dateFr(string $date): string {
        $months = ['Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'];
        $d = new DateTime($date);
        return $d->format('d') . ' ' . $months[(int)$d->format('m') - 1] . ' ' . $d->format('Y');
    }
}

/** Badge statut réservation */
if (!function_exists('statusBadge')) {
    function statusBadge(string $status): string {
        $map = [
            'pending'   => ['🕐 En attente',  '#D97706', '#FFF7ED'],
            'accepted'  => ['✅ Acceptée',    '#059669', '#ECFDF5'],
            'refused'   => ['❌ Refusée',     '#DC2626', '#FEF2F2'],
            'completed' => ['🏁 Terminée',    '#1C3D2E', '#F0FDF4'],
            'cancelled' => ['🚫 Annulée',     '#6B7280', '#F9FAFB'],
        ];
        [$label, $color, $bg] = $map[$status] ?? ['—', '#999', '#eee'];
        return "<span style=\"background:{$bg};color:{$color};padding:0.25rem 0.75rem;border-radius:50px;font-size:0.78rem;font-weight:700\">{$label}</span>";
    }
}

/** Étoiles rating */
if (!function_exists('stars')) {
    function stars(float $rating, bool $small = false): string {
        $full  = floor($rating);
        $half  = ($rating - $full) >= 0.5 ? 1 : 0;
        $empty = 5 - $full - $half;
        $size  = $small ? 'font-size:0.85rem' : 'font-size:1rem';

        $html  = "<span style=\"color:#E8A838;{$size}\">";
        $html .= str_repeat('★', $full);
        $html .= $half ? '½' : '';
        $html .= "</span><span style=\"color:#ddd;{$size}\">" . str_repeat('★', $empty) . '</span>';

        return $html;
    }
}

/** Couleur catégorie */
if (!function_exists('categoryColor')) {
    function categoryColor(string $cat): string {
        $map = [
            'plomberie'   => '#2563EB',
            'coiffure'    => '#7C3AED',
            'informatique'=> '#059669',
            'electricite' => '#D97706',
            'jardinage'   => '#16A34A',
            'menage'      => '#DC2626',
            'transport'   => '#0891B2',
            'peinture'    => '#EA580C',
        ];
        return $map[$cat] ?? '#6B7280';
    }
}

/** Icône catégorie */
if (!function_exists('categoryIcon')) {
    function categoryIcon(string $cat): string {
        $map = [
            'plomberie'   => '🔧',
            'electricite' => '⚡',
            'gaz'      => '🔥',
            
            
        ];
        return $map[$cat] ?? '✦';
    }
}

/** Nom catégorie */
if (!function_exists('categoryName')) {
    function categoryName(string $cat): string {
        $map = [
            'plomberie'   => 'Plomberie',
            'electricite' => 'Électricité',
            'gaz'      => 'Gaz',
        ];
        return $map[$cat] ?? ucfirst($cat);
    }
}