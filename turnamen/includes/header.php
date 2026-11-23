<?php
// Variabel yang diharapkan: $judul (judul halaman)
$u = $_SESSION['user'];
$hal = basename($_SERVER['PHP_SELF']);
function nav_item($file, $icon, $label)
{
    global $hal;
    $aktif = ($hal === $file) ? 'active' : '';
    echo '<li class="nav-item"><a class="nav-link ' . $aktif . '" href="' . $file . '">'
        . '<i class="nav-icon bi bi-' . $icon . '"></i><p>' . $label . '</p></a></li>';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($judul ?? 'Turnamen') ?> - Sistem Turnamen Game</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@300;400;600;700&display=fallback">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.10.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
<div class="app-wrapper">

    <nav class="app-header navbar navbar-expand bg-body">
        <div class="container-fluid">
            <ul class="navbar-nav">
                <li class="nav-item"><a class="nav-link" data-lte-toggle="sidebar" href="#" role="button"><i class="bi bi-list"></i></a></li>
                <li class="nav-item d-none d-md-block"><a href="index.php" class="nav-link">Beranda</a></li>
            </ul>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="#" data-lte-toggle="fullscreen">
                        <i data-lte-icon="maximize" class="bi bi-arrows-fullscreen"></i>
                        <i data-lte-icon="minimize" class="bi bi-fullscreen-exit" style="display:none"></i>
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a href="#" class="nav-link dropdown-toggle fw-semibold" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle me-1"></i><?= e($u['nickname']) ?>
                        <span class="badge text-bg-<?= $u['peran'] === 'admin' ? 'danger' : 'primary' ?>"><?= e($u['peran']) ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li class="px-3 py-2 small text-muted"><?= e($u['nama_lengkap']) ?><br><?= e($u['email']) ?></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="profil.php"><i class="bi bi-gear me-2"></i>Profil Saya</a></li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Keluar</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </nav>

    <aside class="app-sidebar shadow" data-bs-theme="dark">
        <div class="sidebar-brand">
            <a href="index.php" class="brand-link">
                <i class="bi bi-controller brand-icon"></i>
                <span class="brand-text fw-semibold">Turnamen Game</span>
            </a>
        </div>
        <div class="sidebar-wrapper">
            <nav class="mt-2">
                <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="navigation" aria-label="Menu utama" data-accordion="false">
                    <li class="nav-header">MENU UTAMA</li>
                    <?php
                    nav_item('index.php', 'speedometer2', 'Dashboard');
                    nav_item('turnamen.php', 'trophy-fill', 'Turnamen');
                    nav_item('tim.php', 'people-fill', 'Tim');
                    nav_item('pendaftaran.php', 'clipboard-check-fill', 'Pendaftaran');
                    nav_item('pertandingan.php', 'joystick', 'Pertandingan');
                    nav_item('klasemen.php', 'bar-chart-fill', 'Klasemen');
                    if (is_admin()) {
                        echo '<li class="nav-header">ADMINISTRASI</li>';
                        nav_item('game.php', 'controller', 'Data Game');
                        nav_item('pemain.php', 'person-badge-fill', 'Data Pemain');
                    }
                    echo '<li class="nav-header">AKUN</li>';
                    nav_item('profil.php', 'person-gear', 'Profil Saya');
                    ?>
                    <li class="nav-item"><a class="nav-link" href="logout.php"><i class="nav-icon bi bi-box-arrow-right text-danger"></i><p>Keluar</p></a></li>
                </ul>
            </nav>
        </div>
    </aside>

    <main class="app-main">
        <div class="app-content-header">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-sm-6"><h3 class="mb-0 page-title"><?= e($judul ?? '') ?></h3></div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-end">
                            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                            <li class="breadcrumb-item active"><?= e($judul ?? '') ?></li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <div class="app-content">
            <div class="container-fluid">
                <?php
                if (!empty($_SESSION['flash'])) {
                    foreach ($_SESSION['flash'] as [$tipe, $pesan]) {
                        echo '<div class="alert alert-' . e($tipe) . ' alert-dismissible fade show" role="alert">' . e($pesan)
                            . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
                    }
                    unset($_SESSION['flash']);
                }
                ?>
