import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'

const animateMock = vi.fn((target, config) => {
  config?.onComplete?.()
})

vi.mock('animejs', () => ({
  animate: animateMock,
}))

const usePageMock = vi.fn(() => ({ props: { auth: { user: null, roles: [] } } }))

vi.mock('@inertiajs/vue3', () => ({
  usePage: () => usePageMock(),
}))

describe('AnimeJsNav', () => {
  it('renders with minimal props and no sections (plain nav, no anchor links)', async () => {
    const { default: AnimeJsNav } = await import('@/Components/Templates/Animejs/AnimeJsNav.vue')
    const wrapper = mount(AnimeJsNav, { props: { settings: { site_name: 'My Site' } } })
    expect(wrapper.text()).toContain('My Site')
    expect(wrapper.text()).toContain('Library')
    expect(wrapper.find('a[href="/library"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Login')
    expect(wrapper.text()).toContain('Sign up')
  })

  it('renders section anchor links when sections are provided', async () => {
    const { default: AnimeJsNav } = await import('@/Components/Templates/Animejs/AnimeJsNav.vue')
    const wrapper = mount(AnimeJsNav, {
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
    const { default: AnimeJsNav } = await import('@/Components/Templates/Animejs/AnimeJsNav.vue')
    const wrapper = mount(AnimeJsNav, { props: { settings: {} } })
    expect(wrapper.text()).toContain('Dashboard →')
    expect(wrapper.find('a[href="/admin/dashboard"]').exists()).toBe(true)
  })

  it('animates the mobile panel open when the hamburger is clicked', async () => {
    const { default: AnimeJsNav } = await import('@/Components/Templates/Animejs/AnimeJsNav.vue')
    const wrapper = mount(AnimeJsNav, { props: { settings: {} }, attachTo: document.body })
    animateMock.mockClear()
    await wrapper.find('button[aria-label="Open menu"]').trigger('click')
    expect(animateMock).toHaveBeenCalled()
    const [, config] = animateMock.mock.calls[0]
    expect(config.opacity).toEqual([0, 1])
    wrapper.unmount()
  })

  it('pressing Escape closes the mobile menu', async () => {
    const { default: AnimeJsNav } = await import('@/Components/Templates/Animejs/AnimeJsNav.vue')
    const wrapper = mount(AnimeJsNav, { props: { settings: {} }, attachTo: document.body })

    await wrapper.find('button[aria-label="Open menu"]').trigger('click')
    expect(wrapper.find('button').attributes('aria-expanded')).toBe('true')

    animateMock.mockClear()
    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
    await wrapper.vm.$nextTick()

    expect(animateMock).toHaveBeenCalled()
    const [, config] = animateMock.mock.calls[0]
    expect(config.opacity).toEqual([1, 0])
    expect(wrapper.find('button').attributes('aria-expanded')).toBe('false')

    wrapper.unmount()
  })
})

describe('AnimeJsFooter', () => {
  it('renders site name and legal links, hides donate link by default', async () => {
    const { default: AnimeJsFooter } = await import('@/Components/Templates/Animejs/AnimeJsFooter.vue')
    const wrapper = mount(AnimeJsFooter, { props: { settings: { site_name: 'My Site' } } })
    expect(wrapper.text()).toContain('My Site')
    expect(wrapper.find('a[href="/terms"]').exists()).toBe(true)
    expect(wrapper.find('a[href="/privacy"]').exists()).toBe(true)
    expect(wrapper.find('a[href="/refund"]').exists()).toBe(true)
    expect(wrapper.find('a[href="/donate"]').exists()).toBe(false)
  })

  it('shows the donate link when settings.show_donate_banner is true', async () => {
    const { default: AnimeJsFooter } = await import('@/Components/Templates/Animejs/AnimeJsFooter.vue')
    const wrapper = mount(AnimeJsFooter, {
      props: { settings: { site_name: 'My Site', show_donate_banner: true } },
    })
    expect(wrapper.find('a[href="/donate"]').exists()).toBe(true)
  })
})
