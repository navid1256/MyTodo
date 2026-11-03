/** Installs an isolated native-dialog test double without replacing production dialog functions. */
export function installMessageDialog(document) {
    const dialog = new EventTarget();
    const fields = Object.fromEntries(['title', 'message', 'cancel', 'confirm'].map(name => [name, {}]));
    const fixture = { dialog, ...fields, confirmations: [], alerts: [], autoRespond: true, confirmed: true };
    let opening = 0;
    dialog.open = false;
    dialog.returnValue = '';
    dialog.classList = { toggle() {} };
    dialog.querySelector = selector => fields[selector.match(/data-dialog-(\w+)/)?.[1]];
    dialog.close = (value = '') => {
        dialog.returnValue = value;
        dialog.open = false;
        dialog.dispatchEvent(new Event('close'));
    };
    dialog.showModal = () => {
        if (dialog.open) throw new Error('Dialog already open.');
        dialog.open = true;
        const sequence = ++opening;
        (fields.cancel.hidden ? fixture.alerts : fixture.confirmations).push(fields.message.textContent);
        if (fixture.autoRespond) {
            queueMicrotask(() => {
                if (dialog.open && opening === sequence) dialog.close(fields.cancel.hidden || fixture.confirmed ? 'confirm' : 'cancel');
            });
        }
    };
    const originalGetElementById = document.getElementById;
    document.getElementById = id => id === 'appMessageDialog' ? dialog : originalGetElementById?.call(document, id);
    return fixture;
}
