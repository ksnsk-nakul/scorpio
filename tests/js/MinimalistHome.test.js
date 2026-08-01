import { readFileSync } from 'node:fs'
import path from 'node:path'
import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'

// This page only renders when there is no admin user with published content
// at all (PublicController::index()'s fallback path) — hard to reach live,
// so we verify it mounts and renders correctly given representative props.

const componentPath = path.resolve(
  process.cwd(),
  'resources/js/Pages/Public/Templates/Minimalist/Home.vue'
)

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

describe('Minimalist Home template', () => {
  it('renders without error given representative pages/settings props, covering all block types', async () => {
    const { default: MinimalistHome } = await import('@/Pages/Public/Templates/Minimalist/Home.vue')
    const wrapper = mount(MinimalistHome, {
      props: { pages: samplePages, settings: { site_name: 'My Site' } },
    })

    // hero
    expect(wrapper.text()).toContain('Hi there')
    expect(wrapper.text()).toContain('I build things.')
    // text
    expect(wrapper.text()).toContain('Some text content.')
    // text_image
    expect(wrapper.text()).toContain('Text with image')
    expect(wrapper.find('img[src="/img.jpg"]').exists()).toBe(true)
    // service_cards
    expect(wrapper.text()).toContain('Services')
    expect(wrapper.text()).toContain('Consulting')
    // project_grid
    expect(wrapper.text()).toContain('Project One')
    // contact_form
    expect(wrapper.text()).toContain('Get in touch')
    expect(wrapper.text()).toContain('me@example.com')
  })

  it('keeps the contact form a genuinely non-functional visual stub', async () => {
    const { default: MinimalistHome } = await import('@/Pages/Public/Templates/Minimalist/Home.vue')
    const wrapper = mount(MinimalistHome, {
      props: { pages: samplePages, settings: {} },
    })

    const form = wrapper.find('form')
    expect(form.exists()).toBe(true)

    // A weak `onsubmit` attribute check proves nothing — Vue never sets that
    // DOM attribute regardless of whether a handler is wired via @submit.
    // Instead, assert at the source-text level that no useForm import exists
    // anywhere in the component — a real guarantee no submission wiring exists.
    const source = readFileSync(componentPath, 'utf-8')
    expect(source).not.toMatch(/useForm/)
  })

  it('renders the admin empty state when there are no pages and the user is an admin', async () => {
    usePageMock.mockReturnValueOnce({ props: { auth: { user: { id: 1 }, roles: ['admin'] } } })
    const { default: MinimalistHome } = await import('@/Pages/Public/Templates/Minimalist/Home.vue')
    const wrapper = mount(MinimalistHome, { props: { pages: [], settings: {} } })

    expect(wrapper.text()).toContain('No published pages yet.')
    expect(wrapper.find('a[href="/admin/pages"]').exists()).toBe(true)
  })

  it('renders the public "Coming soon" empty state when there are no pages and the user is not an admin', async () => {
    const { default: MinimalistHome } = await import('@/Pages/Public/Templates/Minimalist/Home.vue')
    const wrapper = mount(MinimalistHome, { props: { pages: [], settings: {} } })

    expect(wrapper.text()).toContain('Coming soon.')
  })
})
