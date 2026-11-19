const supportsInvokerCommands = 'commandForElement' in HTMLButtonElement.prototype;

document.addEventListener('click', (event) => {
    const trigger = event.target.closest('button[command]');
    if (!trigger || supportsInvokerCommands) return;

    const dialog = document.getElementById(trigger.getAttribute('commandfor'));
    if (!(dialog instanceof HTMLDialogElement)) return;

    if (trigger.getAttribute('command') === 'show-modal') {
        dialog.showModal();
    } else if (trigger.getAttribute('command') === 'close') {
        dialog.close();
    }
});

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;

    form.querySelectorAll('button[type="submit"]').forEach((button) => {
        button.disabled = true;
    });
});

window.addEventListener('pageshow', () => {
    document.querySelectorAll('form button[type="submit"]:disabled').forEach((button) => {
        button.disabled = false;
    });
});
