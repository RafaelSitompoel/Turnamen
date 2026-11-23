<?php
require_once 'config.php';
login_required();

$statusOpsi = ['pendaftaran', 'berlangsung', 'selesai'];
$edit = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    admin_required();
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'hapus') {
        $pdo->prepare('DELETE FROM turnamen WHERE id_turnamen = ?')->execute([(int)($_POST['id'] ?? 0)]);
        flash('success', 'Turnamen beserta pendaftaran dan pertandingannya berhasil dihapus.');
        redirect('turnamen.php');
    }

    if ($aksi === 'simpan') {
        $id = (int)($_POST['id_turnamen'] ?? 0);
        $f = [
            'id_turnamen' => $id,
            'id_game' => (int)($_POST['id_game'] ?? 0),
            'nama_turnamen' => trim($_POST['nama_turnamen'] ?? ''),
            'tgl_mulai' => $_POST['tgl_mulai'] ?? '',
            'tgl_selesai' => $_POST['tgl_selesai'] ?? '',
            'total_hadiah' => $_POST['total_hadiah'] ?? '0',
            'status' => $_POST['status'] ?? 'pendaftaran',
        ];
        $err = [];
        $cek = $pdo->prepare('SELECT COUNT(*) FROM game WHERE id_game = ?');
        $cek->execute([$f['id_game']]);
        if (!$cek->fetchColumn()) $err[] = 'Pilih game yang valid.';
        if ($f['nama_turnamen'] === '' || mb_strlen($f['nama_turnamen']) > 150) $err[] = 'Nama turnamen wajib diisi (maks 150 karakter).';
        $d1 = DateTime::createFromFormat('Y-m-d', $f['tgl_mulai']);
        $d2 = DateTime::createFromFormat('Y-m-d', $f['tgl_selesai']);
        if (!$d1 || !$d2) $err[] = 'Tanggal tidak valid.';
        elseif ($d2 < $d1) $err[] = 'Tanggal selesai tidak boleh sebelum tanggal mulai.';
        if (!is_numeric($f['total_hadiah']) || $f['total_hadiah'] < 0 || $f['total_hadiah'] > 9999999999.99) $err[] = 'Total hadiah tidak valid.';
        if (!in_array($f['status'], $statusOpsi, true)) $err[] = 'Status tidak valid.';

        if (!$err) {
            $vals = [$f['id_game'], $f['nama_turnamen'], $f['tgl_mulai'], $f['tgl_selesai'], (float)$f['total_hadiah'], $f['status']];
            if ($id === 0) {
                $pdo->prepare('INSERT INTO turnamen (id_game, nama_turnamen, tgl_mulai, tgl_selesai, total_hadiah, status) VALUES (?,?,?,?,?,?)')->execute($vals);
                flash('success', 'Turnamen berhasil dibuat.');
            } else {
                $vals[] = $id;
                $pdo->prepare('UPDATE turnamen SET id_game=?, nama_turnamen=?, tgl_mulai=?, tgl_selesai=?, total_hadiah=?, status=? WHERE id_turnamen=?')->execute($vals);
                flash('success', 'Turnamen berhasil diperbarui.');
            }
            redirect('turnamen.php');
        }
        foreach ($err as $m) flash('danger', $m);
        $edit = $f;
    }
}

if (!$edit && is_admin() && isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM turnamen WHERE id_turnamen = ?');
    $st->execute([(int)$_GET['edit']]);
    $edit = $st->fetch() ?: null;
}
$f = $edit ?? ['id_turnamen' => 0, 'id_game' => 0, 'nama_turnamen' => '', 'tgl_mulai' => '', 'tgl_selesai' => '', 'total_hadiah' => '0', 'status' => 'pendaftaran'];

$games = $pdo->query('SELECT id_game, nama_game, platform FROM game ORDER BY nama_game')->fetchAll();

$filter = $_GET['status'] ?? '';
$sql = 'SELECT t.*, g.nama_game, g.platform,
        (SELECT COUNT(*) FROM pendaftaran p WHERE p.id_turnamen = t.id_turnamen) AS jml_tim
    FROM turnamen t JOIN game g ON g.id_game = t.id_game';
$par = [];
if (in_array($filter, $statusOpsi, true)) { $sql .= ' WHERE t.status = ?'; $par[] = $filter; }
$st = $pdo->prepare($sql . ' ORDER BY t.tgl_mulai DESC');
$st->execute($par);
$rows = $st->fetchAll();

$judul = 'Turnamen';
include 'includes/header.php';
?>
<div class="row g-3">
    <?php if (is_admin()): ?>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-grad-oranye text-dark"><i class="bi bi-trophy-fill me-2"></i><?= $f['id_turnamen'] ? 'Ubah Turnamen' : 'Buat Turnamen' ?></div>
            <div class="card-body">
                <?php if (!$games): ?>
                    <div class="alert alert-warning mb-0">Belum ada game. Buka <a href="game.php" class="alert-link">Data Game</a>, lalu tambah manual atau tekan <b>Tambah Semua Game Populer</b>.</div>
                <?php else: ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="aksi" value="simpan">
                    <input type="hidden" name="id_turnamen" value="<?= (int)$f['id_turnamen'] ?>">
                    <div class="mb-2"><label class="form-label">Nama Turnamen</label>
                        <input type="text" name="nama_turnamen" class="form-control" maxlength="150" value="<?= e($f['nama_turnamen']) ?>" required></div>
                    <div class="mb-2"><label class="form-label">Game</label>
                        <select name="id_game" class="form-select" required>
                            <option value="">-- Pilih game --</option>
                            <?php foreach ($games as $g): ?>
                                <option value="<?= (int)$g['id_game'] ?>" <?= (int)$f['id_game'] === (int)$g['id_game'] ? 'selected' : '' ?>><?= e($g['nama_game']) ?> (<?= e($g['platform']) ?>)</option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="row g-2 mb-2">
                        <div class="col-6"><label class="form-label">Tanggal Mulai</label>
                            <input type="date" name="tgl_mulai" class="form-control" value="<?= e($f['tgl_mulai']) ?>" required></div>
                        <div class="col-6"><label class="form-label">Tanggal Selesai</label>
                            <input type="date" name="tgl_selesai" class="form-control" value="<?= e($f['tgl_selesai']) ?>" required></div>
                    </div>
                    <div class="mb-2"><label class="form-label">Total Hadiah (Rp)</label>
                        <input type="number" name="total_hadiah" min="0" step="1000" class="form-control" value="<?= e($f['total_hadiah']) ?>" required></div>
                    <div class="mb-3"><label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <?php foreach ($statusOpsi as $s): ?>
                                <option value="<?= $s ?>" <?= $f['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <button class="btn btn-grad"><i class="bi bi-save me-1"></i>Simpan</button>
                    <?php if ($f['id_turnamen']): ?><a href="turnamen.php" class="btn btn-secondary">Batal</a><?php endif; ?>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <div class="<?= is_admin() ? 'col-lg-8' : 'col-12' ?>">
        <div class="card"><div class="card-body">
            <div class="mb-3 d-flex gap-2 flex-wrap">
                <a href="turnamen.php" class="btn btn-sm <?= $filter === '' ? 'btn-primary' : 'btn-outline-primary' ?>">Semua</a>
                <?php foreach ($statusOpsi as $s): ?>
                    <a href="turnamen.php?status=<?= $s ?>" class="btn btn-sm <?= $filter === $s ? 'btn-primary' : 'btn-outline-primary' ?>"><?= ucfirst($s) ?></a>
                <?php endforeach; ?>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead><tr><th>Turnamen</th><th>Game</th><th>Jadwal</th><th>Hadiah</th><th>Tim</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
                    <tbody>
                    <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-4">Belum ada turnamen.</td></tr><?php endif; ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($r['nama_turnamen']) ?></td>
                            <td><?= e($r['nama_game']) ?><br><small class="text-muted"><?= e($r['platform']) ?></small></td>
                            <td class="small"><?= tgl($r['tgl_mulai']) ?><br>s/d <?= tgl($r['tgl_selesai']) ?></td>
                            <td><?= rupiah($r['total_hadiah']) ?></td>
                            <td><span class="badge bg-success"><?= (int)$r['jml_tim'] ?></span></td>
                            <td><?= badge_status_turnamen($r['status']) ?></td>
                            <td class="text-end text-nowrap">
                                <?php if ($r['status'] === 'pendaftaran'): ?>
                                    <a href="pendaftaran.php?id_turnamen=<?= (int)$r['id_turnamen'] ?>" class="btn btn-sm btn-success" title="Daftar / lihat peserta"><i class="bi bi-clipboard-check"></i></a>
                                <?php endif; ?>
                                <a href="pertandingan.php?id_turnamen=<?= (int)$r['id_turnamen'] ?>" class="btn btn-sm btn-danger" title="Pertandingan"><i class="bi bi-joystick"></i></a>
                                <a href="klasemen.php?id_turnamen=<?= (int)$r['id_turnamen'] ?>" class="btn btn-sm btn-info text-white" title="Klasemen"><i class="bi bi-bar-chart"></i></a>
                                <?php if (is_admin()): ?>
                                    <a href="turnamen.php?edit=<?= (int)$r['id_turnamen'] ?>" class="btn btn-sm btn-warning"><i class="bi bi-pencil-square"></i></a>
                                    <?= btn_hapus($r['id_turnamen']) ?>
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
