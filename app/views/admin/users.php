<?php require_once __DIR__ . '/../../../config/constants.php'; ?>
<?php require_once __DIR__ . '/../../../app/middleware/Auth.php'; ?>
<?php $pageTitle = 'User Management'; ?>
<?php require __DIR__ . '/../layouts/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0"><i class="bi bi-people me-2"></i>User Management</h4>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Registered</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= htmlspecialchars($user['name']) ?></td>
                    <td><?= htmlspecialchars($user['email']) ?></td>
                    <td>
                        <span class="badge bg-<?= $user['role'] === ROLE_ADMIN ? 'primary' : 'secondary' ?>">
                            <?= htmlspecialchars($user['role']) ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge bg-<?= $user['approved'] ? 'success' : 'warning text-dark' ?>">
                            <?= $user['approved'] ? 'Approved' : 'Pending' ?>
                        </span>
                    </td>
                    <td><?= date('Y-m-d', strtotime($user['created_at'])) ?></td>
                    <td class="text-end">
                        <?php if (!$user['approved']): ?>
                        <form method="post" action="<?= BASE_URL ?>/admin/users/approve" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
                            <input type="hidden" name="id" value="<?= $user['id'] ?>">
                            <button class="btn btn-sm btn-success"><i class="bi bi-check2"></i> Approve</button>
                        </form>
                        <?php else: ?>
                        <form method="post" action="<?= BASE_URL ?>/admin/users/revoke" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
                            <input type="hidden" name="id" value="<?= $user['id'] ?>">
                            <button class="btn btn-sm btn-warning"><i class="bi bi-slash-circle"></i> Revoke</button>
                        </form>
                        <?php endif; ?>
                        <form method="post" action="<?= BASE_URL ?>/admin/users/delete" class="d-inline"
                              onsubmit="return confirm('Delete this user?')">
                            <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
                            <input type="hidden" name="id" value="<?= $user['id'] ?>">
                            <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
