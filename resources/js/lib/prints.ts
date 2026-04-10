export type PrintRequestStatus = 'pending' | 'accepted' | 'printing' | 'complete' | string;
export type PrintRequestActionKey = 'accept' | 'printing' | 'complete' | 'revert';

const ISO_DATE_ONLY_PATTERN = /^\d{4}-\d{2}-\d{2}$/;
const MASKED_DATE_PATTERN = /^\d{2}\/\d{2}\/\d{4}$/;

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
    const resolvedDate = resolveDateValue(value);

    if (!resolvedDate) {
        return 'Unavailable';
    }

    return resolvedDate.toLocaleDateString([], {
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

export function stripNonDigits(value: string) {
    return value.replace(/\D/g, '').slice(0, 8);
}

export function maskDateInput(value: string) {
    const digits = stripNonDigits(value);

    if (digits.length <= 2) {
        return digits;
    }

    if (digits.length <= 4) {
        return `${digits.slice(0, 2)}/${digits.slice(2)}`;
    }

    return `${digits.slice(0, 2)}/${digits.slice(2, 4)}/${digits.slice(4)}`;
}

export function maskedDateToIso(value: string) {
    if (!MASKED_DATE_PATTERN.test(value)) {
        return null;
    }

    const [month, day, year] = value.split('/').map((part) => Number(part));

    if (!isValidCalendarDate(year, month, day)) {
        return null;
    }

    return `${year.toString().padStart(4, '0')}-${month.toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`;
}

export function isoDateToMaskedDisplay(value?: string | null) {
    if (!value || !ISO_DATE_ONLY_PATTERN.test(value)) {
        return '';
    }

    const [year, month, day] = value.split('-');

    return `${month}/${day}/${year}`;
}

export function parseDateOnly(value?: string | null) {
    if (!value || !ISO_DATE_ONLY_PATTERN.test(value)) {
        return null;
    }

    const [year, month, day] = value.split('-').map((part) => Number(part));

    if (!isValidCalendarDate(year, month, day)) {
        return null;
    }

    return new Date(year, month - 1, day);
}

export function differenceInCalendarDaysFromToday(value?: string | null, referenceDate = new Date()) {
    const targetDate = parseDateOnly(value);

    if (!targetDate) {
        return null;
    }

    const referenceDay = new Date(referenceDate.getFullYear(), referenceDate.getMonth(), referenceDate.getDate());

    return Math.round((targetDate.getTime() - referenceDay.getTime()) / 86_400_000);
}

function resolveDateValue(value?: string | null) {
    if (!value) {
        return null;
    }

    if (ISO_DATE_ONLY_PATTERN.test(value)) {
        return parseDateOnly(value);
    }

    const parsedDate = new Date(value);

    return Number.isNaN(parsedDate.getTime()) ? null : parsedDate;
}

function isValidCalendarDate(year: number, month: number, day: number) {
    const candidate = new Date(year, month - 1, day);

    return (
        candidate.getFullYear() === year
        && candidate.getMonth() === month - 1
        && candidate.getDate() === day
    );
}
