<?php
// ========================================
// CONFIG GÉNÉRALE
// ========================================
define('APP_NAME', 'Rekik Location');
define('APP_VERSION', '1.0');

// Infos entreprise (à personnaliser)
define('COMPANY_NAME', 'Societe Rekik Location Maintenance et Service  ');
define('COMPANY_ADDRESS', 'AV.Farhat Hached');
define('COMPANY_CITY', '3000 SFAX, Tunisie');
define('COMPANY_PHONE', '+216 26 316 326');
define('COMPANY_EMAIL', 'oussema_-rekik@outlook.fr');
define('COMPANY_RC', 'RC : B123456789');
define('COMPANY_TVA', 'TVA : 1854682T/A/M/000');
define('COMPANY_RIB', 'RIB : ');
define('CURRENCY', 'DT');
define('TVA_RATE', 19); // % TVA par défaut

// MySQL connexion
define('DB_HOST',  'sql112.byethost7.com');
define('DB_PORT',  '3306');
define('DB_NAME', 'b7_42277991_location');
define('DB_USER', 'b7_42277991');
define('DB_PASSWORD',  'rekik123@2025');
define('DB_CHARSET', 'utf8mb4');

// define('DB_HOST',  'localhost');
// define('DB_PORT',  '3306');
// define('DB_NAME', 'locations');
// define('DB_USER', 'root');
// define('DB_PASSWORD',  '');
// define('DB_CHARSET', 'utf8mb4');

// Chemins
define('DATA_PATH', __DIR__ . '/../data/');
define('DOC_PATH', __DIR__ . '/../documents/');

// Chargement des données JSON
function loadData(string $file): array {
    $pdo = getDbConnection();
    if ($pdo) {
        $data = loadDataFromMysql($pdo, $file);
        if ($data !== null) {
            return $data;
        }
    }

    $path = DATA_PATH . $file . '.json';
    if (!file_exists($path)) return [];
    return json_decode(file_get_contents($path), true) ?? [];
}

function saveData(string $file, array $data): bool {
    $pdo = getDbConnection();
    if ($pdo) {
        $saved = saveDataToMysql($pdo, $file, $data);
        if ($saved !== null) {
            return $saved;
        }
    }

    $path = DATA_PATH . $file . '.json';
    return file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
}

// Génération numéro de document
function generateNumber(string $prefix): string {
    $key = strtolower($prefix);
    // Read-only preview of the next number (do not increment here)
    $current = getCounterValue($key);
    $next = $current + 1;
    return $prefix . '-' . date('Y') . '-' . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
}

// Formatage montant
function formatMoney(float $amount): string {
    return number_format($amount, 3, '.', ' ') . ' ' . CURRENCY;
}

// Date FR
function dateFR(string $date = ''): string {
    $ts = $date ? strtotime($date) : time();
    return date('d/m/Y', $ts);
}

// Statuts avec couleurs
function statusBadge(string $status): string {
    $map = [
        'brouillon'  => ['label' => 'Brouillon',  'color' => '#6c757d'],
        'envoye'     => ['label' => 'Envoyé',      'color' => '#0d6efd'],
        'accepte'    => ['label' => 'Accepté',     'color' => '#198754'],
        'refuse'     => ['label' => 'Refusé',      'color' => '#dc3545'],
        'en_cours'   => ['label' => 'En cours',    'color' => '#fd7e14'],
        'livre'      => ['label' => 'Livré',       'color' => '#20c997'],
        'paye'       => ['label' => 'Payé',        'color' => '#198754'],
        'annule'     => ['label' => 'Annulé',      'color' => '#dc3545'],
        'valide'     => ['label' => 'Validé',      'color' => '#198754'],
        'service'    => ['label' => 'En service',  'color' => '#198754'],
        'vidange'    => ['label' => 'Vidange',     'color' => '#d97706'],
        'panne'      => ['label' => 'En panne',    'color' => '#dc2626'],
        'actif'      => ['label' => 'Actif',       'color' => '#198754'],
        'termine'    => ['label' => 'Terminé',     'color' => '#6c757d'],
        'suspendu'   => ['label' => 'Suspendu',    'color' => '#d97706'],
    ];
    $s = $map[$status] ?? ['label' => ucfirst($status), 'color' => '#6c757d'];
    return "<span style='background:{$s['color']};color:#fff;padding:2px 10px;border-radius:20px;font-size:12px;font-weight:600'>{$s['label']}</span>";
}

// Session start
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Enable display errors on local development to help debug 500s
$remote = $_SERVER['REMOTE_ADDR'] ?? '';
if (in_array($remote, ['127.0.0.1', '::1'], true)) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

function flash(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

require_once __DIR__ . '/db.php';

// Load authentication system
require_once __DIR__ . '/auth.php';
