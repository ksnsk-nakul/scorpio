import { describe, it, expect, vi, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'

vi.mock('@inertiajs/vue3', () => ({
  // Real Inertia Head teleports its content to document.head — stub it as a
  // no-op render so its slot (the <title>) doesn't leak into wrapper.text().
  Head: { render: () => null },
  // BackLink.vue (used for the footer "back to portfolio" link) renders a
  // real <Link>; stub it as a plain anchor for the DOM assertions here.
  Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}))

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
  const { default: MinimalistProjectDetail } = await import('@/Pages/Public/Templates/Minimalist/ProjectDetail.vue')
  return mount(MinimalistProjectDetail, {
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

describe('Minimalist ProjectDetail template — lightbox state machine', () => {
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
    await wrapper.findAll('[data-testid="lightbox-dot"]')[2].trigger('click')
    expect(currentLightboxIndex(wrapper)).toBe(2)

    // Re-query after the click rather than reusing the pre-click dot
    // wrappers: with `stubs: { teleport: true }` and no intermediary
    // component (e.g. Transition) between Teleport and the v-if'd content,
    // @vue/test-utils' teleport stub doesn't preserve DOM node identity
    // across the keyed v-for re-render the way a real <Teleport> does —
    // a test-harness quirk, not a production bug (verified against real,
    // un-stubbed Teleport).
    const dotsAfter = wrapper.findAll('[data-testid="lightbox-dot"]')
    expect(dotsAfter[2].attributes('data-active')).toBe('true')
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
    const removeSpy = vi.spyOn(window, 'removeEventListener')
    const wrapper = await mountPage()
    await openGallery(wrapper, 0)
    wrapper.unmount()

    // Strong assertion: the component's own handler was actually detached,
    // not just "dispatching an event afterwards doesn't throw" (that would
    // pass even with a leaked listener, since none of the handlers throw).
    expect(removeSpy).toHaveBeenCalledWith('keydown', expect.any(Function))
    removeSpy.mockRestore()
  })

  it('renders no prev/next/dot controls for a single-item gallery', async () => {
    const wrapper = await mountPage([media[0]])
    await openGallery(wrapper, 0)

    expect(wrapper.find('[data-testid="lightbox-prev"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="lightbox-next"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="lightbox-dot"]').exists()).toBe(false)
  })
})
