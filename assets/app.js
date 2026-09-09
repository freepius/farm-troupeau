import './styles/app.scss';

// Toggle the secondary table row attached to one animal row.
const toggleAnimalDetail = (trigger) => {
    const detailId = trigger.getAttribute('aria-controls');
    const detailRow = detailId ? document.getElementById(detailId) : null;

    if (!(detailRow instanceof HTMLTableRowElement)) {
        return;
    }

    const isExpanded = trigger.getAttribute('aria-expanded') === 'true';

    trigger.setAttribute('aria-expanded', String(!isExpanded));
    detailRow.hidden = isExpanded;
};

// The dedicated print page still relies on the browser print dialog.
const printPage = () => {
    window.print();
};

// Table animal rows are clickable so users do not have to target a tiny control.
document.addEventListener('click', (event) => {
    const target = event.target;

    if (!(target instanceof HTMLElement)) {
        return;
    }

    const printTrigger = target.closest('[data-print-page]');
    if (printTrigger instanceof HTMLButtonElement) {
        printPage();

        return;
    }

    const detailTrigger = target.closest('[data-row-detail-trigger]');
    if (detailTrigger instanceof HTMLTableRowElement) {
        toggleAnimalDetail(detailTrigger);

        return;
    }

    // Family accordions live on the family page and share the main app bundle.
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

// Keep clickable animal rows operable from the keyboard.
document.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter' && event.key !== ' ') {
        return;
    }

    const target = event.target;

    if (!(target instanceof HTMLElement)) {
        return;
    }

    const detailTrigger = target.closest('[data-row-detail-trigger]');
    if (!(detailTrigger instanceof HTMLTableRowElement)) {
        return;
    }

    event.preventDefault();
    toggleAnimalDetail(detailTrigger);
});
