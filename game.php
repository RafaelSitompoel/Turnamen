<?php
require_once 'config.php';
admin_required();

$platformOpsi = ['PC', 'Android', 'iOS', 'Mobile (Android & iOS)', 'PlayStation', 'Xbox', 'Nintendo Switch', 'Lintas Platform'];
$edit = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'hapus') {
        try {
            $pdo->prepare('DELETE FROM game WHERE id_game = ?')->execute([(int)($_POST['id'] ?? 0)]);
            flash('success', 'Game berhasil dihapus.');
        } catch (PDOException $ex) {
            flash('danger', 'Game tidak dapat dihapus karena sudah dipakai oleh turnamen.');
        }
        redirect('game.php');
    }

    if ($aksi === 'impor_katalog') {
        $katalog = require __DIR__ . '/includes/katalog_game.php';
        $cek = $pdo->prepare('SELECT COUNT(*) FROM game WHERE nama_game = ? AND platform = ?');
        $ins = $pdo->prepare('INSERT INTO game (nama_game, platform) VALUES (?,?)');
        $baru = 0;
        $pdo->beginTransaction();
        foreach ($katalog as [$nama, $plat]) {
            $cek->execute([$nama, $plat]);
            if (!$cek->fetchColumn()) { $ins->execute([$nama, $plat]); $baru++; }
        }
        $pdo->commit();
        flash($baru ? 'success' : 'info', $baru ? $baru . ' game populer berhasil ditambahkan.' : 'Semua game dalam katalog sudah ada.');
        redirect('game.php');
    }

    if ($aksi === 'simpan') {
        $id = (int)($_POST['id_game'] ?? 0);
        $f = [
            'id_game' => $id,
            'nama_game' => trim($_POST['nama_game'] ?? ''),
            'platform' => trim($_POST['platform'] ?? ''),
        ];
        $err = [];
        if ($f['nama_game'] === '' || mb_strlen($f['nama_game']) > 100) $err[] = 'Nama game wajib diisi (maks 100 karakter).';
        if (!in_array($f['platform'], $platformOpsi, true)) $err[] = 'Pilih platform yang valid.';
        if (!$err) {
            $st = $pdo->prepare('SELECT COUNT(*) FROM game WHERE nama_game = ? AND platform = ? AND id_game <> ?');
            $st->execute([$f['nama_game'], $f['platform'], $id]);
            if ($st->fetchColumn() > 0) $err[] = 'Game dengan platform yang sama sudah ada.';
        }
        if (!$err) {
            if ($id === 0) {
                $pdo->prepare('INSERT INTO game (nama_game, platform) VALUES (?,?)')->execute([$f['nama_game'], $f['platform']]);
                flash('success', 'Game berhasil ditambahkan.');
            } else {
                $pdo->prepare('UPDATE game SET nama_game=?, platform=? WHERE id_game=?')->execute([$f['nama_game'], $f['platform'], $id]);
                flash('success', 'Game berhasil diperbarui.');
            }
            redirect('game.php');
        }
        foreach ($err as $m) flash('danger', $m);
        $edit = $f;
    }
}

if (!$edit && isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM game WHERE id_game = ?');
    $st->execute([(int)$_GET['edit']]);
    $edit = $st->fetch() ?: null;
}
$f = $edit ?? ['id_game' => 0, 'nama_game' => '', 'platform' => ''];

$q = trim($_GET['q'] ?? '');
$st = $pdo->prepare('SELECT g.*, (SELECT COUNT(*) FROM turnamen t WHERE t.id_game = g.id_game) AS jml_turnamen
    FROM game g WHERE g.nama_game LIKE ? OR g.platform LIKE ? ORDER BY g.nama_game');
$st->execute(['%' . $q . '%', '%' . $q . '%']);
$rows = $st->fetchAll();
$jmlKatalog = count(require __DIR__ . '/includes/katalog_game.php');

$judul = 'Data Game';
include 'includes/header.php';
?>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-grad-biru"><i class="bi bi-controller me-2"></i><?= $f['id_game'] ? 'Ubah Game' : 'Tambah Game' ?></div>
            <div class="card-body">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="aksi" value="simpan">
                    <input type="hidden" name="id_game" value="<?= (int)$f['id_game'] ?>">
                    <div class="mb-2"><label class="form-label">Nama Game</label>
                        <input type="text" name="nama_game" class="form-control" maxlength="100" value="<?= e($f['nama_game']) ?>" required></div>
                    <div class="mb-3"><label class="form-label">Platform</label>
                        <select name="platform" class="form-select" required>
                            <option value="">-- Pilih platform --</option>
                            <?php foreach ($platformOpsi as $p): ?>
                                <option <?= $f['platform'] === $p ? 'selected' : '' ?>><?= e($p) ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <button class="btn btn-grad"><i class="bi bi-save me-1"></i>Simpan</button>
                    <?php if ($f['id_game']): ?><a href="game.php" class="btn btn-secondary">Batal</a><?php endif; ?>
                </form>
            </div>
        </div>
        <div class="card mt-3">
            <div class="card-header bg-grad-oranye"><i class="bi bi-stars me-2"></i>Katalog Game Populer</div>
            <div class="card-body">
                <p class="small mb-2">Masukkan <?= $jmlKatalog ?> game esports populer (Mobile Legends, Free Fire, Valorant, PUBG Mobile, Dota 2, EA FC, dll.) sekaligus. Game yang sudah ada tidak digandakan.</p>
                <form method="post" onsubmit="return confirm('Tambahkan katalog game populer?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="aksi" value="impor_katalog">
                    <button class="btn btn-warning w-100"><i class="bi bi-cloud-plus-fill me-1"></i>Tambah Semua Game Populer</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card"><div class="card-body">
            <form class="row g-2 mb-3" method="get">
                <div class="col"><input type="search" name="q" class="form-control" placeholder="Cari nama game atau platform..." value="<?= e($q) ?>"></div>
                <div class="col-auto"><button class="btn btn-primary"><i class="bi bi-search"></i></button></div>
            </form>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead><tr><th>#</th><th>Nama Game</th><th>Platform</th><th>Turnamen</th><th class="text-end">Aksi</th></tr></thead>
                    <tbody>
                    <?php if (!$rows): ?><tr><td colspan="5" class="text-center text-muted py-4">Belum ada data game.</td></tr><?php endif; ?>
                    <?php foreach ($rows as $i => $r): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td class="fw-semibold"><?= e($r['nama_game']) ?></td>
                            <td><span class="badge bg-primary"><?= e($r['platform']) ?></span></td>
                            <td><?= (int)$r['jml_turnamen'] ?></td>
                            <td class="text-end text-nowrap">
                                <a href="game.php?edit=<?= (int)$r['id_game'] ?>" class="btn btn-sm btn-warning"><i class="bi bi-pencil-square"></i></a>
                                <?= btn_hapus($r['id_game']) ?>
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
