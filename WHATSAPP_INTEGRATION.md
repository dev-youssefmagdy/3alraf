# MASTER ENGINEERING TASK
# 3alraf Platform – WhatsApp Multi-Provider Integration + Phone-Based Admin Authentication with OTP

## ROLE AND EXPECTATIONS

Act as a Senior Laravel Architect, Senior Full-Stack Developer, Senior DevOps Engineer, Security Engineer, and Senior QA Engineer.

You are responsible for implementing two major production-ready systems inside the existing 3alraf multi-tenant e-commerce platform:

**SYSTEM A: WhatsApp Communication Platform**
- WhatsApp Web integration using QR Code.
- Official Meta WhatsApp Business Cloud API integration.
- Ability to switch between providers.
- Unified WhatsApp inbox.
- Real-time messaging using Laravel Reverb.
- Real-time notifications with sound.
- Complete tenant isolation.

**SYSTEM B: Phone-Based Administrator Authentication**
- Replace email-based admin login with phone number login.
- Password authentication followed by OTP verification.
- Support both Central Admin and Tenant Admin panels.
- International phone number validation and normalization.
- Tenant-specific country code restrictions.
- Exactly one predefined special administrator account exempt from OTP.

Both systems must be fully integrated into the existing project.

This is not a prototype or a UI-only implementation. Deliver working backend functionality, database architecture, frontend integration, security, automated testing, and deployment documentation.

---

# PHASE 1: PROJECT AUDIT AND ARCHITECTURE DISCOVERY

Before modifying the codebase, conduct a complete technical audit.

Inspect:

1. Laravel version and PHP version.
2. Existing project directory structure.
3. Multi-tenancy architecture.
4. Central database configuration.
5. Tenant database configuration.
6. Tenant provisioning and initialization.
7. Central Admin authentication.
8. Tenant Admin authentication.
9. Authentication guards and providers.
10. Existing administrator models.
11. Existing roles and permissions.
12. Existing login and administrator CRUD pages.
13. Existing frontend framework and component conventions.
14. Existing Laravel Reverb configuration.
15. Broadcasting authorization and private channels.
16. Existing notification infrastructure.
17. Queue configuration and workers.
18. Existing API architecture.
19. Existing storage and attachment handling.
20. Existing deployment and Supervisor configuration.

Understand the complete tenant lifecycle, including how tenant context is resolved and how database connections are switched.

### Architecture rules

- Preserve the existing multi-tenant architecture.
- Never mix central and tenant authentication.
- Never execute tenant operations against the central database unintentionally.
- Never allow cross-tenant data access.
- Reuse existing services and conventions where appropriate.
- Avoid duplicate authentication systems.
- Avoid unnecessary dependencies.
- Preserve existing functionality and permissions.
- Do not modify unrelated modules.

After the audit, prepare an implementation plan with identified files, database changes, dependencies, risks, and execution phases.

Then proceed with implementation.

---

# SYSTEM A: WHATSAPP MULTI-PROVIDER COMMUNICATION PLATFORM

# PHASE 2: WHATSAPP ARCHITECTURE

## 2.1 Core Objective

Build a complete WhatsApp communication module inside the 3alraf admin dashboard.

The platform must support two independent integration methods:

**Provider 1: WhatsApp Web – QR Code Integration**
- Connect an existing WhatsApp account by scanning a QR Code.
- Maintain an authenticated WhatsApp Web session.
- Send and receive messages.

**Provider 2: Official Meta WhatsApp Business Cloud API**
- Connect a WhatsApp Business account through Meta.
- Authenticate using Meta credentials.
- Send and receive messages through the official API and webhooks.

Both providers must be completely implemented.

The administrator must be able to switch between them from WhatsApp Settings.

This must be a real backend provider-switching operation, not a frontend-only toggle.

## 2.2 Recommended Architecture

Use:

- Laravel: Main backend and business logic.
- Node.js: Dedicated WhatsApp Web integration service.
- Baileys or whatsapp-web.js: WhatsApp Web connectivity, after evaluating maintenance and production suitability.
- Meta Graph API: Official Cloud API integration.
- Laravel Reverb: Real-time communication.
- Laravel Queues: Background message processing.
- Existing frontend stack: Dashboard and inbox interface.

Keep the Node.js WhatsApp Web service isolated from Laravel.

The Meta Cloud API integration should be implemented as a dedicated Laravel service/provider, unless the existing architecture justifies a separate service.

Do not implement WhatsApp Web protocol handling directly inside Laravel PHP.

### Provider abstraction

Implement a clean provider architecture.

Suggested classes:

- WhatsAppProviderInterface
- WhatsAppWebProvider
- MetaCloudApiProvider
- WhatsAppProviderManager
- WhatsAppMessageService
- WhatsAppConnectionService
- WhatsAppWebhookService
- WhatsAppConversationService

Both providers must implement a consistent interface for shared capabilities.

The interface should account for provider-specific capability differences instead of pretending every feature is supported by both providers.

Provider-specific logic must never be scattered across controllers or frontend components.

### Compliance and limitations

WhatsApp Web automation is unofficial and may be subject to session restrictions or account limitations.

Clearly document its risks and limitations.

The official Meta Cloud API must follow Meta's messaging policies, template requirements, customer service window, and rate limits.

Keep the architecture replaceable and extensible.

---

# PHASE 3: WHATSAPP PROVIDER MANAGEMENT AND SWITCHING

## 3.1 Tenant-Specific Provider Configuration

Each tenant must manage its own WhatsApp integration independently.

Example:

- Tenant A uses WhatsApp Web QR Code.
- Tenant B uses Meta Cloud API.
- Tenant C uses WhatsApp Web QR Code.

These configurations must not interfere with each other.

Only ONE provider can be active per tenant at any given time.

The central platform must not impose one global provider on every tenant.

If the existing product includes central-level WhatsApp operations, implement their configuration separately without sharing tenant credentials or sessions.

## 3.2 WhatsApp Settings Interface

Create a professional WhatsApp Integration Settings page.

Provide two integration cards:

**WhatsApp Web – QR Code**
- Provider name.
- Description.
- Connection status.
- Connect button.
- QR Code display.
- Disconnect button.
- Reconnect button.
- Last connection information.

**Meta WhatsApp Business Cloud API**
- Provider name.
- Configuration status.
- Connected business number.
- Credential configuration.
- Test connection button.
- Activate button.
- Deactivate button.

The page must clearly indicate:

- Currently active provider.
- Configured providers.
- Connected providers.
- Disconnected providers.
- Configuration errors.

## 3.3 Provider Switching Workflow

The administrator must be able to switch between QR Code and Meta integration.

Example:

Currently active: WhatsApp Web.

Administrator selects Meta Cloud API.

The system must:

1. Verify the administrator has permission to change integrations.
2. Validate Meta configuration.
3. Test connectivity when possible.
4. Require explicit confirmation.
5. Safely deactivate the previous provider for new messaging operations.
6. Activate the new provider.
7. Update the tenant's active provider configuration.
8. Record the switching operation in the audit log.
9. Broadcast the provider change through Laravel Reverb.
10. Update the dashboard without requiring a page refresh.

### Failure handling

If the new provider cannot be activated:

- Preserve the previous active provider.
- Do not delete its configuration.
- Do not delete its sessions.
- Do not leave the tenant in an invalid provider state.
- Display a meaningful error.

Use a robust activation workflow with proper locking and transaction handling.

Do not assume that database transactions alone can roll back external provider operations.

Historical messages and conversations must remain accessible after switching.

Delayed incoming webhooks from a previously active provider must be handled safely and must never be silently assigned to the new provider.

Each message must retain its original provider association.

---

# PHASE 4: WHATSAPP WEB QR CODE INTEGRATION

## 4.1 Connection Management

Implement:

- Generate QR Code.
- Display QR Code in dashboard.
- Scan QR Code using WhatsApp mobile application.
- Detect successful authentication.
- Show connection status.
- Disconnect account.
- Reconnect account.
- Regenerate expired QR Code.
- Detect authentication failures.
- Persist sessions securely.
- Automatically reconnect after service restarts where possible.

Connection statuses:

- Disconnected.
- Initializing.
- Connecting.
- QR Required.
- Connected.
- Reconnecting.
- Authentication Failed.
- Logged Out.

Connection status updates must be real-time.

Do not require QR scanning every time an administrator opens the dashboard.

## 4.2 Session Management

- Persist authenticated sessions.
- Secure session storage.
- Isolate sessions per tenant.
- Prevent one tenant from accessing another tenant's session.
- Implement session cleanup.
- Handle expired authentication.
- Avoid unnecessary QR regeneration.
- Implement service health checks.

Never store WhatsApp authentication credentials in plaintext.

Ensure service restarts do not unnecessarily destroy valid sessions.

---

# PHASE 5: OFFICIAL META WHATSAPP BUSINESS CLOUD API

Implement a complete official Meta Cloud API provider.

## 5.1 Configuration

Support:

- Meta App ID.
- Meta App Secret.
- WhatsApp Business Account ID (WABA ID).
- Phone Number ID.
- Access Token.
- Webhook Verify Token.
- Configurable Graph API version.
- Business phone number.

Store credentials encrypted at rest.

Never expose access tokens or app secrets to frontend JavaScript.

## 5.2 Connection and Verification

Implement:

- Validate Meta credentials.
- Verify access token.
- Verify WABA configuration.
- Verify Phone Number ID.
- Test Graph API connectivity.
- Retrieve available business phone information where permitted.
- Display connection/configuration status.
- Handle expired or revoked access tokens.
- Provide clear configuration error messages.

Support manual configuration first.

Design the architecture to support Meta OAuth onboarding in a future iteration without rewriting the provider.

## 5.3 Messaging Capabilities

Implement all applicable supported messaging operations:

- Send text messages.
- Receive text messages.
- Send images.
- Receive images.
- Send documents.
- Receive documents.
- Send supported audio messages.
- Receive supported audio messages.
- Send supported video messages.
- Receive supported video messages.
- Send approved message templates.
- Support template parameters and variables.
- Support applicable interactive messages.
- Process message delivery updates.
- Process read updates.
- Process failed message updates.

Do not implement unsupported features by simulating WhatsApp Web functionality.

## 5.4 Meta Webhooks

Implement:

- Webhook verification endpoint.
- GET verification challenge.
- POST event processing.
- Signature validation using the configured Meta App Secret.
- Incoming message processing.
- Delivery status processing.
- Read status processing.
- Failure status processing.
- Webhook idempotency.
- Safe event logging.
- Retry handling.
- Error monitoring.

Validate the authenticity of incoming events before processing them.

Store processed webhook event identifiers to prevent duplicate processing.

---

# PHASE 6: UNIFIED WHATSAPP INBOX

## 6.1 Core Objective

Both providers must use ONE unified inbox.

Administrators should not need different dashboard pages depending on whether they use QR Code or Meta Cloud API.

The inbox must use a normalized messaging architecture.

## 6.2 Conversation Sidebar

Display:

- Customer name.
- WhatsApp phone number.
- Customer profile image when available.
- Last message.
- Last message timestamp.
- Unread message count.
- Conversation status.
- Active provider indicator.
- Search conversations.
- Filter unread conversations.
- Sort by latest activity.

## 6.3 Chat Window

Display:

- Incoming messages.
- Outgoing messages.
- Message timestamps.
- Date separators.
- Message grouping.
- Message delivery status where supported.
- Message read status where supported.
- Failed message indicators.
- Auto-scroll to latest message.
- Message input.
- Send button.
- Emoji support.
- Image attachments.
- Document attachments.
- Supported audio/video attachments.
- Retry failed messages when supported.

Keyboard behavior:

- Enter: Send message.
- Shift + Enter: New line.

## 6.4 Customer Information Sidebar

Display:

- Customer name.
- WhatsApp number.
- Conversation history.
- Customer information when linked to an existing store customer.
- Related customer details when available.

Avoid exposing unrelated tenant customer data.

## 6.5 Unified Message Normalization

Normalize both providers into a common data structure.

Suggested message fields:

- id
- tenant_id
- provider_id
- connection_id
- conversation_id
- external_message_id
- sender_phone
- recipient_phone
- direction
- message_type
- message_body
- media_path
- media_mime_type
- status
- sent_at
- received_at
- created_at

Normalize provider-specific statuses where possible while preserving the original provider status.

Do not assume QR Code and Meta support identical delivery/read semantics.

Maintain provider-specific metadata when required.

## 6.6 Historical Data

Switching providers must not delete:

- Existing conversations.
- Existing messages.
- Customer information.
- Attachments.
- Message history.

Do not assume messages can be automatically migrated between WhatsApp Web and Meta.

Each conversation and message must retain its originating provider and connection.

---

# PHASE 7: REAL-TIME COMMUNICATION WITH LARAVEL REVERB

Use Laravel Reverb for all real-time dashboard communication.

Suggested events:

- WhatsAppProviderChanged
- WhatsAppProviderStatusChanged
- WhatsAppConnectionStatusChanged
- WhatsAppQRCodeUpdated
- WhatsAppMessageReceived
- WhatsAppMessageSent
- WhatsAppMessageStatusUpdated
- WhatsAppConversationUpdated
- WhatsAppUnreadCountUpdated

## Requirements

- Real-time QR Code updates.
- Real-time connection status.
- Real-time incoming messages.
- Real-time outgoing messages.
- Real-time message status.
- Real-time conversation updates.
- Real-time unread counters.
- Real-time provider switching updates.

Use private channels with authorization.

Suggested channel structure:

- private-tenant.{tenantId}.whatsapp
- private-tenant.{tenantId}.whatsapp.conversations.{conversationId}

Adapt naming to existing broadcasting conventions.

Never use public channels for private customer messages.

Verify tenant membership and permissions during channel authorization.

Prevent unauthorized administrators from accessing conversations.

---

# PHASE 8: WHATSAPP NOTIFICATION SOUND

Implement notification sounds for incoming messages.

Requirements:

1. Play sound when a new incoming WhatsApp message arrives.
2. Do not play sound for outgoing messages.
3. Prevent duplicate sounds for duplicate webhook/events.
4. Support notification sound enable/disable.
5. Add sound preview.
6. Save preferences appropriately.
7. Respect browser autoplay restrictions.
8. Initialize audio after the administrator's first dashboard interaction.
9. Avoid repeated sounds when a conversation is already being updated through multiple event listeners.
10. Support real-time unread count updates.

Use the existing dashboard notification preferences where possible.

---

# PHASE 9: WHATSAPP DATABASE DESIGN

Design a scalable database structure compatible with the existing multi-tenant architecture.

Suggested tables:

- whatsapp_providers
- whatsapp_connections
- whatsapp_provider_settings
- whatsapp_conversations
- whatsapp_messages
- whatsapp_contacts
- whatsapp_message_attachments
- whatsapp_webhook_logs
- whatsapp_webhook_events
- whatsapp_audit_logs

Adapt the design to avoid unnecessary duplication.

### Data isolation

If tenants use separate databases:

- Store tenant conversations in the corresponding tenant database.
- Store tenant messages in the corresponding tenant database.
- Store tenant contacts in the corresponding tenant database.
- Ensure provider settings are tenant-specific.
- Ensure webhook events resolve to the correct tenant and connection before accessing tenant data.

If central infrastructure is required for routing webhooks or managing service connections, store only the minimum necessary routing metadata centrally.

Never place all tenants' private conversations in an unprotected shared database.

### Performance

- Add indexes for external message IDs.
- Add indexes for conversation IDs.
- Add indexes for phone numbers.
- Add indexes for timestamps.
- Add appropriate unique constraints.
- Prevent duplicate messages.
- Use database transactions.
- Implement pagination.
- Avoid N+1 queries.
- Implement attachment cleanup policies.

---

# SYSTEM B: PHONE-BASED ADMIN AUTHENTICATION WITH OTP

# PHASE 10: REPLACE EMAIL LOGIN WITH PHONE NUMBER LOGIN

## 10.1 Objective

Replace email-based administrator authentication across:

1. Central Admin Panel.
2. Tenant Admin Panel.

New authentication flow:

**Step 1: Phone Number + Password**

**Step 2: OTP Verification**

After successful OTP verification, authenticate the administrator and redirect to the appropriate dashboard.

Email must no longer be required for administrator login.

Do not modify customer authentication unless absolutely necessary. Keep customer and admin authentication properly isolated.

## 10.2 Authentication Flow

### Step 1: Login

Administrator enters:

- Phone number.
- Password.

Backend must:

1. Resolve the correct central or tenant authentication context.
2. Validate the submitted phone number.
3. Normalize the phone number to E.164.
4. Find the administrator in the correct database.
5. Verify the password.
6. Check account status.
7. Check the special OTP exemption attribute.
8. If exempt, complete authentication securely.
9. Otherwise, generate an OTP challenge.
10. Send OTP using the configured delivery provider.
11. Return a temporary authentication challenge.

Do not create a fully authenticated session before successful OTP verification.

### Step 2: OTP Verification

Administrator enters the OTP.

Validate:

- OTP correctness.
- OTP expiration.
- Maximum attempts.
- Challenge validity.
- OTP usage status.
- Account status.

If valid:

1. Mark OTP as consumed.
2. Regenerate the session ID.
3. Authenticate using the correct guard.
4. Initialize tenant context when applicable.
5. Redirect to the correct dashboard.

If invalid:

- Display a meaningful error.
- Track failed attempts.
- Apply rate limiting.

---

# PHASE 11: INTERNATIONAL PHONE NUMBER MANAGEMENT

## 11.1 Canonical Phone Number Format

All administrator phone numbers must be saved in international E.164 format.

Examples:

Egypt: +201012345678

Saudi Arabia: +966501234567

UAE: +971501234567

The country calling code must be part of the actual stored number.

Do not store the country code only as a frontend selection.

Use Google's libphonenumber implementation or a reliable Laravel-compatible package.

Avoid manually concatenating country codes.

Normalize and validate phone numbers on the backend.

## 11.2 Central Admin Panel: intl-tel-input

Use intl-tel-input for all relevant central administrator phone fields.

Apply to:

- Central Admin Login.
- Add Admin.
- Edit Admin.

Requirements:

- Country flags.
- Country selection.
- International dialing codes.
- Phone number formatting.
- Country-specific validation.
- E.164 normalization.
- Proper validation errors.
- Display international formatted numbers.

Allow country selection when creating or editing administrators.

The country selector must not silently modify existing phone numbers.

Enforce unique normalized phone numbers within the central administrator scope.

## 11.3 Tenant Admin Panel: Tenant-Specific Country Code

Tenant administrator phone numbers must use the country configured for that tenant.

Examples:

Tenant country = Egypt:
- Country = Egypt.
- Calling code = +20.
- Number must be validated using Egyptian numbering rules.

Tenant country = Saudi Arabia:
- Country = Saudi Arabia.
- Calling code = +966.
- Number must be validated using Saudi numbering rules.

Requirements:

1. Read tenant country from existing tenant configuration.
2. Determine calling code dynamically.
3. Use the correct country in login.
4. Use the correct country in Add Admin.
5. Use the correct country in Edit Admin.
6. Prevent unauthorized country changes.
7. Validate submitted numbers server-side.
8. Store complete E.164 numbers.

Never hardcode Egypt for all tenants.

Never trust the frontend country code.

Apply the tenant country restriction at the API/backend validation level.

---

# PHASE 12: ADMINISTRATOR MANAGEMENT

Update Central Admin and Tenant Admin CRUD functionality.

## Central Admin

Support:

- Create administrator using international phone number.
- Edit administrator phone number.
- Validate phone numbers.
- Enforce uniqueness.
- Select country.
- Display formatted phone number.
- Preserve roles and permissions.

## Tenant Admin

Support:

- Create administrator using tenant-specific country.
- Edit administrator phone number.
- Automatically determine country calling code.
- Validate tenant-specific numbering rules.
- Enforce uniqueness within the correct tenant database.
- Preserve existing roles and permissions.

Do not allow ordinary administrator CRUD operations to modify OTP exemption privileges.

Ensure all authorization policies remain intact.

---

# PHASE 13: OTP PROVIDER ARCHITECTURE

## 13.1 Provider-Based Delivery

Build an independent OTP delivery service.

Support:

- SMS provider integration.
- WhatsApp OTP delivery only when supported by an appropriate compliant provider and approved messaging method.
- Development/testing OTP driver.

Do not tightly couple authentication to one SMS provider.

Make provider configuration manageable through environment variables and existing application settings.

Do not expose OTP values in production API responses or logs.

## 13.2 OTP Security

Implement:

- Cryptographically secure random OTP generation.
- Six-digit OTP.
- Hash OTP before database storage.
- Five-minute expiration by default.
- Maximum five verification attempts by default.
- Sixty-second resend cooldown by default.
- Single-use OTP.
- Previous OTP invalidation.
- Challenge identifiers.
- Rate limiting by phone number and IP.
- Brute-force protection.
- OTP enumeration protection.
- Audit logging.

All values must be configurable.

Use secure challenge storage and prevent replay attacks.

---

# PHASE 14: SPECIAL SUPER ADMIN ACCOUNT – OTP EXEMPTION

This is a critical business requirement.

We need exactly ONE predefined logical administrator account that can log in without OTP.

This account must be seeded in:

1. Central database.
2. Every tenant database.

It must be available automatically for existing and newly provisioned tenants.

## Requirements

- One configured identity.
- One configured phone number.
- One configured password.
- Same identity across central and tenant environments.
- No OTP required for this account.
- All other administrators must complete OTP verification.

Use an explicit server-side database attribute, such as:

otp_exempt = true

Do not implement OTP bypass using:

- Hardcoded phone numbers.
- Role names alone.
- Frontend conditions.
- User-editable fields.
- Ordinary admin CRUD.

The OTP exemption must be controlled exclusively by trusted provisioning/seeding logic.

## Environment Configuration

Use environment variables:

SUPER_ADMIN_NAME=
SUPER_ADMIN_PHONE=
SUPER_ADMIN_PASSWORD=

Never commit actual credentials.

The seeder must be idempotent.

The special account must be provisioned into new tenant databases automatically.

Existing tenants must receive the account through a safe provisioning or deployment procedure.

### Important security considerations

- Do not allow ordinary users to grant OTP exemption.
- Do not allow tenant administrators to modify the exemption.
- Prevent privilege escalation.
- Preserve correct roles and permissions.
- Verify account status before authentication.
- Never exempt other administrators who share the same role.
- Ensure exactly one configured logical identity is provisioned.

Document the security implications of using a single privileged identity across multiple tenant databases.

---

# PHASE 15: LOGIN UI/UX

Redesign Central and Tenant Admin login screens consistently.

Use the existing frontend framework and dashboard branding.

## Login Screen

Fields:

- Phone number.
- Password.
- Remember Me, only if compatible with the security architecture.
- Login button.

Remove email login.

## OTP Screen

Fields:

- Six-digit OTP.
- Verify button.
- Resend countdown.
- Change phone number / Back to Login.

UX requirements:

- Responsive design.
- Professional visual design.
- Automatic focus.
- Paste complete OTP.
- Keyboard navigation.
- Loading indicators.
- Validation feedback.
- Proper error handling.
- No unnecessary page refreshes.

Ensure both central and tenant login screens use the same authentication design principles without mixing guards or tenant contexts.

---

# PHASE 16: DATABASE MIGRATIONS AND BACKWARD COMPATIBILITY

Create safe migrations for central and tenant databases.

Required changes may include:

- Administrator phone number fields.
- E.164 normalization support.
- Unique indexes.
- OTP challenge storage.
- OTP exemption attribute.
- WhatsApp provider tables.
- WhatsApp connection tables.
- WhatsApp message tables.
- WhatsApp conversation tables.
- Webhook event storage.
- Notification preferences.

Requirements:

1. Migrations must support existing tenants.
2. Migrations must support fresh installations.
3. Tenant provisioning must execute required migrations.
4. Existing administrators must not be deleted.
5. Never fabricate missing phone numbers.
6. Identify administrators without valid phone numbers.
7. Provide a controlled migration procedure.
8. Preserve existing email fields until migration and compatibility have been verified.
9. Avoid destructive migrations.
10. Use appropriate indexes and constraints.

Document any manual intervention required for existing administrator accounts.

---

# PHASE 17: AUTOMATED TESTING

Build comprehensive automated tests.

## 17.1 Authentication Tests

Test:

- Central phone/password login.
- Tenant phone/password login.
- Correct OTP verification.
- Invalid OTP rejection.
- Expired OTP rejection.
- Reused OTP rejection.
- OTP rate limiting.
- OTP resend cooldown.
- OTP exemption for the predefined account.
- OTP enforcement for all other accounts.
- Central/tenant guard isolation.
- Cross-tenant authentication protection.
- Phone uniqueness.
- Invalid international number rejection.
- Tenant country restrictions.
- Session regeneration.
- Account suspension.
- Existing roles and permissions preservation.

## 17.2 WhatsApp Tests

Test:

- QR Code generation workflow.
- QR Code status updates.
- WhatsApp Web connection status.
- Meta credential validation.
- Meta webhook verification.
- Meta webhook signature validation.
- Incoming messages.
- Outgoing messages.
- Media attachments.
- Message status updates.
- Duplicate webhook prevention.
- Duplicate message prevention.
- Provider switching.
- Failed provider switching rollback.
- Previous provider preservation.
- Historical message preservation.
- Tenant isolation.
- Private channel authorization.
- Queue processing.
- Reverb broadcasting.
- Notification sound preferences.
- Inactive provider event handling.

Mock external WhatsApp services in automated tests.

Never require an actual WhatsApp account or Meta credentials during CI testing.

## 17.3 Frontend QA

Test:

- Central login.
- Tenant login.
- OTP screen.
- Central administrator creation/editing.
- Tenant administrator creation/editing.
- Country code selection.
- QR Code connection interface.
- Meta configuration interface.
- Provider switching.
- Unified inbox.
- Incoming and outgoing messages.
- Real-time updates.
- Notification sound.
- Mobile responsiveness.

---

# PHASE 18: PERFORMANCE, SECURITY AND CODE QUALITY

Conduct a dedicated security review.

### Authentication

- Correct guards.
- Secure sessions.
- CSRF protection.
- Login throttling.
- OTP hashing.
- OTP expiration.
- Challenge validation.
- Session regeneration.
- Account status validation.
- Protection against session fixation.

### WhatsApp

- Encrypted credentials.
- Secure service authentication.
- Webhook signature validation.
- Private broadcasting channels.
- Tenant isolation.
- Secure attachments.
- Input validation.
- Idempotency.
- Rate limiting.
- Safe logging.

### Performance

- Queue background tasks.
- Avoid blocking HTTP requests.
- Optimize database queries.
- Add required indexes.
- Implement pagination.
- Avoid unnecessary Reverb events.
- Prevent duplicate listeners.
- Handle high-volume incoming messages.
- Implement sensible retry/backoff mechanisms.

Follow SOLID principles, clean architecture, Laravel conventions, and existing project patterns.

---

# PHASE 19: DEPLOYMENT AND INFRASTRUCTURE

Prepare complete production deployment documentation.

## WhatsApp Web Service

Document:

- Node.js installation.
- Service dependencies.
- Session storage.
- Environment configuration.
- Supervisor/systemd configuration.
- Restart procedures.
- Health checks.
- Logging.
- Backup and recovery.

## Laravel

Document:

- Environment variables.
- Database migrations.
- Tenant migration execution.
- Queue worker configuration.
- Reverb configuration.
- Supervisor configuration.
- Storage permissions.
- Webhook routing.
- Scheduler requirements.

## Meta Cloud API

Document:

- Meta App configuration.
- WhatsApp Business setup.
- Access token configuration.
- Phone Number ID.
- WABA ID.
- Webhook callback URL.
- Webhook verification.
- Required permissions.
- Production token management.

## OTP

Document:

- SMS provider configuration.
- Delivery testing.
- Rate-limit configuration.
- OTP expiration configuration.
- Production logging restrictions.

Never expose the WhatsApp Web service publicly without authentication.

Use secure internal service-to-service communication.

---

# PHASE 20: FINAL DELIVERABLES

At completion, provide:

1. Complete architecture summary.
2. Project audit findings.
3. Implementation decisions.
4. Database schema changes.
5. New migrations.
6. New models.
7. Services and interfaces.
8. Controllers.
9. API endpoints.
10. Reverb events and channels.
11. WhatsApp Web service implementation.
12. Meta Cloud API implementation.
13. Provider switching functionality.
14. Unified inbox frontend.
15. Notification sound implementation.
16. Phone-based authentication implementation.
17. OTP service and provider integration.
18. Special super-admin seeder.
19. Tenant provisioning changes.
20. Automated test results.
21. Security review results.
22. Deployment commands.
23. Environment variables.
24. Supervisor configuration.
25. Technical documentation.
26. Known limitations and remaining manual configuration.

---

# STRICT ENGINEERING RULES

1. Do not modify unrelated modules.
2. Do not break existing functionality.
3. Do not create disconnected or duplicate authentication systems.
4. Both WhatsApp providers must be fully implemented.
5. Provider switching must work on the backend.
6. Only one provider can be active per tenant.
7. Different tenants can use different providers simultaneously.
8. Do not delete historical messages when switching providers.
9. Do not hardcode country codes.
10. Do not trust frontend validation.
11. Do not expose OTP values in production.
12. Only the explicitly provisioned special administrator is exempt from OTP.
13. Do not allow ordinary administrators to grant OTP exemption.
14. Do not allow cross-tenant data access.
15. Do not broadcast private messages publicly.
16. Do not store credentials or WhatsApp sessions insecurely.
17. Do not fake external provider functionality.
18. Do not ignore provider-specific limitations.
19. Do not remove legacy data without a verified migration plan.
20. Do not consider a feature complete merely because its UI exists.
21. Run tests after every major implementation phase.
22. Fix regressions before proceeding.
23. Maintain production-grade code quality.

---

# EXECUTION STRATEGY

Work in these stages:

**Stage 1 – Discovery**
- Audit existing architecture.
- Identify risks.
- Prepare implementation plan.

**Stage 2 – Authentication Backend**
- Database migrations.
- Phone normalization.
- OTP service.
- Authentication flow.
- Special administrator provisioning.

**Stage 3 – Authentication Frontend**
- Central login.
- Tenant login.
- OTP verification.
- Admin CRUD updates.
- Country-specific phone inputs.

**Stage 4 – WhatsApp Architecture**
- Provider interfaces.
- Provider manager.
- Database design.
- Tenant-specific configuration.

**Stage 5 – WhatsApp QR Code**
- Node.js service.
- QR generation.
- Session management.
- Sending and receiving messages.

**Stage 6 – Meta Cloud API**
- Credentials.
- Connection verification.
- Messaging.
- Webhooks.
- Status updates.

**Stage 7 – Provider Switching**
- Activation/deactivation workflow.
- Validation.
- Rollback behavior.
- Audit logging.

**Stage 8 – Unified Inbox**
- Conversations.
- Messages.
- Attachments.
- Customer information.
- Historical data.

**Stage 9 – Reverb and Notifications**
- Private channels.
- Real-time events.
- Unread counters.
- Notification sounds.

**Stage 10 – QA and Deployment**
- Automated testing.
- Security review.
- Performance review.
- Production deployment documentation.

After every stage, report:

- What was implemented.
- Files created or modified.
- Database changes.
- Tests executed.
- Test results.
- Remaining issues.

Do not skip stages or claim completion without verification.

# FINAL ACCEPTANCE CRITERIA

The project is considered complete only when:

### WhatsApp

- QR Code connection works.
- QR Code messages can be sent and received.
- Meta Cloud API connection works.
- Meta messages can be sent and received.
- Both providers work independently.
- Provider switching works.
- Only one provider is active per tenant.
- Different tenants can use different providers.
- Both providers share one unified inbox.
- Messages update in real time.
- Notification sounds work.
- Historical conversations survive provider switching.
- Credentials and sessions are secure.
- Tenant isolation is verified.

### Authentication

- Email login is replaced by phone login for administrators.
- Password is the first authentication step.
- OTP is the second step.
- Central authentication works.
- Tenant authentication works.
- Central country selection works using intl-tel-input.
- Tenant country code is dynamically determined.
- Phone numbers are stored in E.164 format.
- Admin CRUD supports phone numbers.
- OTP security is fully implemented.
- Exactly one predefined logical administrator is exempt from OTP.
- That account is provisioned into central and tenant databases.
- All other administrators must verify OTP.
- Existing permissions and tenant isolation remain intact.

**ULTIMATE OBJECTIVE:**

Deliver a production-ready, secure, scalable multi-tenant platform where each tenant independently manages its WhatsApp integration using either QR Code or the official Meta Business Cloud API, switches between providers whenever needed, communicates through one unified real-time dashboard, and uses secure phone-number-based administrator authentication with OTP across both Central and Tenant Admin Panels.