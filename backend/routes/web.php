<?php

// Routes are split by context and loaded from bootstrap/app.php (one host, APP_URL):
//   routes/admin.php           /admin/...            admin panel (guard `admin`, names admin.*)
//   routes/storefront.php      /{locale}/...         storefront (names storefront.*), `/` -> /{default_locale}
//   routes/api_v1.php          /api/v1/...           mobile JSON API (names api.v1.*), routes/api.php: health, app-config, legacy store list
//   routes/api_integration.php /api/integration/v1   n8n integration API (names integration.*)
