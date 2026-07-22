function initTableSearch(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    if (!input || !table) return;

    const rows = table.querySelectorAll('tbody tr');

    input.addEventListener('input', function() {
        const query = this.value.toLowerCase().trim();
        let visibleCount = 0;

        rows.forEach(function(row) {
            const text = row.textContent.toLowerCase();
            const match = text.includes(query);
            row.style.display = match ? '' : 'none';
            if (match) visibleCount++;
        });

        const noRow = table.parentElement.querySelector('.no-results-msg');
        if (noRow) noRow.remove();

        if (visibleCount === 0 && rows.length > 0) {
            const msg = document.createElement('p');
            msg.className = 'no-results-msg';
            msg.style.cssText = 'text-align:center; color:#718096; padding:20px;';
            msg.textContent = 'No results found for "' + input.value + '"';
            table.parentElement.appendChild(msg);
        }
    });
}

function initTableFilter(selectId, tableId, colIndex) {
    const select = document.getElementById(selectId);
    const table = document.getElementById(tableId);
    if (!select || !table) return;

    const rows = table.querySelectorAll('tbody tr');

    select.addEventListener('change', function() {
        const value = this.value.toLowerCase();

        rows.forEach(function(row) {
            if (!value) {
                row.style.display = '';
            } else {
                const cell = row.cells[colIndex];
                const match = cell && cell.textContent.toLowerCase().includes(value);
                row.style.display = match ? '' : 'none';
            }
        });
    });
}

function initSearchableSelect(selectId) {
    const select = document.getElementById(selectId);
    if (!select) return;

    const wrapper = document.createElement('div');
    wrapper.className = 'searchable-select-wrapper';
    wrapper.style.cssText = 'position:relative;';

    const searchInput = document.createElement('input');
    searchInput.type = 'text';
    searchInput.placeholder = 'Search...';
    searchInput.className = 'searchable-select-search';
    searchInput.style.cssText = 'width:100%; padding:8px 12px; border:2px solid #e2e8f0; border-radius:6px; margin-bottom:6px; font-size:13px; box-sizing:border-box;';

    select.parentNode.insertBefore(wrapper, select);
    wrapper.appendChild(searchInput);
    wrapper.appendChild(select);

    const options = select.querySelectorAll('option');

    searchInput.addEventListener('input', function() {
        const query = this.value.toLowerCase();
        options.forEach(function(opt) {
            if (opt.value === '0' || opt.value === '') {
                opt.style.display = '';
                return;
            }
            opt.style.display = opt.textContent.toLowerCase().includes(query) ? '' : 'none';
        });
    });
}
