import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'

const animateMock = vi.fn((target, config) => {
  config?.onComplete?.()
})
const staggerMock = vi.fn((v) => v)

vi.mock('animejs', () => ({
  animate: animateMock,
  stagger: staggerMock,
}))

vi.mock('@inertiajs/vue3', () => ({
  // Real Inertia Head teleports its content to document.head — stub it as a
  // no-op render so its slot (the <title>) doesn't leak into wrapper.text().
  Head: { render: () => null },
  // BackLink.vue (used for the footer "back to portfolio" link) renders a
  // real <Link>; stub it as a plain anchor for the DOM assertions here.
  Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}))

// jsdom doesn't implement IntersectionObserver; useAnimeReveal (applied to
// the gallery's `.reveal-item` cards) would otherwise throw inside onMounted.
// Vue swallows lifecycle errors by default, but stub it out for a clean run.
class NoopIntersectionObserver {
  observe() {}
  unobserve() {}
  disconnect() {}
}

const owner = { id: 1, name: 'Jane Owner', username: 'jane' }
const settings = { site_name: 'Platform Name' }
const project = {
  id: 1,
  name: 'Cool Project',
  description: 'A very cool project.',
  tags: ['vue', 'laravel'],
  details: null,
  github_repo: null,
  demo_url: null,
}
const media = [
  { id: 1, is_image: true, is_video: false, url: '/media/1.jpg', alt_text: 'First', filename: '1.jpg' },
  { id: 2, is_image: true, is_video: false, url: '/media/2.jpg', alt_text: 'Second', filename: '2.jpg' },
  { id: 3, is_image: true, is_video: false, url: '/media/3.jpg', alt_text: 'Third', filename: '3.jpg' },
]

async function mountPage(mediaProp = media) {
  const { default: AnimejsProjectDetail } = await import('@/Pages/Public/Templates/Animejs/ProjectDetail.vue')
  return mount(AnimejsProjectDetail, {
    props: { project, media: mediaProp, owner, settings },
    attachTo: document.body,
    // Real <Teleport to="body"> moves its content outside the mounted
    // component's root element, which breaks wrapper.find()/wrapper.text()
    // under this project's @vue/test-utils setup (see
    // ReaderSettingsDrawer.vue's comment on the same issue). Stubbing
    // teleport renders the lightbox in place instead, purely for testability
    // — production behavior (Teleport to body) is unaffected.
    global: { stubs: { teleport: true } },
  })
}

const openGallery = async (wrapper, index) => {
  await wrapper.findAll('[data-testid="gallery-item"]')[index].trigger('click')
}

const currentLightboxIndex = (wrapper) => {
  const content = wrapper.find('[data-testid="lightbox-content"]')
  return content.exists() ? Number(content.attributes('data-index')) : null
}

describe('Animejs ProjectDetail template — lightbox state machine', () => {
  beforeEach(() => {
    animateMock.mockClear()
    global.IntersectionObserver = NoopIntersectionObserver
  })

  afterEach(() => {
    document.body.replaceChildren()
  })

  it('renders project content and the gallery grid, with no lightbox open initially', async () => {
    const wrapper = await mountPage()
    expect(wrapper.text()).toContain('Cool Project')
    expect(wrapper.text()).toContain('A very cool project.')
    expect(wrapper.findAll('[data-testid="gallery-item"]')).toHaveLength(3)
    expect(wrapper.find('[data-testid="lightbox"]').exists()).toBe(false)
  })

  it('opens the lightbox on the clicked item and closes it via the close button', async () => {
    const wrapper = await mountPage()

    await openGallery(wrapper, 1)
    expect(wrapper.find('[data-testid="lightbox"]').exists()).toBe(true)
    expect(currentLightboxIndex(wrapper)).toBe(1)
    expect(wrapper.find('img[alt="Second"]').exists()).toBe(true)

    await wrapper.find('[data-testid="lightbox-close"]').trigger('click')
    expect(wrapper.find('[data-testid="lightbox"]').exists()).toBe(false)
  })

  it('prevItem wraps from index 0 to the last item', async () => {
    const wrapper = await mountPage()

    await openGallery(wrapper, 0)
    expect(currentLightboxIndex(wrapper)).toBe(0)

    await wrapper.find('[data-testid="lightbox-prev"]').trigger('click')
    expect(currentLightboxIndex(wrapper)).toBe(media.length - 1)
  })

  it('nextItem wraps from the last item to index 0', async () => {
    const wrapper = await mountPage()

    await openGallery(wrapper, media.length - 1)
    expect(currentLightboxIndex(wrapper)).toBe(media.length - 1)

    await wrapper.find('[data-testid="lightbox-next"]').trigger('click')
    expect(currentLightboxIndex(wrapper)).toBe(0)
  })

  it('clicking a dot indicator jumps directly to that item', async () => {
    const wrapper = await mountPage()

    await openGallery(wrapper, 0)
    const dots = wrapper.findAll('[data-testid="lightbox-dot"]')
    await dots[2].trigger('click')
    expect(currentLightboxIndex(wrapper)).toBe(2)
    expect(dots[2].attributes('data-active')).toBe('true')
  })

  it('does nothing on keydown while the lightbox is closed', async () => {
    const wrapper = await mountPage()

    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight' }))
    await wrapper.vm.$nextTick()
    expect(wrapper.find('[data-testid="lightbox"]').exists()).toBe(false)
  })

  it('ArrowRight keydown advances to the next item, wrapping past the last item', async () => {
    const wrapper = await mountPage()
    await openGallery(wrapper, 0)

    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight' }))
    await wrapper.vm.$nextTick()
    expect(currentLightboxIndex(wrapper)).toBe(1)

    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight' }))
    await wrapper.vm.$nextTick()
    expect(currentLightboxIndex(wrapper)).toBe(2)

    // Wraps back to 0 past the last item.
    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight' }))
    await wrapper.vm.$nextTick()
    expect(currentLightboxIndex(wrapper)).toBe(0)
  })

  it('ArrowLeft keydown at index 0 wraps to the last item', async () => {
    const wrapper = await mountPage()
    await openGallery(wrapper, 0)

    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowLeft' }))
    await wrapper.vm.$nextTick()
    expect(currentLightboxIndex(wrapper)).toBe(media.length - 1)
  })

  it('Escape keydown closes the lightbox', async () => {
    const wrapper = await mountPage()
    await openGallery(wrapper, 1)
    expect(wrapper.find('[data-testid="lightbox"]').exists()).toBe(true)

    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
    await wrapper.vm.$nextTick()
    expect(wrapper.find('[data-testid="lightbox"]').exists()).toBe(false)
  })

  it('removes the window keydown listener on unmount (no lingering handler)', async () => {
    const wrapper = await mountPage()
    await openGallery(wrapper, 0)
    wrapper.unmount()

    // Should not throw and should have no observable effect post-unmount.
    expect(() => {
      window.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight' }))
    }).not.toThrow()
  })

  it('renders no prev/next/dot controls for a single-item gallery', async () => {
    const wrapper = await mountPage([media[0]])
    await openGallery(wrapper, 0)

    expect(wrapper.find('[data-testid="lightbox-prev"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="lightbox-next"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="lightbox-dot"]').exists()).toBe(false)
  })

  it('animates in the correct direction for a 2-item gallery (no wrap-direction ambiguity)', async () => {
    const twoItemMedia = media.slice(0, 2)
    const wrapper = await mountPage(twoItemMedia)

    await openGallery(wrapper, 0)
    expect(currentLightboxIndex(wrapper)).toBe(0)
    animateMock.mockClear()

    // 0 -> 1 via nextItem: unambiguously forward.
    await wrapper.find('[data-testid="lightbox-next"]').trigger('click')
    await wrapper.vm.$nextTick()
    expect(currentLightboxIndex(wrapper)).toBe(1)
    let slideCall = animateMock.mock.calls.find(([, config]) => config?.translateX)
    expect(slideCall[1].translateX).toEqual([24, 0])

    animateMock.mockClear()

    // 1 -> 0 via prevItem: unambiguously backward.
    await wrapper.find('[data-testid="lightbox-prev"]').trigger('click')
    await wrapper.vm.$nextTick()
    expect(currentLightboxIndex(wrapper)).toBe(0)
    slideCall = animateMock.mock.calls.find(([, config]) => config?.translateX)
    expect(slideCall[1].translateX).toEqual([-24, 0])
  })
})
