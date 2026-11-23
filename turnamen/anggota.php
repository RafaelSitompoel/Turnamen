<?php
require_once 'config.php';
login_required();

$idTim = (int)($_GET['id_tim'] ?? $_POST['id_tim'] ?? 0);
$st = $pdo->prepare('SELECT t.*, p.nickname AS kapten FROM tim t JOIN pemain p ON p.id_pemain = t.id_kapten WHERE t.id_tim = ?');
$st->execute([$idTim]);
$tim = $st->fetch();
if (!$tim) {
    flash('danger', 'Tim tidak ditemukan.');
    redirect('tim.php');
}
$bisa = boleh_kelola_tim($tim);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (!$bisa) {
        flash('danger', 'Hanya kapten atau admin yang dapat mengelola anggota.');
        redirect('anggota.php?id_tim=' . $idTim);
    }
    $aksi = $_POST['aksi'] ?? '';
    $posisi = mb_substr(trim($_POST['posisi'] ?? ''), 0, 30);

    if ($aksi === 'tambah') {
        $idPemain = (int)($_POST['id_pemain'] ?? 0);
        $cek = $pdo->prepare('SELECT COUNT(*) FROM pemain WHERE id_pemain = ?');
        $cek->execute([$idPemain]);
        if (!$cek->fetchColumn()) {
            flash('danger', 'Pemain tidak valid.');
        } else {
            try {
                $pdo->prepare('INSERT INTO anggota_tim (id_tim, id_pemain, posisi) VALUES (?,?,?)')
                    ->execute([$idTim, $idPemain, $posisi !== '' ? $posisi : null]);
                flash('success', 'Anggota berhasil ditambahkan.');
            } catch (PDOException $ex) {
                flash('warning', 'Pemain sudah menjadi anggota tim ini.');
            }
        }
    } elseif ($aksi === 'posisi') {
        $pdo->prepare('UPDATE anggota_tim SET posisi = ? WHERE id_anggota = ? AND id_tim = ?')
            ->execute([$posisi !== '' ? $posisi : null, (int)($_POST['id_anggota'] ?? 0), $idTim]);
        flash('success', 'Posisi diperbarui.');
    } elseif ($aksi === 'hapus') {
        $cek = $pdo->prepare('SELECT id_pemain FROM anggota_tim WHERE id_anggota = ? AND id_tim = ?');
        $cek->execute([(int)($_POST['id'] ?? 0), $idTim]);
        $idPemain = (int)$cek->fetchColumn();
        if ($idPemain === (int)$tim['id_kapten']) {
            flash('warning', 'Kapten tidak dapat dikeluarkan. Ganti kapten terlebih dahulu (oleh admin).');
        } else {
            $pdo->prepare('DELETE FROM anggota_tim WHERE id_anggota = ? AND id_tim = ?')->execute([(int)($_POST['id'] ?? 0), $idTim]);
            flash('success', 'Anggota dikeluarkan dari tim.');
        }
    }
    redirect('anggota.php?id_tim=' . $idTim);
}

$st = $pdo->prepare('SELECT a.*, p.nama_lengkap, p.nickname FROM anggota_tim a JOIN pemain p ON p.id_pemain = a.id_pemain WHERE a.id_tim = ? ORDER BY a.id_anggota');
$st->execute([$idTim]);
$anggota = $st->fetchAll();

$st = $pdo->prepare('SELECT id_pemain, nama_lengkap, nickname FROM pemain WHERE id_pemain NOT IN (SELECT id_pemain FROM anggota_tim WHERE id_tim = ?) ORDER BY nickname');
$st->execute([$idTim]);
$kandidat = $st->fetchAll();

$st = $pdo->prepare('SELECT t.nama_turnamen, t.status, pd.status_bayar FROM pendaftaran pd JOIN turnamen t ON t.id_turnamen = pd.id_turnamen WHERE pd.id_tim = ? ORDER BY pd.tgl_daftar DESC');
$st->execute([$idTim]);
$riwayat = $st->fetchAll();

$judul = 'Anggota Tim: ' . $tim['nama_tim'];
include 'includes/header.php';
?>
<a href="tim.php" class="btn btn-sm btn-outline-secondary mb-3"><i class="bi bi-arrow-left"></i> Kembali ke daftar tim</a>
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header bg-grad-ungu d-flex justify-content-between">
                <span><i class="bi bi-people-fill me-2"></i>Daftar Anggota</span>
                <span>Kapten: @<?= e($tim['kapten']) ?></span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead><tr><th>#</th><th>Pemain</th><th>Posisi</th><?php if ($bisa): ?><th class="text-end">Aksi</th><?php endif; ?></tr></thead>
                        <tbody>
                        <?php foreach ($anggota as $i => $a): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><?= e($a['nama_lengkap']) ?> <span class="badge bg-info text-dark">@<?= e($a['nickname']) ?></span>
                                    <?php if ((int)$a['id_pemain'] === (int)$tim['id_kapten']): ?><span class="badge bg-warning text-dark"><i class="bi bi-star-fill"></i> Kapten</span><?php endif; ?></td>
                                <?php if ($bisa): ?>
                                    <td>
                                        <form method="post" class="d-flex gap-1">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="aksi" value="posisi">
                                            <input type="hidden" name="id_tim" value="<?= $idTim ?>">
                                            <input type="hidden" name="id_anggota" value="<?= (int)$a['id_anggota'] ?>">
                                            <input type="text" name="posisi" maxlength="30" class="form-control form-control-sm" value="<?= e($a['posisi']) ?>" placeholder="Posisi">
                                            <button class="btn btn-sm btn-success" title="Simpan"><i class="bi bi-check2"></i></button>
                                        </form>
                                    </td>
                                    <td class="text-end">
                                        <?php if ((int)$a['id_pemain'] !== (int)$tim['id_kapten']) echo btn_hapus($a['id_anggota'], ['id_tim' => $idTim]); ?>
                                    </td>
                                <?php else: ?>
                                    <td><?= e($a['posisi'] ?: '-') ?></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <?php if ($bisa): ?>
            <div class="card mb-3">
                <div class="card-header bg-grad-hijau"><i class="bi bi-person-plus-fill me-2"></i>Tambah Anggota</div>
                <div class="card-body">
                    <?php if (!$kandidat): ?>
                        <span class="text-muted">Tidak ada pemain lain yang dapat ditambahkan.</span>
                    <?php else: ?>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="aksi" value="tambah">
                            <input type="hidden" name="id_tim" value="<?= $idTim ?>">
                            <div class="mb-2"><label class="form-label">Pemain</label>
                                <select name="id_pemain" class="form-select" required>
                                    <option value="">-- Pilih pemain --</option>
                                    <?php foreach ($kandidat as $k): ?>
                                        <option value="<?= (int)$k['id_pemain'] ?>"><?= e($k['nickname']) ?> - <?= e($k['nama_lengkap']) ?></option>
                                    <?php endforeach; ?>
                                </select></div>
                            <div class="mb-3"><label class="form-label">Posisi</label>
                                <input type="text" name="posisi" maxlength="30" class="form-control" placeholder="mis. Support, Carry, Striker"></div>
                            <button class="btn btn-grad w-100"><i class="bi bi-plus-circle me-1"></i>Tambahkan</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        <div class="card">
            <div class="card-header bg-grad-oranye"><i class="bi bi-trophy me-2"></i>Turnamen yang Diikuti</div>
            <ul class="list-group list-group-flush">
                <?php if (!$riwayat): ?><li class="list-group-item text-muted">Belum mengikuti turnamen.</li><?php endif; ?>
                <?php foreach ($riwayat as $r): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <?= e($r['nama_turnamen']) ?>
                        <span><?= badge_status_turnamen($r['status']) ?>
                        <span class="badge bg-<?= $r['status_bayar'] === 'lunas' ? 'success' : 'secondary' ?>"><?= e($r['status_bayar']) ?></span></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
