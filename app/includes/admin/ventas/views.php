<?php
// Bootstrap ya maneja: APP_ENTRY_POINT, includes, session
/**
 * Ventas Views - Componentes de vista reutilizables
 * Funciones para renderizar secciones HTML del panel de ventas
 */

// Prevent direct access
if (!defined('ADMIN_ACCESS')) {
    die('Direct access not permitted');
}

/**
 * Render statistics cards
 * @param array $stats Statistics array from calculate_order_stats()
 */
function render_stats_cards($stats) {
    ?>
    <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
        <div class="stat-card" style="border-left: 4px solid #3498db;">
            <div class="stat-value">$<?php echo number_format($stats['total_orders_amount'], 2, ',', '.'); ?></div>
            <div class="stat-label">Total Órdenes</div>
            <div style="font-size: 13px; color: #999; margin-top: 4px;">
                <?php echo $stats['total_orders']; ?> operaciones
            </div>
        </div>
        <div class="stat-card" style="border-left: 4px solid #FF9800;">
            <div class="stat-value">$<?php echo number_format($stats['unpaid_amount'], 2, ',', '.'); ?></div>
            <div class="stat-label">Impago</div>
            <div style="font-size: 13px; color: #999; margin-top: 4px;">
                <?php echo $stats['unpaid_orders']; ?> operaciones
            </div>
        </div>
        <div class="stat-card" style="border-left: 4px solid #4CAF50;">
            <div class="stat-value">$<?php echo number_format($stats['paid_amount_gross'], 2, ',', '.'); ?></div>
            <div class="stat-label">Pagado (Bruto)</div>
            <div style="font-size: 13px; color: #999; margin-top: 4px;">
                <?php echo $stats['confirmed_orders']; ?> operaciones
            </div>
        </div>
        <div class="stat-card" style="border-left: 4px solid #dc3545;">
            <div class="stat-value">$<?php echo number_format($stats['total_fees'], 2, ',', '.'); ?></div>
            <div class="stat-label">Comisiones MP</div>
            <div style="font-size: 13px; color: #999; margin-top: 4px;">
                de <?php echo $stats['confirmed_orders']; ?> ventas pagadas
            </div>
        </div>
        <div class="stat-card" style="border-left: 4px solid #27ae60;">
            <div class="stat-value">$<?php echo number_format($stats['net_revenue'], 2, ',', '.'); ?></div>
            <div class="stat-label">Ingreso Neto</div>
            <div style="font-size: 13px; color: #999; margin-top: 4px;">
                Pagado - Comisiones
            </div>
        </div>
    </div>
    <?php
}

/**
 * Render advanced filters form
 * @param array $filters Current filter values
 */
function render_advanced_filters($filters) {
    ?>
    <div class="card">
        <div class="card-header">Filtros Avanzados</div>
        <form method="GET" action="" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; align-items: end;">
            <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filters['status']); ?>">

            <div class="form-group" style="margin: 0;">
                <label for="search" style="font-size: 13px; margin-bottom: 5px; display: block;">Buscar (Nro pedido, cliente, email)</label>
                <input type="text" id="search" name="search" placeholder="Ej: 1001 o Juan Perez"
                       value="<?php echo htmlspecialchars($filters['search']); ?>"
                       style="padding: 8px; border: 1px solid #ddd; border-radius: 4px; width: 100%;">
            </div>

            <div class="form-group" style="margin: 0;">
                <label for="date_from" style="font-size: 13px; margin-bottom: 5px; display: block;">Desde</label>
                <input type="date" id="date_from" name="date_from"
                       value="<?php echo htmlspecialchars($filters['date_from']); ?>"
                       style="padding: 8px; border: 1px solid #ddd; border-radius: 4px; width: 100%;">
            </div>

            <div class="form-group" style="margin: 0;">
                <label for="date_to" style="font-size: 13px; margin-bottom: 5px; display: block;">Hasta</label>
                <input type="date" id="date_to" name="date_to"
                       value="<?php echo htmlspecialchars($filters['date_to']); ?>"
                       style="padding: 8px; border: 1px solid #ddd; border-radius: 4px; width: 100%;">
            </div>

            <div style="display: flex; gap: 8px;">
                <button type="submit" class="btn btn-primary btn-sm">Aplicar Filtros</button>
                <a href="?" class="btn btn-secondary btn-sm">Limpiar</a>
            </div>
        </form>
    </div>
    <?php
}

/**
 * Render compact actions bar with bulk actions and status filters
 * @param array $filters Current filter values
 * @param string $csrf_token CSRF token for forms
 */
function render_compact_actions_bar($filters, $csrf_token) {
    ?>
    <div class="card">
        <div class="compact-actions-bar">
            <!-- Bulk Actions Form -->
            <form method="POST" id="bulkForm" class="bulk-actions-section">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <select name="bulk_action" id="bulkAction">
                    <option value="">Seleccionar acción...</option>
                    <option value="impago">Marcar como Impago</option>
                    <option value="pagado">Marcar como Pagado</option>
                    <option value="lista_retiro">Marcar Lista para Retiro</option>
                    <option value="en_transito">Marcar En Tránsito</option>
                    <option value="en_reparto">Marcar En Reparto</option>
                    <option value="entregada">Marcar como Entregada</option>
                    <option value="cancelada">Cancelar</option>
                    <option value="archive">Archivar</option>
                </select>
                <button type="submit" class="btn btn-primary btn-sm" data-action="confirmBulkAction">Aplicar a Seleccionadas</button>
                <a href="?page=archivo-ventas" class="btn btn-secondary btn-sm">Ver Archivo</a>
                <button type="button" class="btn btn-primary btn-sm" data-action="exportSelectedToCSV">📊 Exportar CSV</button>
                <span id="selectedCount"></span>
            </form>

            <!-- Status Filters -->
            <div class="status-filters-section">
                <a href="?page=ventas&filter=all" class="filter-btn <?php echo $filters['status'] === 'all' ? 'active' : ''; ?>">Todas</a>
                <a href="?page=ventas&filter=impago" class="filter-btn <?php echo $filters['status'] === 'impago' ? 'active' : ''; ?>">Impago</a>
                <a href="?page=ventas&filter=pagado" class="filter-btn <?php echo $filters['status'] === 'pagado' ? 'active' : ''; ?>">Pagado</a>
                <a href="?page=ventas&filter=lista_retiro" class="filter-btn <?php echo $filters['status'] === 'lista_retiro' ? 'active' : ''; ?>">Lista Retiro</a>
                <a href="?page=ventas&filter=en_transito" class="filter-btn <?php echo $filters['status'] === 'en_transito' ? 'active' : ''; ?>">En Tránsito</a>
                <a href="?page=ventas&filter=entregada" class="filter-btn <?php echo $filters['status'] === 'entregada' ? 'active' : ''; ?>">Entregada</a>
                <a href="?page=ventas&filter=cancelada" class="filter-btn <?php echo $filters['status'] === 'cancelada' ? 'active' : ''; ?>">Cancelada</a>
            </div>
        </div>
    <?php
}

/**
 * Render orders table with all orders
 * @param array $orders Filtered orders array
 * @param array $filters Current filter values
 * @param array $status_labels Status label mappings
 */
function render_orders_table($orders, $filters, $status_labels) {
    ?>
        <!-- Orders Table -->
        <div class="table-container">
            <table class="orders-table">
            <thead>
                <tr>
                    <th style="width: 40px;">
                        <input type="checkbox" id="selectAll" data-onchange="toggleAllCheckboxes">
                    </th>
                    <th>Pedido #</th>
                    <th>Cliente</th>
                    <th>Fecha</th>
                    <th>Total ARS</th>
                    <th>Total USD</th>
                    <th>Cotiz. $</th>
                    <th>Método de Pago</th>
                    <th>Estado</th>
                    <th>Envío</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="11" style="text-align: center; padding: 40px; color: #999;">
                            No hay órdenes<?php echo $filters['status'] !== 'all' ? ' con este estado' : ''; ?>.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                        <?php
                        // Calculate amounts in ARS and USD
                        $exchange_rate = $order['exchange_rate'] ?? 1000;
                        $total = $order['total'];
                        $currency = $order['currency'];

                        if ($currency === 'USD') {
                            $total_usd = $total;
                            $total_ars = $total * $exchange_rate;
                        } else {
                            $total_ars = $total;
                            $total_usd = $total / $exchange_rate;
                        }
                        ?>
                        <tr>
                            <td>
                                <input type="checkbox" name="selected_orders[]"
                                       value="<?php echo htmlspecialchars($order['id']); ?>"
                                       class="order-checkbox"
                                       form="bulkForm"
                                       data-onchange="updateSelectedCount">
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($order['order_number']); ?></strong>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($order['customer_name'] ?? 'N/A'); ?><br>
                                <small style="color: #666;">
                                    <?php echo htmlspecialchars($order['customer_email'] ?? ''); ?>
                                </small>
                            </td>
                            <td>
                                <?php echo date('d/m/Y H:i', strtotime($order['date'])); ?>
                            </td>
                            <td>
                                <strong>$<?php echo number_format($total_ars, 2, ',', '.'); ?></strong>
                            </td>
                            <td>
                                <strong>U$D <?php echo number_format($total_usd, 2, ',', '.'); ?></strong>
                            </td>
                            <td>
                                <small><?php echo number_format($exchange_rate, 2, ',', '.'); ?></small>
                            </td>
                            <td>
                                <?php
                                $payment_icons = [
                                    'mercadopago' => '💳',
                                    'arrangement' => '🤝',
                                    'pickup_payment' => '💵',
                                    'presencial' => '💵'
                                ];
                                $icon = $payment_icons[$order['payment_method']] ?? '💵';
                                echo $icon . ' ';

                                if ($order['payment_method'] === 'mercadopago') {
                                    echo 'Mercadopago';
                                } elseif ($order['payment_method'] === 'arrangement') {
                                    echo 'Arreglo vendedor';
                                } elseif ($order['payment_method'] === 'pickup_payment') {
                                    echo 'Pago al retirar';
                                } else {
                                    echo 'Presencial';
                                }
                                ?>
                            </td>
                            <td>
                                <?php
                                $status = $order['status'];
                                $label = $status_labels[$status]['label'] ?? ucfirst($status);
                                $color = $status_labels[$status]['color'] ?? '#999';
                                ?>
                                <span class="status-badge" style="background: <?php echo $color; ?>;">
                                    <?php echo $label; ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                // Check if order has shipping
                                $has_carrier_shipment = !empty($order['shipping']['carrier_shipment_id']);
                                $has_shipping_data = !empty($order['shipping_quote_data']['rate_id']) || !empty($order['shipping_service_id']);
                                $shipment_id = $order['shipping']['carrier_shipment_id'] ?? '';
                                $carrier = $order['shipping']['carrier'] ?? ($order['shipping_quote_data']['carrier_name'] ?? '');
                                ?>
                                <?php if ($has_carrier_shipment): ?>
                                    <!-- Ya existe envío creado -->
                                    <div style="display: flex; flex-direction: column; gap: 5px; align-items: center;">
                                        <small style="color: #666; font-size: 11px;">
                                            <?php echo htmlspecialchars($carrier); ?> #<?php echo htmlspecialchars(substr($shipment_id, -6)); ?>
                                        </small>
                                        <button type="button" class="btn btn-sm"
                                                style="background: #667eea; color: white; font-size: 11px; padding: 4px 8px;"
                                                data-action="printShippingLabel"
                                                data-order-id="<?php echo htmlspecialchars($order['id']); ?>"
                                                data-shipment-id="<?php echo htmlspecialchars($shipment_id); ?>"
                                                title="Imprimir etiqueta de envío">
                                            🖨️ Etiqueta
                                        </button>
                                    </div>
                                <?php elseif ($has_shipping_data): ?>
                                    <!-- Tiene datos de shipping pero no se ha creado envío aún -->
                                    <div style="display: flex; flex-direction: column; gap: 5px; align-items: center;">
                                        <small style="color: #28a745; font-size: 11px;">
                                            <?php echo htmlspecialchars($carrier); ?>
                                        </small>
                                        <button type="button" class="btn btn-sm"
                                                style="background: #28a745; color: white; font-size: 11px; padding: 4px 8px;"
                                                data-action="createAndPrintShippingLabel"
                                                data-order-id="<?php echo htmlspecialchars($order['id']); ?>"
                                                title="Crear envío y obtener etiqueta">
                                            📦 Crear
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <small style="color: #999;">-</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="actions">
                                    <button type="button" class="btn btn-primary btn-sm"
                                            data-action="viewOrder"
                                            data-order-id="<?php echo htmlspecialchars($order['id']); ?>">
                                        Ver
                                    </button>
                                    <button type="button" class="btn btn-secondary btn-sm"
                                            data-action="showArchiveModal"
                                            data-order-id="<?php echo htmlspecialchars($order['id']); ?>"
                                            data-order-number="<?php echo htmlspecialchars($order['order_number']); ?>">
                                        Archivar
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            </table>
        </div>
    </div>
    <?php
}
