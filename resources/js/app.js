import Alpine from 'alpinejs';

window.Alpine = Alpine;

// Bootstrap-style tooltip hooks stay safe here because the initializer exits when Bootstrap is not present.
document.addEventListener('DOMContentLoaded', () => {
    if (! window.bootstrap?.Tooltip) {
        return;
    }

    document
        .querySelectorAll('[data-bs-toggle="tooltip"]')
        .forEach((element) => {
            window.bootstrap.Tooltip.getOrCreateInstance(element);
        });
});

Alpine.start();
