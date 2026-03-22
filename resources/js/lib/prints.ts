export type PrintRequestStatus = 'pending' | 'accepted' | 'printing' | 'complete' | string;

export function statusTone(status: PrintRequestStatus) {
    switch (status) {
        case 'pending':
            return 'bg-white/[0.06] text-white/78';
        case 'accepted':
            return 'bg-secondary/15 text-secondary';
        case 'printing':
            return 'bg-primary/15 text-primary';
        case 'complete':
            return 'bg-emerald-400/12 text-emerald-300';
        default:
            return 'bg-white/[0.06] text-white/78';
    }
}

export function statusLabel(status: PrintRequestStatus) {
    return status.charAt(0).toUpperCase() + status.slice(1);
}

export function formatDateTime(value?: string | null) {
    if (!value) {
        return 'Unavailable';
    }

    return new Date(value).toLocaleString([], {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

export function formatDateOnly(value?: string | null) {
    if (!value) {
        return 'Unavailable';
    }

    return new Date(value).toLocaleDateString([], {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

export function formatRelative(value?: string | null) {
    if (!value) {
        return 'Unavailable';
    }

    const target = new Date(value);
    const diffMs = target.getTime() - Date.now();
    const diffMinutes = Math.round(diffMs / 60000);

    if (Math.abs(diffMinutes) < 60) {
        return new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' }).format(diffMinutes, 'minute');
    }

    const diffHours = Math.round(diffMinutes / 60);

    if (Math.abs(diffHours) < 48) {
        return new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' }).format(diffHours, 'hour');
    }

    const diffDays = Math.round(diffHours / 24);

    return new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' }).format(diffDays, 'day');
}

export function formatFileSize(bytes: number) {
    if (bytes <= 0) {
        return '0 MB';
    }

    const mb = bytes / (1024 * 1024);

    if (mb >= 1) {
        return `${mb.toFixed(1)} MB`;
    }

    return `${(bytes / 1024).toFixed(0)} KB`;
}
