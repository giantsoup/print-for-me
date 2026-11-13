export type PlateStatus = 'queued' | 'printing' | 'done' | 'failed';
export type PlateActionKey = 'start' | 'finish' | 'fail' | 'retry' | 'requeue' | 'reopen';

export interface QueuePlate {
    id: number;
    name: string;
    position: number;
    status: PlateStatus;
    estimated_minutes: number | null;
    filament: string | null;
    notes: string | null;
    started_at: string | null;
    finished_at: string | null;
    availableActions: PlateActionKey[];
}
export interface QueueLinkedRequest {
    id: number;
    status: string;
    user_name: string | null;
    is_deleted: boolean;
}
export interface QueueItem {
    id: number;
    title: string;
    position: number | null;
    for_whom: string | null;
    needed_by_date: string | null;
    filament: string | null;
    notes: string | null;
    completed_at: string | null;
    plate_counts: { total: number; queued: number; printing: number; done: number; failed: number };
    remaining_minutes: number;
    unestimated_plates: number;
    linked_request: QueueLinkedRequest | null;
    plates?: QueuePlate[];
}
export interface QueueSlot {
    item: Pick<QueueItem, 'id' | 'title' | 'for_whom' | 'needed_by_date'>;
    plate: QueuePlate;
}
export interface QueueTotals {
    active_items: number;
    remaining_plates: number;
    remaining_minutes: number;
    unestimated_plates: number;
}
export interface QueueAwaitingCompletion {
    count: number;
    requests: { id: number; user_name: string | null; item_id: number; item_title: string }[];
}
export interface LinkableRequest {
    id: number;
    label: string;
    user_name: string | null;
}
export function plateStatusTone(status: PlateStatus): string {
    return {
        queued: 'bg-white/[0.06] text-white/78',
        printing: 'bg-primary/15 text-primary',
        done: 'bg-emerald-400/12 text-emerald-300',
        failed: 'bg-rose-400/12 text-rose-300',
    }[status];
}
export function plateStatusLabel(status: PlateStatus): string {
    return { queued: 'Queued', printing: 'Printing', done: 'Done', failed: 'Failed' }[status];
}
export function formatMinutes(minutes: number): string {
    const total = Math.max(0, Math.floor(minutes));
    if (total < 60) return `${total}m`;
    if (total < 1440) return `${Math.floor(total / 60)}h${total % 60 ? ` ${total % 60}m` : ''}`;
    return `${Math.floor(total / 1440)}d${Math.floor((total % 1440) / 60) ? ` ${Math.floor((total % 1440) / 60)}h` : ''}`;
}
export function splitMinutes(value: number | null): { hours: string; minutes: string } {
    return value === null ? { hours: '', minutes: '' } : { hours: String(Math.floor(value / 60)), minutes: String(value % 60) };
}
export function joinMinutes(hours: string, minutes: string): number | null {
    if (!hours.trim() && !minutes.trim()) return null;
    const total = Number(hours || 0) * 60 + Number(minutes || 0);
    return Number.isFinite(total) ? total : 0;
}
export function remainingLabel(minutes: number, unestimated: number): string {
    return formatMinutes(minutes) + ' left' + (unestimated > 0 ? ' + ' + unestimated + ' unestimated' : '');
}

export function autofillForWhom(current: string, previousName: string | null | undefined, nextName: string | null | undefined): string {
    if (current.trim() === '' || current === (previousName ?? '')) {
        return nextName ?? '';
    }
    return current;
}
