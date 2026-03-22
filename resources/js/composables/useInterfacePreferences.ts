import { onMounted, ref } from 'vue';

export type MotionPreference = 'standard' | 'reduced';

const STORAGE_KEY = 'ui.motion';
const COOKIE_NAME = 'ui_motion';
const MOTION_CLASS = 'motion-reduced-ui';

const motionPreference = ref<MotionPreference>('standard');

function getStoredMotionPreference(): MotionPreference {
    if (typeof window === 'undefined') {
        return 'standard';
    }

    const stored = window.localStorage.getItem(STORAGE_KEY);

    return stored === 'reduced' ? 'reduced' : 'standard';
}

function writeCookie(value: MotionPreference) {
    if (typeof document === 'undefined') {
        return;
    }

    document.cookie = `${COOKIE_NAME}=${value};path=/;max-age=${60 * 60 * 24 * 365};SameSite=Lax`;
}

export function applyMotionPreference(value: MotionPreference) {
    if (typeof document === 'undefined') {
        return;
    }

    document.documentElement.classList.toggle(MOTION_CLASS, value === 'reduced');
}

export function initializeInterfacePreferences() {
    if (typeof window === 'undefined') {
        return;
    }

    const initial = getStoredMotionPreference();
    motionPreference.value = initial;
    applyMotionPreference(initial);
}

export function useInterfacePreferences() {
    onMounted(() => {
        motionPreference.value = getStoredMotionPreference();
        applyMotionPreference(motionPreference.value);
    });

    function updateMotionPreference(value: MotionPreference) {
        motionPreference.value = value;

        if (typeof window !== 'undefined') {
            window.localStorage.setItem(STORAGE_KEY, value);
        }

        writeCookie(value);
        applyMotionPreference(value);
    }

    return {
        motionPreference,
        updateMotionPreference,
    };
}
