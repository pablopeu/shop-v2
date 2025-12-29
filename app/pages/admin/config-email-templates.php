<?php
/**
 * Admin - Email Templates Configuration
 * Configuración de plantillas de emails del sistema
 */

require_admin();

$message = '';
$error = '';

// Save template
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_template'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        $templates_file = APP_PATH . '/config/email-templates.json';
        $templates = read_json($templates_file);

        $template_name = $_POST['template_name'] ?? '';

        if (isset($templates[$template_name])) {
            $templates[$template_name] = [
                'subject' => sanitize_input($_POST['subject'] ?? ''),
                'title' => sanitize_input($_POST['title'] ?? ''),
                'content' => $_POST['content'] ?? '' // Allow HTML
            ];

            if (write_json($templates_file, $templates)) {
                $_SESSION['email_templates_message'] = 'Template guardado exitosamente';
                log_admin_action('email_template_updated', $_SESSION['username'], ['template' => $template_name]);

                header('Location: ' . url('/admin/?page=config-email-templates&active=' . $template_name));
                exit;
            } else {
                $error = 'Error al guardar el template';
            }
        } else {
            $error = 'Template no encontrado';
        }
    }
}

// Check for message from redirect
if (isset($_SESSION['email_templates_message'])) {
    $message = $_SESSION['email_templates_message'];
    unset($_SESSION['email_templates_message']);
}

// Load templates
$templates_file = APP_PATH . '/config/email-templates.json';
$templates = read_json($templates_file);

// Get active template from query parameter
$active_template = $_GET['active'] ?? 'order_shipped';

// Template labels
$template_labels = [
    'order_created' => 'Orden Creada',
    'order_paid' => 'Pago Confirmado',
    'order_shipped' => 'Pedido Enviado',
    'order_in_delivery' => 'En Reparto',
    'order_delivered' => 'Pedido Entregado'
];

// Get site config
$site_config = read_json(APP_PATH . '/config/site.json');
$page_title = 'Templates de Email';
$csrf_token = generate_csrf_token();
$user = get_logged_user();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Admin</title>

    <style nonce="<?= csp_nonce() ?>">
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            color: #333;
        }

        .main-content {
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
        }

        .card {
            background: white;
            border-radius: 8px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .card h2 {
            margin-bottom: 20px;
            color: #333;
            font-size: 24px;
        }

        .message {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
            overflow-x: auto;
        }

        .tab {
            padding: 12px 20px;
            background: transparent;
            border: none;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            color: #666;
            white-space: nowrap;
            transition: all 0.3s;
        }

        .tab:hover {
            color: #667eea;
            background: #f8f9fa;
        }

        .tab.active {
            color: #667eea;
            border-bottom-color: #667eea;
            background: #f8f9fa;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-group input[type="text"],
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.3s;
        }

        .form-group input[type="text"]:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 200px;
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            font-size: 13px;
        }

        .form-group .help-text {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        .variables-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            border-left: 4px solid #667eea;
            margin-bottom: 20px;
        }

        .variables-info h4 {
            margin-bottom: 10px;
            color: #667eea;
        }

        .variables-info code {
            background: #e9ecef;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 12px;
            color: #d63384;
        }

        .variables-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }

        .variable-item {
            font-size: 13px;
        }

        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-save {
            background: #667eea;
            color: white;
        }

        .btn-save:hover {
            background: #5568d3;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(102, 126, 234, 0.3);
        }

        .btn-preview {
            background: #28a745;
            color: white;
        }

        .btn-preview:hover {
            background: #218838;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        /* Preview Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 10000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content-large {
            background: white;
            border-radius: 8px;
            max-width: 900px;
            width: 90%;
            max-height: 90vh;
            overflow: auto;
            position: relative;
        }

        .modal-header {
            padding: 20px 30px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            background: white;
            z-index: 1;
        }

        .modal-close {
            font-size: 28px;
            font-weight: bold;
            color: #999;
            cursor: pointer;
            line-height: 1;
        }

        .modal-close:hover {
            color: #333;
        }

        .modal-body {
            padding: 30px;
        }

        .preview-frame {
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            background: #f5f5f5;
            padding: 20px;
        }
    </style>
</head>
<body>
    <?php include APP_PATH . '/includes/admin/sidebar.php'; ?>

    <div class="main-content">
        <?php include APP_PATH . '/includes/admin/header.php'; ?>

        <?php if ($message): ?>
            <div class="message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card">
            <h2>📧 Templates de Email</h2>
            <p style="color: #666; margin-bottom: 30px;">
                Personaliza los emails que se envían a los clientes en cada etapa del pedido.
            </p>

            <!-- Tabs -->
            <div class="tabs">
                <?php foreach ($template_labels as $key => $label): ?>
                    <button class="tab <?php echo $key === $active_template ? 'active' : ''; ?>"
                            data-action="switchTemplateTab"
                            data-tab-name="<?php echo $key; ?>">
                        <?php echo htmlspecialchars($label); ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <!-- Tab Contents -->
            <?php foreach ($templates as $template_name => $template_data): ?>
                <div class="tab-content <?php echo $template_name === $active_template ? 'active' : ''; ?>"
                     id="tab-<?php echo $template_name; ?>">

                    <!-- Variables Info -->
                    <div class="variables-info">
                        <h4>📝 Variables Disponibles</h4>
                        <p style="font-size: 13px; margin-bottom: 10px;">
                            Usa estas variables en el subject, title o content. Serán reemplazadas automáticamente:
                        </p>
                        <div class="variables-list">
                            <div class="variable-item"><code>{order_number}</code> - Número de orden</div>
                            <div class="variable-item"><code>{date}</code> - Fecha actual</div>
                            <div class="variable-item"><code>{items_list}</code> - Lista de productos</div>
                            <div class="variable-item"><code>{tracking_info}</code> - Info de seguimiento</div>
                            <div class="variable-item"><code>{total}</code> - Total del pedido</div>
                            <div class="variable-item"><code>{site_name}</code> - Nombre del sitio</div>
                        </div>
                    </div>

                    <form method="POST" action="" id="form-<?php echo $template_name; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="save_template" value="1">
                        <input type="hidden" name="template_name" value="<?php echo $template_name; ?>">

                        <div class="form-group">
                            <label for="subject-<?php echo $template_name; ?>">Asunto del Email</label>
                            <input type="text"
                                   id="subject-<?php echo $template_name; ?>"
                                   name="subject"
                                   value="<?php echo htmlspecialchars($template_data['subject']); ?>"
                                   required>
                            <div class="help-text">El asunto que verá el cliente en su bandeja de entrada</div>
                        </div>

                        <div class="form-group">
                            <label for="title-<?php echo $template_name; ?>">Título del Email</label>
                            <input type="text"
                                   id="title-<?php echo $template_name; ?>"
                                   name="title"
                                   value="<?php echo htmlspecialchars($template_data['title']); ?>"
                                   required>
                            <div class="help-text">El título principal que aparece dentro del email</div>
                        </div>

                        <div class="form-group">
                            <label for="content-<?php echo $template_name; ?>">Contenido del Email (HTML)</label>
                            <textarea id="content-<?php echo $template_name; ?>"
                                      name="content"
                                      required><?php echo htmlspecialchars($template_data['content']); ?></textarea>
                            <div class="help-text">
                                Puedes usar HTML básico: &lt;p&gt;, &lt;strong&gt;, &lt;ul&gt;, &lt;li&gt;, &lt;div&gt;, etc.
                            </div>
                        </div>

                        <div class="btn-group">
                            <button type="submit" class="btn btn-save">
                                💾 Guardar Template
                            </button>
                            <button type="button" class="btn btn-preview"
                                    data-action="previewTemplate"
                                    data-template-name="<?php echo $template_name; ?>">
                                👁️ Vista Previa
                            </button>
                        </div>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Preview Modal -->
    <div id="previewModal" class="modal">
        <div class="modal-content-large">
            <div class="modal-header">
                <h2>Vista Previa del Email</h2>
                <span class="modal-close" data-action="closePreviewModal">&times;</span>
            </div>
            <div class="modal-body">
                <div class="preview-frame" id="previewContent">
                    <!-- Preview will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script nonce="<?= csp_nonce() ?>">
        // Switch template tab
        function switchTemplateTab(event, element, params) {
            const tabName = params?.tabName;
            if (!tabName) return;

            // Update URL
            const url = new URL(window.location);
            url.searchParams.set('active', tabName);
            window.history.pushState({}, '', url);

            // Update tabs
            document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
            element.classList.add('active');

            // Update content
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            document.getElementById('tab-' + tabName).classList.add('active');
        }

        // Preview template
        function previewTemplate(event, element, params) {
            const templateName = params?.templateName;
            if (!templateName) return;

            const form = document.getElementById('form-' + templateName);
            const formData = new FormData(form);

            // Show loading
            const modal = document.getElementById('previewModal');
            const previewContent = document.getElementById('previewContent');
            previewContent.innerHTML = '<p style="text-align: center; padding: 40px; color: #999;">⏳ Cargando vista previa...</p>';
            modal.classList.add('active');

            // Send preview request
            fetch('<?php echo url('/api/?endpoint=preview-email-template'); ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    previewContent.innerHTML = data.html;
                } else {
                    previewContent.innerHTML = '<p style="color: red;">Error: ' + (data.error || 'Error desconocido') + '</p>';
                }
            })
            .catch(error => {
                console.error('Preview error:', error);
                previewContent.innerHTML = '<p style="color: red;">Error al cargar la vista previa</p>';
            });
        }

        // Close preview modal
        function closePreviewModal() {
            document.getElementById('previewModal').classList.remove('active');
        }

        // Export for event delegation
        window.switchTemplateTab = switchTemplateTab;
        window.previewTemplate = previewTemplate;
        window.closePreviewModal = closePreviewModal;

        // Close modal on outside click
        document.getElementById('previewModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closePreviewModal();
            }
        });
    </script>

    <?php include APP_PATH . '/includes/admin/modal.php'; ?>
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/event-handlers.js'); ?>"></script>
</body>
</html>
