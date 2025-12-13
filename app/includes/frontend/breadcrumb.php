<?php
/**
 * Componente: Breadcrumb
 *
 * Navegación de migas de pan (breadcrumb trail)
 * Puede ser usado en: producto.php, carrito.php, checkout.php, etc.
 */

if (!defined('APP_ENTRY_POINT')) {
    die('Direct access not permitted');
}

/**
 * Renderiza un breadcrumb
 *
 * @param array $items Array de items del breadcrumb:
 *   Cada item es un array con:
 *   - 'label' (string) Texto a mostrar
 *   - 'url' (string|null) URL del link (null para el último item)
 * @param array $options Opciones de visualización:
 *   - 'separator' (string) Separador entre items (default: '>')
 *   - 'class' (string) Clase CSS adicional (default: '')
 *
 * Ejemplo de uso:
 * render_breadcrumb([
 *     ['label' => 'Inicio', 'url' => url('/')],
 *     ['label' => 'Productos', 'url' => url('/productos')],
 *     ['label' => 'Nombre del Producto', 'url' => null] // último item sin link
 * ]);
 */
function render_breadcrumb($items, $options = []) {
    if (empty($items)) {
        return;
    }

    $defaults = [
        'separator' => '>',
        'class' => ''
    ];

    $options = array_merge($defaults, $options);

    $class_attr = $options['class'] ? ' ' . $options['class'] : '';
    $separator = htmlspecialchars($options['separator']);
    ?>

    <nav class="breadcrumb<?php echo $class_attr; ?>" aria-label="Breadcrumb">
        <ol>
            <?php foreach ($items as $index => $item): ?>
                <li>
                    <?php if (!empty($item['url'])): ?>
                        <a href="<?php echo htmlspecialchars($item['url']); ?>">
                            <?php echo htmlspecialchars($item['label']); ?>
                        </a>
                    <?php else: ?>
                        <span aria-current="page"><?php echo htmlspecialchars($item['label']); ?></span>
                    <?php endif; ?>

                    <?php if ($index < count($items) - 1): ?>
                        <span class="breadcrumb-separator"><?php echo $separator; ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    </nav>

    <?php
}
