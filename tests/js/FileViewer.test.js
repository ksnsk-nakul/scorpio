import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import axios from 'axios'
import FileViewer from '@/Components/FileViewer/FileViewer.vue'
import ImageRenderer from '@/Components/FileViewer/renderers/ImageRenderer.vue'
import ComicRenderer from '@/Components/FileViewer/renderers/ComicRenderer.vue'
import ProcessingState from '@/Components/FileViewer/ProcessingState.vue'
import UnsupportedRenderer from '@/Components/FileViewer/UnsupportedRenderer.vue'

vi.mock('axios')

const readyImage = { id: 1, filename: 'photo.png', url: '/photo.png', status: 'ready' }

describe('FileViewer', () => {
  it('dispatches to ImageRenderer for image files', () => {
    const wrapper = mount(FileViewer, { props: { media: readyImage } })
    expect(wrapper.findComponent(ImageRenderer).exists()).toBe(true)
  })

  it('dispatches to ComicRenderer for cbz files', () => {
    const media = { id: 2, filename: 'issue-1.cbz', status: 'ready', comic_page_urls: ['/p1.jpg'] }
    const wrapper = mount(FileViewer, { props: { media } })
    expect(wrapper.findComponent(ComicRenderer).exists()).toBe(true)
  })

  it('shows ProcessingState while status is pending or processing', () => {
    const wrapper = mount(FileViewer, { props: { media: { ...readyImage, status: 'pending' } } })
    expect(wrapper.findComponent(ProcessingState).exists()).toBe(true)
  })

  describe('status polling', () => {
    beforeEach(() => {
      vi.useFakeTimers()
      axios.get.mockReset()
    })

    afterEach(() => {
      vi.useRealTimers()
    })

    it('polls the status endpoint while processing and switches to the real renderer once ready', async () => {
      axios.get.mockResolvedValueOnce({
        data: {
          id: 1,
          status: 'ready',
          status_reason: null,
          converted_pdf_url: null,
          comic_page_urls: null,
        },
      })

      const wrapper = mount(FileViewer, {
        props: { media: { ...readyImage, status: 'pending' } },
      })

      expect(wrapper.findComponent(ProcessingState).exists()).toBe(true)
      expect(axios.get).not.toHaveBeenCalled()

      await vi.advanceTimersByTimeAsync(2000)
      await flushPromises()

      expect(axios.get).toHaveBeenCalledWith('/admin/media/1/status')
      expect(wrapper.findComponent(ProcessingState).exists()).toBe(false)
      expect(wrapper.findComponent(ImageRenderer).exists()).toBe(true)

      // Should have stopped polling once the status settled to 'ready'.
      await vi.advanceTimersByTimeAsync(4000)
      await flushPromises()
      expect(axios.get).toHaveBeenCalledTimes(1)
    })
  })

  it('shows UnsupportedRenderer for unknown extensions', () => {
    const wrapper = mount(FileViewer, { props: { media: { id: 3, filename: 'archive.7z', status: 'ready' } } })
    expect(wrapper.findComponent(UnsupportedRenderer).exists()).toBe(true)
  })

  it('shows UnsupportedRenderer with the failure reason when status is failed', () => {
    const media = { ...readyImage, status: 'failed', status_reason: 'unrar not found' }
    const wrapper = mount(FileViewer, { props: { media } })
    expect(wrapper.findComponent(UnsupportedRenderer).props('reason')).toBe('unrar not found')
  })

  it('toggles fullscreen state on the expand button', async () => {
    const wrapper = mount(FileViewer, { props: { media: readyImage } })
    expect(wrapper.classes()).not.toContain('fixed')

    await wrapper.find('[data-testid="toggle-fullscreen"]').trigger('click')
    expect(wrapper.classes()).toContain('fixed')
  })

  it('syncs fullscreen state back when the browser exits fullscreen natively (e.g. Escape)', async () => {
    const wrapper = mount(FileViewer, { props: { media: readyImage } })

    await wrapper.find('[data-testid="toggle-fullscreen"]').trigger('click')
    expect(wrapper.classes()).toContain('fixed')

    // Simulate the browser exiting fullscreen on its own (Escape key), which fires
    // a native fullscreenchange event with document.fullscreenElement cleared.
    document.dispatchEvent(new Event('fullscreenchange'))
    await wrapper.vm.$nextTick()

    expect(wrapper.classes()).not.toContain('fixed')
  })
})
