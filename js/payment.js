// PHP supplies policy through native HTML constraints; retain immediate feedback.
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('membershipForm');
    if (!form) return;
    const plan = form.elements.selected_plan;
    const method = form.elements.paymentMethod;
    const button = document.getElementById('payNowButton');
    const fields = Array.from(form.querySelectorAll('[data-payment-field]'));
    let submitting = false;

    function validateField(input) {
        let message = '';
        if (!input.value.trim()) message = input.dataset.requiredMessage;
        else if (input.validity.patternMismatch) message = input.dataset.invalidMessage;
        else if (input.id === 'expiryDate') {
            const [month, year] = input.value.split('/').map(Number);
            const now = new Date();
            if (2000 + year < now.getFullYear() ||
                (2000 + year === now.getFullYear() && month < now.getMonth() + 1)) message = 'Expired date.';
        }
        // Match the existing quiet feedback while a partial expiry is being typed.
        document.getElementById(input.id + 'Error').textContent =
            input.id === 'expiryDate' && input.value.length > 0 && input.value.length < 5 ? '' : message;
        return !message;
    }
    function validateForm() {
        let fieldsValid = true;
        fields.filter(input => !input.disabled).forEach(input => {
            if (fieldsValid) fieldsValid = validateField(input);
        });
        const valid = !!plan.value && !!method.value && fieldsValid;
        button.disabled = submitting || !valid;
        return valid;
    }
    function togglePaymentFields(clear = true) {
        fields.forEach(input => {
            if (clear) input.value = '';
            input.disabled = input.closest('[data-payment-method]').dataset.paymentMethod !== method.value;
            input.required = !input.disabled;
            document.getElementById(input.id + 'Error').textContent = '';
        });
        form.querySelectorAll('[data-payment-method]').forEach(section => {
            section.style.display = section.dataset.paymentMethod === method.value ? 'block' : 'none';
        });
        validateForm();
    }
    form.querySelectorAll('.select-button').forEach(select => {
        select.addEventListener('click', () => {
            plan.value = select.dataset.plan;
            form.querySelectorAll('.select-button').forEach(other => {
                const selected = other === select;
                other.classList.toggle('selected', selected);
                other.setAttribute('aria-pressed', String(selected));
            });
            validateForm();
        });
    });
    fields.forEach(input => input.addEventListener('input', () => {
        if (input.id === 'cardName') input.value = input.value.replace(/[^a-zA-Z\s.-]/g, '');
        else {
            let value = input.value.replace(/\D/g, '');
            if (input.id === 'expiryDate' && value.length > 2) value = value.slice(0, 2) + '/' + value.slice(2, 4);
            input.value = value;
        }
        validateField(input);
        validateForm();
    }));
    method.addEventListener('change', () => togglePaymentFields());
    form.addEventListener('submit', event => {
        event.preventDefault();
        if (submitting || !validateForm()) return;
        submitting = true;
        button.classList.add('button--loading');
        button.disabled = true;
        setTimeout(() => form.submit(), 3000);
    });
    togglePaymentFields(false);
});
