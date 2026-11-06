document.addEventListener('submit', e => {
    const f = e.target;
    
    if (!(f instanceof HTMLFormElement) || !f.matches('[data-validate]')) {
        return;
    }
    
    const p = f.elements.namedItem('phone_number');
    if (p && p.value && !/^(?:\+233|0)[235]\d{8}$/.test(p.value.trim())) {
        p.setCustomValidity('Use a valid Ghana phone number.');
        p.reportValidity();
        e.preventDefault();
        return;
    } else if (p) {
        p.setCustomValidity('');
    }

    const pwd = f.elements.namedItem('password');
    const cpwd = f.elements.namedItem('confirm_password');
    if (pwd && cpwd && pwd.value !== cpwd.value) {
        cpwd.setCustomValidity('Passwords do not match.');
        cpwd.reportValidity();
        e.preventDefault();
        return;
    } else if (cpwd) {
        cpwd.setCustomValidity('');
    }

    if (!f.reportValidity()) {
        e.preventDefault();
    }
});

document.addEventListener('input', e => {
    const target = e.target;
    if (target instanceof HTMLInputElement) {
        if (target.name === 'confirm_password' || target.name === 'password') {
            const form = target.form;
            if (form) {
                const pwd = form.elements.namedItem('password');
                const cpwd = form.elements.namedItem('confirm_password');
                if (pwd && cpwd && cpwd.value) {
                    if (pwd.value !== cpwd.value) {
                        cpwd.setCustomValidity('Passwords do not match.');
                    } else {
                        cpwd.setCustomValidity('');
                    }
                }
            }
        } else if (target.name === 'phone_number') {
            target.setCustomValidity('');
        }
    }
});
