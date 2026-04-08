export interface AliceState {
    isAnalyzing: boolean;
    pageId: string | null;
    runnerUri: string | null;
    analyzeUri: string | null;
    getPagesUri: string | null;
    saveSettingsUri: string | null;
    labels: Record<string, string>;
    errors: Record<string, string>;
}

const state: AliceState = {
    isAnalyzing: false,
    pageId: null,
    runnerUri: null,
    analyzeUri: null,
    getPagesUri: null,
    saveSettingsUri: null,
    labels: {},
    errors: {}
};

export const State = {
    get: () => state,
    set: (newState: Partial<AliceState>) => {
        Object.assign(state, newState);
    },
    initFromDOM: (container: HTMLElement) => {
        const dataset = container.dataset;
        State.set({
            analyzeUri: dataset.analyzeUri,
            getPagesUri: dataset.getPagesUri,
            saveSettingsUri: dataset.saveSettingsUri,
            labels: {
                good: dataset.labelsGood || 'Good',
                med: dataset.labelsMed || 'Needs Improvement',
                poor: dataset.labelsPoor || 'Poor',
                ok: dataset.labelsOk || 'OK',
                measuring: dataset.labelsMeasuring || 'Measuring...',
                waiting: dataset.labelsWaiting || 'Capture...',
                captured: dataset.labelsCaptured || 'Captured',
                alt: dataset.labelsAlt || 'Alt',
                dimensions: dataset.labelsDimensions || 'Dimensions',
                unset: dataset.labelsUnset || 'unset',
                loading: dataset.labelsLoading || 'Loading',
                loadingEager: dataset.labelsLoadingEager || 'eager',
                loadingLazy: dataset.labelsLoadingLazy || 'lazy',
                path: dataset.labelsPath || 'Path',
                internal: dataset.labelsInternal || 'Internal',
                external: dataset.labelsExternal || 'External',
                text: dataset.labelsText || 'Text',
                time: dataset.labelsTime || 'Time',
                noLinks: dataset.labelsNoLinks || 'No links found.'
            },
            errors: {
                timeout: dataset.errorTimeout || 'Timeout',
                access: dataset.errorAccess || 'Access Denied',
                network: dataset.errorNetwork || 'Network Error',
                selection: dataset.errorSelection || 'Selection Error',
                route: dataset.errorRoute || 'Route Missing',
                unknown: dataset.errorUnknown || 'Unknown Error',
                seoTitle: dataset.seoMissingTitle || 'Missing Title',
                seoDesc: dataset.seoMissingDescription || 'Missing Description',
                seoKeywords: dataset.seoMissingKeywords || 'Missing Keywords',
                seoRobots: dataset.seoMissingRobots || 'Missing Robots'
            }
        });
    }
};
