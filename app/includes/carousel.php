<?php
/**
 * Carousel V2 Component
 * Slider con loop infinito hacia la izquierda
 * Trencito de imágenes centradas con dots de navegación
 */

$carousel_config = read_json(__DIR__ . '/../config/carousel.json');

if (!($carousel_config['enabled'] ?? false) || empty($carousel_config['slides'])) {
    return;
}

// Get alignment configuration
$alignment = $carousel_config['alignment'] ?? 'center';
$background_color = $carousel_config['background_color'] ?? '';
?>

<div class="carousel-v2-wrapper" data-alignment="<?php echo htmlspecialchars($alignment); ?>" <?php if (!empty($background_color)): ?>style="--carousel-bg: <?php echo htmlspecialchars($background_color); ?>;"<?php endif; ?>>
    <div class="carousel-v2-container">
        <div class="carousel-v2-track" id="carousel-track">
            <?php
            // Duplicate slides 5 times for infinite loop effect
            // This ensures enough images to fill the viewport and enable seamless looping
            $slides_to_render = array_merge(
                $carousel_config['slides'],
                $carousel_config['slides'],
                $carousel_config['slides'],
                $carousel_config['slides'],
                $carousel_config['slides']
            );

            $original_count = count($carousel_config['slides']);
            foreach ($slides_to_render as $index => $slide):
                // Store original index for dot navigation
                $original_index = $index % $original_count;
            ?>
                <div class="carousel-v2-slide" data-index="<?php echo $index; ?>" data-original-index="<?php echo $original_index; ?>">
                    <?php if (!empty($slide['link'])): ?>
                        <?php
                            $is_external = ($slide['link_type'] ?? 'none') === 'custom';
                            $link_url = $is_external ? $slide['link'] : htmlspecialchars($slide['link']);
                            $target_attr = $is_external ? ' target="_blank" rel="noopener noreferrer"' : '';
                        ?>
                        <a href="<?php echo $link_url; ?>" class="carousel-v2-link"<?php echo $target_attr; ?>>
                            <img src="<?php echo htmlspecialchars(url($slide['image'])); ?>"
                                 alt="<?php echo htmlspecialchars($slide['title'] ?? 'Slide'); ?>"
                                 class="carousel-v2-image"
                                 draggable="false">

                            <?php if (!empty($slide['title'])): ?>
                                <div class="carousel-v2-title">
                                    <?php echo htmlspecialchars($slide['title']); ?>
                                </div>
                            <?php endif; ?>
                        </a>
                    <?php else: ?>
                        <div class="carousel-v2-item">
                            <img src="<?php echo htmlspecialchars(url($slide['image'])); ?>"
                                 alt="<?php echo htmlspecialchars($slide['title'] ?? 'Slide'); ?>"
                                 class="carousel-v2-image"
                                 draggable="false">

                            <?php if (!empty($slide['title'])): ?>
                                <div class="carousel-v2-title">
                                    <?php echo htmlspecialchars($slide['title']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <!-- Los dots se crean dinámicamente con JavaScript -->
    </div>
</div>

<script nonce="<?= csp_nonce() ?>">
    // Initialize carousel v2 data
    window.carouselV2Data = {
        slides: <?php echo json_encode($carousel_config['slides']); ?>,
        autoAdvanceTime: <?php echo intval($carousel_config['auto_advance_time'] ?? 5000); ?>
    };
</script>
