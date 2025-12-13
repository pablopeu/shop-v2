<?php
require_admin();

$message = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token inválido';
    } else {
        $config = read_json(APP_PATH . '/config/maintenance.json');
        $config['enabled'] = isset($_POST['enabled']);
        $config['message'] = sanitize_input($_POST['message'] ?? '');
        $config['bypass_code'] = sanitize_input($_POST['bypass_code'] ?? '');
        if (write_json(APP_PATH . '/config/maintenance.json', $config)) {
            $message = 'Guardado';
            log_admin_action('maintenance_updated', $_SESSION['username'], $config);
        } else $error = 'Error';
    }
}
$config = read_json(APP_PATH . '/config/maintenance.json');
$site_config = read_json(APP_PATH . '/config/site.json');
$page_title = 'Modo Mantenimiento';
$csrf_token = generate_csrf_token();
$user = get_logged_user();

// Get site URL for bypass link
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$site_url = $protocol . $_SERVER['HTTP_HOST'] . url('/');
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>Mantenimiento</title>
<style nonce="<?= csp_nonce() ?>">* { margin: 0; padding: 0; box-sizing: border-box; } body { font-family: system-ui; background: #f5f7fa; } .main-content { margin-left: 260px; padding: 20px; max-width: 900px; } .content-header h1 { font-size: 24px; margin-bottom: 20px; } .message { padding: 12px; border-radius: 6px; margin-bottom: 15px; } .message.success { background: #d4edda; color: #155724; } .message.error { background: #f8d7da; color: #721c24; } .card { background: white; border-radius: 8px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); } .form-group { margin-bottom: 18px; } .form-group label { display: block; margin-bottom: 6px; font-weight: 500; } .form-group input, .form-group textarea { width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 6px; } textarea { min-height: 80px; } .checkbox-group { display: flex; align-items: center; gap: 8px; } .checkbox-group input { width: auto; } .btn-save { padding: 12px 30px; background: #6c757d; color: white; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; } .btn-save.changed { background: #dc3545; animation: pulse 1.5s infinite; } .btn-save.saved { background: #28a745; } @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.8; } } .bypass-url { background: #f8f9fa; padding: 10px; border-radius: 6px; font-family: 'Courier New', monospace; font-size: 13px; word-break: break-all; border: 1px solid #dee2e6; margin-top: 8px; } .copy-btn { padding: 8px 16px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; margin-top: 8px; transition: all 0.3s; } .copy-btn:hover { background: #218838; } .copy-btn.copied { background: #0d6efd; animation: flash 0.5s; } @keyframes flash { 0%, 100% { opacity: 1; } 50% { opacity: 0.7; transform: scale(1.05); } } .help-text { font-size: 12px; color: #666; margin-top: 4px; } @media (max-width: 1024px) { .main-content { margin-left: 0; } }</style>
</head><body>
<?php include APP_PATH . '/includes/admin/sidebar.php'; ?>
<div class="main-content">
<?php include APP_PATH . '/includes/admin/header.php'; ?>
<?php if ($message): ?><div class="message success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="message error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<div class="card">
<form method="POST" id="configForm">
<input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
<div style="background: #e7f3ff; border-left: 4px solid #2196F3; padding: 12px; margin-bottom: 20px; border-radius: 4px; font-size: 13px;">
<strong>ℹ️ ¿Cómo funciona?</strong>
<p style="margin: 8px 0 0 0; color: #555;">
El modo mantenimiento bloquea el <strong>frontend</strong> (sitio público) pero el <strong>admin siempre queda activo</strong>.
El bypass code te permite acceder al frontend durante el mantenimiento para testing o compartir con clientes VIP.
</p>
</div>
<div class="form-group"><div class="checkbox-group"><input type="checkbox" id="enabled" name="enabled" <?= ($config['enabled'] ?? false) ? 'checked' : '' ?>><label for="enabled">Activar Modo Mantenimiento</label></div></div>
<div class="form-group"><label for="message">Mensaje</label><textarea id="message" name="message"><?= htmlspecialchars($config['message'] ?? '') ?></textarea></div>
<div class="form-group"><label for="bypass_code">Código Bypass</label><input type="text" id="bypass_code" name="bypass_code" value="<?= htmlspecialchars($config['bypass_code'] ?? '') ?>" placeholder="Ejemplo: acceso2024"><div class="help-text">Este código permite acceder al frontend durante el mantenimiento</div></div>
<?php if (!empty($config['bypass_code'])): ?>
<div class="form-group"><label>Link de acceso con bypass</label><div class="bypass-url" id="bypassUrl"><?= htmlspecialchars($site_url . '?bypass=' . $config['bypass_code']) ?></div><button type="button" class="copy-btn" data-action="copyBypassUrl">📋 Copiar Link</button><div class="help-text">Compartí este link para dar acceso al frontend durante el mantenimiento</div></div>
<?php endif; ?>
<button type="submit" name="save_config" class="btn-save" id="saveBtn">💾 Guardar</button>
</form></div></div>
<script nonce="<?= csp_nonce() ?>">
const form=document.getElementById('configForm'),saveBtn=document.getElementById('saveBtn'),inputs=form.querySelectorAll('input,textarea');
let o={},s=<?= $message?'true':'false'?>;
inputs.forEach(i=>o[i.name]=i.type==='checkbox'?i.checked:i.value);
inputs.forEach(i=>i.addEventListener('change',()=>{
    let c=Array.from(inputs).some(inp=>(inp.type==='checkbox'?inp.checked:inp.value)!==o[inp.name]);
    saveBtn.classList.toggle('changed',c);
    saveBtn.classList.toggle('saved',!c&&s);
}));
if(s){
    saveBtn.classList.add('saved');
    setTimeout(()=>saveBtn.classList.remove('saved'),3000);
}

function copyBypassUrl() {
    const url = document.getElementById('bypassUrl').textContent;
    const btn = event.target;
    const originalText = btn.textContent;

    navigator.clipboard.writeText(url).then(() => {
        // Cambiar texto y aplicar animación
        btn.textContent = '✅ Link copiado';
        btn.classList.add('copied');

        // Restaurar después de 2 segundos
        setTimeout(() => {
            btn.textContent = originalText;
            btn.classList.remove('copied');
        }, 2000);
    }).catch(() => {
        // Si falla, cambiar temporalmente el texto
        btn.textContent = '❌ Error al copiar';
        setTimeout(() => {
            btn.textContent = originalText;
        }, 2000);
    });
}

// Wrapper for event delegation
window.copyBypassUrl = copyBypassUrl;
</script>

<!-- Event Delegation System for CSP -->
<script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/event-handlers.js'); ?>"></script>
</body></html>
