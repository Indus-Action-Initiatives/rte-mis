document.addEventListener('DOMContentLoaded', function () {

    const dropdown = document.querySelector('.language-dropdown');
    if (!dropdown) return;

    const select = dropdown.querySelector('.lang-dropdown-select-element');
    const text = dropdown.querySelector('.language-dropdown__icon');
    const arrow = dropdown.querySelector('.language-dropdown__button');

    // ✅ 3. Click anywhere → trigger select
    dropdown.addEventListener('click', function (e) {
        // Prevent double trigger
        if (e.target !== select) {
            select.focus();
            select.click(); // works in most cases
        }
    });

    // ✅ 4. Arrow animation
    select.addEventListener('focus', () => {
        arrow.classList.add('open');
    });

    select.addEventListener('blur', () => {
        arrow.classList.remove('open');
    });

});