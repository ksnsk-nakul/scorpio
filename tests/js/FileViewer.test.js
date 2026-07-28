import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import FileViewer from '@/Components/FileViewer/FileViewer.vue'
import ImageRenderer from '@/Components/FileViewer/renderers/ImageRenderer.vue'
import ComicRenderer from '@/Components/FileViewer/renderers/ComicRenderer.vue'
import ProcessingState from '@/Components/FileViewer/ProcessingState.vue'
import UnsupportedRenderer from '@/Components/FileViewer/UnsupportedRenderer.vue'

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
})
