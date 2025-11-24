<?php

use App\Database;
use App\Auth;

$conn = Database::connection();
$canManage = Auth::canManageContent();
$uploadDir = upload_dir('avatars');

// ============================================
// UNIT MANAGEMENT (Admin only)
// ============================================
if ($canManage && is_post() && isset($_POST['unit_action'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        die('Token CSRF inválido.');
    }

    $unitId = $_POST['unit_id'] ?? null;
    $name = trim($_POST['name'] ?? '');
    $parentId = $_POST['parent_id'] ?: null;

    if ($name) {
        if ($unitId) {
            // Update unit
            $stmt = $conn->prepare('UPDATE org_units SET name = :name, parent_id = :parent_id WHERE id = :id');
            $stmt->execute(['name' => $name, 'parent_id' => $parentId, 'id' => $unitId]);
            $message = 'Departamento actualizado.';
        } else {
            // Create unit
            $stmt = $conn->prepare('INSERT INTO org_units (name, parent_id) VALUES (:name, :parent_id)');
            $stmt->execute(['name' => $name, 'parent_id' => $parentId]);
            $message = 'Departamento creado.';
        }
    } else {
        $error = 'El nombre del departamento es obligatorio.';
    }
}

// Delete unit
if ($canManage && isset($_GET['action'], $_GET['unit_id']) && $_GET['action'] === 'delete_unit' && verify_csrf($_GET['token'] ?? '')) {
    // Set unit_id to NULL for all members in this unit
    $stmt = $conn->prepare('UPDATE org_members SET unit_id = NULL WHERE unit_id = :unit_id');
    $stmt->execute(['unit_id' => $_GET['unit_id']]);

    // Delete the unit
    $stmt = $conn->prepare('DELETE FROM org_units WHERE id = :id');
    $stmt->execute(['id' => $_GET['unit_id']]);
    $message = 'Departamento eliminado. Los miembros quedaron sin departamento asignado.';
}

// Edit unit
if ($canManage && isset($_GET['action'], $_GET['unit_id']) && $_GET['action'] === 'edit_unit') {
    $stmt = $conn->prepare('SELECT * FROM org_units WHERE id = :id');
    $stmt->execute(['id' => $_GET['unit_id']]);
    $editingUnit = $stmt->fetch();
}

// ============================================
// MEMBER MANAGEMENT
// ============================================
if ($canManage && is_post() && isset($_POST['member_action'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        die('Token CSRF inválido.');
    }

    $memberId = $_POST['member_id'] ?? null;
    $payload = [
        'name' => trim($_POST['name'] ?? ''),
        'job_title' => trim($_POST['job_title'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'unit_id' => $_POST['unit_id'] ?: null,
        'manager_id' => $_POST['manager_id'] ?: null,
    ];

    $photo = null;
    $removePhoto = isset($_POST['remove_photo']);

    // Handle photo upload
    if (!empty($_FILES['photo']['name'])) {
        $maxSize = 1 * 1024 * 1024; // 1MB
        if ($_FILES['photo']['size'] > $maxSize) {
            $error = 'La foto no debe superar 1MB.';
        } else {
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
            $type = mime_content_type($_FILES['photo']['tmp_name']);
            if (isset($allowed[$type])) {
                $filename = uniqid('avatar_') . '.' . $allowed[$type];
                $destination = $uploadDir . DIRECTORY_SEPARATOR . $filename;
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $destination)) {
                    $photo = 'uploads/avatars/' . $filename;

                    // Delete old photo if updating
                    if ($memberId) {
                        $stmt = $conn->prepare('SELECT photo_path FROM org_members WHERE id = :id');
                        $stmt->execute(['id' => $memberId]);
                        if ($row = $stmt->fetch()) {
                            delete_public_file($row['photo_path']);
                        }
                    }
                }
            } else {
                $error = 'Solo se permiten imágenes JPG o PNG.';
            }
        }
    }

    if ($payload['name'] && !isset($error)) {
        if ($memberId) {
            // Update member
            $payload['id'] = $memberId;

            // Handle photo removal
            if ($removePhoto && !$photo) {
                $stmt = $conn->prepare('SELECT photo_path FROM org_members WHERE id = :id');
                $stmt->execute(['id' => $memberId]);
                if ($row = $stmt->fetch()) {
                    delete_public_file($row['photo_path']);
                }
                $payload['photo_path'] = null;
            } elseif ($photo) {
                $payload['photo_path'] = $photo;
            } else {
                // Keep existing photo - don't update photo_path
                $stmt = $conn->prepare('UPDATE org_members SET name = :name, job_title = :job_title, email = :email, unit_id = :unit_id, manager_id = :manager_id WHERE id = :id');
                $stmt->execute($payload);
                $message = 'Colaborador actualizado.';
                goto skip_photo_update;
            }

            $stmt = $conn->prepare('UPDATE org_members SET name = :name, job_title = :job_title, email = :email, unit_id = :unit_id, manager_id = :manager_id, photo_path = :photo_path WHERE id = :id');
            $stmt->execute($payload);
            $message = 'Colaborador actualizado.';
            skip_photo_update:
        } else {
            // Create member
            $stmt = $conn->prepare('INSERT INTO org_members (name, job_title, email, unit_id, manager_id, photo_path) VALUES (:name, :job_title, :email, :unit_id, :manager_id, :photo_path)');
            $stmt->execute($payload + ['photo_path' => $photo]);
            $message = 'Colaborador agregado.';
        }
    } elseif (!isset($error)) {
        $error = 'El nombre del colaborador es obligatorio.';
    }
}

// Delete member
if ($canManage && isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'delete_member' && verify_csrf($_GET['token'] ?? '')) {
    // Get member info
    $stmt = $conn->prepare('SELECT photo_path, manager_id FROM org_members WHERE id = :id');
    $stmt->execute(['id' => $_GET['id']]);
    $memberToDelete = $stmt->fetch();

    if ($memberToDelete) {
        // Reassign subordinates to this member's manager
        $newManagerId = $memberToDelete['manager_id'];
        $stmt = $conn->prepare('UPDATE org_members SET manager_id = :new_manager_id WHERE manager_id = :deleted_member_id');
        $stmt->execute(['new_manager_id' => $newManagerId, 'deleted_member_id' => $_GET['id']]);

        // Delete photo
        delete_public_file($memberToDelete['photo_path']);

        // Delete member
        $stmt = $conn->prepare('DELETE FROM org_members WHERE id = :id');
        $stmt->execute(['id' => $_GET['id']]);
        $message = 'Colaborador eliminado. Sus subordinados fueron reasignados.';
    }
}

// Edit member
if ($canManage && isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'edit_member') {
    $stmt = $conn->prepare('SELECT * FROM org_members WHERE id = :id');
    $stmt->execute(['id' => $_GET['id']]);
    $editingMember = $stmt->fetch();
}

// ============================================
// FETCH DATA
// ============================================
$units = $conn->query('SELECT * FROM org_units ORDER BY name ASC')->fetchAll();
$members = $conn->query('SELECT * FROM org_members')->fetchAll();

$membersByManager = [];
foreach ($members as $member) {
    $manager = $member['manager_id'] ?: 'root';
    $membersByManager[$manager][] = $member;
}

function renderTree($membersByManager, $managerId = 'root', $canManage = false)
{
    if (empty($membersByManager[$managerId])) {
        return;
    }
    echo '<div class="organigram-children">';
    foreach ($membersByManager[$managerId] as $member) {
        echo '<div>';
        echo '<div class="organigram-node">';
        if (!empty($member['photo_path'])) {
            echo '<img src="' . htmlspecialchars(base_url($member['photo_path'])) . '" class="organigram-photo" alt="Foto">';
        }
        echo '<h6 class="mb-1">' . htmlspecialchars($member['name']) . '</h6>';
        echo '<p class="mb-1 text-muted">' . htmlspecialchars($member['job_title']) . '</p>';
        if (!empty($member['email'])) {
            echo '<a href="mailto:' . htmlspecialchars($member['email']) . '" class="small">' . htmlspecialchars($member['email']) . '</a>';
        }
        if ($canManage) {
            echo '<div class="mt-2 d-flex gap-1 justify-content-center">';
            echo '<a href="?module=organigram&action=edit_member&id=' . $member['id'] . '" class="btn btn-sm btn-outline-primary">Editar</a>';
            echo '<a href="?module=organigram&action=delete_member&id=' . $member['id'] . '&token=' . csrf_token() . '" class="btn btn-sm btn-outline-danger" onclick="return confirm(\'¿Eliminar colaborador?\');">Eliminar</a>';
            echo '</div>';
        }
        echo '</div>';
        renderTree($membersByManager, $member['id'], $canManage);
        echo '</div>';
    }
    echo '</div>';
}

$topMembers = $membersByManager['root'] ?? [];
?>
<div class="row g-4">
    <?php if ($canManage): ?>
        <!-- ADMIN VIEW: Management Panel -->
        <div class="col-12 col-xl-4">
            <div class="module-card">
                <h2 class="h4 mb-3">Organigrama</h2>
                <p class="text-muted">Define las jerarquías, responsables y equipos por departamento.</p>
                <?php if (!empty($message)): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <!-- Unit Management -->
                <h5 class="mt-3">Gestión de Departamentos</h5>
                <form method="post" class="vstack gap-3 mb-4">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                    <input type="hidden" name="unit_action" value="1">
                    <input type="hidden" name="unit_id" value="<?php echo $editingUnit['id'] ?? ''; ?>">

                    <div>
                        <label class="form-label">Nombre</label>
                        <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($editingUnit['name'] ?? ''); ?>" required>
                    </div>

                    <div>
                        <label class="form-label">Depende de</label>
                        <select name="parent_id" class="form-select">
                            <option value="">Nivel raíz</option>
                            <?php foreach ($units as $unit): ?>
                                <?php if (!isset($editingUnit) || $unit['id'] != $editingUnit['id']): ?>
                                    <option value="<?php echo $unit['id']; ?>" <?php echo (($editingUnit['parent_id'] ?? '') == $unit['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($unit['name']); ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="d-flex justify-content-between">
                        <?php if (isset($editingUnit)): ?>
                            <a href="?module=organigram" class="btn btn-outline-secondary">Cancelar</a>
                        <?php endif; ?>
                        <button class="btn btn-outline-primary btn-neumorphic" type="submit">
                            <?php echo isset($editingUnit) ? 'Actualizar' : 'Crear'; ?> departamento
                        </button>
                    </div>
                </form>

                <!-- List of Units -->
                <?php if (!empty($units)): ?>
                    <div class="mb-4">
                        <h6 class="mb-2">Departamentos existentes</h6>
                        <div class="list-group">
                            <?php foreach ($units as $unit): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><?php echo htmlspecialchars($unit['name']); ?></span>
                                    <div class="btn-group btn-group-sm">
                                        <a href="?module=organigram&action=edit_unit&unit_id=<?php echo $unit['id']; ?>" class="btn btn-outline-primary btn-sm">Editar</a>
                                        <a href="?module=organigram&action=delete_unit&unit_id=<?php echo $unit['id']; ?>&token=<?php echo csrf_token(); ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('¿Eliminar departamento? Los miembros quedarán sin departamento.');">Eliminar</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <hr>

                <!-- Member Management -->
                <h5 class="mt-4">Gestión de Colaboradores</h5>
                <form method="post" enctype="multipart/form-data" class="vstack gap-3">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                    <input type="hidden" name="member_action" value="1">
                    <input type="hidden" name="member_id" value="<?php echo $editingMember['id'] ?? ''; ?>">

                    <div>
                        <label class="form-label">Nombre</label>
                        <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($editingMember['name'] ?? ''); ?>" required>
                    </div>

                    <div>
                        <label class="form-label">Puesto</label>
                        <input type="text" name="job_title" class="form-control" value="<?php echo htmlspecialchars($editingMember['job_title'] ?? ''); ?>" required>
                    </div>

                    <div>
                        <label class="form-label">Correo</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($editingMember['email'] ?? ''); ?>">
                    </div>

                    <div>
                        <label class="form-label">Departamento</label>
                        <select name="unit_id" class="form-select">
                            <option value="">Sin departamento</option>
                            <?php foreach ($units as $unit): ?>
                                <option value="<?php echo $unit['id']; ?>" <?php echo (($editingMember['unit_id'] ?? '') == $unit['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($unit['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="form-label">Jefe directo</label>
                        <select name="manager_id" class="form-select">
                            <option value="">Ninguno</option>
                            <?php foreach ($members as $member): ?>
                                <?php if (!isset($editingMember) || $member['id'] != $editingMember['id']): ?>
                                    <option value="<?php echo $member['id']; ?>" <?php echo (($editingMember['manager_id'] ?? '') == $member['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($member['name']); ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="form-label">Fotografía</label>
                        <?php if (isset($editingMember) && !empty($editingMember['photo_path'])): ?>
                            <div class="mb-2">
                                <img src="<?php echo htmlspecialchars(base_url($editingMember['photo_path'])); ?>" alt="Foto actual" class="img-thumbnail" style="max-width: 100px;">
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="remove_photo" id="remove_photo">
                                    <label class="form-check-label" for="remove_photo">
                                        Eliminar foto actual
                                    </label>
                                </div>
                            </div>
                        <?php endif; ?>
                        <input type="file" name="photo" class="form-control" accept="image/*">
                        <small class="text-muted">JPG o PNG, máximo 1MB</small>
                    </div>

                    <div class="d-flex justify-content-between">
                        <?php if (isset($editingMember)): ?>
                            <a href="?module=organigram" class="btn btn-outline-secondary">Cancelar</a>
                        <?php endif; ?>
                        <button class="btn btn-primary btn-neumorphic" type="submit">
                            <?php echo isset($editingMember) ? 'Actualizar' : 'Agregar'; ?> colaborador
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <div class="col-12 col-xl-8">
        <?php else: ?>
            <!-- END USER VIEW: Full Width Chart -->
            <div class="col-12">
            <?php endif; ?>
            <div class="module-card">
                <h5 class="mb-4">Estructura organizacional</h5>
                <?php if (!empty($topMembers)): ?>
                    <div class="d-flex justify-content-center">
                        <?php foreach ($topMembers as $rootMember): ?>
                            <div class="text-center">
                                <div class="organigram-node">
                                    <?php if (!empty($rootMember['photo_path'])): ?>
                                        <img src="<?php echo htmlspecialchars(base_url($rootMember['photo_path'])); ?>" class="organigram-photo" alt="Foto">
                                    <?php endif; ?>
                                    <h5 class="mb-1"><?php echo htmlspecialchars($rootMember['name']); ?></h5>
                                    <p class="text-muted mb-1"><?php echo htmlspecialchars($rootMember['job_title']); ?></p>
                                    <?php if (!empty($rootMember['email'])): ?>
                                        <a href="mailto:<?php echo htmlspecialchars($rootMember['email']); ?>" class="small"><?php echo htmlspecialchars($rootMember['email']); ?></a>
                                    <?php endif; ?>
                                    <?php if ($canManage): ?>
                                        <div class="mt-2 d-flex gap-1 justify-content-center">
                                            <a href="?module=organigram&action=edit_member&id=<?php echo $rootMember['id']; ?>" class="btn btn-sm btn-outline-primary">Editar</a>
                                            <a href="?module=organigram&action=delete_member&id=<?php echo $rootMember['id']; ?>&token=<?php echo csrf_token(); ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Eliminar colaborador?');">Eliminar</a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php renderTree($membersByManager, $rootMember['id'], $canManage); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted py-5">
                        <?php if ($canManage): ?>
                            Agrega colaboradores para visualizar el organigrama.
                        <?php else: ?>
                            El organigrama aún no ha sido configurado.
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            </div>
        </div>