<?php

namespace App\Services\Orders;

use App\Models\Order;
use ArPHP\I18N\Arabic;
use Barryvdh\DomPDF\Facade\Pdf;

/** Order invoice as a PDF (dompdf). Arabic text is shaped/reordered with ar-php because dompdf has no complex-script support. */
class InvoicePdf
{
    public function render(Order $order): \Barryvdh\DomPDF\PDF
    {
        $order->loadMissing(['items', 'city', 'paymentMethod']);
        $rtl = (bool) current_tenant()?->isRtl(app()->getLocale());
        $shaper = $rtl ? new Arabic : null;
        // Western digits, no 50-char wrapping; mixed Latin/Arabic strings are handled by the shaper.
        $t = fn (?string $s) => ($shaper && $s !== null && $s !== '') ? $shaper->utf8Glyphs($s, 5000, false) : (string) $s;

        return Pdf::setOption(['defaultFont' => 'DejaVu Sans', 'isRemoteEnabled' => false, 'isHtml5ParserEnabled' => true])
            ->loadView('tenant.orders.invoice-pdf', ['order' => $order, 'rtl' => $rtl, 't' => $t, 'tenant' => current_tenant()])
            ->setPaper('a4');
    }

    public function filename(Order $order): string
    {
        return 'invoice-'.preg_replace('/[^A-Za-z0-9_-]+/', '-', $order->number).'.pdf';
    }
}
