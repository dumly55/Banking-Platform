// Small form behaviours shared by every page, replacing per-field inline jQuery snippets.
//   data-filter="digits"  strips non-digits as you type
//   data-filter="upper"   uppercases and keeps letters only (state codes)
//   data-filter="letters" keeps letters, spaces and commas (city names)
//   data-filter="money"   allows digits with up to 2 decimals
//   data-match="#id"      shows whether this field matches another (password / PIN confirmation)
document.addEventListener('input', event => {
    const el = event.target;
    const filter = el.dataset && el.dataset.filter;
    if (filter === 'digits') el.value = el.value.replace(/\D/g, '');
    else if (filter === 'upper') el.value = el.value.toUpperCase().replace(/[^A-Z]/g, '');
    else if (filter === 'letters') el.value = el.value.replace(/[^a-zA-Z, ]/g, '');
    else if (filter === 'money') {
        const match = el.value.replace(/[^\d.]/g, '').match(/^\d*(\.\d{0,2})?/);
        el.value = match ? match[0] : '';
    }

    document.querySelectorAll('[data-match]').forEach(confirm => {
        const original = document.querySelector(confirm.dataset.match);
        const message = confirm.parentElement.querySelector('.match-message');
        if (!original || !message || confirm.value === '') {
            if (message) message.textContent = '';
            return;
        }
        const matches = original.value === confirm.value;
        message.textContent = matches ? 'Matches ✓' : 'Does not match ✕';
        message.style.color = matches ? 'green' : 'red';
        confirm.setCustomValidity(matches ? '' : 'Values do not match');
    });
});

function togglePopup(id) {
    document.getElementById(id).classList.toggle('active');
}
