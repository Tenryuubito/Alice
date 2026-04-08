import { Selectors } from '../Constants';

export const LoaderManager = {
    show: () => {
        const loader = document.querySelector(Selectors.loader) as HTMLElement;
        if (loader) {
            loader.classList.remove('hidden');
        }
    },

    hide: () => {
        const loader = document.querySelector(Selectors.loader) as HTMLElement;
        if (loader) {
            loader.classList.add('hidden');
        }
    },

    updateStep: (stepId: string, status: 'active' | 'success' | 'error' | 'pending', detail: string = '') => {
        const step = document.getElementById(stepId);
        if (!step) {
            return;
        }

        step.className = `alice-progress-item step-${status}`;
        const icon = step.querySelector('i');
        const statusText = step.querySelector('.step-status');

        if (icon) {
            icon.className = 'alice-icon';
            if (status === 'success') {
                icon.classList.add('alice-icon-check-circle');
            } else if (status === 'error') {
                icon.classList.add('alice-icon-error');
            } else if (status === 'active') {
                icon.classList.add('alice-icon-spinner');
            } else {
                icon.classList.add('alice-icon-circle');
            }
        }

        if (statusText) {
            if (detail) {
                statusText.textContent = `(${detail})`;
            } else {
                statusText.textContent = status === 'success' ? 'OK' : (status === 'error' ? 'Error' : '');
            }
        }
    },

    resetSteps: () => {
        const steps = document.querySelectorAll('.alice-progress-item');
        steps.forEach(step => {
            step.className = 'alice-progress-item step-pending';
            const icon = step.querySelector('i');
            const statusText = step.querySelector('.step-status');
            if (icon) {
                icon.className = 'alice-icon alice-icon-circle';
            }
            if (statusText) {
                statusText.textContent = '';
            }
        });
        const errorText = document.querySelector(Selectors.errorText) as HTMLElement;
        if (errorText) {
            errorText.classList.add('hidden');
            errorText.textContent = '';
        }
    },

    showError: (message: string) => {
        const errorText = document.querySelector(Selectors.errorText) as HTMLElement;
        if (errorText) {
            errorText.classList.remove('hidden');
            errorText.textContent = message;
        }
    },

    updateGlobalProgress: (completed: number, total: number) => {
        const container = document.querySelector(Selectors.globalProgress.container) as HTMLElement;
        const bar = document.querySelector(Selectors.globalProgress.bar) as HTMLElement;
        const text = document.querySelector(Selectors.globalProgress.text) as HTMLElement;

        if (container) {
            container.classList.remove('hidden');
        }
        if (bar) {
            bar.style.width = `${(completed / total) * 100}%`;
        }
        if (text) {
            text.textContent = `${completed} / ${total} Pages`;
        }
    }
};
