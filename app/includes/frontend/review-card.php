<?php
/**
 * Componente: Review Card
 *
 * Tarjeta de opinión/reseña de cliente
 * Usado en: producto.php
 */

if (!defined('APP_ENTRY_POINT')) {
    die('Direct access not permitted');
}

/**
 * Renderiza una tarjeta de reseña
 *
 * @param array $review Datos de la reseña:
 *   - 'user_name' (string) Nombre del usuario
 *   - 'rating' (int) Calificación (1-5)
 *   - 'comment' (string) Comentario
 *   - 'created_at' (string) Fecha de creación
 * @param array $options Opciones de visualización:
 *   - 'show_date' (bool) Mostrar fecha (default: true)
 */
function render_review_card($review, $options = []) {
    $defaults = [
        'show_date' => true
    ];

    $options = array_merge($defaults, $options);
    ?>

    <div class="review">
        <div class="review-header">
            <span class="review-author"><?php echo htmlspecialchars($review['user_name']); ?></span>

            <?php if ($options['show_date']): ?>
            <span class="review-date"><?php echo date('d/m/Y', strtotime($review['created_at'])); ?></span>
            <?php endif; ?>
        </div>

        <div class="review-rating">
            <?php
            for ($i = 1; $i <= 5; $i++) {
                echo $i <= $review['rating'] ? '★' : '☆';
            }
            ?>
        </div>

        <div class="review-text">
            <?php echo nl2br(htmlspecialchars($review['comment'])); ?>
        </div>
    </div>

    <?php
}
