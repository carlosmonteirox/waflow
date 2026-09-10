export function initials(value, fallback = 'WF') {
    return `${value || fallback}`.trim().slice(0, 2).toUpperCase();
}

export function formatNumber(value) {
    return Number(value || 0).toLocaleString('pt-BR');
}

export function paymentMethodLabel(method) {
    return { credit_card: 'Cartão de crédito', debit_card: 'Cartão de débito', pix: 'Pix Automático' }[method] || method;
}

export function roleLabel(role) {
    return {
        owner: 'Proprietário',
        admin: 'Administrador',
        developer: 'Desenvolvedor',
        support: 'Suporte',
        member: 'Membro',
        platform_admin: 'Administrador da plataforma',
        partner_owner: 'Proprietário do parceiro',
        partner_admin: 'Administrador do parceiro',
        partner_sales: 'Comercial',
        partner_support: 'Suporte do parceiro',
    }[role] || role || 'Membro';
}

export function statusLabel(status) {
    return {
        active: 'Ativo',
        accepted: 'Aceito',
        authorized: 'Autorizado',
        canceled: 'Cancelado',
        cancelled: 'Cancelado',
        completed: 'Concluído',
        connected: 'Conectada',
        degraded: 'Com atenção',
        delivered: 'Entregue',
        disconnected: 'Desconectada',
        draft: 'Rascunho',
        error: 'Erro',
        expired: 'Expirado',
        failed: 'Falha',
        inactive: 'Inativo',
        offline: 'Indisponível',
        online: 'Online',
        open: 'Aberto',
        paid: 'Pago',
        paused: 'Pausado',
        pending: 'Pendente',
        processing: 'Processando',
        provider_pending: 'Aguardando provedor',
        provisioning: 'Provisionando',
        qr_pending: 'Aguardando QR Code',
        queued: 'Na fila',
        revoked: 'Revogado',
        sent: 'Enviada',
        trialing: 'Período de teste',
        verified: 'Verificado',
    }[status] || `${status || '-'}`.replaceAll('_', ' ');
}

export function directionLabel(direction) {
    return {
        inbound: 'Entrada',
        outbound: 'Saída',
    }[direction] || direction || '-';
}

export function triggerLabel(trigger) {
    return {
        instance_connected: 'Instância conectada',
        message_failed: 'Mensagem com falha',
        message_received: 'Mensagem recebida',
    }[trigger] || `${trigger || '-'}`.replaceAll('_', ' ');
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
    event.preventDefault();
    window.dispatchEvent(new CustomEvent('waflow:confirm-submit', {
        detail: {
            form: event.currentTarget,
            message,
            submitter: event.submitter,
        },
    }));
}

export function progressWidth(item) {
    return `${item?.percent ?? 0}%`;
}

export async function copyText(value) {
    if (!value) {
        return false;
    }

    if (navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(value);

        return true;
    }

    const temporary = document.createElement('textarea');
    temporary.value = value;
    temporary.setAttribute('readonly', '');
    temporary.style.position = 'fixed';
    temporary.style.opacity = '0';
    document.body.appendChild(temporary);
    temporary.select();
    const copied = document.execCommand('copy');
    temporary.remove();

    return copied;
}
