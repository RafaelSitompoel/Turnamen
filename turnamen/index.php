<?php
require_once 'config.php';
login_required();

$stat = [
    ['Pemain', 'SELECT COUNT(*) FROM pemain', 'person-badge-fill', 'grad-ungu', is_admin() ? 'pemain.php' : 'tim.php'],
    ['Tim', 'SELECT COUNT(*) FROM tim', 'people-fill', 'grad-hijau', 'tim.php'],
    ['Game', 'SELECT COUNT(*) FROM game', 'controller', 'grad-biru', is_admin() ? 'game.php' : 'turnamen.php'],
    ['Turnamen', 'SELECT COUNT(*) FROM turnamen', 'trophy-fill', 'grad-oranye', 'turnamen.php'],
    ['Pertandingan', 'SELECT COUNT(*) FROM pertandingan', 'joystick', 'grad-merah', 'pertandingan.php'],
];

$turnamenAktif = $pdo->query("SELECT t.*, g.nama_game FROM turnamen t JOIN game g ON g.id_game=t.id_game
    WHERE t.status IN ('pendaftaran','berlangsung') ORDER BY t.tgl_mulai ASC LIMIT 6")->fetchAll();

$mendatang = $pdo->query("SELECT p.*, a.nama_tim AS tim_a, b.nama_tim AS tim_b, t.nama_turnamen
    FROM pertandingan p JOIN tim a ON a.id_tim=p.id_tim_a JOIN tim b ON b.id_tim=p.id_tim_b
    JOIN turnamen t ON t.id_turnamen=p.id_turnamen
    WHERE p.waktu_main >= NOW() ORDER BY p.waktu_main ASC LIMIT 6")->fetchAll();

$timSaya = $pdo->prepare("SELECT t.* FROM tim t WHERE t.id_kapten = ? OR t.id_tim IN (SELECT id_tim FROM anggota_tim WHERE id_pemain = ?) ORDER BY t.nama_tim");
$timSaya->execute([user_id(), user_id()]);
$timSaya = $timSaya->fetchAll();

$adaGame = (int)$pdo->query('SELECT COUNT(*) FROM game')->fetchColumn();
$adaTurnamen = (int)$pdo->query('SELECT COUNT(*) FROM turnamen')->fetchColumn();

$judul = 'Dashboard';
include 'includes/header.php';
?>
<?php if (is_admin() && (!$adaGame || !$adaTurnamen)): ?>
    <div class="alert alert-info">
        <i class="bi bi-lightbulb-fill me-1"></i><b>Langkah awal:</b>
        <?= !$adaGame ? 'tambahkan <a href="game.php" class="alert-link">Game</a>' : '' ?>
        <?= (!$adaGame && !$adaTurnamen) ? ', lalu ' : '' ?>
        <?= !$adaTurnamen ? 'buat <a href="turnamen.php" class="alert-link">Turnamen</a>' : '' ?>.
    </div>
<?php endif; ?>
<div class="row g-3 mb-4">
    <?php foreach ($stat as [$label, $sql, $ikon, $kelas, $link]): ?>
        <div class="col-6 col-md-4 col-xl">
            <div class="stat-card bg-<?= $kelas ?>">
                <i class="bi bi-<?= $ikon ?> ikon"></i>
                <div class="angka"><?= (int)$pdo->query($sql)->fetchColumn() ?></div>
                <div class="mb-1"><?= $label ?></div>
                <a href="<?= $link ?>">Selengkapnya <i class="bi bi-arrow-right-circle"></i></a>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-grad-oranye"><i class="bi bi-trophy-fill me-2"></i>Turnamen Aktif</div>
            <div class="list-group list-group-flush">
                <?php if (!$turnamenAktif): ?>
                    <div class="list-group-item text-muted text-center py-4">Belum ada turnamen aktif.</div>
                <?php endif; ?>
                <?php foreach ($turnamenAktif as $t): ?>
                    <a href="klasemen.php?id_turnamen=<?= (int)$t['id_turnamen'] ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-semibold"><?= e($t['nama_turnamen']) ?></div>
                            <small class="text-muted"><?= e($t['nama_game']) ?> &middot; <?= tgl($t['tgl_mulai']) ?> - <?= tgl($t['tgl_selesai']) ?></small>
                        </div>
                        <?= badge_status_turnamen($t['status']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-grad-merah"><i class="bi bi-calendar-event me-2"></i>Pertandingan Mendatang</div>
            <div class="list-group list-group-flush">
                <?php if (!$mendatang): ?>
                    <div class="list-group-item text-muted text-center py-4">Belum ada jadwal pertandingan.</div>
                <?php endif; ?>
                <?php foreach ($mendatang as $m): ?>
                    <div class="list-group-item">
                        <div class="fw-semibold"><?= e($m['tim_a']) ?> <span class="text-danger">vs</span> <?= e($m['tim_b']) ?></div>
                        <small class="text-muted"><?= e($m['nama_turnamen']) ?> &middot; <?= e($m['babak'] ?: '-') ?> &middot; <?= tgl_waktu($m['waktu_main']) ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-grad-hijau"><i class="bi bi-people-fill me-2"></i>Tim Saya</div>
            <div class="card-body">
                <?php if (!$timSaya): ?>
                    <span class="text-muted">Anda belum tergabung di tim mana pun. <a href="tim.php">Buat tim</a> atau minta kapten menambahkan Anda.</span>
                <?php endif; ?>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($timSaya as $t): ?>
                        <a href="anggota.php?id_tim=<?= (int)$t['id_tim'] ?>" class="btn btn-outline-success">
                            <?= e($t['nama_tim']) ?>
                            <?php if ((int)$t['id_kapten'] === user_id()): ?><span class="badge bg-warning text-dark">Kapten</span><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
