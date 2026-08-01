import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'

const usePageMock = vi.fn(() => ({ props: { auth: { user: null, roles: [] } } }))

vi.mock('@inertiajs/vue3', () => ({
  // Real Inertia Head teleports its content to document.head — stub it as a
  // no-op render so its slot (the <title>) doesn't leak into wrapper.text().
  Head: { render: () => null },
  usePage: () => usePageMock(),
}))

const owner = { id: 1, name: 'Jane Owner', username: 'jane' }
const members = [
  { id: 1, name: 'Alex Member', username: 'alex', role: 'editor' },
  { id: 2, name: 'Sam Member', username: 'sam', role: 'viewer' },
]
const achievements = [
  { id: 1, title: 'Shipped 100 books', icon: '🏆', achieved_at: '2026-01-01', user: { name: 'Alex Member' } },
]
const settings = { site_name: 'Platform Name' }

describe('Minimalist OrgPage template', () => {
  it('white_label = false: shows the full MinimalistNav (Library link, auth CTA) and MinimalistFooter (legal links, "Powered by" absent since MinimalistFooter has its own branding)', async () => {
    const { default: MinimalistOrgPage } = await import('@/Pages/Public/Templates/Minimalist/OrgPage.vue')
    const organization = { id: 1, name: 'Acme Org', description: 'An org.', white_label: false, custom_brand_name: null }

    const wrapper = mount(MinimalistOrgPage, {
      props: { organization, owner, members, achievements, settings },
    })

    // Full shared nav: Library link + auth CTAs, present only via MinimalistNav.
    expect(wrapper.find('a[href="/library"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Login')
    expect(wrapper.text()).toContain('Sign up')

    // Full shared footer: legal links, present only via MinimalistFooter.
    expect(wrapper.find('a[href="/terms"]').exists()).toBe(true)
    expect(wrapper.find('a[href="/privacy"]').exists()).toBe(true)
    expect(wrapper.find('a[href="/refund"]').exists()).toBe(true)

    // Org content still renders.
    expect(wrapper.text()).toContain('Acme Org')
    expect(wrapper.text()).toContain('Alex Member')
    expect(wrapper.text()).toContain('Shipped 100 books')
  })

  it('white_label = true: shows NO Library link, NO auth CTA, NO legal footer links, and no "Powered by" footer at all — just a minimal brand-only nav', async () => {
    const { default: MinimalistOrgPage } = await import('@/Pages/Public/Templates/Minimalist/OrgPage.vue')
    const organization = { id: 2, name: 'WhiteLabel Org', description: null, white_label: true, custom_brand_name: 'Acme Books' }

    const wrapper = mount(MinimalistOrgPage, {
      props: { organization, owner, members, achievements, settings },
    })

    // No shared platform nav chrome.
    expect(wrapper.find('a[href="/library"]').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('Login')
    expect(wrapper.text()).not.toContain('Sign up')
    expect(wrapper.find('button[aria-label="Open menu"]').exists()).toBe(false)

    // No footer element of any kind (no legal links, no "Powered by" strip).
    expect(wrapper.find('footer').exists()).toBe(false)
    expect(wrapper.find('a[href="/terms"]').exists()).toBe(false)
    expect(wrapper.find('a[href="/privacy"]').exists()).toBe(false)
    expect(wrapper.find('a[href="/refund"]').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('Powered by')

    // Minimal brand-only nav shows the org's custom brand name, not the platform's site_name.
    expect(wrapper.text()).toContain('Acme Books')
    expect(wrapper.text()).not.toContain('Platform Name')

    // Org content still renders.
    expect(wrapper.text()).toContain('WhiteLabel Org')
    expect(wrapper.text()).toContain('Sam Member')
  })

  it('white_label = true without a custom_brand_name falls back to settings.site_name for the minimal nav', async () => {
    const { default: MinimalistOrgPage } = await import('@/Pages/Public/Templates/Minimalist/OrgPage.vue')
    const organization = { id: 3, name: 'WhiteLabel Org 2', description: null, white_label: true, custom_brand_name: null }

    const wrapper = mount(MinimalistOrgPage, {
      props: { organization, owner, members, achievements, settings },
    })

    expect(wrapper.text()).toContain('Platform Name')
    expect(wrapper.find('footer').exists()).toBe(false)
  })

  it('renders an empty state when there are no members', async () => {
    const { default: MinimalistOrgPage } = await import('@/Pages/Public/Templates/Minimalist/OrgPage.vue')
    const organization = { id: 4, name: 'Empty Org', description: null, white_label: false, custom_brand_name: null }

    const wrapper = mount(MinimalistOrgPage, {
      props: { organization, owner, members: [], achievements: [], settings },
    })

    expect(wrapper.text()).toContain('No members listed.')
  })
})
