<?php
/**
 * Componente: Share Buttons
 *
 * Botones para compartir en redes sociales
 * Usado en: producto.php
 */

if (!defined('APP_ENTRY_POINT')) {
    die('Direct access not permitted');
}

/**
 * Renderiza botones para compartir
 *
 * @param array $data Datos para compartir:
 *   - 'url' (string) URL a compartir
 *   - 'title' (string) Título del contenido
 *   - 'description' (string) Descripción (opcional)
 * @param array $options Opciones de visualización:
 *   - 'networks' (array) Redes sociales a mostrar (default: ['facebook', 'twitter', 'whatsapp', 'copy'])
 *   - 'class' (string) Clase CSS adicional (default: '')
 *   - 'show_copy' (bool) Mostrar botón de copiar link (default: true)
 */
function render_share_buttons($data, $options = []) {
    $defaults = [
        'networks' => ['facebook', 'twitter', 'whatsapp', 'copy'],
        'class' => '',
        'show_copy' => true
    ];

    $options = array_merge($defaults, $options);

    $class_attr = $options['class'] ? ' ' . $options['class'] : '';
    $url = urlencode($data['url']);
    $title = urlencode($data['title']);
    $description = isset($data['description']) ? urlencode($data['description']) : '';

    // Share URLs
    $share_urls = [
        'facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . $url,
        'twitter' => 'https://twitter.com/intent/tweet?url=' . $url . '&text=' . $title,
        'whatsapp' => 'https://wa.me/?text=' . $title . ' - ' . $url,
        'linkedin' => 'https://www.linkedin.com/sharing/share-offsite/?url=' . $url,
        'telegram' => 'https://t.me/share/url?url=' . $url . '&text=' . $title
    ];
    ?>

    <div class="share-section-modern<?php echo $class_attr; ?>">
        <?php if ($options['show_copy'] && in_array('copy', $options['networks'])): ?>
        <button class="share-btn-icon"
                data-action="copyLink"
                data-url="<?php echo htmlspecialchars($data['url']); ?>"
                title="Copiar enlace">
            <i class="fas fa-link"></i>
        </button>
        <?php endif; ?>

        <?php if (in_array('facebook', $options['networks'])): ?>
        <a href="<?php echo htmlspecialchars($share_urls['facebook']); ?>"
           class="share-btn-icon"
           target="_blank"
           rel="noopener noreferrer"
           title="Compartir en Facebook">
            <i class="fab fa-facebook-f"></i>
        </a>
        <?php endif; ?>

        <?php if (in_array('twitter', $options['networks'])): ?>
        <a href="<?php echo htmlspecialchars($share_urls['twitter']); ?>"
           class="share-btn-icon"
           target="_blank"
           rel="noopener noreferrer"
           title="Compartir en X">
            <i class="fab fa-x-twitter"></i>
        </a>
        <?php endif; ?>

        <?php if (in_array('whatsapp', $options['networks'])): ?>
        <a href="<?php echo htmlspecialchars($share_urls['whatsapp']); ?>"
           class="share-btn-icon"
           target="_blank"
           rel="noopener noreferrer"
           title="Compartir en WhatsApp">
            <i class="fab fa-whatsapp"></i>
        </a>
        <?php endif; ?>

        <?php if (in_array('linkedin', $options['networks'])): ?>
        <a href="<?php echo htmlspecialchars($share_urls['linkedin']); ?>"
           class="share-btn-icon"
           target="_blank"
           rel="noopener noreferrer"
           title="Compartir en LinkedIn">
            <i class="fab fa-linkedin-in"></i>
        </a>
        <?php endif; ?>

        <?php if (in_array('telegram', $options['networks'])): ?>
        <a href="<?php echo htmlspecialchars($share_urls['telegram']); ?>"
           class="share-btn-icon"
           target="_blank"
           rel="noopener noreferrer"
           title="Compartir en Telegram">
            <i class="fab fa-telegram"></i>
        </a>
        <?php endif; ?>
    </div>

    <?php
}
