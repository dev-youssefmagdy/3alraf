# Senior Software Architect & Full-Stack Engineer — WhatsApp QR Authentication & Phone-Based Login

Act as a **Senior Software Architect, Senior Laravel Developer, Node.js Engineer, and Security Engineer**. I need you to analyze the existing multi-tenant architecture and implement a complete WhatsApp QR-based authentication and phone-based login system for both the Central Panel and Tenant Admin Panels.

This is a production-level implementation. Do not build a prototype or introduce unnecessary architectural changes.

## 1. Core Objective

I want to introduce WhatsApp QR Code integration using a reliable Node.js/NPM package (such as Baileys or another suitable WhatsApp Web integration library).

The system must support two independent WhatsApp connections:

1. **Central WhatsApp Connection:** One WhatsApp account connected through the Central Panel QR Code.
2. **Tenant WhatsApp Connection:** Each tenant has its own independent WhatsApp account connected through its Tenant Admin Panel QR Code.

These connections must be completely isolated from each other.

The WhatsApp connections will be used for authentication OTP delivery and potentially other WhatsApp business notifications in the future.

## 2. Central Panel Authentication

The Central Panel must use phone-number-based authentication instead of email-based authentication.

### Central Super Admin

- There must be exactly ONE Super Admin account for the entire central platform.
- The Super Admin logs in using:
  - Phone Number
  - Password
- The Super Admin must NOT require OTP verification.
- The Super Admin must have unrestricted access to central administrative functionality according to the existing authorization architecture.
- The Super Admin's phone number must be unique and properly validated.

### Other Central Admins

All other central administrators must authenticate using:

1. Phone Number.
2. Password.
3. OTP verification delivered through the Central WhatsApp connection.

Authentication flow:
- Validate phone number and password.
- Generate a secure OTP.
- Deliver OTP through the connected Central WhatsApp account.
- Verify OTP.
- Create the authenticated session only after successful verification.

## 3. Tenant Admin Authentication

Every tenant must have its own independent WhatsApp QR connection.

### Tenant Super Admin

- Exactly ONE Tenant Super Admin per tenant.
- Each tenant must have its own independent Super Admin account.
- Login requires:
  - Phone Number
  - Password
- No OTP is required for the Tenant Super Admin.
- The Tenant Super Admin must be isolated within their tenant database and cannot access another tenant.

### Other Tenant Admins

All other tenant administrators must authenticate using:

1. Phone Number.
2. Password.
3. OTP delivered through that tenant's connected WhatsApp account.

Important:
- Tenant OTP messages must always be sent using the WhatsApp account connected to the same tenant.
- Never use the Central WhatsApp connection for tenant authentication.
- Never allow one tenant to access another tenant's WhatsApp session, QR code, messages, credentials, or connection status.

## 4. WhatsApp QR Connection Management

### Central Panel

Create a WhatsApp Connection Management section inside the Central Panel.

Features:
- Generate QR Code.
- Display QR Code in real time.
- Scan QR Code using WhatsApp mobile application.
- Detect successful authentication.
- Display connection status.
- Display connected WhatsApp phone number.
- Disconnect WhatsApp account.
- Reconnect after disconnection.
- Automatically restore the connection after Node.js service restarts.
- Display last successful connection timestamp.
- Display connection errors and logs.

### Tenant Panel

Create the same WhatsApp Connection Management functionality inside every Tenant Admin Panel.

Each tenant must have:
- Its own QR Code.
- Its own WhatsApp authentication session.
- Its own connection status.
- Its own connected phone number.
- Its own reconnect functionality.
- Its own session persistence.

The connection must be tenant-aware and securely isolated.

## 5. Node.js / NPM Architecture

Use Node.js as a dedicated WhatsApp integration service.

Before choosing the package:
- Evaluate Baileys and other appropriate NPM packages.
- Consider maintenance status, stability, session persistence, reconnect handling, and production suitability.
- Select the most appropriate solution.
- Document the reasons for choosing it.

Requirements:

- Implement a clean REST API between Laravel and the Node.js service.
- Implement real-time QR Code updates and connection status updates.
- Use Laravel Reverb for broadcasting real-time events to the appropriate Central or Tenant Panel.
- Handle multiple independent WhatsApp sessions.
- Each session must have a unique identifier and tenant/central ownership.
- Implement automatic reconnection and session recovery.
- Handle WhatsApp disconnections and authentication failures.
- Prevent duplicate connections for the same tenant.
- Handle Node.js process restarts without requiring users to scan the QR code again.
- Implement secure communication between Laravel and Node.js using internal API authentication.
- Never expose WhatsApp session credentials to frontend applications.

Use Supervisor or the existing process management infrastructure to keep the Node.js service running in production.

Do not run a separate Node.js process for every tenant unless there is a strong technical justification. Prefer a centralized session manager that safely manages multiple isolated WhatsApp sessions.

## 6. Database Architecture & Configuration

CRITICAL REQUIREMENT:

**All runtime configurations and integration settings MUST be stored in the database. Do not store configurable values in .env files or configuration files.**

This includes:
- WhatsApp integration enabled/disabled status.
- WhatsApp connection information.
- Session identifiers.
- Connected phone numbers.
- Connection status.
- OTP expiration time.
- OTP length.
- OTP resend cooldown.
- Maximum OTP verification attempts.
- Maximum OTP resend attempts.
- OTP delivery settings.
- Authentication rules.
- Super Admin identification.
- Integration settings.
- Other configurable authentication parameters.

Use the existing settings tables or introduce appropriate database tables where necessary.

Suggested architecture:

### Central Database
Store:
- Central WhatsApp connection configuration.
- Central WhatsApp session metadata.
- Central authentication settings.
- Central Super Admin identification.
- Central OTP settings.

### Tenant Database
Each tenant database must independently store:
- Tenant WhatsApp connection configuration.
- Tenant WhatsApp session metadata.
- Tenant authentication settings.
- Tenant Super Admin identification.
- Tenant OTP settings.

### Session Storage

WhatsApp authentication credentials and session files must be stored securely in persistent storage, not in temporary directories.

The database should store session metadata and the secure storage reference.

Encrypt sensitive credentials at rest and implement proper access restrictions.

Do not store passwords or OTPs in plain text.

Do not confuse application runtime secrets with business configuration: infrastructure secrets may remain in secure deployment secrets, but all application-level configurable settings must be database-driven.

## 7. Authentication Security

Implement production-grade security:

- Password hashing using Laravel's supported secure hashing mechanism.
- Cryptographically secure OTP generation.
- OTP expiration.
- OTP single-use enforcement.
- OTP resend cooldown.
- Maximum verification attempts.
- Rate limiting by account, phone number, and IP.
- Prevent OTP brute-force attacks.
- Prevent phone-number enumeration.
- Secure session and token management.
- Secure logout and session invalidation.
- Audit logging for authentication activities.
- Prevent unauthorized Super Admin privilege assignment.
- Validate tenant ownership on every authentication and WhatsApp operation.
- Protect against cross-tenant access.
- Avoid logging OTP values, passwords, or WhatsApp credentials.

Super Admin accounts must be provisioned through a secure administrative process, not through public registration.

Enforce the one-Super-Admin rule at the database/business-logic level.

## 8. Login UI/UX

Update the existing Central and Tenant login interfaces.

Replace email login with:

- International phone number input.
- Country code support.
- Password input.
- Login button.

For administrators requiring OTP:
- Display a separate OTP verification screen.
- Support OTP resend with a countdown.
- Show clear connection/delivery errors.
- Provide a clean and responsive experience.

Super Admin users should proceed directly to the dashboard after successful phone/password authentication without seeing the OTP screen.

Add a WhatsApp connection management page to both Central and Tenant dashboards with:
- QR Code display.
- Connection status indicator.
- Connected phone number.
- Connect / Disconnect actions.
- Reconnect action.
- Last connection information.
- Helpful troubleshooting messages.

## 9. Tenant Isolation & Multi-Tenancy

This is a multi-tenant SaaS platform with a central database and independent tenant databases.

Respect the existing tenancy architecture.

Important rules:
- Central WhatsApp sessions belong exclusively to the central application.
- Tenant WhatsApp sessions belong exclusively to their respective tenants.
- Never mix tenant session data.
- Never use the wrong WhatsApp connection when sending OTPs.
- Ensure every operation resolves the correct tenant context.
- Ensure tenant-specific events are broadcast only to authorized tenant channels.
- Use proper tenant-aware queue jobs and middleware.

Do not assume that tenant database connections are interchangeable with central database connections.

## 10. Future Extensibility

Design the integration so it can later support:
- Sending WhatsApp notifications.
- Order updates.
- Customer support messaging.
- WhatsApp chat management.
- Automated notifications.
- Multiple WhatsApp message templates.

Keep the WhatsApp service modular and independent of authentication business logic.

## 11. Implementation Workflow

Before modifying anything:

1. Analyze the existing Laravel application.
2. Understand the central/tenant database architecture.
3. Identify current authentication guards, providers, middleware, roles, and permissions.
4. Inspect existing settings tables and configuration management.
5. Inspect existing Reverb implementation.
6. Identify the current deployment and process management structure.
7. Identify any existing WhatsApp integration or authentication functionality.

Then prepare a short implementation plan and proceed with implementation.

Do not unnecessarily replace or break existing functionality.

## 12. Testing Requirements

Implement automated tests covering:

- Central Super Admin login without OTP.
- Central regular admin login with OTP.
- Tenant Super Admin login without OTP.
- Tenant regular admin login with OTP.
- Central OTP delivery through the central WhatsApp account.
- Tenant OTP delivery through the correct tenant WhatsApp account.
- Cross-tenant WhatsApp session isolation.
- QR generation and connection lifecycle.
- Session persistence after Node.js restart.
- WhatsApp disconnection and reconnection.
- OTP expiration and single-use enforcement.
- OTP rate limiting and maximum attempts.
- Prevention of multiple Super Admin accounts.
- Unauthorized access to WhatsApp management pages.
- Database-driven settings without relying on application configuration files.

Include integration tests for Laravel ↔ Node.js communication where practical.

## 13. Final Deliverables

I expect a complete working implementation, including:

1. Laravel authentication changes.
2. Central and tenant Super Admin logic.
3. Central and tenant regular admin OTP authentication.
4. Node.js WhatsApp integration service.
5. NPM package integration.
6. Independent central and tenant WhatsApp sessions.
7. Real-time QR Code and connection status using Reverb.
8. Database migrations and seeders.
9. Settings management.
10. Secure persistent WhatsApp session storage.
11. Updated login interfaces.
12. Central and tenant WhatsApp management interfaces.
13. Automated tests.
14. Production deployment instructions.
15. Supervisor configuration and service management instructions.
16. Documentation covering architecture, API endpoints, database structure, and recovery procedures.

## 14. Strict Engineering Rules

- Follow existing project coding standards.
- Use clean architecture and separation of concerns.
- Avoid duplicated central/tenant business logic where reusable services are appropriate.
- Do not introduce unnecessary dependencies.
- Do not hardcode tenant IDs, phone numbers, OTP values, or configuration values.
- Do not put application-level configuration in .env or config files.
- Never expose WhatsApp authentication credentials to the frontend.
- Never compromise tenant isolation.
- Do not break existing login, permissions, roles, or dashboard functionality.
- Do not mark a feature as completed unless it is implemented and tested.
- Do not use mocked QR codes or simulated WhatsApp connections in production functionality.

**Important:** This is a real authentication system, not just a WhatsApp QR display feature. The QR connection, session persistence, phone-based authentication, OTP delivery, tenant isolation, and Super Admin exceptions must all work together as one coherent production-ready architecture.

Start by analyzing the existing codebase, then implement the complete solution. At the end, provide a summary of changed files, database changes, implemented features, test results, and exact deployment steps.