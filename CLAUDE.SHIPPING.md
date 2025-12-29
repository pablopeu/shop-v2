# Shipping Integration (Zipnova & Multi-Carrier)

## Overview

The system includes a **full shipping/logistics integration** with Zipnova as the primary carrier, built on an extensible **multi-carrier architecture** for future integrations (Andreani, Correo Argentino, etc.).

## Key Components

**Backend:**
- **Configuration**: `app/config/shipping.json` - Multi-carrier settings
- **Core Logic**: `app/includes/carriers.php` (931 lines) - Universal carrier integration
- **Admin Panel**: `app/pages/admin/config-shipping.php` - Carrier configuration
- **Shipment Management**:
  - `app/pages/admin/envios-pendientes.php` - Pending shipments
  - `app/pages/admin/envios-archivo.php` - Archived shipments
- **API Endpoints**: `app/pages/api/shipping.php` - Quotes, create, track
- **Data Storage**: `app/data/shipments/` - Per-order shipping data

**Frontend:**
- **New Checkout**: `app/pages/frontend/checkout-new.php` (2800+ lines) - Vertical layout with shipping
- **JavaScript Module**: `public_html/assets/js/shipping.js` (500+ lines) - Frontend shipping logic

**Logs:**
- `/logs/zipnova/` - Daily event logs
- `/logs/zipnova-responses/` - Debug JSON responses

## Multi-Carrier Architecture

**Carrier Identification:**
- Carriers identified by **4-letter tags** (ZNVA for Zipnova, etc.)
- Extensible for future carriers (ANDR, OCAS, etc.)

**Universal Base Status:**
```
pendiente       → Shipment created, not yet dispatched
en_transito     → In transit to destination
en_reparto      → Out for delivery
entregada       → Successfully delivered
cancelada       → Cancelled by seller/customer
rechazada       → Rejected by recipient
devuelta        → Returned to sender
fallida         → Delivery failed
```

**Per-Carrier Configuration:**
```json
{
  "carriers": {
    "ZNVA": {
      "tag": "ZNVA",
      "name": "Zipnova",
      "type": "zipnova",
      "enabled": false,
      "mode": "sandbox",
      "credentials": {
        "account_id": "...",
        "client_id": "...",
        "client_secret": "..."
      },
      "origin": {
        "origin_id": "...",
        "name": "...",
        "address": "...",
        "city": "...",
        "province": "...",
        "postal_code": "...",
        "country": "AR",
        "phone": "...",
        "email": "..."
      },
      "default_package": {
        "weight": 500,
        "length": 20,
        "width": 15,
        "height": 10
      },
      "options": {
        "webhook_secret": "...",
        "auto_create_shipment": false,
        "shipping_cost_margin": 0,
        "cache_quotes_minutes": 30,
        "timeout_seconds": 30,
        "max_retries": 3
      },
      "enabled_services": {
        "standard": true,
        "express": true,
        "same_day": false
      }
    }
  }
}
```

## Orders Structure with Shipping

New `shipping` object in orders:

```json
{
  "shipping": {
    "method": "standard",
    "service_name": "Envío Estándar",
    "cost": 2500,
    "carrier": "ZNVA",
    "carrier_shipment_id": "123456",
    "carrier_status": "in_transit",
    "tracking_id": "TRACK123",
    "status": "en_transito",
    "address": {
      "name": "Juan Pérez",
      "street": "Av. Corrientes 1234",
      "city": "Buenos Aires",
      "province": "Buenos Aires",
      "postal_code": "C1043AAZ",
      "country": "AR",
      "phone": "+54 11 1234-5678"
    },
    "estimated_delivery": "3-5",
    "created_at": "2024-01-15T10:30:00Z",
    "updated_at": "2024-01-15T14:20:00Z",
    "history": [
      {
        "status": "pendiente",
        "timestamp": "2024-01-15T10:30:00Z",
        "notes": "Shipment created"
      },
      {
        "status": "en_transito",
        "timestamp": "2024-01-15T14:20:00Z",
        "notes": "Picked up by carrier"
      }
    ]
  }
}
```

## Common Shipping Functions

**Carrier Configuration:**
```php
get_carrier_config($carrier_tag)  // Get config for a specific carrier
get_all_carriers()                 // List all configured carriers
```

**Zipnova API:**
```php
// Get shipping quotes
zipnova_get_quotes($destination, $items, $value)

// Create shipment
zipnova_create_shipment($data)

// Get shipment status
zipnova_get_shipment($shipment_id)

// Cancel shipment
zipnova_cancel_shipment($shipment_id)

// Test API connection
zipnova_test_connection()
```

**Helper Functions:**
```php
// Calculate delivery time from ISO 8601 duration
calculate_delivery_days($delivery_time)  // e.g., "P3DT2H" → "3-5 días"

// Parse ISO 8601 duration to days
parse_iso8601_duration_to_days($duration)

// Build packages from cart items
zipnova_build_packages_from_cart($cart_items)

// Calculate cart metrics
zipnova_calculate_cart_weight($cart_items)
zipnova_calculate_cart_dimensions($cart_items)
zipnova_calculate_cart_value($cart_items, $currency)

// Status mapping
map_carrier_status_to_base($type, $status)  // Map carrier status → base status
get_status_label($status)                   // Get human-readable label

// Render status HTML
render_shipping_status($status)
```

**Logging:**
```php
zipnova_log($message, $level, $context)           // Log events
zipnova_save_response_json($response, $endpoint)  // Save debug JSON
```

## New Vertical Checkout (`checkout-new.php`)

**Features:**
- Vertical responsive layout (2-column on desktop, stacked on mobile)
- Step-by-step validation (blocked until previous steps complete)
- Delivery method selection (pickup vs shipping)
- Real-time shipping quotes from Zipnova
- Automatic weight/dimension calculation from cart
- Shipping cost integration in total
- Session timeout (1 hour)
- Multi-currency support (ARS/USD)
- MercadoPago integration with shipping cost included

**Shipping Calculation:**
- Weight from product data or defaults (500g per item if missing)
- Dimensions from product data or defaults (20×15×10 cm if missing)
- Declared value = total cart value in ARS
- Automatic package consolidation

**Flow:**
1. Select delivery method (retiro/envío)
2. If envío → Enter shipping address
3. Click "Cotizar Envío" → Get real-time quotes from Zipnova
4. Select shipping service → Cost added to total
5. Complete customer info
6. Proceed to MercadoPago payment (includes shipping cost)

## API Endpoints

**Shipping API (`/api/shipping`):**

```php
// Get quotes
GET  /api/shipping?action=quotes&postal_code=1234&city=...
POST /api/shipping (with full address + cart data)

// Create shipment
POST /api/shipping?action=create

// Track shipment
GET  /api/shipping?action=track&id=SHIPMENT_ID

// Webhook (for carrier status updates)
POST /api/shipping (with webhook signature)
```

## Admin Shipment Management

**Pending Shipments (`envios-pendientes.php`):**
- List all pending shipments
- Filter by status, reference, date
- Create shipment in carrier system
- Cancel shipment
- View tracking details
- Export to CSV

**Archived Shipments (`envios-archivo.php`):**
- Historical record of completed/cancelled shipments
- Same filters and export options

## Security Features

**Zipnova API:**
- HTTP Basic Authentication (client_id:client_secret)
- Retry logic with exponential backoff (max 3 retries)
- Request timeout (30 seconds default)
- Rate limiting
- Webhook signature validation
- Detailed logging of all requests/responses

**Data Validation:**
- Address validation (required fields, postal code format)
- Weight/dimension validation
- Package value validation
- Service availability checks

## Future Carrier Integration

The architecture is ready for:
- **Andreani** (tag: ANDR)
- **Correo Argentino** (tag: OCAS)
- **DHL** (tag: DHLE)
- Custom carriers with adapter pattern

**Adding a new carrier:**
1. Create carrier config in `shipping.json`
2. Implement carrier-specific functions in `carriers.php`
3. Map carrier statuses to base statuses
4. Add to admin UI in `config-shipping.php`
