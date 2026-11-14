import './lib/boot-core';
import $ from 'jquery';
import toastr from 'toastr';
import './lib/ajax-form';
import './lib/storefront';
import './lib/gift-finder';
import './lib/sliders';
import './lib/ui';

window.$ = window.jQuery = $;
window.toastr = toastr;

// toasts: top-end on desktop, bottom-centre above the bottom bar on phones / tablets (design 21:1807)
const rtl = document.documentElement.dir === 'rtl';
toastr.options = { closeButton: true, progressBar: true, positionClass: matchMedia('(min-width: 1024px)').matches ? (rtl ? 'toast-top-left' : 'toast-top-right') : 'toast-bottom-center', timeOut: 4000, rtl };
