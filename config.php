<?php
// ===== Konfigurasi database =====
define('DB_HOST', 'localhost');
define('DB_NAME', 'db_turnamen');
define('DB_USER', 'root');
define('DB_PASS', '');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $ex) {
    die('Koneksi database gagal. Pastikan MySQL aktif dan database <b>db_turnamen</b> sudah diimpor (database/db_turnamen.sql).');
}

// ===== Helper umum =====
function e($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function redirect($url)
{
    header('Location: ' . $url);
    exit;
}

function flash($type, $msg)
{
    $_SESSION['flash'][] = [$type, $msg];
}

function csrf_token()
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field()
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function csrf_check()
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
            http_response_code(403);
            die('Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.');
        }
    }
}

function login_required()
{
    if (empty($_SESSION['user'])) {
        redirect('login.php');
    }
}

function is_admin()
{
    return !empty($_SESSION['user']) && $_SESSION['user']['peran'] === 'admin';
}

function admin_required()
{
    login_required();
    if (!is_admin()) {
        flash('danger', 'Halaman ini hanya untuk admin.');
        redirect('index.php');
    }
}

function user_id()
{
    return (int)($_SESSION['user']['id_pemain'] ?? 0);
}

function rupiah($n)
{
    return 'Rp ' . number_format((float)$n, 0, ',', '.');
}

function tgl($d)
{
    if (!$d) return '-';
    return date('d M Y', strtotime($d));
}

function tgl_waktu($d)
{
    if (!$d) return '-';
    return date('d M Y, H:i', strtotime($d));
}

function badge_status_turnamen($s)
{
    $map = ['pendaftaran' => 'info', 'berlangsung' => 'warning text-dark', 'selesai' => 'success'];
    return '<span class="badge bg-' . ($map[$s] ?? 'secondary') . '">' . e(ucfirst($s)) . '</span>';
}

function btn_hapus($id, $extra = [])
{
    $h = '<form method="post" class="d-inline" onsubmit="return confirm(\'Yakin ingin menghapus data ini?\')">'
        . csrf_field()
        . '<input type="hidden" name="aksi" value="hapus">'
        . '<input type="hidden" name="id" value="' . (int)$id . '">';
    foreach ($extra as $k => $v) {
        $h .= '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
    }
    return $h . '<button class="btn btn-sm btn-danger" title="Hapus"><i class="bi bi-trash"></i></button></form>';
}

function logo_url($file)
{
    return $file ? 'uploads/logo/' . rawurlencode($file) : '';
}

// Cek apakah pengguna login boleh mengelola tim (admin atau kapten)
function boleh_kelola_tim(array $tim)
{
    return is_admin() || (int)$tim['id_kapten'] === user_id();
}
