/// <reference types="node" />
import { defineConfig } from '@playwright/test'
import { execSync } from 'child_process'

const baseURL = process.env.TYPO3_BASE_URL ?? 'https://typo314.ddev.site'
const cli = process.env.TYPO3_CLI ?? 'ddev typo3'

let raw: string
try {
  raw = execSync(`${cli} gedankenfolger-faq:test-pages`).toString().trim()
} catch (e) {
  console.warn('[Playwright] Could not run gedankenfolger-faq:test-pages. Is TYPO3 running?', e)
  raw = '{}'
}

try {
  const pages = JSON.parse(raw)
  if (pages?.variants) {
    process.env.FAQ_DEFAULT = String(pages.variants.default)
    process.env.FAQ_OPEN_FIRST = String(pages.variants['open-first'])
    process.env.FAQ_OPEN_SINGLE_ONLY = String(pages.variants['open-single-only'])
    process.env.FAQ_GROUPED = String(pages.variants.grouped)
    process.env.FAQ_GROUPED_TITLES = String(pages.variants['grouped-titles'])
  } else {
    console.warn('[Playwright] FAQ test pages not found. Run: ddev typo3 gedankenfolger-faq:setup-test-pages')
  }
} catch (e) {
  console.warn('[Playwright] Could not parse test pages JSON:', raw, e)
}

export default defineConfig({
  testDir: './tests/playwright',
  use: {
    baseURL,
    ignoreHTTPSErrors: true,
  },
  projects: [
    { name: 'chromium', use: { browserName: 'chromium' } },
  ],
})
