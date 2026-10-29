import './lib/boot-core';
import $ from 'jquery';
import toastr from 'toastr';
import './lib/ajax-form';
import './lib/storefront';
import './lib/ui';

window.$ = window.jQuery = $;
window.toastr = toastr;

toastr.options = { closeButton: true, progressBar: true, positionClass: document.documentElement.dir === 'rtl' ? 'toast-top-left' : 'toast-top-right', timeOut: 4000, rtl: document.documentElement.dir === 'rtl' };
