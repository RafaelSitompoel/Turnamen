<?php
require_once 'config.php';
if (!empty($_SESSION['user'])) redirect('index.php');

$idLogin = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $idLogin = trim($_POST['identitas'] ?? '');
    $sandi = $_POST['kata_sandi'] ?? '';

    $st = $pdo->prepare('SELECT * FROM pemain WHERE email = ? OR nickname = ? LIMIT 1');
    $st->execute([$idLogin, $idLogin]);
    $p = $st->fetch();

    if ($p && password_verify($sandi, $p['kata_sandi'])) {
        session_regenerate_id(true);
        unset($p['kata_sandi']);
        $_SESSION['user'] = $p;
        flash('success', 'Selamat datang, ' . $p['nama_lengkap'] . '!');
        redirect('index.php');
    }
    flash('danger', 'Email/nickname atau kata sandi salah.');
}
$jumlahUser = (int)$pdo->query('SELECT COUNT(*) FROM pemain')->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk - Sistem Turnamen Game</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
</head>
<body>
<div class="auth-bg">
    <div class="auth-card">
        <div class="auth-head">
            <i class="bi bi-controller"></i>
            <h4 class="fw-bold mb-0 mt-2">Sistem Turnamen Game</h4>
            <small>Masuk untuk melanjutkan</small>
        </div>
        <div class="auth-body">
            <?php
            if (!empty($_SESSION['flash'])) {
                foreach ($_SESSION['flash'] as [$t, $m]) echo '<div class="alert alert-' . e($t) . '">' . e($m) . '</div>';
                unset($_SESSION['flash']);
            }
            if ($jumlahUser === 0) {
                echo '<div class="alert alert-warning small"><i class="bi bi-info-circle me-1"></i>Belum ada akun. Silakan daftar — akun pertama otomatis menjadi <b>Admin</b>.</div>';
            }
            ?>
            <form method="post" autocomplete="on">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Email atau Nickname</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
                        <input type="text" name="identitas" class="form-control" value="<?= e($idLogin) ?>" required autofocus>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Kata Sandi</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                        <input type="password" name="kata_sandi" class="form-control" required>
                    </div>
                </div>
                <button class="btn btn-grad w-100 py-2"><i class="bi bi-box-arrow-in-right me-1"></i>Masuk</button>
            </form>
            <div class="text-center mt-3 small">Belum punya akun? <a href="register.php" class="fw-semibold">Daftar sekarang</a></div>
        </div>
    </div>
</div>
</body>
</html>
