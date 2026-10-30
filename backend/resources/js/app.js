import './lib/boot';
import $ from 'jquery';
import toastr from 'toastr';
import './lib/ajax-form';
import './lib/datatable';
import './lib/repeater';
import './lib/product-form';
import './lib/ops-form';
import './lib/orders-form';
import './lib/growth-form';
import './lib/reports';
import './lib/access-form';
import './lib/storefront';
import './lib/search-select';
import './lib/pos';
import './lib/whatsapp';
import './lib/phone-input';

window.$ = window.jQuery = $;
window.toastr = toastr;

toastr.options = { closeButton: true, progressBar: true, positionClass: document.documentElement.dir === 'rtl' ? 'toast-top-left' : 'toast-top-right', timeOut: 4000, rtl: document.documentElement.dir === 'rtl' };
import './lib/ui';
