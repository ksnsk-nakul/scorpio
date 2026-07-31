import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'

beforeEach(() => {
  localStorage.clear()
  vi.resetModules()
})

describe('ReaderSettingsDrawer', () => {
  it('renders nothing when closed', async () => {
    const { default: ReaderSettingsDrawer } = await import('@/Components/ReaderSettingsDrawer.vue')
    const wrapper = mount(ReaderSettingsDrawer, { props: { open: false } })
    expect(wrapper.text()).not.toContain('Reading Settings')
  })

  it('renders theme, font, and reading-mode sections when open', async () => {
    const { default: ReaderSettingsDrawer } = await import('@/Components/ReaderSettingsDrawer.vue')
    const wrapper = mount(ReaderSettingsDrawer, { props: { open: true } })
    expect(wrapper.text()).toContain('Reading Settings')
    expect(wrapper.text()).toContain('Sepia')
    expect(wrapper.text()).toContain('A+')
    expect(wrapper.text()).toContain('H-Page')
  })

  it('only shows autoscroll speed controls when mode is autoscroll', async () => {
    const { useReaderMode } = await import('@/composables/useReaderMode')
    const { default: ReaderSettingsDrawer } = await import('@/Components/ReaderSettingsDrawer.vue')
    const { setMode } = useReaderMode()

    const wrapper = mount(ReaderSettingsDrawer, { props: { open: true } })
    expect(wrapper.text()).not.toContain('Autoscroll Speed')

    setMode('autoscroll')
    await wrapper.vm.$nextTick()
    expect(wrapper.text()).toContain('Autoscroll Speed')
    expect(wrapper.text()).toContain('Slow')
    expect(wrapper.text()).toContain('Play')
  })

  it('emits close when the backdrop or close button is clicked', async () => {
    const { default: ReaderSettingsDrawer } = await import('@/Components/ReaderSettingsDrawer.vue')
    const wrapper = mount(ReaderSettingsDrawer, { props: { open: true } })
    await wrapper.find('[data-testid="drawer-close"]').trigger('click')
    expect(wrapper.emitted('close')).toHaveLength(1)
  })

  it('setMode buttons update the shared useReaderMode state', async () => {
    const { useReaderMode } = await import('@/composables/useReaderMode')
    const { default: ReaderSettingsDrawer } = await import('@/Components/ReaderSettingsDrawer.vue')
    const { mode } = useReaderMode()

    const wrapper = mount(ReaderSettingsDrawer, { props: { open: true } })
    await wrapper.find('[data-testid="mode-h-page"]').trigger('click')
    expect(mode.value).toBe('h-page')
  })
})
