<?php
require_once 'config.php';
login_required();

$idT = (int)($_GET['id_turnamen'] ?? 0);
$turnamenSemua = $pdo->query('SELECT id_turnamen, nama_turnamen, status FROM turnamen ORDER BY tgl_mulai DESC')->fetchAll();

// Jika belum memilih, otomatis tampilkan turnamen yang paling relevan:
// berlangsung > pendaftaran > selesai (yang terbaru).
if (!$idT && $turnamenSemua) {
    foreach (['berlangsung', 'pendaftaran', 'selesai'] as $prioritas) {
        foreach ($turnamenSemua as $t) {
            if ($t['status'] === $prioritas) { $idT = (int)$t['id_turnamen']; break 2; }
        }
    }
}
$hasil = [];

$trn = null;
$klasemen = [];
if ($idT) {
    $st = $pdo->prepare('SELECT t.*, g.nama_game FROM turnamen t JOIN game g ON g.id_game = t.id_game WHERE t.id_turnamen = ?');
    $st->execute([$idT]);
    $trn = $st->fetch();
}

if ($trn) {
    $st = $pdo->prepare('SELECT t.id_tim, t.nama_tim, t.logo FROM pendaftaran pd JOIN tim t ON t.id_tim = pd.id_tim WHERE pd.id_turnamen = ?');
    $st->execute([$idT]);
    foreach ($st->fetchAll() as $t) {
        $klasemen[$t['id_tim']] = $t + ['main' => 0, 'menang' => 0, 'seri' => 0, 'kalah' => 0, 'gm' => 0, 'gk' => 0, 'poin' => 0];
    }

    $st = $pdo->prepare('SELECT p.*, a.nama_tim AS tim_a, b.nama_tim AS tim_b FROM pertandingan p
        JOIN tim a ON a.id_tim = p.id_tim_a JOIN tim b ON b.id_tim = p.id_tim_b
        WHERE p.id_turnamen = ? ORDER BY p.waktu_main DESC');
    $st->execute([$idT]);
    $hasil = $st->fetchAll();
    foreach ($hasil as $m) {
        $a = (int)$m['id_tim_a']; $b = (int)$m['id_tim_b'];
        $sa = (int)$m['skor_a']; $sb = (int)$m['skor_b'];
        // Hanya pertandingan yang sudah ada hasilnya (ada pemenang atau skor tercatat)
        if ($m['id_tim_menang'] === null && $sa + $sb === 0) continue;
        if (!isset($klasemen[$a]) || !isset($klasemen[$b])) continue;
        $klasemen[$a]['main']++; $klasemen[$b]['main']++;
        $klasemen[$a]['gm'] += $sa; $klasemen[$a]['gk'] += $sb;
        $klasemen[$b]['gm'] += $sb; $klasemen[$b]['gk'] += $sa;
        if ($sa > $sb) { $klasemen[$a]['menang']++; $klasemen[$b]['kalah']++; $klasemen[$a]['poin'] += 3; }
        elseif ($sb > $sa) { $klasemen[$b]['menang']++; $klasemen[$a]['kalah']++; $klasemen[$b]['poin'] += 3; }
        else { $klasemen[$a]['seri']++; $klasemen[$b]['seri']++; $klasemen[$a]['poin']++; $klasemen[$b]['poin']++; }
    }
    usort($klasemen, function ($x, $y) {
        return [$y['poin'], $y['gm'] - $y['gk'], $y['menang'], $y['gm']] <=> [$x['poin'], $x['gm'] - $x['gk'], $x['menang'], $x['gm']];
    });
}

$judul = 'Klasemen Turnamen';
include 'includes/header.php';
?>
<div class="card mb-3"><div class="card-body">
    <form method="get">
        <select name="id_turnamen" class="form-select" onchange="this.form.submit()">
            <option value="0">-- Pilih turnamen --</option>
            <?php foreach ($turnamenSemua as $t): ?>
                <option value="<?= (int)$t['id_turnamen'] ?>" <?= $idT === (int)$t['id_turnamen'] ? 'selected' : '' ?>><?= e($t['nama_turnamen']) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div></div>

<?php if (!$trn): ?>
    <div class="card">
        <div class="card-header bg-grad-biru"><i class="bi bi-bar-chart-fill me-2"></i>Klasemen</div>
        <div class="card-body text-center py-5">
            <i class="bi bi-trophy display-4 text-warning"></i>
            <h5 class="mt-3">Belum ada turnamen</h5>
            <p class="text-muted mb-3">Klasemen terisi otomatis dari tim yang mendaftar dan hasil pertandingan.</p>
            <?php if (is_admin()): ?>
                <a href="game.php" class="btn btn-outline-primary">1. Data Game</a>
                <a href="turnamen.php" class="btn btn-outline-warning">2. Buat Turnamen</a>
            <?php else: ?>
                <a href="turnamen.php" class="btn btn-outline-primary">Lihat Turnamen</a>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-header bg-grad-biru d-flex justify-content-between flex-wrap">
            <span><i class="bi bi-bar-chart-fill me-2"></i><?= e($trn['nama_turnamen']) ?> &middot; <?= e($trn['nama_game']) ?></span>
            <span><?= badge_status_turnamen($trn['status']) ?> Hadiah <?= rupiah($trn['total_hadiah']) ?></span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle text-center">
                    <thead><tr><th>#</th><th class="text-start">Tim</th><th>Main</th><th>M</th><th>S</th><th>K</th><th>GM</th><th>GK</th><th>Selisih</th><th>Poin</th></tr></thead>
                    <tbody>
                    <?php if (!$klasemen): ?><tr><td colspan="10" class="text-muted py-4">Belum ada tim terdaftar pada turnamen ini.
                        <a href="pendaftaran.php?id_turnamen=<?= $idT ?>">Daftarkan tim</a></td></tr><?php endif; ?>
                    <?php foreach ($klasemen as $i => $k): ?>
                        <tr class="<?= $i === 0 && $k['main'] > 0 ? 'table-warning' : '' ?>">
                            <td><?= $i + 1 ?></td>
                            <td class="text-start fw-semibold">
                                <?php if ($k['logo']): ?><img src="<?= e(logo_url($k['logo'])) ?>" class="logo-thumb me-2" alt=""><?php else: ?><span class="logo-kosong me-2"><i class="bi bi-shield-fill"></i></span><?php endif; ?>
                                <?= e($k['nama_tim']) ?>
                            </td>
                            <td><?= $k['main'] ?></td><td><?= $k['menang'] ?></td><td><?= $k['seri'] ?></td><td><?= $k['kalah'] ?></td>
                            <td><?= $k['gm'] ?></td><td><?= $k['gk'] ?></td><td><?= $k['gm'] - $k['gk'] ?></td>
                            <td><span class="badge bg-primary fs-6"><?= $k['poin'] ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <small class="text-muted">M = Menang, S = Seri, K = Kalah, GM/GK = skor masuk/kebobolan. Menang 3 poin, seri 1 poin.</small>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header bg-grad-merah"><i class="bi bi-joystick me-2"></i>Hasil &amp; Jadwal Pertandingan</div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead><tr><th>Waktu</th><th>Babak</th><th class="text-end">Tim A</th><th class="text-center">Skor</th><th>Tim B</th></tr></thead>
                    <tbody>
                    <?php if (!$hasil): ?><tr><td colspan="5" class="text-center text-muted py-3">Belum ada pertandingan dijadwalkan.
                        <?php if (is_admin()): ?><a href="pertandingan.php?id_turnamen=<?= $idT ?>">Jadwalkan pertandingan</a><?php endif; ?></td></tr><?php endif; ?>
                    <?php foreach ($hasil as $m): ?>
                        <tr>
                            <td class="small"><?= tgl_waktu($m['waktu_main']) ?></td>
                            <td><?= e($m['babak'] ?: '-') ?></td>
                            <td class="text-end fw-semibold <?= (int)$m['id_tim_menang'] === (int)$m['id_tim_a'] ? 'text-success' : '' ?>"><?= e($m['tim_a']) ?></td>
                            <td class="text-center"><span class="badge bg-dark"><?= (int)$m['skor_a'] ?> - <?= (int)$m['skor_b'] ?></span></td>
                            <td class="fw-semibold <?= (int)$m['id_tim_menang'] === (int)$m['id_tim_b'] ? 'text-success' : '' ?>"><?= e($m['tim_b']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>
<?php include 'includes/footer.php'; ?>
