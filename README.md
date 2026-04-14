# SyncEngine — Magento 2 Connector

Magento 2 extension that enhances integration between Magento 2 and a [SyncEngine](https://syncengine.io) instance by automatically discovering and triggering configured automations when products, customers, and orders change.

## Features

- **Enhanced Media Gallery API** — Adds full URL and relative path support to product Media Gallery endpoints (instead of base64-only encoding)
- **Enhanced REST API queries** — Adds query parameter enhancements for advanced filtering on REST endpoints
- **SyncEngine API connection** — Connect your Magento instance to SyncEngine via admin configuration (host + token)
- **Automatic event triggers** — Fires connected SyncEngine automations on Magento entity events (products, orders, customers)
- **Intelligent trigger mapping** — Auto-discovers applicable automations from SyncEngine based on trigger blueprints and connection matching
- **Auto-refresh mechanism** — Trigger endpoint map is refreshed when a Magento connection is tested or related automation is saved in SyncEngine
- **Refresh trust & throttling** — Protects the plugin from request storms with tiered throttling based on request origin
- **Admin debug UI** — Dedicated trigger dispatch debug page showing trigger map status, recent dispatch logs, and manual refresh controls
- **Developer extension points** — Code-level filters for modifying payloads and controlling dispatch behavior

---

## Requirements

- **Magento 2.4+** or compatible extension environment
- An active and reachable [SyncEngine](https://syncengine.io) installation
- A SyncEngine API token with **read** access to:
  - Automations (to discover trigger mappings)
  - Connections (to validate local connection references)
  - Endpoints (optional, for endpoint availability checking)
- For automatic trigger discovery and mapping, the corresponding SyncEngine module blueprints must be installed. Check the [SyncEngine Marketplace](https://marketplace.syncengine.io/) for available modules.

---

## Installation

1. Install the extension via Composer:
   ```bash
   composer require syncengine/connector
   ```
2. Enable the module:
   ```bash
   bin/magento module:enable SyncEngine_Connector
   bin/magento setup:upgrade
   ```
3. Recompile if in production mode:
   ```bash
   bin/magento setup:di:compile
   ```
4. Navigate to **Stores → Configuration → SyncEngine → Connector** (or **System → Configuration** in older Magento UI).
5. Configure the connector:
   - **SyncEngine Host** — Full URL of your SyncEngine instance (e.g., `https://syncengine.example.com`)
   - **SyncEngine Token** — API token with read access to automations and connections
   - **Auth Header** (optional) — Custom authentication header name. Defaults to `Authorization: Bearer <token>` when empty.
6. Toggle **Enable trigger dispatching** to **Yes**.
7. Click **Save Config**. The module will immediately attempt to connect and discover automations.

---

## Configuration

All settings are stored in Magento's configuration under the `syncengine/connector/` path.

### API Connection Settings

| Setting | Config Path | Description | Example |
|---|---|---|---|
| **Enable Connector** | `syncengine/connector/enabled` | Enable/disable the entire connector module | Yes/No |
| **SyncEngine Host** | `syncengine/connector/api_host` | Base URL of your SyncEngine instance | `https://syncengine.example.com` |
| **SyncEngine Token** | `syncengine/connector/api_token` | API authentication token | `sk_live_abc123...` |
| **Auth Header** | `syncengine/connector/api_auth_header` | Custom auth header name (optional) | `X-Custom-Auth` or empty |
| **Enable trigger dispatching** | `syncengine/connector/triggers_enabled` | Master switch for all event triggers | Yes/No |

---

## Supported Magento Events

The extension automatically detects and triggers SyncEngine automations for the following Magento entity events.

### Trigger Events

| Entity | Event | Trigger Key | SyncEngine Blueprint Class | Magento Event |
|---|---|---|---|---|
| Product | New | `new_product` | `SyncEngine/Magento2RestV1:NewProduct` | `catalog_product_save_after` (+ `getOrigData` check) |
| Product | Updated | `updated_product` | `SyncEngine/Magento2RestV1:UpdatedProduct` | `catalog_product_save_after` |
| Product | Deleted | `deleted_product` | `SyncEngine/Magento2RestV1:DeletedProduct` | `catalog_product_delete_after` |
| Order | New | `new_order` | `SyncEngine/Magento2RestV1:NewOrder` | `sales_order_save_after` (+ `getOrigData` check) |
| Order | Updated | `updated_order` | `SyncEngine/Magento2RestV1:UpdatedOrder` | `sales_order_save_after` |
| Order | Deleted | `deleted_order` | `SyncEngine/Magento2RestV1:DeletedOrder` | `sales_order_delete_after` |
| Customer | New | `new_customer` | `SyncEngine/Magento2RestV1:NewCustomer` | `customer_save_after` (+ `getOrigData` check) |
| Customer | Updated | `updated_customer` | `SyncEngine/Magento2RestV1:UpdatedCustomer` | `customer_save_after` |
| Customer | Deleted | `deleted_customer` | `SyncEngine/Magento2RestV1:DeletedCustomer` | `customer_delete_after` |

### Payload Structure

All payloads follow a consistent structure. For active/updated entities, the payload includes fetched entity data via REST API. For deleted entities, a minimal payload with just ID is sent.

**Example product payload:**
```json
{
  "id": 123,
  "event": "magento_new_product",
  "data": {
    "id": 123,
    "sku": "PROD-001",
    "name": "Example Product",
    "status": 1,
    "type_id": "simple",
    "price": 99.99,
    "attribute_set_id": 9,
    ...
  },
  "request": { "id": 123 }
}
```

### Trigger Discovery

The module executes a discovery process to build a dynamic trigger-to-endpoint map:

1. **Automation Query** — Fetches all automations from SyncEngine API
2. **Blueprint Matching** — Compares each automation's trigger blueprint class against the table above
3. **Connection Verification** — Validates that the automation's connected Magento connection matches this store's base URL
4. **Endpoint Collection** — Collects the endpoint slug for all matching automations
5. **Cache Storage** — Stores the resulting map in Magento's configuration cache with configurable TTL

The discovery result is **cached** (default 5-minute TTL) to reduce API calls.

The cache is automatically cleared (triggering a refresh on next event) when:
- Configuration is saved in System → Configuration or Stores → Configuration
- A Magento connection is tested in SyncEngine (via the "Connect" button)
- An automation using a Magento trigger blueprint is saved in SyncEngine
- The `/rest/V1/syncengine/trigger-map/refresh` endpoint is called

---

## REST API Endpoints

The extension registers the following REST API endpoints for integration with SyncEngine:

| Method | Route | Auth Required | Description |
|---|---|---|---|
| `GET` | `/rest/V1/syncengine/status` | No | Returns connector status (`online`/details) |
| `POST` | `/rest/V1/syncengine/trigger-map/refresh` | No | Clears trigger map cache; triggers discovery on next event. Throttled (see below). |

Both endpoints are intentionally **unauthenticated** — the `/refresh` endpoint is stateless and only clears cached data; actual event dispatch is protected by Magento's observer system.

### Refresh Throttling

Requests to `/rest/V1/syncengine/trigger-map/refresh` are throttled based on request origin. The extension identifies trusted SyncEngine requests via the `X-SyncEngine-Connection` header.

| Request Type | Throttle Window | Cache Key |
|---|---|---|
| Anonymous / Unrecognized | 10 seconds | `syncengine_refresh_throttle_untrusted` |
| Trusted (SyncEngine origin) | 1 second | `syncengine_refresh_throttle_trusted` |

When a throttle window is active, the endpoint returns `{"refreshed": false, "reason": "throttled"}` but still returns `success: true`.

**Custom throttle configuration (via `etc/config.xml`):**
```xml
<config>
    <default>
        <syncengine>
            <connector>
                <refresh_throttle_ttl>10</refresh_throttle_ttl>
                <refresh_throttle_trusted_ttl>1</refresh_throttle_trusted_ttl>
            </connector>
        </syncengine>
    </default>
</config>
```

---

## Admin Debug UI

Navigate to **System → Tools → SyncEngine Trigger Debug** to access:

- **Current Connection Status** — Shows if the Magento connection to SyncEngine is active
- **Trigger Map Status** — Displays all discovered triggers and their mapped endpoints
- **Manual Refresh** — Button to immediately refresh the trigger map cache
- **Recent Dispatch Log** — List of recently triggered endpoints with timestamp and result
- **Skip Log** — Events that were skipped due to configuration or filter conditions

---

## Media Gallery API Enhancement

The extension enhances the product Media Gallery API endpoints to support:

- **Full URLs** — Include complete URLs to media files (not base64-encoded)
- **Relative paths** — Use relative paths for CDN-based media

This is handled via the `MediaGalleryProcessorPlugin` which intercepts gallery processing during product REST API responses.

---

## Developer Integration

### Trigger Dispatch Hooks & Filters

The extension provides extension points via Magento's event system and plugin pattern.

**Observe trigger events:**
```php
// In etc/events.xml
<event name="syncengine_trigger_dispatched">
    <observer name="my_observer" instance="MyModule\Observer\SyncEngineTriggerObserver" />
</event>

// In your observer
public function execute(\Magento\Framework\Event\Observer $observer)
{
    $trigger = $observer->getData('trigger');
    $payload = $observer->getData('payload');
    $results = $observer->getData('results');
    // React to trigger dispatch
}
```

**Modify payload via plugins:**
```php
// In your di.xml
<type name="SyncEngine\Connector\Service\MagentoRestPayloadService">
    <plugin name="my_payload_modifier" type="MyModule\Plugin\PayloadModifier" />
</type>

// In your plugin
public function afterGetProductData(
    \SyncEngine\Connector\Service\MagentoRestPayloadService $subject,
    $result
) {
    // Modify $result before it's used as payload
    return $result;
}
```

### Dispatch Logging

The `DispatchLogService` logs all endpoint dispatch attempts. Access logs programmatically:

```php
$this->dispatchLogService->getRecentDispatches(10); // Last 10 dispatches
$this->dispatchLogService->getSkippedEvents(5);     // Last 5 skipped events
```

---

## Execution Flow

1. **Event occurs** — Magento observes a product/order/customer save or delete event
2. **Observer triggered** — Corresponding observer (ProductSaveObserver, etc.) intercepts the event
3. **Payload built** — `MagentoRestPayloadService` fetches full entity data via REST API
4. **Trigger resolved** — Maps operation (new/updated/deleted) to trigger key
5. **Endpoint lookup** — `MagentoPlatformService` fetches cached or refreshes trigger-to-endpoint map
6. **Dispatch** — `EndpointDispatcherService` calls each matched endpoint with the payload
7. **Logging** — `DispatchLogService` records the dispatch result for admin debugging

---

## Troubleshooting

### Extension not triggering automations

**Check:**
1. Is the extension enabled? (`bin/magento module:list | grep SyncEngine`)
2. Is **Enable trigger dispatching** set to "Yes" in configuration?
3. Do matching automations exist in SyncEngine with the correct trigger type?
4. Are the Magento connection and store base URL correctly configured in SyncEngine?
5. Check Magento logs: `var/log/system.log` and `var/log/syncengine.log`

### Trigger map not updating

**Check:**
1. Call the refresh endpoint manually: `POST /rest/V1/syncengine/trigger-map/refresh`
2. Verify the SyncEngine API token has read access to automations and connections
3. Check that network connectivity exists between Magento and SyncEngine
4. Verify the Magento store URL matches the connection URL in SyncEngine

### High refresh throttle rejections

**Solution:** Externally throttle calls to the refresh endpoint or adjust the throttle TTL configuration values above for your use case.

### Permission Denied on API endpoints

**Check:**
1. Are the REST endpoints properly registered? Check `etc/webapi.xml`
2. Do customer REST permissions allow access to `/V1/syncengine/*` routes?
3. For unauthenticated access, verify Magento's REST API configuration permits it
