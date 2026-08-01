import { readFileSync } from 'node:fs'
import path from 'node:path'
import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'

const componentsDir = path.resolve(process.cwd(), 'resources/js/Components/Templates/Minimalist')

const usePageMock = vi.fn(() => ({ props: { auth: { user: null, roles: [] } } }))

vi.mock('@inertiajs/vue3', () => ({
  usePage: () => usePageMock(),
}))

describe('MinimalistNav / MinimalistFooter source', () => {
  it('does not import animejs or useAnimeReveal in either file', () => {
    const navSource = readFileSync(path.join(componentsDir, 'MinimalistNav.vue'), 'utf-8')
    const footerSource = readFileSync(path.join(componentsDir, 'MinimalistFooter.vue'), 'utf-8')
    expect(navSource).not.toMatch(/animejs/i)
    expect(navSource).not.toMatch(/useAnimeReveal/)
    expect(footerSource).not.toMatch(/animejs/i)
    expect(footerSource).not.toMatch(/useAnimeReveal/)
  })
})

describe('MinimalistNav', () => {
  it('renders with minimal props and no sections (plain nav, no anchor links)', async () => {
    const { default: MinimalistNav } = await import('@/Components/Templates/Minimalist/MinimalistNav.vue')
    const wrapper = mount(MinimalistNav, { props: { settings: { site_name: 'My Site' } } })
    expect(wrapper.text()).toContain('My Site')
    expect(wrapper.text()).toContain('Library')
    expect(wrapper.find('a[href="/library"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Login')
    expect(wrapper.text()).toContain('Sign up')
  })

  it('renders section anchor links when sections are provided', async () => {
    const { default: MinimalistNav } = await import('@/Components/Templates/Minimalist/MinimalistNav.vue')
    const wrapper = mount(MinimalistNav, {
      props: {
        settings: { site_name: 'My Site' },
        sections: [{ id: 'about', label: 'About' }, { id: 'contact', label: 'Contact' }],
      },
    })
    expect(wrapper.find('a[href="#about"]').exists()).toBe(true)
    expect(wrapper.find('a[href="#contact"]').exists()).toBe(true)
  })

  it('renders the Dashboard link when the logged-in user has the admin role', async () => {
    usePageMock.mockReturnValueOnce({
      props: { auth: { user: { id: 1, name: 'Admin' }, roles: ['admin'] } },
    })
    const { default: MinimalistNav } = await import('@/Components/Templates/Minimalist/MinimalistNav.vue')
    const wrapper = mount(MinimalistNav, { props: { settings: {} } })
    expect(wrapper.text()).toContain('Dashboard →')
    expect(wrapper.find('a[href="/admin/dashboard"]').exists()).toBe(true)
  })

  it('renders neither auth CTA for a logged-in non-admin user', async () => {
    usePageMock.mockReturnValueOnce({
      props: { auth: { user: { id: 2, name: 'Reader' }, roles: [] } },
    })
    const { default: MinimalistNav } = await import('@/Components/Templates/Minimalist/MinimalistNav.vue')
    const wrapper = mount(MinimalistNav, { props: { settings: {} } })
    expect(wrapper.find('a[href="/admin/dashboard"]').exists()).toBe(false)
    expect(wrapper.find('a[href="/login"]').exists()).toBe(false)
    expect(wrapper.find('a[href="/register"]').exists()).toBe(false)
  })

  it('toggles aria-expanded when the hamburger is clicked, and closes on Escape', async () => {
    const { default: MinimalistNav } = await import('@/Components/Templates/Minimalist/MinimalistNav.vue')
    const wrapper = mount(MinimalistNav, { props: { settings: {} }, attachTo: document.body })

    const button = wrapper.find('button')
    expect(button.attributes('aria-expanded')).toBe('false')
    expect(button.attributes('aria-label')).toBe('Open menu')
    expect(wrapper.find('#mobile-nav-panel').attributes('inert')).toBe('true')

    await button.trigger('click')
    expect(wrapper.find('button').attributes('aria-expanded')).toBe('true')
    expect(wrapper.find('button').attributes('aria-label')).toBe('Close menu')
    expect(wrapper.find('#mobile-nav-panel').attributes('inert')).toBe('false')

    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
    await wrapper.vm.$nextTick()

    expect(wrapper.find('button').attributes('aria-expanded')).toBe('false')
    expect(wrapper.find('#mobile-nav-panel').attributes('inert')).toBe('true')

    wrapper.unmount()
  })
})

describe('MinimalistFooter', () => {
  it('renders site name and legal links, hides donate link by default', async () => {
    const { default: MinimalistFooter } = await import('@/Components/Templates/Minimalist/MinimalistFooter.vue')
    const wrapper = mount(MinimalistFooter, { props: { settings: { site_name: 'My Site' } } })
    expect(wrapper.text()).toContain('My Site')
    expect(wrapper.find('a[href="/terms"]').exists()).toBe(true)
    expect(wrapper.find('a[href="/privacy"]').exists()).toBe(true)
    expect(wrapper.find('a[href="/refund"]').exists()).toBe(true)
    expect(wrapper.find('a[href="/donate"]').exists()).toBe(false)
  })

  it('shows the donate link when settings.show_donate_banner is true', async () => {
    const { default: MinimalistFooter } = await import('@/Components/Templates/Minimalist/MinimalistFooter.vue')
    const wrapper = mount(MinimalistFooter, {
      props: { settings: { site_name: 'My Site', show_donate_banner: true } },
    })
    expect(wrapper.find('a[href="/donate"]').exists()).toBe(true)
  })
})
