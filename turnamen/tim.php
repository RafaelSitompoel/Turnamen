<?php
require_once 'config.php';
login_required();

$dirLogo = __DIR__ . '/uploads/logo/';
$edit = null;

function simpan_logo(array $file, &$error)
{
    global $dirLogo;
    if ($file['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($file['error'] !== UPLOAD_ERR_OK) { $error = 'Upload logo gagal.'; return null; }
    if ($file['size'] > 2 * 1024 * 1024) { $error = 'Ukuran logo maksimal 2 MB.'; return null; }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][$mime] ?? null;
    if (!$ext || !getimagesize($file['tmp_name'])) { $error = 'Logo harus berupa gambar JPG, PNG, WEBP, atau GIF.'; return null; }
    if (!is_dir($dirLogo)) mkdir($dirLogo, 0755, true);
    $nama = bin2hex(random_bytes(12)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dirLogo . $nama)) { $error = 'Gagal menyimpan logo.'; return null; }
    return $nama;
}

function hapus_file_logo($nama)
{
    global $dirLogo;
    if ($nama && is_file($dirLogo . basename($nama))) @unlink($dirLogo . basename($nama));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'hapus') {
        $st = $pdo->prepare('SELECT * FROM tim WHERE id_tim = ?');
        $st->execute([(int)($_POST['id'] ?? 0)]);
        $tim = $st->fetch();
        if (!$tim || !boleh_kelola_tim($tim)) {
            flash('danger', 'Tim tidak ditemukan atau Anda tidak berhak menghapusnya.');
        } else {
            try {
                $pdo->prepare('DELETE FROM tim WHERE id_tim = ?')->execute([$tim['id_tim']]);
                hapus_file_logo($tim['logo']);
                flash('success', 'Tim berhasil dihapus.');
            } catch (PDOException $ex) {
                flash('danger', 'Tim tidak dapat dihapus karena sudah tercatat dalam pertandingan.');
            }
        }
        redirect('tim.php');
    }

    if ($aksi === 'simpan') {
        $id = (int)($_POST['id_tim'] ?? 0);
        $lama = null;
        if ($id > 0) {
            $st = $pdo->prepare('SELECT * FROM tim WHERE id_tim = ?');
            $st->execute([$id]);
            $lama = $st->fetch();
            if (!$lama || !boleh_kelola_tim($lama)) {
                flash('danger', 'Anda tidak berhak mengubah tim ini.');
                redirect('tim.php');
            }
        }
        $nama = trim($_POST['nama_tim'] ?? '');
        $kapten = is_admin() ? (int)($_POST['id_kapten'] ?? 0) : ($lama ? (int)$lama['id_kapten'] : user_id());
        $err = [];
        if ($nama === '' || mb_strlen($nama) > 100) $err[] = 'Nama tim wajib diisi (maks 100 karakter).';
        $st = $pdo->prepare('SELECT COUNT(*) FROM pemain WHERE id_pemain = ?');
        $st->execute([$kapten]);
        if (!$st->fetchColumn()) $err[] = 'Kapten tidak valid.';
        $st = $pdo->prepare('SELECT COUNT(*) FROM tim WHERE nama_tim = ? AND id_tim <> ?');
        $st->execute([$nama, $id]);
        if ($st->fetchColumn() > 0) $err[] = 'Nama tim sudah dipakai.';

        $errLogo = '';
        $logoBaru = null;
        if (!$err) {
            $logoBaru = simpan_logo($_FILES['logo'] ?? ['error' => UPLOAD_ERR_NO_FILE], $errLogo);
            if ($errLogo) $err[] = $errLogo;
        }

        if (!$err) {
            if ($id === 0) {
                $pdo->beginTransaction();
                $pdo->prepare('INSERT INTO tim (id_kapten, nama_tim, logo) VALUES (?,?,?)')->execute([$kapten, $nama, $logoBaru]);
                $idBaru = (int)$pdo->lastInsertId();
                $pdo->prepare('INSERT INTO anggota_tim (id_tim, id_pemain, posisi) VALUES (?,?,?)')->execute([$idBaru, $kapten, 'Kapten']);
                $pdo->commit();
                flash('success', 'Tim berhasil dibuat. Tambahkan anggota melalui tombol Anggota.');
            } else {
                $logoFinal = $logoBaru ?: $lama['logo'];
                $pdo->prepare('UPDATE tim SET id_kapten=?, nama_tim=?, logo=? WHERE id_tim=?')->execute([$kapten, $nama, $logoFinal, $id]);
                if ($logoBaru) hapus_file_logo($lama['logo']);
                $pdo->prepare('INSERT IGNORE INTO anggota_tim (id_tim, id_pemain, posisi) VALUES (?,?,?)')->execute([$id, $kapten, 'Kapten']);
                flash('success', 'Tim berhasil diperbarui.');
            }
            redirect('tim.php');
        }
        foreach ($err as $m) flash('danger', $m);
        $edit = ['id_tim' => $id, 'nama_tim' => $nama, 'id_kapten' => $kapten, 'logo' => $lama['logo'] ?? null];
    }
}

if (!$edit && isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM tim WHERE id_tim = ?');
    $st->execute([(int)$_GET['edit']]);
    $t = $st->fetch();
    if ($t && boleh_kelola_tim($t)) $edit = $t;
}
$f = $edit ?? ['id_tim' => 0, 'nama_tim' => '', 'id_kapten' => user_id(), 'logo' => null];

$pemainSemua = is_admin() ? $pdo->query('SELECT id_pemain, nama_lengkap, nickname FROM pemain ORDER BY nickname')->fetchAll() : [];

$q = trim($_GET['q'] ?? '');
$st = $pdo->prepare('SELECT t.*, p.nickname AS kapten, p.nama_lengkap AS nama_kapten,
        (SELECT COUNT(*) FROM anggota_tim a WHERE a.id_tim = t.id_tim) AS jml_anggota
    FROM tim t JOIN pemain p ON p.id_pemain = t.id_kapten
    WHERE t.nama_tim LIKE ? OR p.nickname LIKE ? ORDER BY t.nama_tim');
$st->execute(['%' . $q . '%', '%' . $q . '%']);
$rows = $st->fetchAll();

$judul = 'Data Tim';
include 'includes/header.php';
?>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-grad-hijau"><i class="bi bi-people-fill me-2"></i><?= $f['id_tim'] ? 'Ubah Tim' : 'Buat Tim Baru' ?></div>
            <div class="card-body">
                <form method="post" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="aksi" value="simpan">
                    <input type="hidden" name="id_tim" value="<?= (int)$f['id_tim'] ?>">
                    <div class="mb-2"><label class="form-label">Nama Tim</label>
                        <input type="text" name="nama_tim" class="form-control" maxlength="100" value="<?= e($f['nama_tim']) ?>" required></div>
                    <?php if (is_admin()): ?>
                        <div class="mb-2"><label class="form-label">Kapten</label>
                            <select name="id_kapten" class="form-select" required>
                                <option value="">-- Pilih kapten --</option>
                                <?php foreach ($pemainSemua as $p): ?>
                                    <option value="<?= (int)$p['id_pemain'] ?>" <?= (int)$f['id_kapten'] === (int)$p['id_pemain'] ? 'selected' : '' ?>>
                                        <?= e($p['nickname']) ?> - <?= e($p['nama_lengkap']) ?></option>
                                <?php endforeach; ?>
                            </select></div>
                    <?php else: ?>
                        <div class="mb-2 small text-muted">Kapten: <b><?= e($_SESSION['user']['nickname']) ?></b> (Anda)</div>
                    <?php endif; ?>
                    <div class="mb-3"><label class="form-label">Logo (JPG/PNG/WEBP/GIF, maks 2 MB)</label>
                        <?php if ($f['logo']): ?><div class="mb-1"><img src="<?= e(logo_url($f['logo'])) ?>" class="logo-thumb" alt="logo"> <small class="text-muted">Logo saat ini</small></div><?php endif; ?>
                        <input type="file" name="logo" class="form-control" accept="image/*"></div>
                    <button class="btn btn-grad"><i class="bi bi-save me-1"></i>Simpan</button>
                    <?php if ($f['id_tim']): ?><a href="tim.php" class="btn btn-secondary">Batal</a><?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card"><div class="card-body">
            <form class="row g-2 mb-3" method="get">
                <div class="col"><input type="search" name="q" class="form-control" placeholder="Cari nama tim atau kapten..." value="<?= e($q) ?>"></div>
                <div class="col-auto"><button class="btn btn-primary"><i class="bi bi-search"></i></button></div>
            </form>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead><tr><th>Tim</th><th>Kapten</th><th>Anggota</th><th class="text-end">Aksi</th></tr></thead>
                    <tbody>
                    <?php if (!$rows): ?><tr><td colspan="4" class="text-center text-muted py-4">Belum ada tim.</td></tr><?php endif; ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td>
                                <?php if ($r['logo']): ?><img src="<?= e(logo_url($r['logo'])) ?>" class="logo-thumb me-2" alt=""><?php else: ?><span class="logo-kosong me-2"><i class="bi bi-shield-fill"></i></span><?php endif; ?>
                                <span class="fw-semibold"><?= e($r['nama_tim']) ?></span>
                                <?php if ((int)$r['id_kapten'] === user_id()): ?><span class="badge bg-warning text-dark">Tim Anda</span><?php endif; ?>
                            </td>
                            <td>@<?= e($r['kapten']) ?></td>
                            <td><span class="badge bg-success"><?= (int)$r['jml_anggota'] ?></span></td>
                            <td class="text-end text-nowrap">
                                <a href="anggota.php?id_tim=<?= (int)$r['id_tim'] ?>" class="btn btn-sm btn-info text-white" title="Anggota"><i class="bi bi-people"></i></a>
                                <?php if (boleh_kelola_tim($r)): ?>
                                    <a href="tim.php?edit=<?= (int)$r['id_tim'] ?>" class="btn btn-sm btn-warning"><i class="bi bi-pencil-square"></i></a>
                                    <?= btn_hapus($r['id_tim']) ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div></div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
