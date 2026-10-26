# Order notifications + audit trail — analysis & plan

## 1. What existed (audit of the code, before this change)
| Area | State |
|---|---|
| Admin (tenant) notifications | `database` only, 3 kinds (`new_order`, `status_changed`, `proof_uploaded`), bell polls every 15 s. Gate = `orders.view`. |
| Real time | none (`BROADCAST_CONNECTION=log`, no Echo/Reverb). |
| Admin e-mail | none. |
| Customer e-mail | none (only mobile-app OTP mails). Orders do not store the customer's locale. |
| Payment / return events | no events: proof approved/rejected, online paid, return requested/approved/rejected/refunded notify nobody. |
| Central panel | bell rendered but empty (no data source); `CentralAdmin` is `Notifiable` but never notified (tenant created / deactivated invisible). |
| Audit (spatie/activitylog) | `Order` + money models audited (PII excluded). **Not audited:** `OrderReturn`, `Customer`, `Tenant`. Staff/role changes audited via `AccessService`. No record of *which notifications/e-mails were sent*. No per-order activity view; admins must search the global audit table. |

## 2. Business rules (decided)
1. **Admin DB + realtime** → every active tenant admin allowed `orders.view`. Kinds: new order, status change, proof uploaded, **payment received (online)**, **return requested**.
2. **Admin e-mail** → opt-in by permission **`orders.email_notifications`** (new; owner role gets it via `PermissionSeeder`, custom roles must be granted). Mailed kinds: new order, proof uploaded, return requested. Never mailed to the admin who caused the change.
3. **Customer e-mail** (guest or account, address from the order, queued) → order received, status changes (confirmed / shipped / delivered / cancelled / returned), payment confirmed / proof approved / proof rejected, return requested / approved / rejected / refunded. Written in the language the customer ordered in (`orders.locale`). Tenant can switch each group off (`settings.notifications`, permission `settings.edit`).
4. **Realtime** → Laravel broadcasting over a Pusher-protocol server (Reverb). Private channel per admin (`App.Models.TenantAdmin.{id}` / `CentralAdmin`), authorised against the admin's own guard. The database stays the source of truth; the bell falls back to polling (60 s) and instantly refreshes on a pushed event.
5. **Central admins** → bell + DB + realtime for tenant created / activated / deactivated (`tenants.view`).
6. **Audit** → audit `OrderReturn`, `Customer` (PII values never stored, only flags), `Tenant` (settings/secrets excluded); log every notification sent/failed (`notification` log, subject = order, masked recipient); per-order **Activity** card (order + its returns + notification log) and per-customer card.

## 3. Technical design
* Events: existing `OrderPlaced`, `OrderStatusChanged`, `OrderPaymentProofUploaded` + new `OrderPaymentUpdated(order, kind)` and `OrderReturnUpdated(return, kind)`. Fired after commit from the services (single choke points), never from controllers.
* Listeners (queued, `afterCommit`): admin side reuse `ResolvesOrderAdmins`; customer side `NotifyCustomerOf*` → `CustomerOrderNotification` (mail only, anonymous route).
* `OrderNotification::via()` is per-notifiable: `database`, `broadcast`, plus `mail` when the kind is mailable and the admin has the permission.
* Text for bell/subject comes from one place: `App\Support\NotificationText`.
* Deployment: `reverb` driver in the app needs only `pusher/pusher-php-server` (installed). `laravel/reverb` 1.x pins Guzzle 7 which would downgrade Guzzle 8 app-wide, so the WebSocket **server** runs as its own container (`deploy/reverb`).

## 4. Out of scope / follow-ups
SMS / WhatsApp, per-admin granular mute preferences, e-mail digest, low-stock alerts.
