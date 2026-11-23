<?php
require_once 'config.php';
login_required();

$st = $pdo->prepare('SELECT * FROM pemain WHERE id_pemain = ?');
$st->execute([user_id()]);
$me = $st->fetch();
if (!$me) redirect('logout.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'profil') {
        $nama = trim($_POST['nama_lengkap'] ?? '');
        $nick = trim($_POST['nickname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $err = [];
        if ($nama === '' || mb_strlen($nama) > 100) $err[] = 'Nama lengkap wajib diisi (maks 100 karakter).';
        if (!preg_match('/^[A-Za-z0-9_.\-]{3,50}$/', $nick)) $err[] = 'Nickname 3-50 karakter (huruf, angka, _ . -).';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) $err[] = 'Format email tidak valid.';
        if (!$err) {
            $c = $pdo->prepare('SELECT COUNT(*) FROM pemain WHERE (nickname = ? OR email = ?) AND id_pemain <> ?');
            $c->execute([$nick, $email, user_id()]);
            if ($c->fetchColumn() > 0) $err[] = 'Nickname atau email sudah dipakai pemain lain.';
        }
        if (!$err) {
            $pdo->prepare('UPDATE pemain SET nama_lengkap=?, nickname=?, email=? WHERE id_pemain=?')->execute([$nama, $nick, $email, user_id()]);
            $_SESSION['user']['nama_lengkap'] = $nama;
            $_SESSION['user']['nickname'] = $nick;
            $_SESSION['user']['email'] = $email;
            flash('success', 'Profil berhasil diperbarui.');
        } else {
            foreach ($err as $m) flash('danger', $m);
        }
    }

    if ($aksi === 'sandi') {
        $lama = $_POST['sandi_lama'] ?? '';
        $baru = $_POST['sandi_baru'] ?? '';
        $ulang = $_POST['sandi_ulang'] ?? '';
        if (!password_verify($lama, $me['kata_sandi'])) flash('danger', 'Kata sandi lama salah.');
        elseif (strlen($baru) < 6) flash('danger', 'Kata sandi baru minimal 6 karakter.');
        elseif ($baru !== $ulang) flash('danger', 'Konfirmasi kata sandi baru tidak cocok.');
        else {
            $pdo->prepare('UPDATE pemain SET kata_sandi=? WHERE id_pemain=?')->execute([password_hash($baru, PASSWORD_DEFAULT), user_id()]);
            flash('success', 'Kata sandi berhasil diubah.');
        }
    }
    redirect('profil.php');
}

$judul = 'Profil Saya';
include 'includes/header.php';
?>
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-grad-ungu"><i class="bi bi-person-vcard me-2"></i>Data Profil</div>
            <div class="card-body">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="aksi" value="profil">
                    <div class="mb-2"><label class="form-label">Nama Lengkap</label>
                        <input type="text" name="nama_lengkap" class="form-control" maxlength="100" value="<?= e($me['nama_lengkap']) ?>" required></div>
                    <div class="mb-2"><label class="form-label">Nickname</label>
                        <input type="text" name="nickname" class="form-control" maxlength="50" value="<?= e($me['nickname']) ?>" required></div>
                    <div class="mb-2"><label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" maxlength="100" value="<?= e($me['email']) ?>" required></div>
                    <div class="mb-3"><label class="form-label">Peran</label>
                        <input type="text" class="form-control" value="<?= e($me['peran']) ?>" disabled></div>
                    <button class="btn btn-grad"><i class="bi bi-save me-1"></i>Simpan Profil</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-grad-merah"><i class="bi bi-shield-lock-fill me-2"></i>Ganti Kata Sandi</div>
            <div class="card-body">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="aksi" value="sandi">
                    <div class="mb-2"><label class="form-label">Kata Sandi Lama</label>
                        <input type="password" name="sandi_lama" class="form-control" required></div>
                    <div class="mb-2"><label class="form-label">Kata Sandi Baru</label>
                        <input type="password" name="sandi_baru" class="form-control" minlength="6" required></div>
                    <div class="mb-3"><label class="form-label">Ulangi Kata Sandi Baru</label>
                        <input type="password" name="sandi_ulang" class="form-control" minlength="6" required></div>
                    <button class="btn btn-danger"><i class="bi bi-key-fill me-1"></i>Ubah Kata Sandi</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
