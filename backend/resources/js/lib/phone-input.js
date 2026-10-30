import intlTelInput from 'intl-tel-input';
import 'intl-tel-input/dist/css/intlTelInput.css';

// Upgrades every [data-intl-phone] input; the sibling hidden input named in data-intl-phone gets the ISO-2 country.
document.querySelectorAll('input[data-intl-phone]').forEach((el) => {
    const iso = document.querySelector(`input[name="${el.dataset.intlPhone}"]`);
    const iti = intlTelInput(el, {
        initialCountry: ((iso && iso.value) || 'eg').toLowerCase(),
        separateDialCode: true,
        strictMode: false,
        countryOrder: ['eg', 'sa', 'ae'],
    });
    const sync = () => { if (iso) iso.value = (iti.getSelectedCountryData().iso2 || '').toUpperCase(); };
    el.addEventListener('countrychange', sync);
    sync();
});
