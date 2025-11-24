<?php

use App\Database;
use App\Auth;

$conn = Database::connection();
$user = Auth::user();

$settings = get_user_dashboard_settings($user['id']);

$events = $conn->query("SELECT id, title, date, description FROM events WHERE date >= CURDATE() ORDER BY date ASC LIMIT 5")->fetchAll();
$announcements = $conn->query("SELECT id, title, created_at FROM announcements ORDER BY created_at DESC LIMIT 5")->fetchAll();
$documents = $conn->query("SELECT documents.id, documents.name, folders.name AS folder_name, documents.updated_at FROM documents JOIN folders ON folders.id = documents.folder_id ORDER BY documents.updated_at DESC LIMIT 5")->fetchAll();
$quickLinks = $conn->query("SELECT id, title, url, target FROM quick_links ORDER BY created_at DESC LIMIT 6")->fetchAll();
$carouselItems = $conn->query("SELECT id, title, file_path, media_type FROM dashboard_carousel WHERE is_active = 1 ORDER BY display_order ASC, created_at DESC")->fetchAll();

$layoutClass = $settings['layout'] === 'list' ? 'col-12' : 'col-md-6 col-xl-3';
?>
<div class="row g-4">
    <div class="col-12">
        <div class="module-card">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div>
                    <h2 class="h4 mb-1">Hola, <?php echo htmlspecialchars($user['name']); ?></h2>
                    <p class="text-muted mb-0">Este es tu panel personalizado. Ajusta los módulos visibles desde el menú de perfil.</p>
                </div>
                <span class="badge-role"><?php echo htmlspecialchars($user['role_name']); ?></span>
            </div>
        </div>
    </div>
    <?php if (!empty($carouselItems)): ?>
        <div class="col-12">
            <div class="module-card p-0 overflow-hidden" style="background: transparent; box-shadow: none;">
                <div class="carousel-container" style="position: relative; width: 100%; height: 400px; border-radius: 12px; overflow: hidden;">
                    <?php foreach ($carouselItems as $index => $item): ?>
                        <div class="carousel-slide" data-slide="<?php echo $index; ?>" style="display: <?php echo $index === 0 ? 'block' : 'none'; ?>; position: absolute; width: 100%; height: 100%; transition: opacity 0.5s ease;">
                            <?php if ($item['media_type'] === 'image'): ?>
                                <img src="<?php echo htmlspecialchars(base_url($item['file_path'])); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                                <video src="<?php echo htmlspecialchars(base_url($item['file_path'])); ?>" style="width: 100%; height: 100%; object-fit: cover;" autoplay muted loop></video>
                            <?php endif; ?>
                            <?php if (!empty($item['title'])): ?>
                                <div style="position: absolute; bottom: 0; left: 0; right: 0; background: linear-gradient(to top, rgba(0,0,0,0.7), transparent); padding: 20px; color: white;">
                                    <h5 class="mb-0"><?php echo htmlspecialchars($item['title']); ?></h5>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <?php if (count($carouselItems) > 1): ?>
                        <button class="carousel-btn carousel-prev" onclick="changeSlide(-1)" style="position: absolute; left: 20px; top: 50%; transform: translateY(-50%); background: rgba(255,255,255,0.9); border: none; border-radius: 50%; width: 40px; height: 40px; cursor: pointer; z-index: 10; box-shadow: 0 2px 8px rgba(0,0,0,0.2);">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="15 18 9 12 15 6"></polyline>
                            </svg>
                        </button>
                        <button class="carousel-btn carousel-next" onclick="changeSlide(1)" style="position: absolute; right: 20px; top: 50%; transform: translateY(-50%); background: rgba(255,255,255,0.9); border: none; border-radius: 50%; width: 40px; height: 40px; cursor: pointer; z-index: 10; box-shadow: 0 2px 8px rgba(0,0,0,0.2);">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="9 18 15 12 9 6"></polyline>
                            </svg>
                        </button>

                        <div class="carousel-indicators" style="position: absolute; bottom: 20px; left: 50%; transform: translateX(-50%); display: flex; gap: 8px; z-index: 10;">
                            <?php foreach ($carouselItems as $index => $item): ?>
                                <button class="carousel-indicator" data-slide="<?php echo $index; ?>" onclick="goToSlide(<?php echo $index; ?>)" style="width: 10px; height: 10px; border-radius: 50%; border: 2px solid white; background: <?php echo $index === 0 ? 'white' : 'transparent'; ?>; cursor: pointer; padding: 0;"></button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <script>
                    let currentSlide = 0;
                    const totalSlides = <?php echo count($carouselItems); ?>;
                    let autoPlayInterval;

                    function showSlide(index) {
                        const slides = document.querySelectorAll('.carousel-slide');
                        const indicators = document.querySelectorAll('.carousel-indicator');

                        slides.forEach((slide, i) => {
                            slide.style.display = i === index ? 'block' : 'none';
                        });

                        indicators.forEach((indicator, i) => {
                            indicator.style.background = i === index ? 'white' : 'transparent';
                        });

                        currentSlide = index;
                    }

                    function changeSlide(direction) {
                        let newSlide = currentSlide + direction;
                        if (newSlide >= totalSlides) newSlide = 0;
                        if (newSlide < 0) newSlide = totalSlides - 1;
                        showSlide(newSlide);
                        resetAutoPlay();
                    }

                    function goToSlide(index) {
                        showSlide(index);
                        resetAutoPlay();
                    }

                    function autoPlay() {
                        autoPlayInterval = setInterval(() => {
                            changeSlide(1);
                        }, 5000);
                    }

                    function resetAutoPlay() {
                        clearInterval(autoPlayInterval);
                        autoPlay();
                    }

                    if (totalSlides > 1) {
                        autoPlay();
                    }
                </script>
            </div>
        </div>
    <?php endif; ?>
    <?php if (in_array('calendar', $settings['visible_modules'], true)): ?>
        <div class="<?php echo $layoutClass; ?>">
            <div class="module-card dashboard-widget">
                <div>
                    <h5>Próximos eventos</h5>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($events as $event): ?>
                            <li class="mb-3">
                                <div class="fw-semibold"><?php echo htmlspecialchars($event['title']); ?></div>
                                <small class="text-muted"><?php echo format_date($event['date']); ?></small>
                                <?php if (!empty($event['description'])): ?>
                                    <div class="text-muted mt-1" style="font-size: 0.875rem;"><?php echo nl2br(htmlspecialchars($event['description'])); ?></div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                        <?php if (empty($events)): ?>
                            <li class="text-muted">No hay eventos programados.</li>
                        <?php endif; ?>
                    </ul>
                </div>
                <a href="?module=calendar" class="btn btn-link p-0">Ver calendario</a>
            </div>
        </div>
    <?php endif; ?>
    <?php if (in_array('announcements', $settings['visible_modules'], true)): ?>
        <div class="<?php echo $layoutClass; ?>">
            <div class="module-card dashboard-widget">
                <div>
                    <h5>Últimos anuncios</h5>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($announcements as $item): ?>
                            <li class="mb-3">
                                <div class="fw-semibold"><?php echo htmlspecialchars($item['title']); ?></div>
                                <small class="text-muted"><?php echo format_datetime($item['created_at']); ?></small>
                            </li>
                        <?php endforeach; ?>
                        <?php if (empty($announcements)): ?>
                            <li class="text-muted">Sin anuncios recientes.</li>
                        <?php endif; ?>
                    </ul>
                </div>
                <a href="?module=announcements" class="btn btn-link p-0">Ir al tablón</a>
            </div>
        </div>
    <?php endif; ?>
    <?php if (in_array('documents', $settings['visible_modules'], true)): ?>
        <div class="<?php echo $layoutClass; ?>">
            <div class="module-card dashboard-widget">
                <div>
                    <h5>Documentos recientes</h5>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($documents as $document): ?>
                            <li class="mb-3">
                                <div class="fw-semibold"><?php echo htmlspecialchars($document['name']); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($document['folder_name']); ?> · <?php echo format_datetime($document['updated_at']); ?></small>
                            </li>
                        <?php endforeach; ?>
                        <?php if (empty($documents)): ?>
                            <li class="text-muted">Aún no se han cargado documentos.</li>
                        <?php endif; ?>
                    </ul>
                </div>
                <a href="?module=documents" class="btn btn-link p-0">Abrir repositorio</a>
            </div>
        </div>
    <?php endif; ?>
    <?php if (in_array('quick-links', $settings['visible_modules'], true)): ?>
        <div class="<?php echo $layoutClass; ?>">
            <div class="module-card dashboard-widget">
                <div>
                    <h5>Accesos rápidos</h5>
                    <div class="d-flex flex-column gap-2">
                        <?php foreach ($quickLinks as $link): ?>
                            <a class="btn btn-outline-primary btn-neumorphic" href="<?php echo htmlspecialchars($link['url']); ?>" target="<?php echo htmlspecialchars($link['target']); ?>"><?php echo htmlspecialchars($link['title']); ?></a>
                        <?php endforeach; ?>
                        <?php if (empty($quickLinks)): ?>
                            <span class="text-muted">No hay accesos disponibles.</span>
                        <?php endif; ?>
                    </div>
                </div>
                <a href="?module=quick-links" class="btn btn-link p-0">Gestionar enlaces</a>
            </div>
        </div>
    <?php endif; ?>
</div>