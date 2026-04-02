<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' – ' : '' ?><?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/style.css">
</head>
<body>
<?php if (Auth::isLoggedIn()): ?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="<?= BASE_URL ?>/dashboard">
            <i class="bi bi-thermometer-snow me-2"></i><?= APP_NAME ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE_URL ?>/dashboard">
                        <i class="bi bi-speedometer2 me-1"></i>Dashboard
                    </a>
                </li>
                <?php if (Auth::isAdmin()): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-gear me-1"></i>Admin
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/users"><i class="bi bi-people me-2"></i>Users</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/devices"><i class="bi bi-cpu me-2"></i>Devices</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/alerts"><i class="bi bi-bell me-2"></i>Alerts</a></li>
                    </ul>
                </li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <span class="navbar-text me-3">
                        <i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($_SESSION['user_name'] ?? '') ?>
                    </span>
                </li>
                <li class="nav-item">
                    <form method="post" action="<?= BASE_URL ?>/logout" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
                        <button type="submit" class="btn btn-outline-light btn-sm">
                            <i class="bi bi-box-arrow-right me-1"></i>Logout
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</nav>
<?php endif; ?>
<main class="<?= Auth::isLoggedIn() ? 'container-fluid py-4' : '' ?>">
<?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($_SESSION['flash_success']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>
