export const Selectors = {
    startBtn: '#start-audit-btn',
    analyzeAllBtn: '#analyze-all-btn',
    loader: '#alice-loader',
    closeLoaderBtn: '#alice-close-loader-btn',
    errorText: '#alice-error-text',
    iframe: '#alice-analyzer-iframe',
    resultsContainer: '#alice-results-container',
    globalProgress: {
        container: '#global-progress-container',
        bar: '#global-progress-bar',
        text: '#global-progress-text'
    },
    table: {
        id: '#global-issues-table',
        rows: '.alice-issue-row',
        pagination: '#global-pagination',
        filters: '.filter-btn'
    },
    vitals: {
        lcp: { value: '#lcp-value', status: '#lcp-status', card: '#lcp-card' },
        cls: { value: '#cls-value', status: '#cls-status', card: '#cls-card' },
        inp: { value: '#inp-value', status: '#inp-status', card: '#inp-card' }
    }
};

export const DataAttrs = {
    pageId: 'data-page-id',
    runnerUri: 'data-audit-runner-uri',
    analyzeUri: 'data-analyze-uri',
    getPagesUri: 'data-get-pages-uri',
    perPage: 'data-per-page',
    status: 'data-status',
    filter: 'data-filter'
};
