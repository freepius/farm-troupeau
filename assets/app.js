import './styles/app.scss';

document.addEventListener('click', (event) => {
    const target = event.target;

    if (!(target instanceof HTMLElement)) {
        return;
    }

    const button = target.closest('[data-family-accordion-controls] [data-action]');
    if (!(button instanceof HTMLButtonElement)) {
        return;
    }

    const action = button.dataset.action;
    const accordions = document.querySelectorAll('details[data-theme-accordion]');

    accordions.forEach((accordion) => {
        if (!(accordion instanceof HTMLDetailsElement)) {
            return;
        }

        accordion.open = action === 'open';
    });
});
