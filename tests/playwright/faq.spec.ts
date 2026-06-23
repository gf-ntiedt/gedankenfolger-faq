import { test, expect } from '@playwright/test'

const CTYPE = 'gedankenfolger_faq'

function url(uid: string | undefined): string {
  if (!uid) throw new Error('FAQ test page UID not set — run: ddev typo3 gedankenfolger-faq:setup-test-pages')
  return `/?id=${uid}`
}

test.describe('FAQ content element', () => {
  test('default — renders all items, none open', async ({ page }) => {
    await page.goto(url(process.env.FAQ_DEFAULT))

    const items = page.locator(`details.${CTYPE}__accordionitem`)
    await expect(items).toHaveCount(5)
    await expect(page.locator(`details.${CTYPE}__accordionitem[open]`)).toHaveCount(0)

    const wrapper = page.locator(`.${CTYPE}`)
    await expect(wrapper).toHaveAttribute('data-open-single-only', '0')
  })

  test('open-first — first item has open attribute', async ({ page }) => {
    await page.goto(url(process.env.FAQ_OPEN_FIRST))

    const items = page.locator(`details.${CTYPE}__accordionitem`)
    await expect(items.first()).toHaveAttribute('open', '')
    await expect(items.nth(1)).not.toHaveAttribute('open', '')
  })

  test('open-single-only — wrapper signals single-open mode', async ({ page }) => {
    await page.goto(url(process.env.FAQ_OPEN_SINGLE_ONLY))

    const wrapper = page.locator(`.${CTYPE}`)
    await expect(wrapper).toHaveAttribute('data-open-single-only', '1')

    const items = page.locator(`details.${CTYPE}__accordionitem`)
    await expect(items).toHaveCount(5)
  })

  test('grouped — items rendered, no category headings', async ({ page }) => {
    await page.goto(url(process.env.FAQ_GROUPED))

    const items = page.locator(`details.${CTYPE}__accordionitem`)
    await expect(items).toHaveCount(5)

    const headings = page.locator(`h3.${CTYPE}__list--category`)
    await expect(headings).toHaveCount(0)
  })

  test('grouped-titles — category headings visible', async ({ page }) => {
    await page.goto(url(process.env.FAQ_GROUPED_TITLES))

    const headings = page.locator(`h3.${CTYPE}__list--category`)
    await expect(headings.first()).toBeVisible()
    await expect(headings).toHaveCount(3) // CMS, Development, uncategorized

    const items = page.locator(`details.${CTYPE}__accordionitem`)
    await expect(items).toHaveCount(5)
  })
})
