document.addEventListener('click', event => {
    const opener = event.target.closest('[data-modal-open]');
    if (opener) {
        const modal = document.getElementById(opener.dataset.modalOpen);
        if (modal) {
            modal.hidden = false;
            document.body.classList.add('modal-open');
            const focusable = modal.querySelector('input, select, button, a');
            if (focusable) focusable.focus();
        }
        return;
    }

    const closer = event.target.closest('[data-modal-close]');
    const backdrop = event.target.classList.contains('modal-backdrop') ? event.target : null;
    const modal = closer ? closer.closest('.modal-backdrop') : backdrop;
    if (modal) {
        modal.hidden = true;
        if (!document.querySelector('.modal-backdrop:not([hidden])')) document.body.classList.remove('modal-open');
    }
});

document.addEventListener('keydown', event => {
    if (event.key !== 'Escape') return;
    const modal = document.querySelector('.modal-backdrop:not([hidden])');
    if (modal) {
        modal.hidden = true;
        document.body.classList.remove('modal-open');
    }
});
