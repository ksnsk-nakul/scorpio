import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'

// This page only renders when there is no admin user with published content
// at all (PublicController::index()'s fallback path) — hard to reach live,
// so we verify it mounts and renders correctly given representative props.

vi.mock('animejs', () => ({
  animate: vi.fn(),
  stagger: vi.fn((v) => v),
}))

const usePageMock = vi.fn(() => ({ props: { auth: { user: null, roles: [] } } }))

vi.mock('@inertiajs/vue3', () => ({
  usePage: () => usePageMock(),
}))

const samplePages = [
  {
    id: 1,
    service_cards: [
      { id: 1, icon: '🚀', title: 'Consulting', description: 'Ship faster.' },
    ],
    blocks: [
      { order: 0, type: 'hero', data: { heading: 'Hi there', subheading: 'I build things.' } },
      { order: 1, type: 'text', data: { content: 'Some text content.' } },
      { order: 2, type: 'text_image', data: { text: 'Text with image', image: '/img.jpg', alt: 'alt text' } },
      { order: 3, type: 'service_cards', data: { heading: 'Services' } },
      {
        order: 4,
        type: 'project_grid',
        data: { heading: 'Projects', projects: [{ id: 1, title: 'Project One', description: 'A project.', url: 'https://example.com' }] },
      },
      { order: 5, type: 'contact_form', data: { heading: 'Get in touch', email: 'me@example.com' } },
    ],
  },
]

describe('Animejs Home template', () => {
  it('renders without error given representative pages/settings props', async () => {
    const { default: AnimejsHome } = await import('@/Pages/Public/Templates/Animejs/Home.vue')
    const wrapper = mount(AnimejsHome, {
      props: { pages: samplePages, settings: { site_name: 'My Site' } },
    })

    expect(wrapper.text()).toContain('Hi there')
    expect(wrapper.text()).toContain('Some text content.')
    expect(wrapper.text()).toContain('Consulting')
    expect(wrapper.text()).toContain('Project One')
    expect(wrapper.text()).toContain('Get in touch')

    // Contact form stays a non-functional visual stub: no submit handler wired.
    const form = wrapper.find('form')
    expect(form.exists()).toBe(true)
    expect(form.attributes('onsubmit')).toBeUndefined()
  })

  it('renders the admin empty state when there are no pages and the user is an admin', async () => {
    usePageMock.mockReturnValueOnce({ props: { auth: { user: { id: 1 }, roles: ['admin'] } } })
    const { default: AnimejsHome } = await import('@/Pages/Public/Templates/Animejs/Home.vue')
    const wrapper = mount(AnimejsHome, { props: { pages: [], settings: {} } })

    expect(wrapper.text()).toContain('No published pages yet.')
    expect(wrapper.find('a[href="/admin/pages"]').exists()).toBe(true)
  })

  it('renders the public "Coming soon" empty state when there are no pages and the user is not an admin', async () => {
    const { default: AnimejsHome } = await import('@/Pages/Public/Templates/Animejs/Home.vue')
    const wrapper = mount(AnimejsHome, { props: { pages: [], settings: {} } })

    expect(wrapper.text()).toContain('Coming soon.')
  })
})
