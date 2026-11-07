# Phase 3 route diff (old 943 -> new 779)

## tenant.* -> admin.* (507/507 twins present, same method+URI)

## central.* with an admin.* twin (MERGE/ADD)
central.admins.create -> admin.admins.create
central.admins.destroy -> admin.admins.destroy
central.admins.edit -> admin.admins.edit
central.admins.index -> admin.admins.index
central.admins.store -> admin.admins.store
central.admins.update -> admin.admins.update
central.admins.validate -> admin.admins.validate
central.ai.models.create -> admin.ai.models.create
central.ai.models.datatable -> admin.ai.models.datatable
central.ai.models.destroy -> admin.ai.models.destroy
central.ai.models.edit -> admin.ai.models.edit
central.ai.models.index -> admin.ai.models.index
central.ai.models.store -> admin.ai.models.store
central.ai.models.update -> admin.ai.models.update
central.ai.models.validate -> admin.ai.models.validate
central.ai.providers.create -> admin.ai.providers.create
central.ai.providers.datatable -> admin.ai.providers.datatable
central.ai.providers.destroy -> admin.ai.providers.destroy
central.ai.providers.edit -> admin.ai.providers.edit
central.ai.providers.index -> admin.ai.providers.index
central.ai.providers.store -> admin.ai.providers.store
central.ai.providers.test -> admin.ai.providers.test
central.ai.providers.update -> admin.ai.providers.update
central.ai.providers.validate -> admin.ai.providers.validate
central.ai.usage.datatable -> admin.ai.usage.datatable
central.ai.usage.index -> admin.ai.usage.index
central.attributes.create -> admin.attributes.create
central.attributes.datatable -> admin.attributes.datatable
central.attributes.destroy -> admin.attributes.destroy
central.attributes.edit -> admin.attributes.edit
central.attributes.index -> admin.attributes.index
central.attributes.store -> admin.attributes.store
central.attributes.update -> admin.attributes.update
central.attributes.validate -> admin.attributes.validate
central.audit.datatable -> admin.audit.datatable
central.audit.index -> admin.audit.index
central.barcode -> admin.barcode
central.brands.create -> admin.brands.create
central.brands.datatable -> admin.brands.datatable
central.brands.destroy -> admin.brands.destroy
central.brands.edit -> admin.brands.edit
central.brands.index -> admin.brands.index
central.brands.store -> admin.brands.store
central.brands.update -> admin.brands.update
central.brands.validate -> admin.brands.validate
central.broadcasting.auth -> admin.broadcasting.auth
central.categories.create -> admin.categories.create
central.categories.destroy -> admin.categories.destroy
central.categories.edit -> admin.categories.edit
central.categories.index -> admin.categories.index
central.categories.store -> admin.categories.store
central.categories.update -> admin.categories.update
central.categories.validate -> admin.categories.validate
central.dashboard -> admin.dashboard
central.erp.anomalies -> admin.erp.anomalies
central.erp.anomalies.export -> admin.erp.anomalies.export
central.erp.hub -> admin.erp.hub
central.languages.create -> admin.languages.create
central.languages.datatable -> admin.languages.datatable
central.languages.destroy -> admin.languages.destroy
central.languages.edit -> admin.languages.edit
central.languages.index -> admin.languages.index
central.languages.store -> admin.languages.store
central.languages.update -> admin.languages.update
central.languages.validate -> admin.languages.validate
central.locale -> admin.locale
central.login -> admin.login
central.login.otp -> admin.login.otp
central.login.otp.resend -> admin.login.otp.resend
central.login.otp.verify -> admin.login.otp.verify
central.login.submit -> admin.login.submit
central.login.validate -> admin.login.validate
central.logout -> admin.logout
central.notifications.index -> admin.notifications.index
central.notifications.read -> admin.notifications.read
central.notifications.read-all -> admin.notifications.read-all
central.products.create -> admin.products.create
central.products.datatable -> admin.products.datatable
central.products.destroy -> admin.products.destroy
central.products.edit -> admin.products.edit
central.products.index -> admin.products.index
central.products.store -> admin.products.store
central.products.update -> admin.products.update
central.products.validate -> admin.products.validate
central.reports.export -> admin.reports.export
central.reports.index -> admin.reports.index
central.reports.show -> admin.reports.show
central.roles.create -> admin.roles.create
central.roles.destroy -> admin.roles.destroy
central.roles.edit -> admin.roles.edit
central.roles.index -> admin.roles.index
central.roles.store -> admin.roles.store
central.roles.update -> admin.roles.update
central.roles.validate -> admin.roles.validate
central.tags.create -> admin.tags.create
central.tags.datatable -> admin.tags.datatable
central.tags.destroy -> admin.tags.destroy
central.tags.edit -> admin.tags.edit
central.tags.index -> admin.tags.index
central.tags.store -> admin.tags.store
central.tags.update -> admin.tags.update
central.tags.validate -> admin.tags.validate
central.whatsapp.chat.conversations.assign -> admin.whatsapp.chat.conversations.assign
central.whatsapp.chat.conversations.destroy -> admin.whatsapp.chat.conversations.destroy
central.whatsapp.chat.conversations.index -> admin.whatsapp.chat.conversations.index
central.whatsapp.chat.conversations.presence -> admin.whatsapp.chat.conversations.presence
central.whatsapp.chat.conversations.read -> admin.whatsapp.chat.conversations.read
central.whatsapp.chat.conversations.show -> admin.whatsapp.chat.conversations.show
central.whatsapp.chat.conversations.store -> admin.whatsapp.chat.conversations.store
central.whatsapp.chat.conversations.update -> admin.whatsapp.chat.conversations.update
central.whatsapp.chat.index -> admin.whatsapp.chat.index
central.whatsapp.chat.limits -> admin.whatsapp.chat.limits
central.whatsapp.chat.media.show -> admin.whatsapp.chat.media.show
central.whatsapp.chat.messages.destroy -> admin.whatsapp.chat.messages.destroy
central.whatsapp.chat.messages.index -> admin.whatsapp.chat.messages.index
central.whatsapp.chat.messages.react -> admin.whatsapp.chat.messages.react
central.whatsapp.chat.messages.resend -> admin.whatsapp.chat.messages.resend
central.whatsapp.chat.messages.store -> admin.whatsapp.chat.messages.store
central.whatsapp.chat.messages.update -> admin.whatsapp.chat.messages.update
central.whatsapp.connect -> admin.whatsapp.connect
central.whatsapp.disconnect -> admin.whatsapp.disconnect
central.whatsapp.index -> admin.whatsapp.index
central.whatsapp.reconnect -> admin.whatsapp.reconnect
central.whatsapp.settings -> admin.whatsapp.settings
central.whatsapp.status -> admin.whatsapp.status
central.whatsapp.test -> admin.whatsapp.test

## central.* dropped (DROP per INVENTORY A / replaced)
central.landing
central.ai.tenants.index
central.ai.tenants.datatable
central.ai.tenants.update
central.ai.tenants.edit
central.ai.tenants.validate
central.erp.audit
central.erp.consolidated
central.erp.inventory
central.erp.migration
central.erp.overview
central.erp.overview.export
central.erp.parties
central.erp.performance
central.erp.periods
central.erp.tenant
central.erp.transactions
central.erp.transactions.show
central.switch-tenant
central.tenants.index
central.tenants.store
central.tenants.create
central.tenants.datatable
central.tenants.validate
central.tenants.update
central.tenants.destroy
central.tenants.edit
central.tenants.growth
central.google.callback
central.choose
central.robots

## api.v1.h.* (header twin) removed: 79

## new names (no old twin)
admin.general-settings.edit
admin.general-settings.update
admin.general-settings.validate
storefront.google.callback
