<?php

// Routes are split by context and loaded from bootstrap/app.php:
//   routes/central.php       central domain  (domain.com/admin)
//   routes/tenant_admin.php  tenant domain   (tenant.domain.com/admin)
//   routes/storefront.php    tenant domain   (tenant.domain.com/{locale}/...)
