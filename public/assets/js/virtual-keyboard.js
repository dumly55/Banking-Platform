// Employee login: show/hide password toggle and an on-screen keyboard that types into the
// last focused field (useful on shared terminals where keyloggers are a concern).
(() => {
    const toggle = document.getElementById('togglePassword');
    if (toggle) {
        toggle.addEventListener('click', () => {
            const input = toggle.previousElementSibling;
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            toggle.classList.toggle('fa-eye', !show);
            toggle.classList.toggle('fa-eye-slash', show);
        });
    }

    const inputs = document.querySelectorAll('#form-login .form-input');
    let target = inputs[inputs.length - 1];
    inputs.forEach(input => input.addEventListener('focus', () => { target = input; }));

    function replaceSelection(text, deleteBefore = 0) {
        const start = Math.max(0, (target.selectionStart ?? target.value.length) - deleteBefore);
        const end = target.selectionEnd ?? target.value.length;
        target.value = target.value.slice(0, start) + text + target.value.slice(end);
        target.focus();
        target.setSelectionRange(start + text.length, start + text.length);
        target.dispatchEvent(new Event('input', { bubbles: true }));
    }

    const caps = document.querySelector('.keyboard .capslock');
    document.querySelectorAll('.keyboard li').forEach(key => {
        key.addEventListener('mousedown', event => {
            event.preventDefault(); // keep focus and caret position in the input
            key.classList.add('touch');
            setTimeout(() => key.classList.remove('touch'), 100);

            if (key === caps) {
                const on = caps.classList.toggle('on');
                document.querySelectorAll('.keyboard .char').forEach(c => {
                    c.textContent = on ? c.textContent.toUpperCase() : c.textContent.toLowerCase();
                });
            } else if (key.classList.contains('backspace')) {
                const hasSelection = target.selectionStart !== target.selectionEnd;
                replaceSelection('', hasSelection ? 0 : 1);
            } else {
                replaceSelection(key.textContent);
            }
        });
    });
})();
