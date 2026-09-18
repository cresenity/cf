import { addClass, hasClass, removeClass } from '../dom/classes';

// localStorage bisa melempar (Firefox NS_ERROR_ABORT/SecurityError di mode privat atau saat
// halaman ditutup); tema hanya kenyamanan, jadi gagal baca/tulis diperlakukan sebagai tidak ada
const storageGet = (key) => {
    try {
        return window.localStorage.getItem(key);
    } catch (e) {
        return null;
    }
};
const storageSet = (key, value) => {
    try {
        window.localStorage.setItem(key, value);
    } catch (e) {
        // abaikan
    }
};

export const toggleDarkMode = (localStorageKey) => {
    addClass(document.body, 'cres-theme-dark');
    removeClass(document.body, 'cres-theme-light');
    removeClass(document.body, 'cres-detect-theme');
    storageSet(localStorageKey, 'dark-mode');
};

export const toggleLightMode = (localStorageKey) => {
    addClass(document.body, 'cres-theme-light');
    removeClass(document.body, 'cres-theme-dark');
    removeClass(document.body, 'cres-detect-theme');
    storageSet(localStorageKey, 'light-mode');
};

export const enableAutoDetect = (localStorageKey) => {
    window.matchMedia('(prefers-color-scheme: dark)').addListener((event) => {
        return event.matches && toggleLightMode(localStorageKey);
    });
    window.matchMedia('(prefers-color-scheme: light)').addListener((event) => {
        return event.matches && toggleLightMode(localStorageKey);
    });
};

export const toggleAutoDetectMode = (localStorageKey) => {
    const isPreferDark = window.matchMedia(
        '(prefers-color-scheme: dark)'
    ).matches;
    const isPreferLight = window.matchMedia(
        '(prefers-color-scheme: light)'
    ).matches;
    const isNoPreference = window.matchMedia(
        '(prefers-color-scheme: no-preference)'
    ).matches;
    if (isPreferDark) {
        toggleDarkMode(localStorageKey);
    }
    if (isPreferLight) {
        toggleLightMode(localStorageKey);
    }
};
export const toggleMode = (localStorageKey) => {
    if (hasClass(document.body, 'cres-theme-light')) {
        toggleDarkMode(localStorageKey);
    } else {
        toggleLightMode(localStorageKey);
    }
};
export const initThemeMode = (localStorageKey) => {
    if (storageGet(localStorageKey) == 'dark-mode') {
        toggleDarkMode(localStorageKey);
    }
    if (storageGet(localStorageKey) == 'light-mode') {
        toggleLightMode(localStorageKey);
    }
    if (hasClass(document.body, 'cres-detect-theme')) {
        toggleAutoDetectMode(localStorageKey);
    }
};
