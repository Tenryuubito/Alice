/**
 * Alice Audit Runner (Version 3.0)
 * Optimized for 'Technically Visible' iframe-based measurement.
 */
import { onLCP, onCLS, onINP } from 'web-vitals';

(function() {

    try {
        // Setup standard reporters.
        // NOTE: These only fire in a "painted" context. The iframe is now set to opacity: 0.01 to ensure paints occur.
        onLCP(sendToParent, { reportAllChanges: true });
        onCLS(sendToParent, { reportAllChanges: true });
        onINP(sendToParent, { reportAllChanges: true });

        // Simulation: Tiny interaction to trigger INP
        setTimeout(() => {
            window.scrollTo(0, 10);
            setTimeout(() => window.scrollTo(0, 0), 100);
            
            // Dummy click on body
            const dummy = document.createElement('div');
            dummy.style.position = 'absolute';
            dummy.style.top = '0'; dummy.style.left = '0';
            dummy.style.width = '1px'; dummy.style.height = '1px';
            dummy.style.opacity = '0';
            document.body.appendChild(dummy);
            dummy.click();
            setTimeout(() => dummy.remove(), 100);
        }, 1500);

        // FALLBACK: Observer for already-buffered entries.
        // Using PerformanceObserver with 'buffered: true' is the non-deprecated way 
        // to retrieve entries that occurred before the script was initialized.
        try {
            const observerLCP = new PerformanceObserver((entryList) => {
                const entries = entryList.getEntries();
                if (entries.length > 0) {
                    const latest = entries[entries.length - 1];
                    sendToParent({ name: 'LCP', value: latest.startTime });
                }
            });
            observerLCP.observe({ type: 'largest-contentful-paint', buffered: true });

            const observerCLS = new PerformanceObserver((entryList) => {
                let clsScore = 0;
                entryList.getEntries().forEach((entry: any) => {
                    if (!entry.hadRecentInput) clsScore += entry.value;
                });
                if (clsScore > 0) {
                    sendToParent({ name: 'CLS', value: clsScore });
                }
            });
            observerCLS.observe({ type: 'layout-shift', buffered: true });
        } catch (e) {
            console.warn('Alice: PerformanceObserver fallback not supported or failed.', e);
        }

        function sendToParent(metric: any) {
            window.parent.postMessage({
                type: 'WEB_VITAL',
                name: metric.name,
                value: metric.value
            }, '*');
        }
        
    } catch (e) {
        console.error('Alice Audit Runner error:', e);
        window.parent.postMessage({
            type: 'ERROR',
            msg: 'Audit Runner encountered an internal error: ' + (e as Error).message
        }, '*');
    }
})();
