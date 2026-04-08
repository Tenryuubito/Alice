import '../Scss/Backend.scss';
import { Selectors, DataAttrs } from './Modules/Constants';
import { State } from './Modules/State';
import { LoaderManager } from './Modules/UI/LoaderManager';
import { TableHandler } from './Modules/UI/TableHandler';
import { SingleAudit } from './Modules/Audit/SingleAudit';
import { GlobalCrawler } from './Modules/Audit/GlobalCrawler';

document.addEventListener('DOMContentLoaded', function () {
    const resultsContainer = document.querySelector(Selectors.resultsContainer) as HTMLElement;
    if (!resultsContainer) {
        return;
    }

    State.initFromDOM(resultsContainer);
    TableHandler.init();

    const startBtn = document.querySelector(Selectors.startBtn) as HTMLButtonElement | null;
    if (startBtn) {
        startBtn.addEventListener('click', () => {
            const pageId = startBtn.getAttribute(DataAttrs.pageId);
            const runnerUri = startBtn.getAttribute(DataAttrs.runnerUri);
            if (pageId && runnerUri) {
                SingleAudit.run(pageId, runnerUri);
            }
        });
    }

    const analyzeAllBtn = document.querySelector(Selectors.analyzeAllBtn) as HTMLButtonElement | null;
    if (analyzeAllBtn) {
        analyzeAllBtn.addEventListener('click', () => {
            const runnerUri = analyzeAllBtn.getAttribute(DataAttrs.runnerUri);
            if (runnerUri) {
                GlobalCrawler.run(runnerUri);
            }
        });
    }

    const closeLoaderBtn = document.querySelector(Selectors.closeLoaderBtn);
    if (closeLoaderBtn) {
        closeLoaderBtn.addEventListener('click', () => {
            LoaderManager.hide();
        });
    }
});

