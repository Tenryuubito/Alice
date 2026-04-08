import { Selectors } from '@modules/Constants';
import { State } from '@modules/State';
import { LoaderManager } from '@modules/UI/LoaderManager';
import { Utils } from '@modules/Utils';

export const SingleAudit = {
    run: (pageId: string, runnerUri: string, silent: boolean = false, callback?: Function) => {
        const state = State.get();
        if (!silent) {
            LoaderManager.resetSteps();
            LoaderManager.show();
        }

        LoaderManager.updateStep('step-iframe', 'active');
        const iframe = document.querySelector(Selectors.iframe) as HTMLIFrameElement;
        const frontendUrl = '/index.php?id=' + pageId + '&no_cache=1';
        iframe.src = frontendUrl;

        let vitals = { lcp: 0, cls: 0, inp: 0 };
        let received = { lcp: false, cls: false, inp: false };

        const messageHandler = (event: MessageEvent) => {
            if (event.data?.type === 'WEB_VITAL') {
                const name = (event.data.name as string).toLowerCase();
                const val = event.data.value;
                if (received.hasOwnProperty(name) && !received[name as keyof typeof received]) {
                    vitals[name as keyof typeof vitals] = val;
                    received[name as keyof typeof received] = true;
                    
                    if (!silent) {
                        const labelValue = state.labels.captured.replace('%s', val.toString());
                        LoaderManager.updateStep('step-' + name, 'success', labelValue);
                    }
                    
                    if (received.lcp && received.cls) {
                        finish();
                    }
                }
            }
        };

        const finish = async () => {
            window.removeEventListener('message', messageHandler);
            iframe.src = 'about:blank';
            if (!silent) {
                LoaderManager.updateStep('step-sync', 'active');
            }

            try {
                const payload = {
                    id: pageId,
                    vitals: {
                        lcp: (vitals.lcp / 1000).toString(),
                        cls: vitals.cls.toString(),
                        inp: vitals.inp.toString()
                    }
                };

                const data = await Utils.ajax(state.analyzeUri!, 'POST', payload);

                if (data.success) {
                    LoaderManager.updateStep('step-sync', 'success');
                    if (!silent) {
                        location.reload();
                    }
                } else {
                    LoaderManager.updateStep('step-sync', 'error');
                    LoaderManager.showError(state.errors.unknown);
                }
            } catch (e) {
                LoaderManager.updateStep('step-sync', 'error');
                LoaderManager.showError(state.errors.network);
            }
            if (callback) {
                callback();
            }
        };

        window.addEventListener('message', messageHandler);

        iframe.onload = () => {
            if (!silent) {
                LoaderManager.updateStep('step-iframe', 'success');
            }
            const doc = iframe.contentDocument || iframe.contentWindow?.document;
            if (!doc) {
                return;
            }

            const script = doc.createElement('script');
            script.type = 'module';
            script.src = runnerUri + '?t=' + Date.now();
            script.onload = () => {
                if (!silent) {
                    LoaderManager.updateStep('step-script', 'success');
                    ['lcp', 'cls', 'inp'].forEach(s => {
                        LoaderManager.updateStep('step-' + s, 'active', state.labels.measuring);
                    });
                }
            };
            doc.head.appendChild(script);
        };

        setTimeout(() => {
            if (!received.lcp) {
                window.removeEventListener('message', messageHandler);
                if (!silent) {
                    LoaderManager.updateStep('step-sync', 'error');
                    LoaderManager.showError(state.errors.timeout);
                }
                if (callback) {
                    callback();
                }
            }
        }, 30000);
    }
};
