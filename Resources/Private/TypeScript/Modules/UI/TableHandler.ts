import { Selectors, DataAttrs } from '../Constants';

export const TableHandler = {
    init: () => {
        const filters = document.querySelectorAll(Selectors.table.filters);
        filters.forEach(btn => {
            btn.addEventListener('click', (e) => {
                const target = e.currentTarget as HTMLElement;
                const filter = target.getAttribute(DataAttrs.filter) || 'all';
                
                filters.forEach(b => b.classList.remove('active'));
                target.classList.add('active');
                
                TableHandler.applyFilter(filter);
            });
        });

        // Pagination initial call
        TableHandler.updatePagination(1);
    },

    applyFilter: (filter: string) => {
        const rows = document.querySelectorAll(Selectors.table.rows) as NodeListOf<HTMLElement>;
        rows.forEach(row => {
            const status = row.getAttribute(DataAttrs.status);
            if (filter === 'all' || status === filter) {
                row.classList.remove('filtered-out');
            } else {
                row.classList.add('filtered-out');
            }
        });
        TableHandler.updatePagination(1);
    },

    updatePagination: (page: number) => {
        const table = document.querySelector(Selectors.table.id) as HTMLElement;
        if (!table) return;

        const perPage = parseInt(table.getAttribute(DataAttrs.perPage) || '10');
        const rows = Array.from(document.querySelectorAll(`${Selectors.table.rows}:not(.filtered-out)`)) as HTMLElement[];
        const totalRows = rows.length;
        const totalPages = Math.ceil(totalRows / perPage);

        rows.forEach((row, index) => {
            const start = (page - 1) * perPage;
            const end = start + perPage;
            if (index >= start && index < end) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });

        const paginationContainer = document.querySelector(Selectors.table.pagination);
        if (paginationContainer) {
            paginationContainer.innerHTML = '';
            if (totalPages > 1) {
                for (let i = 1; i <= totalPages; i++) {
                    const btn = document.createElement('button');
                    btn.textContent = i.toString();
                    btn.className = `pagination-btn ${i === page ? 'active' : ''}`;
                    btn.onclick = () => TableHandler.updatePagination(i);
                    paginationContainer.appendChild(btn);
                }
            }
        }
    }
};
