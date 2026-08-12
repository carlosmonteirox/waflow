export function initials(value, fallback = 'WF') {
    return `${value || fallback}`.trim().slice(0, 2).toUpperCase();
}

export function formatNumber(value) {
    return Number(value || 0).toLocaleString('pt-BR');
}

export function paymentMethodLabel(method) {
    return { credit_card: 'Cartão de crédito', debit_card: 'Cartão de débito', pix: 'Pix Automático' }[method] || method;
}

export function badgeVariant(status, fallback = 'secondary') {
    const variants = {
        active: 'success',
        connected: 'success',
        delivered: 'success',
        sent: 'success',
        trialing: 'success',
        online: 'success',
        qr_pending: 'warning',
        pending: 'warning',
        provider_pending: 'warning',
        queued: 'warning',
        draft: 'secondary',
        paused: 'secondary',
        revoked: 'secondary',
        accepted: 'success',
        completed: 'success',
        expired: 'destructive',
        inactive: 'secondary',
        disconnected: 'secondary',
        failed: 'destructive',
        error: 'destructive',
    };

    return variants[status] || fallback;
}

export function confirmSubmit(event, message) {
    if (!window.confirm(message)) {
        event.preventDefault();
    }
}

export function progressWidth(item) {
    return `${item?.percent ?? 0}%`;
}

export async function copyText(value) {
    if (value) {
        await navigator.clipboard.writeText(value);
    }
}
