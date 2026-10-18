import { sendPushRequest } from '../services/push-service.js';
import { translate } from '../utils/i18n.js';

export function decodeApplicationServerKey(key) {
    const base64 = key.replaceAll('-', '+').replaceAll('_', '/');
    const binary = atob(base64.padEnd(Math.ceil(base64.length / 4) * 4, '='));
    return Uint8Array.from(binary, (character) => character.codePointAt(0));
}

export function getPushBrowserState(browser = window) {
    if (!browser.isSecureContext || !browser.navigator?.serviceWorker || !browser.PushManager || !browser.Notification) {
        return 'unsupported';
    }
    return browser.Notification.permission;
}

async function getExistingSubscription() {
    const registration = await navigator.serviceWorker.getRegistration('/');
    return registration ? registration.pushManager.getSubscription() : null;
}

async function getReadyRegistration() {
    await navigator.serviceWorker.register('/service-worker.js', { scope: '/', updateViaCache: 'none' });
    let timer;
    try {
        return await Promise.race([
            navigator.serviceWorker.ready,
            new Promise((resolve, reject) => {
                timer = setTimeout(() => reject(new Error(translate('push.worker_failed'))), 15000);
            })
        ]);
    } finally {
        clearTimeout(timer);
    }
}

export function initPushNotifications(signal = null) {
    const panel = document.getElementById('pushSettings');
    if (!panel || signal?.aborted) return;
    const enableButton = panel.querySelector('[data-push-enable]');
    const disableButton = panel.querySelector('[data-push-disable]');
    const status = panel.querySelector('[data-push-status]');
    const csrfToken = document.querySelector('#accountSettingsForm [name="csrf_token"]')?.value;
    let subscription = null;
    let configuration = { configured: false, subscribed: false };
    let isBusy = false;

    const showStatus = (key, isError = false, message = '') => {
        if (signal.aborted) return;
        status.dataset.i18n = key;
        status.textContent = message || translate(key);
        status.classList.toggle('isError', isError);
    };
    const updateButtons = () => {
        if (signal.aborted) return;
        const permission = getPushBrowserState();
        enableButton.disabled = isBusy || !configuration?.configured || ['unsupported', 'denied'].includes(permission);
        enableButton.hidden = Boolean(configuration?.subscribed);
        disableButton.disabled = isBusy;
        disableButton.hidden = !subscription;
        panel.setAttribute('aria-busy', String(isBusy));
    };
    const renderState = () => {
        const permission = getPushBrowserState();
        let key = 'push.disabled';
        if (permission === 'unsupported') key = 'push.unsupported';
        else if (permission === 'denied') key = 'push.denied';
        else if (!configuration?.configured) key = 'push.not_configured';
        else if (configuration.subscribed && subscription) key = 'push.enabled';
        showStatus(key);
        updateButtons();
    };
    const showError = (error) => {
        if (error.name !== 'AbortError') {
            showStatus(error.code || 'push.request_failed', true, error.message || translate('push.request_failed'));
        }
    };

    enableButton.addEventListener('click', async () => {
        if (isBusy || !configuration?.configured) return;
        // Request immediately inside the click handler, before any network await.
        let permissionRequest;
        try {
            permissionRequest = Notification.permission === 'default'
                ? Notification.requestPermission() : Promise.resolve(Notification.permission);
        } catch (error) {
            showError(error);
            return;
        }
        isBusy = true;
        updateButtons();
        let createdSubscription = null;
        try {
            const permission = await permissionRequest;
            if (permission !== 'granted') {
                renderState();
                return;
            }
            const registration = await getReadyRegistration();
            subscription = await registration.pushManager.getSubscription();
            if (!subscription) {
                subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: decodeApplicationServerKey(configuration.publicKey)
                });
                createdSubscription = subscription;
            }
            // Once consent is given, finish saving even if the user navigates away.
            await sendPushRequest('subscribe', csrfToken, { subscription: JSON.stringify(subscription.toJSON()) });
            configuration.subscribed = true;
            renderState();
        } catch (error) {
            if (createdSubscription) {
                try {
                    await createdSubscription.unsubscribe();
                    subscription = null;
                } catch {
                    showStatus('push.cleanup_failed', true);
                }
            }
            showError(error);
        } finally {
            isBusy = false;
            updateButtons();
        }
    }, { signal });

    disableButton.addEventListener('click', async () => {
        if (isBusy || !subscription) return;
        isBusy = true;
        updateButtons();
        try {
            // Stop server delivery first. Revoking a browser subscription alone is not sufficient.
            await sendPushRequest('unsubscribe', csrfToken, { endpoint: subscription.endpoint });
            configuration.subscribed = false;
            if (!await subscription.unsubscribe()) throw new Error(translate('push.cleanup_failed'));
            subscription = null;
            renderState();
        } catch (error) {
            showError(error);
        } finally {
            isBusy = false;
            updateButtons();
        }
    }, { signal });

    const load = async () => {
        if (getPushBrowserState() === 'unsupported') {
            renderState();
            return;
        }
        isBusy = true;
        showStatus('push.loading');
        updateButtons();
        try {
            subscription = await getExistingSubscription();
            configuration = await sendPushRequest('status', csrfToken, { endpoint: subscription?.endpoint || '' }, signal);
            renderState();
        } catch (error) {
            showError(error);
        } finally {
            isBusy = false;
            updateButtons();
        }
    };
    load();
}
