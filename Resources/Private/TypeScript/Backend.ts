import '../Scss/Backend.scss';

document.addEventListener('DOMContentLoaded', function() {
    const startBtn = document.getElementById('start-audit-btn') as HTMLButtonElement | null;
    const loader = document.getElementById('alice-loader') as HTMLElement | null;
    const resultsContainer = document.getElementById('alice-results-container') as HTMLElement | null;
    const noResultsMessage = document.getElementById('no-analysis-message') as HTMLElement | null;
    const iframe = document.getElementById('alice-analyzer-iframe') as HTMLIFrameElement | null;
    const errorText = document.getElementById('alice-error-text') as HTMLElement | null;
    const closeLoaderBtn = document.getElementById('alice-close-loader-btn') as HTMLButtonElement | null;

    if (!startBtn || !loader || !resultsContainer || !noResultsMessage || !iframe || !errorText || !closeLoaderBtn) return;

    const thresholds: Record<string, { good: number, poor: number }> = {
        lcp: { good: 2.5, poor: 4 },
        cls: { good: 0.1, poor: 0.25 },
        inp: { good: 200, poor: 500 }
    };

    const labels = {
        good: resultsContainer.dataset.labelsGood || 'Good',
        med: resultsContainer.dataset.labelsMed || 'Needs Improvement',
        poor: resultsContainer.dataset.labelsPoor || 'Poor',
        ok: resultsContainer.dataset.labelsOk || 'Basic SEO OK',
        measuring: resultsContainer.dataset.labelsMeasuring || 'Measuring...',
        waiting: resultsContainer.dataset.labelsWaiting || 'Waiting for Interaction...',
        captured: resultsContainer.dataset.labelsCaptured || 'Captured: %s',
        alt: resultsContainer.dataset.labelsAlt || 'Alt',
        dimensions: resultsContainer.dataset.labelsDimensions || 'Dimensions',
        unset: resultsContainer.dataset.labelsUnset || 'Unset',
        loading: resultsContainer.dataset.labelsLoading || 'Loading',
        loadingEager: resultsContainer.dataset.labelsLoadingEager || 'eager',
        loadingLazy: resultsContainer.dataset.labelsLoadingLazy || 'lazy',
        path: resultsContainer.dataset.labelsPath || 'Path',
        inventory: resultsContainer.dataset.labelsInventory || 'Metadata Inventory',
        errorTimeout: resultsContainer.dataset.errorTimeout || 'Timeout',
        errorAccess: resultsContainer.dataset.errorAccess || 'Access Denied',
        errorNetwork: resultsContainer.dataset.errorNetwork || 'Network Error',
        errorSelection: resultsContainer.dataset.errorSelection || 'Selection Error.',
        errorRoute: resultsContainer.dataset.errorRoute || 'Route Error: Missing Analyze URI',
        errorUnknown: resultsContainer.dataset.errorUnknown || 'Unknown Server Error',
        seoMissing: {
            'error.missing_title': resultsContainer.dataset.seoMissingTitle || 'Missing title',
            'error.missing_description': resultsContainer.dataset.seoMissingDescription || 'Missing description',
            'error.missing_keywords': resultsContainer.dataset.seoMissingKeywords || 'Missing keywords',
            'error.missing_robots': resultsContainer.dataset.seoMissingRobots || 'Missing robots'
        }
    };

    closeLoaderBtn.addEventListener('click', () => {
        loader.style.display = 'none';
        resetError();
    });

    startBtn.addEventListener('click', function(this: HTMLButtonElement) {
        const pageId = this.dataset.pageId;
        const auditRunnerUri = this.dataset.auditRunnerUri;
        if (!pageId || !auditRunnerUri) {
            alert(labels.errorSelection);
            return;
        }

        resetProgress();
        resetError();
        loader.style.display = 'flex';
        updateStep('step-iframe', 'active');

        const frontendUrl = '/index.php?id=' + pageId + '&no_cache=1';
        iframe.src = frontendUrl;

        let vitals: Record<string, number> = { lcp: 0, cls: 0, inp: 0 };
        let metricsReceived: Record<string, boolean> = { lcp: false, cls: false, inp: false };

        const messageHandler = function(event: MessageEvent) {
            if (event.data && event.data.type === 'WEB_VITAL') {
                const name = (event.data.name as string).toLowerCase();
                const value = event.data.value;

                if (!metricsReceived[name]) {
                    console.log(`Alice Captured [${name}]: ${value}`);
                    vitals[name] = value;
                    metricsReceived[name] = true;
                    
                    let displayValue = value.toString();
                    if (name === 'lcp') displayValue = (value / 1000).toFixed(2) + 's';
                    if (name === 'cls') displayValue = value.toFixed(3);
                    if (name === 'inp') displayValue = Math.round(value) + 'ms';
                    
                    const capturedText = labels.captured.replace('%s', displayValue);
                    updateStep('step-' + name, 'done', capturedText);

                    if (metricsReceived.lcp && metricsReceived.cls) {
                        finishAudit();
                    }
                }
            } else if (event.data && event.data.type === 'ERROR') {
                showError(event.data.msg || labels.errorAccess);
                cleanupAudit();
            }
        };

        window.addEventListener('message', messageHandler);

        iframe.onload = function() {
            iframe.onload = null;
            updateStep('step-iframe', 'done');
            updateStep('step-script', 'active');

            try {
                const doc = iframe.contentDocument || iframe.contentWindow?.document;
                if (!doc) throw new Error('No document');

                const scriptEl = doc.createElement('script');
                scriptEl.type = 'module';
                scriptEl.src = auditRunnerUri + '?t=' + Date.now();
                scriptEl.onload = () => {
                    updateStep('step-script', 'done');
                    updateStep('step-lcp', 'active', labels.measuring);
                    updateStep('step-cls', 'active', labels.measuring);
                    updateStep('step-inp', 'active', labels.waiting);
                };
                scriptEl.onerror = () => {
                    updateStep('step-script', 'error');
                    showError(labels.errorAccess);
                    cleanupAudit();
                };
                doc.head.appendChild(scriptEl);
            } catch (e) {
                showError(labels.errorAccess);
                cleanupAudit();
            }
        };

        function cleanupAudit() {
            window.removeEventListener('message', messageHandler);
            iframe!.src = 'about:blank';
        }

        function finishAudit() {
            cleanupAudit();
            updateStep('step-sync', 'active');

            const analyzeUri = resultsContainer!.dataset.analyzeUri;
            if (!analyzeUri) {
                showError(labels.errorRoute);
                return;
            }

            const formData = new FormData();
            formData.append('vitals[lcp]', (vitals.lcp / 1000).toString());
            formData.append('vitals[cls]', vitals.cls.toString());
            formData.append('vitals[inp]', vitals.inp.toString());

            fetch(analyzeUri, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(async response => {
                const responseText = await response.text();
                try {
                    const data = JSON.parse(responseText);
                    console.log('Alice: Sync Response received', data);
                    if (data.success) {
                        updateStep('step-sync', 'done');
                        setTimeout(() => {
                            updateDashboard(data.analysis);
                            resultsContainer!.classList.remove('hidden');
                            noResultsMessage!.classList.add('hidden');
                            loader!.style.display = 'none';
                        }, 500);
                    } else {
                        showError(data.error || labels.errorUnknown);
                    }
                } catch (e) {
                    showError(labels.errorNetwork);
                }
            })
            .catch(() => {
                showError(labels.errorNetwork);
            });
        }
    });

    function showError(msg: string) {
        if (errorText) errorText.textContent = msg;
        loader?.classList.add('loader-error');
    }

    function resetError() {
        if (errorText) errorText.textContent = '';
        loader?.classList.remove('loader-error');
    }

    function resetProgress() {
        ['step-iframe', 'step-script', 'step-lcp', 'step-cls', 'step-inp', 'step-sync'].forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.className = 'alice-progress-item step-pending';
                const icon = el.querySelector('i');
                const statusSpan = el.querySelector('.step-status');
                if (icon) icon.className = 'fa fa-circle-o';
                if (statusSpan) statusSpan.textContent = '';
            }
        });
    }

    function updateStep(id: string, status: string, statusDetail: string = '') {
        const el = document.getElementById(id);
        if (!el) return;
        const icon = el.querySelector('i');
        const statusSpan = el.querySelector('.step-status');
        el.className = 'alice-progress-item step-' + status;
        if (icon) {
            if (status === 'active') icon.className = 'fa fa-spinner fa-spin';
            else if (status === 'done') icon.className = 'fa fa-check-circle';
            else if (status === 'error') icon.className = 'fa fa-exclamation-circle';
        }
        if (statusSpan) {
            statusSpan.textContent = statusDetail ? `(${statusDetail})` : '';
        }
    }

    function updateDashboard(analysis: any) {
        console.log('Alice: Dashboard update triggered', analysis);
        const lcpVal = document.getElementById('lcp-value');
        const clsVal = document.getElementById('cls-value');
        const inpVal = document.getElementById('inp-value');

        if (lcpVal) lcpVal.textContent = (analysis.lcp || 0).toFixed(2) + 's';
        if (clsVal) clsVal.textContent = (analysis.cls || 0).toFixed(3);
        if (inpVal) inpVal.textContent = Math.round(analysis.inp || 0) + 'ms';

        updateStatusUI('lcp', analysis.lcp || 0);
        updateStatusUI('cls', analysis.cls || 0);
        updateStatusUI('inp', analysis.inp || 0);

        if (analysis.results && analysis.results.images) {
            const imageList = document.getElementById('image-audit-list');
            if (imageList) {
                imageList.innerHTML = '';
                analysis.results.images.forEach((img: any) => {
                    const li = document.createElement('li');
                    li.className = 'alice-audit-item';
                    li.innerHTML = `
                        <details class="alice-image-accordion">
                            <summary>
                                <span class="alice-status-icon ${img.issue ? 'status-fail' : 'status-ok'}">${img.issue ? '✘' : '✔'}</span>
                                <span class="alice-filename">${img.filename || labels.unset}</span>
                                <i class="fa fa-angle-down alice-chevron"></i>
                            </summary>
                            <div class="alice-accordion-content">
                                <div class="alice-image-detail"><strong>${labels.alt}:</strong> ${img.alt || '---'}</div>
                                <div class="alice-image-detail"><strong>${labels.dimensions}:</strong> ${img.width ? img.width + 'x' + img.height : labels.unset}</div>
                                <div class="alice-image-detail"><strong>${labels.loading}:</strong> ${
                                    img.loading === 'eager' ? labels.loadingEager : 
                                    (img.loading === 'lazy' ? labels.loadingLazy : img.loading)
                                }</div>
                                <div class="alice-image-detail"><strong>${labels.path}:</strong> <small>${img.src}</small></div>
                            </div>
                        </details>`;
                    imageList.appendChild(li);
                });
            }
        }

        if (analysis.results && analysis.results.seo) {
            const seoList = document.getElementById('seo-audit-list');
            if (seoList) {
                seoList.innerHTML = '';
                const seo = analysis.results.seo;

                // Issues
                if (seo.issues && seo.issues.length > 0) {
                    seo.issues.forEach((issueKey: string) => {
                        const li = document.createElement('li');
                        li.className = 'alice-audit-item';
                        const label = (labels.seoMissing as any)[issueKey] || issueKey;
                        li.innerHTML = `<span class="alice-status-icon status-fail">✘</span> ${label}`;
                        seoList.appendChild(li);
                    });
                } else {
                    const li = document.createElement('li');
                    li.className = 'alice-audit-item';
                    li.innerHTML = `<span class="alice-status-icon status-ok">✔</span> ${labels.ok}`;
                    seoList.appendChild(li);
                }

                // Inventory
                if (seo.inventory && seo.inventory.length > 0) {
                    const divider = document.createElement('li');
                    divider.className = 'alice-audit-divider';
                    divider.innerHTML = `<strong>${labels.inventory}</strong>`;
                    seoList.appendChild(divider);

                    seo.inventory.forEach((meta: any) => {
                        const li = document.createElement('li');
                        li.className = 'alice-audit-info-item';
                        const croppedContent = meta.content.length > 50 ? meta.content.substring(0, 50) + '...' : meta.content;
                        li.innerHTML = `
                            <span class="alice-meta-name">${meta.name}:</span>
                            <span class="alice-meta-content">${croppedContent}</span>
                        `;
                        seoList.appendChild(li);
                    });
                }
            }
        }
    }

    function updateStatusUI(type: string, value: number) {
        const card = document.getElementById(`${type}-card`);
        const statusHeader = document.getElementById(`${type}-status`);
        const config = thresholds[type];
        if (!card || !statusHeader) return;

        card.className = 'alice-card vital-card';
        statusHeader.className = 'vital-status';

        if (value <= config.good) {
            card.classList.add('vital-good');
            statusHeader.classList.add('bg-good');
            statusHeader.textContent = labels.good;
        } else if (value <= config.poor) {
            card.classList.add('vital-needs-improvement');
            statusHeader.classList.add('bg-med');
            statusHeader.textContent = labels.med;
        } else {
            card.classList.add('vital-poor');
            statusHeader.classList.add('bg-poor');
            statusHeader.textContent = labels.poor;
        }
    }

    if (resultsContainer && !resultsContainer.classList.contains('hidden')) {
        const lcpValueText = document.getElementById('lcp-value')?.textContent;
        const clsValueText = document.getElementById('cls-value')?.textContent;
        const inpValueText = document.getElementById('inp-value')?.textContent;
        if (lcpValueText) updateStatusUI('lcp', parseFloat(lcpValueText));
        if (clsValueText) updateStatusUI('cls', parseFloat(clsValueText));
        if (inpValueText) updateStatusUI('inp', parseFloat(inpValueText));
    }
});
