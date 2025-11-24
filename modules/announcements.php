<?php

use App\Database;
use App\Auth;

$conn = Database::connection();
$canManage = Auth::canManageContent();
$uploadDir = upload_dir('announcements');

if ($canManage && is_post()) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        die('Token CSRF inválido.');
    }
    $id = $_POST['id'] ?? null;
    $title = trim($_POST['title'] ?? '');
    $content = strip_tags(trim($_POST['content'] ?? ''), '<strong><b><em><u><br><br/><p>');
    $imagePath = null;

    if (!empty($_FILES['image']['name'])) {
        $maxSize = 2 * 1024 * 1024; // 2MB
        if ($_FILES['image']['size'] > $maxSize) {
            $error = 'La imagen no debe superar 2MB.';
        } else {
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif'];
            $type = mime_content_type($_FILES['image']['tmp_name']);
            if (isset($allowed[$type])) {
                $filename = uniqid('announcement_') . '.' . $allowed[$type];
                $destination = $uploadDir . DIRECTORY_SEPARATOR . $filename;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
                    $imagePath = 'uploads/announcements/' . $filename;
                }
            } else {
                $error = 'Solo se permiten imágenes JPG, PNG o GIF.';
            }
        }
    }

    if ($title && $content && !isset($error)) {
        if ($id) {
            $payload = ['title' => $title, 'content' => $content, 'id' => $id];
            if ($imagePath) {
                // Delete old image if updating with new one
                $stmt = $conn->prepare('SELECT image_path FROM announcements WHERE id = :id');
                $stmt->execute(['id' => $id]);
                $oldAnnouncement = $stmt->fetch();
                if ($oldAnnouncement && $oldAnnouncement['image_path']) {
                    delete_public_file($oldAnnouncement['image_path']);
                }

                $payload['image_path'] = $imagePath;
                $stmt = $conn->prepare('UPDATE announcements SET title = :title, content = :content, image_path = :image_path WHERE id = :id');
            } else {
                $stmt = $conn->prepare('UPDATE announcements SET title = :title, content = :content WHERE id = :id');
            }
            $stmt->execute($payload);
            $message = 'Anuncio actualizado.';
        } else {
            $stmt = $conn->prepare('INSERT INTO announcements (title, content, image_path, created_by) VALUES (:title, :content, :image_path, :created_by)');
            $stmt->execute([
                'title' => $title,
                'content' => $content,
                'image_path' => $imagePath,
                'created_by' => Auth::user()['id'],
            ]);
            $message = 'Anuncio publicado.';
        }
    } elseif (!isset($error)) {
        $error = 'El título y contenido son obligatorios.';
    }
}

if ($canManage && isset($_GET['action'], $_GET['id'])) {
    if ($_GET['action'] === 'delete' && verify_csrf($_GET['token'] ?? '')) {
        $stmt = $conn->prepare('SELECT image_path FROM announcements WHERE id = :id');
        $stmt->execute(['id' => $_GET['id']]);
        $row = $stmt->fetch();
        if ($row && $row['image_path']) {
            delete_public_file($row['image_path']);
        }
        $stmt = $conn->prepare('DELETE FROM announcements WHERE id = :id');
        $stmt->execute(['id' => $_GET['id']]);
        $message = 'Anuncio eliminado.';
    } elseif ($_GET['action'] === 'edit' && verify_csrf($_GET['token'] ?? '')) {
        $stmt = $conn->prepare('SELECT * FROM announcements WHERE id = :id');
        $stmt->execute(['id' => $_GET['id']]);
        $editing = $stmt->fetch();
    }
}

$announcements = $conn->query('SELECT announcements.*, users.name AS author FROM announcements LEFT JOIN users ON users.id = announcements.created_by ORDER BY created_at DESC')->fetchAll();
?>

<style>
    .announcement-thumbnail {
        width: 250px;
        height: 250px;
        object-fit: cover;
        cursor: pointer;
        border-radius: 8px;
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .announcement-thumbnail:hover {
        transform: scale(1.05);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .image-modal {
        display: none;
        position: fixed;
        z-index: 9999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.9);
        animation: fadeIn 0.3s;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
        }

        to {
            opacity: 1;
        }
    }

    .image-modal-content {
        position: relative;
        margin: auto;
        display: block;
        max-width: 90%;
        max-height: 90%;
        top: 50%;
        transform: translateY(-50%);
        animation: zoomIn 0.3s;
    }

    @keyframes zoomIn {
        from {
            transform: translateY(-50%) scale(0.8);
        }

        to {
            transform: translateY(-50%) scale(1);
        }
    }

    .image-modal-close {
        position: absolute;
        top: 20px;
        right: 35px;
        color: #fff;
        font-size: 40px;
        font-weight: bold;
        cursor: pointer;
        transition: color 0.3s;
    }

    .image-modal-close:hover,
    .image-modal-close:focus {
        color: #bbb;
    }
</style>

<div class="row g-4">
    <?php if ($canManage): ?>
        <!-- ADMIN VIEW: Management Panel -->
        <div class="col-12 col-lg-4">
            <div class="module-card">
                <h2 class="h4 mb-3">Tablón de anuncios</h2>
                <p class="text-muted">Comparte novedades, reconocimientos y actualizaciones de interés.</p>
                <?php if (!empty($message)): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <h5 class="mt-4 mb-2"><?php echo isset($editing) ? 'Editar anuncio' : 'Nuevo anuncio'; ?></h5>
                <form method="post" enctype="multipart/form-data" class="vstack gap-3">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                    <input type="hidden" name="id" value="<?php echo $editing['id'] ?? ''; ?>">

                    <div>
                        <label class="form-label">Título</label>
                        <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($editing['title'] ?? ''); ?>" required>
                    </div>

                    <div>
                        <label class="form-label">Contenido</label>
                        <textarea name="content" class="form-control" rows="4" required><?php echo htmlspecialchars($editing['content'] ?? ''); ?></textarea>
                        <small class="text-muted">Puedes usar <strong>negritas</strong> usando etiquetas HTML.</small>
                    </div>

                    <div>
                        <label class="form-label">Imagen (opcional, máx 2MB)</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                        <?php if (!empty($editing['image_path'])): ?>
                            <img src="<?php echo htmlspecialchars(base_url($editing['image_path'])); ?>" alt="Previsualización" class="img-fluid rounded mt-2" style="max-width: 200px;">
                        <?php endif; ?>
                    </div>

                    <div class="d-flex justify-content-between">
                        <?php if (isset($editing)): ?>
                            <a href="?module=announcements" class="btn btn-outline-secondary">Cancelar</a>
                        <?php endif; ?>
                        <button class="btn btn-primary btn-neumorphic" type="submit">
                            <?php echo isset($editing) ? 'Actualizar' : 'Publicar'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <div class="col-12 col-lg-8">
        <?php else: ?>
            <!-- END USER VIEW: Full Width Feed -->
            <div class="col-12">
            <?php endif; ?>
            <div class="module-card">
                <h5 class="mb-4">Novedades recientes</h5>
                <div class="d-grid gap-4">
                    <?php foreach ($announcements as $item): ?>
                        <article class="card p-3">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h3 class="h5 mb-1"><?php echo htmlspecialchars($item['title']); ?></h3>
                                    <small class="text-muted">Publicado por <?php echo htmlspecialchars($item['author'] ?? 'Sistema'); ?> · <?php echo format_datetime($item['created_at']); ?></small>
                                </div>
                                <?php if ($canManage): ?>
                                    <div class="d-flex gap-2">
                                        <a href="?module=announcements&action=edit&id=<?php echo $item['id']; ?>&token=<?php echo csrf_token(); ?>" class="btn btn-sm btn-outline-primary">Editar</a>
                                        <a href="?module=announcements&action=delete&id=<?php echo $item['id']; ?>&token=<?php echo csrf_token(); ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Eliminar anuncio?');">Eliminar</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="mt-3">
                                <p><?php echo nl2br(htmlspecialchars($item['content'])); ?></p>
                            </div>
                            <?php if (!empty($item['image_path'])): ?>
                                <div class="mt-2">
                                    <img
                                        src="<?php echo htmlspecialchars(base_url($item['image_path'])); ?>"
                                        alt="Imagen del anuncio"
                                        class="announcement-thumbnail"
                                        onclick="openImageModal(this.src)">
                                </div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                    <?php if (empty($announcements)): ?>
                        <div class="text-center text-muted py-5">
                            <?php if ($canManage): ?>
                                Aún no hay anuncios publicados.
                            <?php else: ?>
                                No hay anuncios disponibles en este momento.
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            </div>
        </div>

        <!-- Image Modal/Lightbox -->
        <div id="imageModal" class="image-modal" onclick="closeImageModal()">
            <span class="image-modal-close">&times;</span>
            <img class="image-modal-content" id="modalImage">
        </div>

        <script>
            function openImageModal(src) {
                const modal = document.getElementById('imageModal');
                const modalImg = document.getElementById('modalImage');
                modal.style.display = 'block';
                modalImg.src = src;
                document.body.style.overflow = 'hidden'; // Prevent scrolling
            }

            function closeImageModal() {
                const modal = document.getElementById('imageModal');
                modal.style.display = 'none';
                document.body.style.overflow = 'auto'; // Restore scrolling
            }

            // Close modal on Escape key
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    closeImageModal();
                }
            });
        </script>