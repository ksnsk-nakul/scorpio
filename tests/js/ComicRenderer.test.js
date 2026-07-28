import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import ComicRenderer from '@/Components/FileViewer/renderers/ComicRenderer.vue'

describe('ComicRenderer', () => {
  it('shows the first page and advances on next', async () => {
    const media = { comic_page_urls: ['/p1.jpg', '/p2.jpg', '/p3.jpg'] }
    const wrapper = mount(ComicRenderer, { props: { media } })

    expect(wrapper.find('img').attributes('src')).toBe('/p1.jpg')

    await wrapper.find('[data-testid="next-page"]').trigger('click')
    expect(wrapper.find('img').attributes('src')).toBe('/p2.jpg')
  })

  it('disables next on the last page and prev on the first', async () => {
    const media = { comic_page_urls: ['/p1.jpg', '/p2.jpg'] }
    const wrapper = mount(ComicRenderer, { props: { media } })

    expect(wrapper.find('[data-testid="prev-page"]').exists()).toBe(false)

    await wrapper.find('[data-testid="next-page"]').trigger('click')
    expect(wrapper.find('[data-testid="next-page"]').exists()).toBe(false)
  })

  it('shows a message when there are no pages', () => {
    const wrapper = mount(ComicRenderer, { props: { media: { comic_page_urls: [] } } })
    expect(wrapper.text()).toContain('No pages')
  })

  it('shows an always-visible page position indicator', () => {
    const media = { comic_page_urls: ['/p1.jpg', '/p2.jpg', '/p3.jpg'] }
    const wrapper = mount(ComicRenderer, { props: { media } })
    expect(wrapper.find('[data-testid="page-indicator"]').text()).toBe('1 / 3')
  })
})
