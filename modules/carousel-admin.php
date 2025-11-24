<?php

use App\Database;
use App\Auth;

// Only Publisher and Admin can manage carousel
if (!in_array(Auth::user()['role_slug'], ['publisher', 'admin'], true)) {
    die('No tienes permisos para acceder a esta sección.');
}

$conn = Database::connection();
$user = Auth::user();

// Handle form submissions
if (is_post()) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        die('Token CSRF inválido.');
    }

    if (isset($_POST['create_item'])) {
        $title = trim($_POST['title'] ?? '');
        $displayOrder = (int)($_POST['display_order'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if (!empty($_FILES['media_file']['name'])) {
            $allowedImages = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/gif' => 'gif', 'image/webp' => 'webp'];
            $allowedVideos = ['video/mp4' => 'mp4', 'video/webm' => 'webm'];
            $allowed = array_merge($allowedImages, $allowedVideos);

            $type = mime_content_type($_FILES['media_file']['tmp_name']);

            if (isset($allowed[$type])) {
                $mediaType = isset($allowedImages[$type]) ? 'image' : 'video';
                $filename = uniqid('carousel_') . '.' . $allowed[$type];
                $destination = upload_dir('carousel') . DIRECTORY_SEPARATOR . $filename;

                if (move_uploaded_file($_FILES['media_file']['tmp_name'], $destination)) {
                    $filePath = 'uploads/carousel/' . $filename;
                    $stmt = $conn->prepare('INSERT INTO dashboard_carousel (title, file_path, media_type, display_order, is_active, created_by) VALUES (:title, :file_path, :media_type, :display_order, :is_active, :created_by)');
                    $stmt->execute([
                        'title' => $title,
                        'file_path' => $filePath,
                        'media_type' => $mediaType,
                        'display_order' => $displayOrder,
                        'is_active' => $isActive,
                        'created_by' => $user['id']
                    ]);
                    $message = 'Elemento del carrusel creado correctamente.';
                } else {
                    $error = 'Error al subir el archivo.';
                }
            } else {
                $error = 'Tipo de archivo no permitido. Solo se aceptan imágenes (PNG, JPG, GIF, WEBP) y videos (MP4, WEBM).';
            }
        } else {
            $error = 'Debes seleccionar un archivo.';
        }
    } elseif (isset($_POST['update_item'])) {
        $id = (int)$_POST['id'];
        $title = trim($_POST['title'] ?? '');
        $displayOrder = (int)($_POST['display_order'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        $stmt = $conn->prepare('UPDATE dashboard_carousel SET title = :title, display_order = :display_order, is_active = :is_active WHERE id = :id');
        $stmt->execute([
            'title' => $title,
            'display_order' => $displayOrder,
            'is_active' => $isActive,
            'id' => $id
        ]);
        $message = 'Elemento actualizado correctamente.';
    }
}

// Handle delete action
if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'delete' && verify_csrf($_GET['token'] ?? '')) {
    $id = (int)$_GET['id'];
    $stmt = $conn->prepare('SELECT file_path FROM dashboard_carousel WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $item = $stmt->fetch();

    if ($item) {
        // Delete file from filesystem
        $fullPath = __DIR__ . '/../public/' . $item['file_path'];
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }

        // Delete from database
        $stmt = $conn->prepare('DELETE FROM dashboard_carousel WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $message = 'Elemento eliminado correctamente.';
    }
}

// Handle edit action
if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'edit') {
    $stmt = $conn->prepare('SELECT * FROM dashboard_carousel WHERE id = :id');
    $stmt->execute(['id' => $_GET['id']]);
    $editing = $stmt->fetch();
}

// Fetch all carousel items
$carouselItems = $conn->query('SELECT * FROM dashboard_carousel ORDER BY display_order ASC, created_at DESC')->fetchAll();
?>
<div class="row g-4">
    <div class="col-12 col-xl-5">
        <div class="module-card">
            <h2 class="h4 mb-3"><?php echo isset($editing) ? 'Editar elemento' : 'Agregar elemento al carrusel'; ?></h2>
            <p class="text-muted">Sube imágenes o videos para mostrar en el carrusel del dashboard.</p>
            <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="post" enctype="multipart/form-data" class="vstack gap-3">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                <?php if (isset($editing)): ?>
                    <input type="hidden" name="update_item" value="1">
                    <input type="hidden" name="id" value="<?php echo $editing['id']; ?>">
                <?php else: ?>
                    <input type="hidden" name="create_item" value="1">
                <?php endif; ?>

                <?php if (!isset($editing)): ?>
                    <div>
                        <label class="form-label">Archivo (imagen o video)</label>
                        <input type="file" name="media_file" class="form-control" accept="image/png,image/jpeg,image/gif,image/webp,video/mp4,video/webm" required>
                        <small class="text-muted">Formatos aceptados: PNG, JPG, GIF, WEBP, MP4, WEBM</small>
                    </div>
                <?php endif; ?>

                <div>
                    <label class="form-label">Título / Descripción (opcional)</label>
                    <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($editing['title'] ?? ''); ?>" placeholder="Ej: Evento corporativo 2025">
                </div>

                <div>
                    <label class="form-label">Orden de visualización</label>
                    <input type="number" name="display_order" class="form-control" value="<?php echo htmlspecialchars($editing['display_order'] ?? 0); ?>" min="0">
                    <small class="text-muted">Los elementos con menor número aparecen primero</small>
                </div>

                <div class="form-check">
                    <input type="checkbox" name="is_active" class="form-check-input" id="is_active" <?php echo (isset($editing) && $editing['is_active']) || !isset($editing) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="is_active">Activo (visible en el carrusel)</label>
                </div>

                <div class="d-flex justify-content-between">
                    <?php if (isset($editing)): ?>
                        <a href="?module=carousel-admin" class="btn btn-outline-secondary">Cancelar</a>
                        <button class="btn btn-primary btn-neumorphic" type="submit">Actualizar elemento</button>
                    <?php else: ?>
                        <button class="btn btn-primary btn-neumorphic ms-auto" type="submit">Agregar al carrusel</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="col-12 col-xl-7">
        <div class="module-card">
            <h5 class="mb-3">Elementos del carrusel</h5>
            <?php if (empty($carouselItems)): ?>
                <p class="text-muted">No hay elementos en el carrusel. Agrega el primero usando el formulario.</p>
            <?php else: ?>
                <div class="table-responsive table-neumorphic">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Vista previa</th>
                                <th>Título</th>
                                <th>Tipo</th>
                                <th>Orden</th>
                                <th>Estado</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($carouselItems as $item): ?>
                                <tr>
                                    <td>
                                        <?php if ($item['media_type'] === 'image'): ?>
                                            <img src="<?php echo htmlspecialchars(base_url($item['file_path'])); ?>" alt="Preview" style="width: 80px; height: 60px; object-fit: cover; border-radius: 4px;">
                                        <?php else: ?>
                                            <video src="<?php echo htmlspecialchars(base_url($item['file_path'])); ?>" style="width: 80px; height: 60px; object-fit: cover; border-radius: 4px;" muted></video>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?php echo htmlspecialchars($item['title'] ?: 'Sin título'); ?></div>
                                        <small class="text-muted"><?php echo format_datetime($item['created_at']); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $item['media_type'] === 'image' ? 'primary' : 'success'; ?>">
                                            <?php echo $item['media_type'] === 'image' ? 'Imagen' : 'Video'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $item['display_order']; ?></td>
                                    <td>
                                        <?php if ($item['is_active']): ?>
                                            <span class="badge bg-success">Activo</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactivo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="?module=carousel-admin&action=edit&id=<?php echo $item['id']; ?>" class="btn btn-sm btn-outline-primary">Editar</a>
                                        <a href="?module=carousel-admin&action=delete&id=<?php echo $item['id']; ?>&token=<?php echo csrf_token(); ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Eliminar este elemento del carrusel?');">Eliminar</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>