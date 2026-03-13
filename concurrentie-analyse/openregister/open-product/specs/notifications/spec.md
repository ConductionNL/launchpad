# Notifications (Notificaties API)

## Summary

Open Product publishes notifications for Product CRUD operations to the VNG Notificaties API standard. This enables external systems to subscribe to product changes and react accordingly.

## Implementation

### Channel Configuration
- Channel name: `producten`
- Main resource: `product`
- Kenmerken (message attributes):
  - `producttype.uuid` -- UUID of the product type
  - `producttype.uniforme_product_naam` -- UPL name
  - `producttype.code` -- product type code

### Triggered Events
- `product.create` -- when a new product is created
- `product.update` -- when a product is updated
- `product.destroy` -- when a product is deleted

### Integration
- `NotificationViewSetMixin` on `ProductViewSet` handles notification publishing
- Uses `notifications-api-common` library (VNG standard)
- Notification service URL configured via admin or setup-configuration
- Configurable: `NOTIFICATIONS_DISABLED` env var (default: True in development)

### Subscriber Use Cases
- Open Inwoner: receive product status changes for citizen portal
- Open Formulieren: react to product creation from form submissions
- Case management: track product lifecycle events

## Already in OpenRegister
- Event-based triggers via n8n webhooks
- Object change notifications possible via OpenConnector

## Not yet in OpenRegister
- **VNG Notificaties API standard** compliance
- **Channel-based pub/sub** with typed kenmerken for filtering
- **Standardized notification payload** (resource URL, action, kenmerken)
- **Subscriber management** via external Notificaties API component
