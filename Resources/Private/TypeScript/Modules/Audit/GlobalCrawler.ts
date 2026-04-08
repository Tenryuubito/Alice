import { State } from '@modules/State';
import { Utils } from '@modules/Utils';
import { LoaderManager } from '@modules/UI/LoaderManager';
import { SingleAudit } from './SingleAudit';

export const GlobalCrawler = {
    run: async (runnerUri: string) => {
        const state = State.get();
        try {
            const data = await Utils.ajax(state.getPagesUri!);
            const pids = data.pages || [];
            const total = pids.length;
            let completed = 0;

            if (total === 0) {
                console.warn('No pages found to analyze.');
                return;
            }

            for (const pid of pids) {
                LoaderManager.updateGlobalProgress(completed, total);
                
                try {
                    await new Promise<void>((resolve) => {
                        SingleAudit.run(pid.toString(), runnerUri, true, () => {
                            completed++;
                            resolve();
                        });
                    });
                } catch (err) {
                    console.error(`Audit failed for page ${pid}, skipping...`, err);
                    completed++;
                }
            }

            LoaderManager.updateGlobalProgress(total, total);
            setTimeout(() => {
                location.reload();
            }, 1000);

        } catch (e) {
            console.error('Global audit failed', e);
        }
    }
};
