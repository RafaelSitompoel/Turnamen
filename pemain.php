<?php
require_once 'config.php';
admin_required();

$edit = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'hapus') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === user_id()) {
            flash('warning', 'Anda tidak dapat menghapus akun sendiri.');
        } else {
            try {
                $pdo->prepare('DELETE FROM pemain WHERE id_pemain = ?')->execute([$id]);
                flash('success', 'Pemain berhasil dihapus.');
            } catch (PDOException $ex) {
                flash('danger', 'Pemain tidak dapat dihapus karena masih menjadi kapten tim.');
            }
        }
        redirect('pemain.php');
    }

    if ($aksi === 'simpan') {
        $id = (int)($_POST['id_pemain'] ?? 0);
        $f = [
            'id_pemain' => $id,
            'nama_lengkap' => trim($_POST['nama_lengkap'] ?? ''),
            'nickname' => trim($_POST['nickname'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'peran' => ($_POST['peran'] ?? '') === 'admin' ? 'admin' : 'pemain',
        ];
        $sandi = $_POST['kata_sandi'] ?? '';
        if ($id === user_id()) $f['peran'] = 'admin'; // admin tidak bisa menurunkan dirinya sendiri
        $err = [];

        if ($f['nama_lengkap'] === '' || mb_strlen($f['nama_lengkap']) > 100) $err[] = 'Nama lengkap wajib diisi (maks 100 karakter).';
        if (!preg_match('/^[A-Za-z0-9_.\-]{3,50}$/', $f['nickname'])) $err[] = 'Nickname 3-50 karakter (huruf, angka, _ . -).';
        if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($f['email']) > 100) $err[] = 'Format email tidak valid.';
        if ($id === 0 && strlen($sandi) < 6) $err[] = 'Kata sandi minimal 6 karakter.';
        if ($id > 0 && $sandi !== '' && strlen($sandi) < 6) $err[] = 'Kata sandi baru minimal 6 karakter.';

        if (!$err) {
            $st = $pdo->prepare('SELECT COUNT(*) FROM pemain WHERE (nickname = ? OR email = ?) AND id_pemain <> ?');
            $st->execute([$f['nickname'], $f['email'], $id]);
            if ($st->fetchColumn() > 0) $err[] = 'Nickname atau email sudah dipakai pemain lain.';
        }

        if (!$err) {
            if ($id === 0) {
                $pdo->prepare('INSERT INTO pemain (nama_lengkap, nickname, email, kata_sandi, peran) VALUES (?,?,?,?,?)')
                    ->execute([$f['nama_lengkap'], $f['nickname'], $f['email'], password_hash($sandi, PASSWORD_DEFAULT), $f['peran']]);
                flash('success', 'Pemain berhasil ditambahkan.');
            } else {
                $pdo->prepare('UPDATE pemain SET nama_lengkap=?, nickname=?, email=?, peran=? WHERE id_pemain=?')
                    ->execute([$f['nama_lengkap'], $f['nickname'], $f['email'], $f['peran'], $id]);
                if ($sandi !== '') {
                    $pdo->prepare('UPDATE pemain SET kata_sandi=? WHERE id_pemain=?')
                        ->execute([password_hash($sandi, PASSWORD_DEFAULT), $id]);
                }
                if ($id === user_id()) {
                    $_SESSION['user']['nama_lengkap'] = $f['nama_lengkap'];
                    $_SESSION['user']['nickname'] = $f['nickname'];
                    $_SESSION['user']['email'] = $f['email'];
                }
                flash('success', 'Data pemain berhasil diperbarui.');
            }
            redirect('pemain.php');
        }
        foreach ($err as $m) flash('danger', $m);
        $edit = $f;
    }
}

if (!$edit && isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT id_pemain, nama_lengkap, nickname, email, peran FROM pemain WHERE id_pemain = ?');
    $st->execute([(int)$_GET['edit']]);
    $edit = $st->fetch() ?: null;
}
$f = $edit ?? ['id_pemain' => 0, 'nama_lengkap' => '', 'nickname' => '', 'email' => '', 'peran' => 'pemain'];

$q = trim($_GET['q'] ?? '');
$st = $pdo->prepare('SELECT p.id_pemain, p.nama_lengkap, p.nickname, p.email, p.peran,
        (SELECT COUNT(*) FROM anggota_tim a WHERE a.id_pemain = p.id_pemain) AS jml_tim
    FROM pemain p WHERE p.nama_lengkap LIKE ? OR p.nickname LIKE ? OR p.email LIKE ? ORDER BY p.id_pemain DESC');
$like = '%' . $q . '%';
$st->execute([$like, $like, $like]);
$rows = $st->fetchAll();

$judul = 'Data Pemain';
include 'includes/header.php';
?>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-grad-ungu"><i class="bi bi-person-plus-fill me-2"></i><?= $f['id_pemain'] ? 'Ubah Pemain' : 'Tambah Pemain' ?></div>
            <div class="card-body">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="aksi" value="simpan">
                    <input type="hidden" name="id_pemain" value="<?= (int)$f['id_pemain'] ?>">
                    <div class="mb-2"><label class="form-label">Nama Lengkap</label>
                        <input type="text" name="nama_lengkap" class="form-control" maxlength="100" value="<?= e($f['nama_lengkap']) ?>" required></div>
                    <div class="mb-2"><label class="form-label">Nickname</label>
                        <input type="text" name="nickname" class="form-control" maxlength="50" value="<?= e($f['nickname']) ?>" required></div>
                    <div class="mb-2"><label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" maxlength="100" value="<?= e($f['email']) ?>" required></div>
                    <div class="mb-2"><label class="form-label">Kata Sandi <?= $f['id_pemain'] ? '<small class="text-muted">(kosongkan jika tidak diubah)</small>' : '' ?></label>
                        <input type="password" name="kata_sandi" class="form-control" minlength="6" <?= $f['id_pemain'] ? '' : 'required' ?>></div>
                    <div class="mb-3"><label class="form-label">Peran</label>
                        <select name="peran" class="form-select" <?= $f['id_pemain'] === user_id() ? 'disabled' : '' ?>>
                            <option value="pemain" <?= $f['peran'] === 'pemain' ? 'selected' : '' ?>>Pemain</option>
                            <option value="admin" <?= $f['peran'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                        </select></div>
                    <button class="btn btn-grad"><i class="bi bi-save me-1"></i>Simpan</button>
                    <?php if ($f['id_pemain']): ?><a href="pemain.php" class="btn btn-secondary">Batal</a><?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <form class="row g-2 mb-3" method="get">
                    <div class="col"><input type="search" name="q" class="form-control" placeholder="Cari nama, nickname, atau email..." value="<?= e($q) ?>"></div>
                    <div class="col-auto"><button class="btn btn-primary"><i class="bi bi-search"></i></button></div>
                </form>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead><tr><th>#</th><th>Nama</th><th>Nickname</th><th>Email</th><th>Peran</th><th>Tim</th><th class="text-end">Aksi</th></tr></thead>
                        <tbody>
                        <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-4">Belum ada data pemain.</td></tr><?php endif; ?>
                        <?php foreach ($rows as $i => $r): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><?= e($r['nama_lengkap']) ?></td>
                                <td><span class="badge bg-info text-dark">@<?= e($r['nickname']) ?></span></td>
                                <td><?= e($r['email']) ?></td>
                                <td><span class="badge bg-<?= $r['peran'] === 'admin' ? 'danger' : 'primary' ?>"><?= e($r['peran']) ?></span></td>
                                <td><?= (int)$r['jml_tim'] ?></td>
                                <td class="text-end text-nowrap">
                                    <a href="pemain.php?edit=<?= (int)$r['id_pemain'] ?>" class="btn btn-sm btn-warning"><i class="bi bi-pencil-square"></i></a>
                                    <?php if ((int)$r['id_pemain'] !== user_id()) echo btn_hapus($r['id_pemain']); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
