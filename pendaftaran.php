<?php
require_once 'config.php';
login_required();

$filterT = (int)($_GET['id_turnamen'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'daftar') {
        $idT = (int)($_POST['id_turnamen'] ?? 0);
        $idTim = (int)($_POST['id_tim'] ?? 0);

        $st = $pdo->prepare('SELECT * FROM turnamen WHERE id_turnamen = ?');
        $st->execute([$idT]);
        $trn = $st->fetch();
        $st = $pdo->prepare('SELECT * FROM tim WHERE id_tim = ?');
        $st->execute([$idTim]);
        $tim = $st->fetch();

        if (!$trn || !$tim) {
            flash('danger', 'Turnamen atau tim tidak valid.');
        } elseif (!boleh_kelola_tim($tim)) {
            flash('danger', 'Hanya kapten tim atau admin yang dapat mendaftarkan tim.');
        } elseif ($trn['status'] !== 'pendaftaran') {
            flash('warning', 'Pendaftaran turnamen ini sudah ditutup.');
        } else {
            try {
                $pdo->prepare('INSERT INTO pendaftaran (id_turnamen, id_tim, tgl_daftar) VALUES (?,?,CURDATE())')->execute([$idT, $idTim]);
                flash('success', 'Tim berhasil didaftarkan. Menunggu konfirmasi pembayaran dari admin.');
            } catch (PDOException $ex) {
                flash('warning', 'Tim tersebut sudah terdaftar di turnamen ini.');
            }
        }
        redirect('pendaftaran.php' . ($filterT ? '?id_turnamen=' . $filterT : ''));
    }

    if ($aksi === 'bayar') {
        admin_required();
        $status = ($_POST['status_bayar'] ?? '') === 'lunas' ? 'lunas' : 'belum';
        $pdo->prepare('UPDATE pendaftaran SET status_bayar = ? WHERE id_pendaftaran = ?')->execute([$status, (int)($_POST['id'] ?? 0)]);
        flash('success', 'Status pembayaran diperbarui.');
        redirect('pendaftaran.php' . ($filterT ? '?id_turnamen=' . $filterT : ''));
    }

    if ($aksi === 'hapus') {
        $st = $pdo->prepare('SELECT pd.*, t.id_kapten, tr.status AS status_turnamen
            FROM pendaftaran pd JOIN tim t ON t.id_tim = pd.id_tim JOIN turnamen tr ON tr.id_turnamen = pd.id_turnamen
            WHERE pd.id_pendaftaran = ?');
        $st->execute([(int)($_POST['id'] ?? 0)]);
        $pd = $st->fetch();
        if (!$pd) {
            flash('danger', 'Data pendaftaran tidak ditemukan.');
        } elseif (is_admin()) {
            $pdo->prepare('DELETE FROM pendaftaran WHERE id_pendaftaran = ?')->execute([$pd['id_pendaftaran']]);
            flash('success', 'Pendaftaran dibatalkan.');
        } elseif ((int)$pd['id_kapten'] === user_id() && $pd['status_bayar'] === 'belum' && $pd['status_turnamen'] === 'pendaftaran') {
            $pdo->prepare('DELETE FROM pendaftaran WHERE id_pendaftaran = ?')->execute([$pd['id_pendaftaran']]);
            flash('success', 'Pendaftaran dibatalkan.');
        } else {
            flash('danger', 'Pendaftaran tidak dapat dibatalkan (sudah lunas, turnamen berjalan, atau bukan tim Anda).');
        }
        redirect('pendaftaran.php' . ($filterT ? '?id_turnamen=' . $filterT : ''));
    }
}

$turnamenBuka = $pdo->query("SELECT t.id_turnamen, t.nama_turnamen, g.nama_game FROM turnamen t JOIN game g ON g.id_game = t.id_game
    WHERE t.status = 'pendaftaran' ORDER BY t.tgl_mulai")->fetchAll();

if (is_admin()) {
    $timOpsi = $pdo->query('SELECT id_tim, nama_tim FROM tim ORDER BY nama_tim')->fetchAll();
} else {
    $st = $pdo->prepare('SELECT id_tim, nama_tim FROM tim WHERE id_kapten = ? ORDER BY nama_tim');
    $st->execute([user_id()]);
    $timOpsi = $st->fetchAll();
}

$turnamenSemua = $pdo->query('SELECT id_turnamen, nama_turnamen FROM turnamen ORDER BY tgl_mulai DESC')->fetchAll();

$sql = 'SELECT pd.*, t.nama_tim, t.logo, t.id_kapten, tr.nama_turnamen, tr.status AS status_turnamen
    FROM pendaftaran pd JOIN tim t ON t.id_tim = pd.id_tim JOIN turnamen tr ON tr.id_turnamen = pd.id_turnamen WHERE 1=1';
$par = [];
if (!is_admin()) { $sql .= ' AND t.id_kapten = ?'; $par[] = user_id(); }
if ($filterT) { $sql .= ' AND pd.id_turnamen = ?'; $par[] = $filterT; }
$st = $pdo->prepare($sql . ' ORDER BY pd.tgl_daftar DESC, pd.id_pendaftaran DESC');
$st->execute($par);
$rows = $st->fetchAll();

$judul = 'Pendaftaran Turnamen';
include 'includes/header.php';
?>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-grad-hijau"><i class="bi bi-clipboard-check-fill me-2"></i>Daftarkan Tim</div>
            <div class="card-body">
                <?php if (!$turnamenBuka): ?>
                    <div class="alert alert-info mb-0">
                        Tidak ada turnamen yang sedang membuka pendaftaran.
                        <?php if (is_admin()): ?>Buat turnamen dengan status <b>Pendaftaran</b> di menu <a href="turnamen.php" class="alert-link">Turnamen</a>.<?php endif; ?>
                    </div>
                <?php elseif (!$timOpsi): ?>
                    <div class="alert alert-warning mb-0">Anda belum menjadi kapten tim mana pun. <a href="tim.php">Buat tim</a> dahulu.</div>
                <?php else: ?>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="aksi" value="daftar">
                        <div class="mb-2"><label class="form-label">Turnamen</label>
                            <select name="id_turnamen" class="form-select" required>
                                <option value="">-- Pilih turnamen --</option>
                                <?php foreach ($turnamenBuka as $t): ?>
                                    <option value="<?= (int)$t['id_turnamen'] ?>" <?= $filterT === (int)$t['id_turnamen'] ? 'selected' : '' ?>><?= e($t['nama_turnamen']) ?> (<?= e($t['nama_game']) ?>)</option>
                                <?php endforeach; ?>
                            </select></div>
                        <div class="mb-3"><label class="form-label">Tim</label>
                            <select name="id_tim" class="form-select" required>
                                <option value="">-- Pilih tim --</option>
                                <?php foreach ($timOpsi as $t): ?>
                                    <option value="<?= (int)$t['id_tim'] ?>"><?= e($t['nama_tim']) ?></option>
                                <?php endforeach; ?>
                            </select></div>
                        <button class="btn btn-grad w-100"><i class="bi bi-send-check me-1"></i>Daftar</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card"><div class="card-body">
            <form class="row g-2 mb-3" method="get">
                <div class="col">
                    <select name="id_turnamen" class="form-select" onchange="this.form.submit()">
                        <option value="0">Semua turnamen</option>
                        <?php foreach ($turnamenSemua as $t): ?>
                            <option value="<?= (int)$t['id_turnamen'] ?>" <?= $filterT === (int)$t['id_turnamen'] ? 'selected' : '' ?>><?= e($t['nama_turnamen']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
            <?php if (!is_admin()): ?><p class="small text-muted">Menampilkan pendaftaran untuk tim yang Anda pimpin.</p><?php endif; ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead><tr><th>Tim</th><th>Turnamen</th><th>Tgl Daftar</th><th>Pembayaran</th><th class="text-end">Aksi</th></tr></thead>
                    <tbody>
                    <?php if (!$rows): ?><tr><td colspan="5" class="text-center text-muted py-4">Belum ada pendaftaran.</td></tr><?php endif; ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($r['nama_tim']) ?></td>
                            <td><?= e($r['nama_turnamen']) ?> <?= badge_status_turnamen($r['status_turnamen']) ?></td>
                            <td><?= tgl($r['tgl_daftar']) ?></td>
                            <td><span class="badge bg-<?= $r['status_bayar'] === 'lunas' ? 'success' : 'secondary' ?>"><?= e(ucfirst($r['status_bayar'])) ?></span></td>
                            <td class="text-end text-nowrap">
                                <?php if (is_admin()): ?>
                                    <form method="post" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="aksi" value="bayar">
                                        <input type="hidden" name="id" value="<?= (int)$r['id_pendaftaran'] ?>">
                                        <input type="hidden" name="status_bayar" value="<?= $r['status_bayar'] === 'lunas' ? 'belum' : 'lunas' ?>">
                                        <button class="btn btn-sm <?= $r['status_bayar'] === 'lunas' ? 'btn-outline-secondary' : 'btn-success' ?>">
                                            <?= $r['status_bayar'] === 'lunas' ? 'Batalkan Lunas' : 'Tandai Lunas' ?></button>
                                    </form>
                                    <?= btn_hapus($r['id_pendaftaran']) ?>
                                <?php elseif ($r['status_bayar'] === 'belum' && $r['status_turnamen'] === 'pendaftaran'): ?>
                                    <?= btn_hapus($r['id_pendaftaran']) ?>
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
