<?php
require_once 'config.php';
if (!empty($_SESSION['user'])) redirect('index.php');

$f = ['nama_lengkap' => '', 'nickname' => '', 'email' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $f['nama_lengkap'] = trim($_POST['nama_lengkap'] ?? '');
    $f['nickname'] = trim($_POST['nickname'] ?? '');
    $f['email'] = trim($_POST['email'] ?? '');
    $sandi = $_POST['kata_sandi'] ?? '';
    $ulang = $_POST['ulang_sandi'] ?? '';
    $err = [];

    if ($f['nama_lengkap'] === '' || mb_strlen($f['nama_lengkap']) > 100) $err[] = 'Nama lengkap wajib diisi (maks 100 karakter).';
    if (!preg_match('/^[A-Za-z0-9_.\-]{3,50}$/', $f['nickname'])) $err[] = 'Nickname 3-50 karakter (huruf, angka, _ . -).';
    if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($f['email']) > 100) $err[] = 'Format email tidak valid.';
    if (strlen($sandi) < 6) $err[] = 'Kata sandi minimal 6 karakter.';
    if ($sandi !== $ulang) $err[] = 'Konfirmasi kata sandi tidak cocok.';

    if (!$err) {
        $st = $pdo->prepare('SELECT COUNT(*) FROM pemain WHERE nickname = ? OR email = ?');
        $st->execute([$f['nickname'], $f['email']]);
        if ($st->fetchColumn() > 0) $err[] = 'Nickname atau email sudah terdaftar.';
    }

    if (!$err) {
        // Akun pertama otomatis menjadi admin
        $peran = ((int)$pdo->query('SELECT COUNT(*) FROM pemain')->fetchColumn() === 0) ? 'admin' : 'pemain';
        $pdo->prepare('INSERT INTO pemain (nama_lengkap, nickname, email, kata_sandi, peran) VALUES (?,?,?,?,?)')
            ->execute([$f['nama_lengkap'], $f['nickname'], $f['email'], password_hash($sandi, PASSWORD_DEFAULT), $peran]);
        flash('success', 'Pendaftaran berhasil sebagai ' . $peran . '. Silakan masuk.');
        redirect('login.php');
    }
    foreach ($err as $m) flash('danger', $m);
}
$jumlahUser = (int)$pdo->query('SELECT COUNT(*) FROM pemain')->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Daftar - Sistem Turnamen Game</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
</head>
<body>
<div class="auth-bg">
    <div class="auth-card">
        <div class="auth-head">
            <i class="bi bi-person-plus-fill"></i>
            <h4 class="fw-bold mb-0 mt-2">Buat Akun Baru</h4>
            <small>Bergabung di turnamen game</small>
        </div>
        <div class="auth-body">
            <?php
            if (!empty($_SESSION['flash'])) {
                foreach ($_SESSION['flash'] as [$t, $m]) echo '<div class="alert alert-' . e($t) . ' py-2">' . e($m) . '</div>';
                unset($_SESSION['flash']);
            }
            if ($jumlahUser === 0) {
                echo '<div class="alert alert-warning small"><i class="bi bi-shield-check me-1"></i>Akun pertama ini akan menjadi <b>Admin</b>.</div>';
            }
            ?>
            <form method="post">
                <?= csrf_field() ?>
                <div class="mb-2">
                    <label class="form-label fw-semibold">Nama Lengkap</label>
                    <div class="input-group"><span class="input-group-text"><i class="bi bi-person-vcard"></i></span>
                        <input type="text" name="nama_lengkap" class="form-control" maxlength="100" value="<?= e($f['nama_lengkap']) ?>" required></div>
                </div>
                <div class="mb-2">
                    <label class="form-label fw-semibold">Nickname</label>
                    <div class="input-group"><span class="input-group-text"><i class="bi bi-at"></i></span>
                        <input type="text" name="nickname" class="form-control" maxlength="50" value="<?= e($f['nickname']) ?>" required></div>
                </div>
                <div class="mb-2">
                    <label class="form-label fw-semibold">Email</label>
                    <div class="input-group"><span class="input-group-text"><i class="bi bi-envelope-fill"></i></span>
                        <input type="email" name="email" class="form-control" maxlength="100" value="<?= e($f['email']) ?>" required></div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label fw-semibold">Kata Sandi</label>
                        <input type="password" name="kata_sandi" class="form-control" minlength="6" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">Ulangi</label>
                        <input type="password" name="ulang_sandi" class="form-control" minlength="6" required>
                    </div>
                </div>
                <button class="btn btn-grad w-100 py-2"><i class="bi bi-check2-circle me-1"></i>Daftar</button>
            </form>
            <div class="text-center mt-3 small">Sudah punya akun? <a href="login.php" class="fw-semibold">Masuk</a></div>
        </div>
    </div>
</div>
</body>
</html>
