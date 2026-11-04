document.addEventListener('click', function (event) {
    const button = event.target.closest('[data-print-ticket]');
    if (button) window.print();
});
