<?php
require_once 'config.php';
login_required();

$idT = (int)($_GET['id_turnamen'] ?? $_POST['id_turnamen'] ?? 0);
$edit = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    admin_required();
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'hapus') {
        $pdo->prepare('DELETE FROM pertandingan WHERE id_pertandingan = ?')->execute([(int)($_POST['id'] ?? 0)]);
        flash('success', 'Pertandingan dihapus.');
        redirect('pertandingan.php?id_turnamen=' . $idT);
    }

    if ($aksi === 'simpan') {
        $id = (int)($_POST['id_pertandingan'] ?? 0);
        $a = (int)($_POST['id_tim_a'] ?? 0);
        $b = (int)($_POST['id_tim_b'] ?? 0);
        $skorA = $_POST['skor_a'] ?? '0';
        $skorB = $_POST['skor_b'] ?? '0';
        $babak = mb_substr(trim($_POST['babak'] ?? ''), 0, 50);
        $waktuRaw = $_POST['waktu_main'] ?? '';
        $err = [];

        $d = DateTime::createFromFormat('Y-m-d\TH:i', $waktuRaw);
        if (!$d) $err[] = 'Waktu main tidak valid.';
        if ($a === 0 || $b === 0) $err[] = 'Pilih kedua tim.';
        elseif ($a === $b) $err[] = 'Tim A dan Tim B tidak boleh sama.';
        if (!ctype_digit((string)$skorA) || !ctype_digit((string)$skorB) || $skorA > 999 || $skorB > 999) $err[] = 'Skor harus angka 0-999.';

        if (!$err) {
            $cek = $pdo->prepare('SELECT COUNT(*) FROM pendaftaran WHERE id_turnamen = ? AND id_tim IN (?, ?)');
            $cek->execute([$idT, $a, $b]);
            if ((int)$cek->fetchColumn() !== 2) $err[] = 'Kedua tim harus sudah terdaftar pada turnamen ini.';
        }

        if (!$err) {
            $skorA = (int)$skorA; $skorB = (int)$skorB;
            $menang = $skorA > $skorB ? $a : ($skorB > $skorA ? $b : null);
            $waktu = $d->format('Y-m-d H:i:s');
            if ($id === 0) {
                $pdo->prepare('INSERT INTO pertandingan (id_turnamen, id_tim_a, id_tim_b, id_tim_menang, skor_a, skor_b, waktu_main, babak) VALUES (?,?,?,?,?,?,?,?)')
                    ->execute([$idT, $a, $b, $menang, $skorA, $skorB, $waktu, $babak !== '' ? $babak : null]);
                flash('success', 'Pertandingan berhasil dijadwalkan.');
            } else {
                $pdo->prepare('UPDATE pertandingan SET id_tim_a=?, id_tim_b=?, id_tim_menang=?, skor_a=?, skor_b=?, waktu_main=?, babak=? WHERE id_pertandingan=? AND id_turnamen=?')
                    ->execute([$a, $b, $menang, $skorA, $skorB, $waktu, $babak !== '' ? $babak : null, $id, $idT]);
                flash('success', 'Pertandingan diperbarui.');
            }
            redirect('pertandingan.php?id_turnamen=' . $idT);
        }
        foreach ($err as $m) flash('danger', $m);
        $edit = ['id_pertandingan' => $id, 'id_tim_a' => $a, 'id_tim_b' => $b, 'skor_a' => (int)$skorA, 'skor_b' => (int)$skorB, 'waktu_main' => str_replace('T', ' ', $waktuRaw), 'babak' => $babak];
    }
}

if (!$edit && is_admin() && isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM pertandingan WHERE id_pertandingan = ? AND id_turnamen = ?');
    $st->execute([(int)$_GET['edit'], $idT]);
    $edit = $st->fetch() ?: null;
}
$f = $edit ?? ['id_pertandingan' => 0, 'id_tim_a' => 0, 'id_tim_b' => 0, 'skor_a' => 0, 'skor_b' => 0, 'waktu_main' => '', 'babak' => ''];
$waktuInput = $f['waktu_main'] ? date('Y-m-d\TH:i', strtotime($f['waktu_main'])) : '';

$turnamenSemua = $pdo->query('SELECT id_turnamen, nama_turnamen FROM turnamen ORDER BY tgl_mulai DESC')->fetchAll();

$timPeserta = [];
if ($idT) {
    $st = $pdo->prepare('SELECT t.id_tim, t.nama_tim FROM pendaftaran pd JOIN tim t ON t.id_tim = pd.id_tim WHERE pd.id_turnamen = ? ORDER BY t.nama_tim');
    $st->execute([$idT]);
    $timPeserta = $st->fetchAll();
}

$sql = 'SELECT p.*, a.nama_tim AS tim_a, b.nama_tim AS tim_b, w.nama_tim AS tim_menang, tr.nama_turnamen
    FROM pertandingan p JOIN tim a ON a.id_tim = p.id_tim_a JOIN tim b ON b.id_tim = p.id_tim_b
    LEFT JOIN tim w ON w.id_tim = p.id_tim_menang JOIN turnamen tr ON tr.id_turnamen = p.id_turnamen';
$par = [];
if ($idT) { $sql .= ' WHERE p.id_turnamen = ?'; $par[] = $idT; }
$st = $pdo->prepare($sql . ' ORDER BY p.waktu_main DESC');
$st->execute($par);
$rows = $st->fetchAll();

$judul = 'Pertandingan';
include 'includes/header.php';
?>
<div class="card mb-3"><div class="card-body">
    <form method="get" class="row g-2 align-items-center">
        <div class="col-md-6">
            <select name="id_turnamen" class="form-select" onchange="this.form.submit()">
                <option value="0">Semua turnamen</option>
                <?php foreach ($turnamenSemua as $t): ?>
                    <option value="<?= (int)$t['id_turnamen'] ?>" <?= $idT === (int)$t['id_turnamen'] ? 'selected' : '' ?>><?= e($t['nama_turnamen']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
</div></div>

<div class="row g-3">
    <?php if (is_admin()): ?>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-grad-merah"><i class="bi bi-joystick me-2"></i><?= $f['id_pertandingan'] ? 'Ubah Pertandingan / Input Skor' : 'Jadwalkan Pertandingan' ?></div>
            <div class="card-body">
                <?php if (!$idT): ?>
                    <div class="alert alert-info mb-0">
                        <?php if (!$turnamenSemua): ?>
                            Belum ada turnamen. Buat dulu di menu <a href="turnamen.php" class="alert-link">Turnamen</a>
                            (tambahkan <a href="game.php" class="alert-link">Game</a> lebih dulu), lalu daftarkan minimal 2 tim.
                        <?php else: ?>
                            Pilih turnamen pada kotak di atas untuk menjadwalkan pertandingan.
                        <?php endif; ?>
                    </div>
                <?php elseif (count($timPeserta) < 2): ?>
                    <div class="alert alert-warning mb-0">Turnamen ini membutuhkan minimal 2 tim terdaftar. <a href="pendaftaran.php?id_turnamen=<?= $idT ?>">Lihat pendaftaran</a>.</div>
                <?php else: ?>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="aksi" value="simpan">
                        <input type="hidden" name="id_turnamen" value="<?= $idT ?>">
                        <input type="hidden" name="id_pertandingan" value="<?= (int)$f['id_pertandingan'] ?>">
                        <div class="mb-2"><label class="form-label">Babak</label>
                            <input type="text" name="babak" list="daftarBabak" maxlength="50" class="form-control" value="<?= e($f['babak']) ?>" placeholder="mis. Grup A, Semifinal, Final">
                            <datalist id="daftarBabak"><option value="Grup"><option value="Perempat Final"><option value="Semifinal"><option value="Perebutan Juara 3"><option value="Final"></datalist></div>
                        <div class="mb-2"><label class="form-label">Waktu Main</label>
                            <input type="datetime-local" name="waktu_main" class="form-control" value="<?= e($waktuInput) ?>" required></div>
                        <div class="row g-2 mb-2">
                            <div class="col-8"><label class="form-label">Tim A</label>
                                <select name="id_tim_a" class="form-select" required>
                                    <option value="">-- Tim A --</option>
                                    <?php foreach ($timPeserta as $t): ?><option value="<?= (int)$t['id_tim'] ?>" <?= (int)$f['id_tim_a'] === (int)$t['id_tim'] ? 'selected' : '' ?>><?= e($t['nama_tim']) ?></option><?php endforeach; ?>
                                </select></div>
                            <div class="col-4"><label class="form-label">Skor</label>
                                <input type="number" name="skor_a" min="0" max="999" class="form-control" value="<?= (int)$f['skor_a'] ?>" required></div>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-8"><label class="form-label">Tim B</label>
                                <select name="id_tim_b" class="form-select" required>
                                    <option value="">-- Tim B --</option>
                                    <?php foreach ($timPeserta as $t): ?><option value="<?= (int)$t['id_tim'] ?>" <?= (int)$f['id_tim_b'] === (int)$t['id_tim'] ? 'selected' : '' ?>><?= e($t['nama_tim']) ?></option><?php endforeach; ?>
                                </select></div>
                            <div class="col-4"><label class="form-label">Skor</label>
                                <input type="number" name="skor_b" min="0" max="999" class="form-control" value="<?= (int)$f['skor_b'] ?>" required></div>
                        </div>
                        <div class="form-text mb-3">Pemenang ditentukan otomatis dari skor (seri = tanpa pemenang).</div>
                        <button class="btn btn-grad"><i class="bi bi-save me-1"></i>Simpan</button>
                        <?php if ($f['id_pertandingan']): ?><a href="pertandingan.php?id_turnamen=<?= $idT ?>" class="btn btn-secondary">Batal</a><?php endif; ?>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <div class="<?= is_admin() ? 'col-lg-8' : 'col-12' ?>">
        <div class="card"><div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead><tr><th>Waktu</th><th>Turnamen / Babak</th><th class="text-end">Tim A</th><th class="text-center">Skor</th><th>Tim B</th><th>Pemenang</th><?php if (is_admin()): ?><th class="text-end">Aksi</th><?php endif; ?></tr></thead>
                    <tbody>
                    <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-4">Belum ada pertandingan.</td></tr><?php endif; ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td class="small"><?= tgl_waktu($r['waktu_main']) ?></td>
                            <td><?= e($r['nama_turnamen']) ?><br><small class="text-muted"><?= e($r['babak'] ?: '-') ?></small></td>
                            <td class="text-end fw-semibold <?= (int)$r['id_tim_menang'] === (int)$r['id_tim_a'] ? 'text-success' : '' ?>"><?= e($r['tim_a']) ?></td>
                            <td class="text-center"><span class="badge bg-dark skor"><?= (int)$r['skor_a'] ?> - <?= (int)$r['skor_b'] ?></span></td>
                            <td class="fw-semibold <?= (int)$r['id_tim_menang'] === (int)$r['id_tim_b'] ? 'text-success' : '' ?>"><?= e($r['tim_b']) ?></td>
                            <td><?php if ($r['tim_menang']): ?><span class="badge bg-success"><i class="bi bi-trophy-fill"></i> <?= e($r['tim_menang']) ?></span>
                                <?php elseif ((int)$r['skor_a'] + (int)$r['skor_b'] > 0): ?><span class="badge bg-secondary">Seri</span>
                                <?php elseif (strtotime($r['waktu_main']) > time()): ?><span class="badge bg-info text-dark">Terjadwal</span>
                                <?php else: ?><span class="badge bg-warning text-dark">Menunggu hasil</span><?php endif; ?></td>
                            <?php if (is_admin()): ?>
                                <td class="text-end text-nowrap">
                                    <a href="pertandingan.php?id_turnamen=<?= (int)$r['id_turnamen'] ?>&edit=<?= (int)$r['id_pertandingan'] ?>" class="btn btn-sm btn-warning"><i class="bi bi-pencil-square"></i></a>
                                    <?= btn_hapus($r['id_pertandingan'], ['id_turnamen' => $r['id_turnamen']]) ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div></div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
