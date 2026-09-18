import { defineConfig, devices } from '@playwright/test';

// Jalan lawan demo site framework sungguhan (dev.cresenity.com, tanpa login) untuk
// memverifikasi cres.js yang sedang tersaji di sana - selalu jalankan di dev server
// (`npx playwright test` dari application/cresenity), bukan mesin lokal.
export default defineConfig({
    testDir: './e2e',
    timeout: 60_000,
    expect: { timeout: 10_000 },
    fullyParallel: true,
    retries: process.env.CI ? 1 : 0,
    reporter: [['list'], ['html', { open: 'never', outputFolder: 'e2e-report' }]],
    use: {
        baseURL: process.env.E2E_BASE_URL ?? 'https://dev.cresenity.com',
        headless: true,
        screenshot: 'only-on-failure',
        trace: 'retain-on-failure',
    },
    projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
});
