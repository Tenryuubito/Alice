/**
 * Alice Audit Runner (Version 3.0)
 * Optimized for 'Technically Visible' iframe-based measurement.
 */
import { onLCP, onCLS, onINP } from 'web-vitals';

(function() {
    console.log('Alice Audit Runner (V3) initialized.');

    try {
        // Setup standard reporters.
        // NOTE: These only fire in a "painted" context. The iframe is now set to opacity: 0.01 to ensure paints occur.
        onLCP(sendToParent, { reportAllChanges: true });
        onCLS(sendToParent, { reportAllChanges: true });
        onINP(sendToParent, { reportAllChanges: true });

        // FALLBACK: Immediate check for already-buffered entries.
        // Sometimes web-vitals is too slow or misses the initial buffered entries in an iframe.
        setTimeout(() => {
            const lcpEntries = performance.getEntriesByType('largest-contentful-paint');
            if (lcpEntries.length > 0) {
                const latest = lcpEntries[lcpEntries.length - 1];
                sendToParent({ name: 'LCP', value: latest.startTime });
            }

            const clsEntries = performance.getEntriesByType('layout-shift');
            if (clsEntries.length > 0) {
                let clsScore = 0;
                clsEntries.forEach((entry: any) => {
                    if (!entry.hadRecentInput) clsScore += entry.value;
                });
                sendToParent({ name: 'CLS', value: clsScore });
            }
        }, 1000);

        function sendToParent(metric: any) {
            console.log(`Alice Captured [${metric.name}]: ${metric.value}`);
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
