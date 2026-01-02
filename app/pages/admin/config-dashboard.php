<?php
require_admin();

// Create default dashboard.json if it doesn't exist
$dashboard_config_file = APP_PATH . '/config/dashboard.json';
if (!file_exists($dashboard_config_file)) {
    $default_config = [
        'widgets_order' => [
            'stock_bajo',
            'productos_activos',
            'sin_stock',
            'ordenes_totales',
            'ingreso_neto_ventas',
            'promociones',
            'cupones',
            'reviews_pendientes'
        ],
        'widgets' => [
            'productos_activos' => true,
            'stock_bajo' => true,
            'sin_stock' => true,
            'ordenes_totales' => true,
            'ingreso_neto_ventas' => true,
            'promociones' => true,
            'cupones' => true,
            'reviews_pendientes' => true
        ],
        'quick_actions_order' => [
            'productos',
            'ventas',
            'cupones',
            'reviews',
            'config',
            'envios',
            'promociones',
            'notificaciones',
            'payment',
            'analytics'
        ],
        'quick_actions' => [
            'productos' => true,
            'ventas' => true,
            'cupones' => true,
            'reviews' => true,
            'config' => true,
            'envios' => true,
            'promociones' => true,
            'notificaciones' => true,
            'payment' => true,
            'analytics' => true
        ]
    ];
    write_json($dashboard_config_file, $default_config);
}

$message = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token inválido';
    } else {
        $config = read_json(APP_PATH . '/config/dashboard.json');
        
        // Update orders
        if (isset($_POST['widgets_order'])) {
            $config['widgets_order'] = json_decode($_POST['widgets_order'], true);
        }
        if (isset($_POST['quick_actions_order'])) {
            $config['quick_actions_order'] = json_decode($_POST['quick_actions_order'], true);
        }
        
        // Update visibility
        $config['widgets'] = [
            'productos_activos' => isset($_POST['widget_productos_activos']),
            'stock_bajo' => isset($_POST['widget_stock_bajo']),
            'sin_stock' => isset($_POST['widget_sin_stock']),
            'ordenes_totales' => isset($_POST['widget_ordenes_totales']),
            'ingreso_neto_ventas' => isset($_POST['widget_ingreso_neto_ventas']),
            'promociones' => isset($_POST['widget_promociones']),
            'cupones' => isset($_POST['widget_cupones']),
            'reviews_pendientes' => isset($_POST['widget_reviews_pendientes'])
        ];
        $config['quick_actions'] = [
            'productos' => isset($_POST['action_productos']),
            'ventas' => isset($_POST['action_ventas']),
            'cupones' => isset($_POST['action_cupones']),
            'reviews' => isset($_POST['action_reviews']),
            'config' => isset($_POST['action_config']),
            'envios' => isset($_POST['action_envios']),
            'promociones' => isset($_POST['action_promociones']),
            'notificaciones' => isset($_POST['action_notificaciones']),
            'payment' => isset($_POST['action_payment']),
            'analytics' => isset($_POST['action_analytics'])
        ];
        
        if (write_json(APP_PATH . '/config/dashboard.json', $config)) {
            $message = 'Guardado';
            log_admin_action('dashboard_updated', $_SESSION['username'], $config);
        } else $error = 'Error';
    }
}
$config = read_json(APP_PATH . '/config/dashboard.json');

// Ensure widgets_order and quick_actions_order exist
if (!isset($config['widgets_order']) || empty($config['widgets_order'])) {
    $config['widgets_order'] = [
        'stock_bajo', 'productos_activos', 'sin_stock', 'ordenes_totales',
        'ingreso_neto_ventas', 'promociones', 'cupones', 'reviews_pendientes'
    ];
}
if (!isset($config['quick_actions_order']) || empty($config['quick_actions_order'])) {
    $config['quick_actions_order'] = [
        'productos', 'ventas', 'cupones', 'reviews', 'config',
        'envios', 'promociones', 'notificaciones', 'payment', 'analytics'
    ];
}
// Ensure widgets and quick_actions exist
if (!isset($config['widgets'])) {
    $config['widgets'] = [
        'productos_activos' => true, 'stock_bajo' => true, 'sin_stock' => true,
        'ordenes_totales' => true, 'ingreso_neto_ventas' => true,
        'promociones' => true, 'cupones' => true, 'reviews_pendientes' => true
    ];
}
if (!isset($config['quick_actions'])) {
    $config['quick_actions'] = [
        'productos' => true, 'ventas' => true, 'cupones' => true, 'reviews' => true,
        'config' => true, 'envios' => true, 'promociones' => true,
        'notificaciones' => true, 'payment' => true, 'analytics' => true
    ];
}

$site_config = read_json(APP_PATH . '/config/site.json');
$page_title = 'Configuración del Dashboard';
$csrf_token = generate_csrf_token();
$user = get_logged_user();
$widget_names = ['productos_activos'=>'📦 Productos Activos','stock_bajo'=>'⚠️ Stock Bajo','sin_stock'=>'🚨 Sin Stock','ordenes_totales'=>'💰 Órdenes','ingreso_neto_ventas'=>'💵 Ingreso Neto','promociones'=>'🎯 Promociones','cupones'=>'🎫 Cupones','reviews_pendientes'=>'⭐ Reviews'];
$action_names = ['productos'=>'📦 Productos','ventas'=>'💰 Ventas','cupones'=>'🎫 Cupones','reviews'=>'⭐ Reviews','config'=>'⚙️ Config','envios'=>'📦 Envíos','promociones'=>'🎯 Promociones','notificaciones'=>'🔔 Notificaciones','payment'=>'💳 Medios de Pago','analytics'=>'📊 Analytics'];
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>Dashboard Config</title>
<!-- SortableJS CSS no es necesario -->
<style nonce="<?= csp_nonce() ?>">* { margin: 0; padding: 0; box-sizing: border-box; } body { font-family: system-ui; background: #f5f7fa; } .main-content { margin-left: 260px; padding: 20px; max-width: 1400px; } .content-header h1 { font-size: 24px; margin-bottom: 20px; } .message { padding: 12px; border-radius: 6px; margin-bottom: 15px; } .message.success { background: #d4edda; color: #155724; } .card { background: white; border-radius: 8px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); margin-bottom: 20px; } .section-title { font-size: 18px; font-weight: 600; margin-bottom: 15px; } .sortable-list { list-style: none; } .sortable-item { background: #f8f9fa; padding: 12px 15px; margin-bottom: 8px; border-radius: 6px; cursor: move; display: flex; align-items: center; gap: 10px; border: 2px solid transparent; } .sortable-item:hover { background: #e9ecef; border-color: #667eea; } .sortable-item.sortable-ghost { opacity: 0.4; } .sortable-item input[type="checkbox"] { width: auto; } .drag-handle { color: #999; cursor: grab; } .btn-save { padding: 12px 30px; background: #6c757d; color: white; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; } .btn-save.changed { background: #dc3545; animation: pulse 1.5s infinite; } .btn-save.saved { background: #28a745; } @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.8; } } @media (max-width: 1024px) { .main-content { margin-left: 0; } }</style>
</head><body>
<?php include APP_PATH . '/includes/admin/sidebar.php'; ?>
<div class="main-content">
<?php include APP_PATH . '/includes/admin/header.php'; ?>
<?php if ($message): ?><div class="message success"><?= $message ?></div><?php endif; ?>
<form method="POST" id="configForm">
<input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
<input type="hidden" name="widgets_order" id="widgets_order">
<input type="hidden" name="quick_actions_order" id="quick_actions_order">

<div class="card">
<div class="section-title">Widgets del Dashboard (arrastra para reordenar)</div>
<ul id="widgets-list" class="sortable-list">
<?php foreach ($config['widgets_order'] ?? [] as $key): ?>
<li class="sortable-item" data-id="<?= $key ?>">
<span class="drag-handle">⋮⋮</span>
<input type="checkbox" name="widget_<?= $key ?>" id="widget_<?= $key ?>" <?= ($config['widgets'][$key] ?? true) ? 'checked' : '' ?>>
<label for="widget_<?= $key ?>"><?= $widget_names[$key] ?? $key ?></label>
</li>
<?php endforeach; ?>
</ul>
</div>

<div class="card">
<div class="section-title">Acciones Rápidas (arrastra para reordenar)</div>
<ul id="actions-list" class="sortable-list">
<?php foreach ($config['quick_actions_order'] ?? [] as $key): ?>
<li class="sortable-item" data-id="<?= $key ?>">
<span class="drag-handle">⋮⋮</span>
<input type="checkbox" name="action_<?= $key ?>" id="action_<?= $key ?>" <?= ($config['quick_actions'][$key] ?? true) ? 'checked' : '' ?>>
<label for="action_<?= $key ?>"><?= $action_names[$key] ?? $key ?></label>
</li>
<?php endforeach; ?>
</ul>
</div>

<button type="submit" name="save_config" class="btn-save" id="saveBtn">💾 Guardar</button>
</form>
</div>
<!-- SortableJS Local -->
<script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/Sortable.min.js'); ?>"></script>
<script nonce="<?= csp_nonce() ?>">
const wList = document.getElementById('widgets-list');
const aList = document.getElementById('actions-list');
Sortable.create(wList, {animation: 150, handle: '.drag-handle', onEnd: () => markChanged()});
Sortable.create(aList, {animation: 150, handle: '.drag-handle', onEnd: () => markChanged()});
const form = document.getElementById('configForm');
const saveBtn = document.getElementById('saveBtn');
const inputs = form.querySelectorAll('input[type="checkbox"]');
let saveSuccess = <?= $message ? 'true' : 'false' ?>;
inputs.forEach(i => i.addEventListener('change', markChanged));
function markChanged() { saveBtn.classList.add('changed'); saveBtn.classList.remove('saved'); }
form.addEventListener('submit', () => {
    const wOrder = Array.from(wList.children).map(li => li.dataset.id);
    const aOrder = Array.from(aList.children).map(li => li.dataset.id);
    document.getElementById('widgets_order').value = JSON.stringify(wOrder);
    document.getElementById('quick_actions_order').value = JSON.stringify(aOrder);
});
if (saveSuccess) { saveBtn.classList.add('saved'); setTimeout(() => saveBtn.classList.remove('saved'), 3000); }
</script>
</body></html>
