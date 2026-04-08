export const Utils = {
    ajax: async (url: string, method: string = 'GET', data: any = null) => {
        const options: RequestInit = {
            method,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        };
        if (data) {
            options.body = data instanceof FormData ? data : JSON.stringify(data);
            if (!(data instanceof FormData)) {
                options.headers = { ...options.headers, 'Content-Type': 'application/json' };
            }
        }
        const response = await fetch(url, options);
        const text = await response.text();
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('ALICE AJAX ERROR: Could not parse JSON. Response was:', text.substring(0, 500));
            throw e;
        }
    },

    formatNumber: (num: number, decimals: number = 2) => {
        return Number(num).toFixed(decimals);
    },

    debounce: (fn: Function, ms: number) => {
        let timeoutId: ReturnType<typeof setTimeout>;
        return function(this: any, ...args: any[]) {
            clearTimeout(timeoutId);
            timeoutId = setTimeout(() => fn.apply(this, args), ms);
        };
    }
};
