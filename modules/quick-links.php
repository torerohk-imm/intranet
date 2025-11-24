<?php

use App\Database;
use App\Auth;

$conn = Database::connection();
$canManage = Auth::canManageContent();

// ============================================
// CATEGORY MANAGEMENT (Admin only)
// ============================================
if ($canManage && is_post() && isset($_POST['category_action'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        die('Token CSRF inválido.');
    }

    $categoryId = $_POST['category_id'] ?? null;
    $categoryPayload = [
        'name' => trim($_POST['category_name'] ?? ''),
        'icon' => trim($_POST['category_icon'] ?? ''),
        'color' => trim($_POST['category_color'] ?? '#3498db'),
        'display_order' => (int)($_POST['display_order'] ?? 0),
    ];

    if ($categoryPayload['name']) {
        if ($categoryId) {
            // Update category
            $categoryPayload['id'] = $categoryId;
            $stmt = $conn->prepare('UPDATE link_categories SET name = :name, icon = :icon, color = :color, display_order = :display_order WHERE id = :id');
            $stmt->execute($categoryPayload);
            $message = 'Categoría actualizada.';
        } else {
            // Create category
            $stmt = $conn->prepare('INSERT INTO link_categories (name, icon, color, display_order, created_by) VALUES (:name, :icon, :color, :display_order, :created_by)');
            $stmt->execute($categoryPayload + ['created_by' => Auth::user()['id']]);
            $message = 'Categoría creada.';
        }
    } else {
        $error = 'El nombre de la categoría es obligatorio.';
    }
}

// Delete category
if ($canManage && isset($_GET['action'], $_GET['cat_id']) && $_GET['action'] === 'delete_category' && verify_csrf($_GET['token'] ?? '')) {
    $stmt = $conn->prepare('DELETE FROM link_categories WHERE id = :id');
    $stmt->execute(['id' => $_GET['cat_id']]);
    $message = 'Categoría eliminada.';
}

// Edit category
if ($canManage && isset($_GET['action'], $_GET['cat_id']) && $_GET['action'] === 'edit_category') {
    $stmt = $conn->prepare('SELECT * FROM link_categories WHERE id = :id');
    $stmt->execute(['id' => $_GET['cat_id']]);
    $editingCategory = $stmt->fetch();
}

// ============================================
// LINK MANAGEMENT
// ============================================
if ($canManage && is_post() && isset($_POST['link_action'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        die('Token CSRF inválido.');
    }

    $id = $_POST['id'] ?? null;
    $payload = [
        'title' => trim($_POST['title'] ?? ''),
        'url' => trim($_POST['url'] ?? ''),
        'target' => in_array($_POST['target'] ?? '_self', ['_self', '_blank'], true) ? $_POST['target'] : '_self',
        'icon' => trim($_POST['icon'] ?? ''),
        'category_id' => !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null,
    ];

    if ($payload['title'] && $payload['url']) {
        if ($id) {
            $payload['id'] = $id;
            $stmt = $conn->prepare('UPDATE quick_links SET title = :title, url = :url, target = :target, icon = :icon, category_id = :category_id WHERE id = :id');
            $stmt->execute($payload);
            $message = 'Enlace actualizado.';
        } else {
            $stmt = $conn->prepare('INSERT INTO quick_links (title, url, target, icon, category_id, created_by) VALUES (:title, :url, :target, :icon, :category_id, :created_by)');
            $stmt->execute($payload + ['created_by' => Auth::user()['id']]);
            $message = 'Enlace creado.';
        }
    } else {
        $error = 'El título y la URL son obligatorios.';
    }
}

// Delete link
if ($canManage && isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'delete' && verify_csrf($_GET['token'] ?? '')) {
    $stmt = $conn->prepare('DELETE FROM quick_links WHERE id = :id');
    $stmt->execute(['id' => $_GET['id']]);
    $message = 'Enlace eliminado.';
}

// Edit link
if ($canManage && isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'edit') {
    $stmt = $conn->prepare('SELECT * FROM quick_links WHERE id = :id');
    $stmt->execute(['id' => $_GET['id']]);
    $editing = $stmt->fetch();
}

// ============================================
// FETCH DATA
// ============================================
$categories = $conn->query('SELECT * FROM link_categories ORDER BY display_order ASC, name ASC')->fetchAll();

// Get selected category filter (for end users)
$selectedCategory = isset($_GET['category']) ? (int)$_GET['category'] : null;

// Fetch links with optional category filter
if ($selectedCategory) {
    $stmt = $conn->prepare('SELECT ql.*, lc.name as category_name, lc.color as category_color 
                           FROM quick_links ql 
                           LEFT JOIN link_categories lc ON ql.category_id = lc.id 
                           WHERE ql.category_id = :category_id
                           ORDER BY ql.created_at DESC');
    $stmt->execute(['category_id' => $selectedCategory]);
    $links = $stmt->fetchAll();
} elseif (isset($_GET['category']) && $_GET['category'] === 'uncategorized') {
    $links = $conn->query('SELECT ql.*, lc.name as category_name, lc.color as category_color 
                          FROM quick_links ql 
                          LEFT JOIN link_categories lc ON ql.category_id = lc.id 
                          WHERE ql.category_id IS NULL
                          ORDER BY ql.created_at DESC')->fetchAll();
} else {
    $links = $conn->query('SELECT ql.*, lc.name as category_name, lc.color as category_color 
                          FROM quick_links ql 
                          LEFT JOIN link_categories lc ON ql.category_id = lc.id 
                          ORDER BY ql.created_at DESC')->fetchAll();
}
?>

<?php if ($canManage): ?>
    <!-- ADMIN VIEW -->
    <div class="row g-4">
        <!-- Category Management -->
        <div class="col-12 col-lg-4">
            <div class="module-card">
                <h2 class="h5 mb-3">Gestión de Categorías</h2>
                <?php if (!empty($message)): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="post" class="vstack gap-3 mb-4">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                    <input type="hidden" name="category_action" value="1">
                    <input type="hidden" name="category_id" value="<?php echo $editingCategory['id'] ?? ''; ?>">

                    <div>
                        <label class="form-label">Nombre de Categoría</label>
                        <input type="text" name="category_name" class="form-control" value="<?php echo htmlspecialchars($editingCategory['name'] ?? ''); ?>" required>
                    </div>

                    <div>
                        <label class="form-label">Icono (opcional)</label>
                        <input type="text" name="category_icon" class="form-control" placeholder="bi bi-folder" value="<?php echo htmlspecialchars($editingCategory['icon'] ?? ''); ?>">
                        <small class="text-muted">Compatible con <a href="https://icons.getbootstrap.com/" target="_blank">Bootstrap Icons</a>.</small>
                    </div>

                    <div>
                        <label class="form-label">Color</label>
                        <input type="color" name="category_color" class="form-control form-control-color" value="<?php echo htmlspecialchars($editingCategory['color'] ?? '#3498db'); ?>">
                    </div>

                    <div>
                        <label class="form-label">Orden de visualización</label>
                        <input type="number" name="display_order" class="form-control" value="<?php echo htmlspecialchars($editingCategory['display_order'] ?? '0'); ?>" min="0">
                    </div>

                    <div class="d-flex justify-content-between">
                        <?php if (isset($editingCategory)): ?>
                            <a href="?module=quick-links" class="btn btn-outline-secondary">Cancelar</a>
                        <?php endif; ?>
                        <button class="btn btn-primary btn-neumorphic" type="submit">
                            <?php echo isset($editingCategory) ? 'Actualizar' : 'Crear'; ?>
                        </button>
                    </div>
                </form>

                <!-- List of categories -->
                <div class="mt-4">
                    <h6 class="mb-3">Categorías existentes</h6>
                    <?php if (empty($categories)): ?>
                        <p class="text-muted small">No hay categorías creadas.</p>
                    <?php else: ?>
                        <div class="vstack gap-2">
                            <?php foreach ($categories as $cat): ?>
                                <div class="d-flex align-items-center justify-content-between p-2 border rounded">
                                    <div class="d-flex align-items-center gap-2">
                                        <?php if (!empty($cat['icon'])): ?>
                                            <i class="<?php echo htmlspecialchars($cat['icon']); ?>" style="color: <?php echo htmlspecialchars($cat['color']); ?>"></i>
                                        <?php endif; ?>
                                        <span><?php echo htmlspecialchars($cat['name']); ?></span>
                                    </div>
                                    <div class="btn-group btn-group-sm">
                                        <a href="?module=quick-links&action=edit_category&cat_id=<?php echo $cat['id']; ?>" class="btn btn-outline-primary btn-sm">Editar</a>
                                        <a href="?module=quick-links&action=delete_category&cat_id=<?php echo $cat['id']; ?>&token=<?php echo csrf_token(); ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('¿Eliminar categoría?');">Eliminar</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Link Management -->
        <div class="col-12 col-lg-4">
            <div class="module-card">
                <h2 class="h5 mb-3">Gestión de Enlaces</h2>
                <p class="text-muted small">Centraliza accesos a herramientas externas o internas con un clic.</p>

                <form method="post" class="vstack gap-3">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                    <input type="hidden" name="link_action" value="1">
                    <input type="hidden" name="id" value="<?php echo $editing['id'] ?? ''; ?>">

                    <div>
                        <label class="form-label">Título</label>
                        <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($editing['title'] ?? ''); ?>" required>
                    </div>

                    <div>
                        <label class="form-label">URL</label>
                        <input type="url" name="url" class="form-control" value="<?php echo htmlspecialchars($editing['url'] ?? ''); ?>" required>
                    </div>

                    <div>
                        <label class="form-label">Categoría</label>
                        <select name="category_id" class="form-select">
                            <option value="">Sin categoría</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo (($editing['category_id'] ?? '') == $cat['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="form-label">Destino</label>
                        <select name="target" class="form-select">
                            <option value="_self" <?php echo (($editing['target'] ?? '_self') === '_self') ? 'selected' : ''; ?>>Abrir en la misma pestaña</option>
                            <option value="_blank" <?php echo (($editing['target'] ?? '') === '_blank') ? 'selected' : ''; ?>>Abrir en nueva pestaña</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label">Icono (opcional)</label>
                        <input type="text" name="icon" class="form-control" placeholder="Ej. bi bi-link-45deg" value="<?php echo htmlspecialchars($editing['icon'] ?? ''); ?>">
                        <small class="text-muted">Compatible con <a href="https://icons.getbootstrap.com/" target="_blank">Bootstrap Icons</a>.</small>
                    </div>

                    <div class="d-flex justify-content-between">
                        <?php if (isset($editing)): ?>
                            <a href="?module=quick-links" class="btn btn-outline-secondary">Cancelar</a>
                        <?php endif; ?>
                        <button class="btn btn-primary btn-neumorphic" type="submit"><?php echo isset($editing) ? 'Actualizar' : 'Crear'; ?></button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Links Preview -->
        <div class="col-12 col-lg-4">
            <div class="module-card">
                <h5 class="mb-4">Vista Previa de Enlaces</h5>
                <div class="quick-links">
                    <?php foreach ($links as $link): ?>
                        <a class="quick-link-item text-decoration-none" href="<?php echo htmlspecialchars($link['url']); ?>" target="<?php echo htmlspecialchars($link['target']); ?>">
                            <?php if (!empty($link['icon'])): ?>
                                <i class="<?php echo htmlspecialchars($link['icon']); ?> fs-2 mb-2"></i>
                            <?php endif; ?>
                            <h6 class="fw-semibold"><?php echo htmlspecialchars($link['title']); ?></h6>
                            <?php if (!empty($link['category_name'])): ?>
                                <span class="badge" style="background-color: <?php echo htmlspecialchars($link['category_color'] ?? '#6c757d'); ?>">
                                    <?php echo htmlspecialchars($link['category_name']); ?>
                                </span>
                            <?php endif; ?>
                            <div class="mt-3 d-flex gap-2 justify-content-center">
                                <a href="?module=quick-links&action=edit&id=<?php echo $link['id']; ?>" class="btn btn-sm btn-outline-primary">Editar</a>
                                <a href="?module=quick-links&action=delete&id=<?php echo $link['id']; ?>&token=<?php echo csrf_token(); ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Eliminar enlace?');">Eliminar</a>
                            </div>
                        </a>
                    <?php endforeach; ?>
                    <?php if (empty($links)): ?>
                        <div class="text-muted">No se han agregado enlaces aún.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- END USER VIEW -->
    <div class="row g-4">
        <!-- Category Filter Sidebar -->
        <div class="col-12 col-lg-3">
            <div class="module-card">
                <h5 class="mb-3">Categorías</h5>
                <div class="list-group">
                    <!-- All links -->
                    <a href="?module=quick-links" class="list-group-item list-group-item-action <?php echo !isset($_GET['category']) ? 'active' : ''; ?>">
                        <i class="bi bi-grid-3x3-gap me-2"></i>
                        Todos los enlaces
                    </a>

                    <!-- Categories -->
                    <?php foreach ($categories as $cat): ?>
                        <a href="?module=quick-links&category=<?php echo $cat['id']; ?>"
                            class="list-group-item list-group-item-action <?php echo ($selectedCategory == $cat['id']) ? 'active' : ''; ?>">
                            <?php if (!empty($cat['icon'])): ?>
                                <i class="<?php echo htmlspecialchars($cat['icon']); ?> me-2" style="color: <?php echo htmlspecialchars($cat['color']); ?>"></i>
                            <?php endif; ?>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </a>
                    <?php endforeach; ?>

                    <!-- Uncategorized -->
                    <a href="?module=quick-links&category=uncategorized" class="list-group-item list-group-item-action <?php echo (isset($_GET['category']) && $_GET['category'] === 'uncategorized') ? 'active' : ''; ?>">
                        <i class="bi bi-question-circle me-2"></i>
                        Sin categoría
                    </a>
                </div>
            </div>
        </div>

        <!-- Links Display -->
        <div class="col-12 col-lg-9">
            <div class="module-card">
                <h5 class="mb-4">Accesos disponibles</h5>
                <div class="quick-links">
                    <?php foreach ($links as $link): ?>
                        <a class="quick-link-item text-decoration-none" href="<?php echo htmlspecialchars($link['url']); ?>" target="<?php echo htmlspecialchars($link['target']); ?>">
                            <?php if (!empty($link['icon'])): ?>
                                <i class="<?php echo htmlspecialchars($link['icon']); ?> fs-2 mb-2"></i>
                            <?php endif; ?>
                            <h6 class="fw-semibold"><?php echo htmlspecialchars($link['title']); ?></h6>
                            <?php if (!empty($link['category_name'])): ?>
                                <span class="badge mt-2" style="background-color: <?php echo htmlspecialchars($link['category_color'] ?? '#6c757d'); ?>">
                                    <?php echo htmlspecialchars($link['category_name']); ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                    <?php if (empty($links)): ?>
                        <div class="text-muted text-center py-5">
                            <i class="bi bi-inbox fs-1 d-block mb-3"></i>
                            No hay enlaces en esta categoría.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>