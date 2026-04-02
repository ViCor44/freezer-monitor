<?php require_once __DIR__ . '/../../../config/constants.php'; ?>
<?php require_once __DIR__ . '/../../../app/middleware/Auth.php'; ?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Approval – <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-light d-flex align-items-center min-vh-100">
<div class="container text-center">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <i class="bi bi-hourglass-split display-1 text-warning"></i>
            <h3 class="mt-3">Approval Pending</h3>
            <p class="text-muted">Your account is awaiting admin approval. Please check back later.</p>
            <form method="post" action="<?= BASE_URL ?>/logout">
                <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
                <button class="btn btn-outline-secondary">Logout</button>
            </form>
        </div>
    </div>
</div>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
