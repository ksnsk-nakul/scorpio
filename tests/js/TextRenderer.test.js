import { describe, it, expect, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import TextRenderer from '@/Components/FileViewer/renderers/TextRenderer.vue'

global.fetch = vi.fn()

function mockFetchText(text) {
  fetch.mockResolvedValueOnce({ text: () => Promise.resolve(text) })
}

describe('TextRenderer', () => {
  it('renders plain text in a <pre> block', async () => {
    mockFetchText('hello world')
    const wrapper = mount(TextRenderer, {
      props: { media: { filename: 'notes.txt', url: '/notes.txt' } },
    })
    await flushPromises()
    expect(wrapper.find('pre').text()).toBe('hello world')
  })

  it('renders markdown as HTML', async () => {
    mockFetchText('# Title')
    const wrapper = mount(TextRenderer, {
      props: { media: { filename: 'notes.md', url: '/notes.md' } },
    })
    await flushPromises()
    expect(wrapper.find('h1').text()).toBe('Title')
  })

  it('renders csv as a table', async () => {
    mockFetchText('name,age\nAda,30\nGrace,45')
    const wrapper = mount(TextRenderer, {
      props: { media: { filename: 'data.csv', url: '/data.csv' } },
    })
    await flushPromises()
    expect(wrapper.findAll('th').map((th) => th.text())).toEqual(['name', 'age'])
    expect(wrapper.findAll('tbody tr')).toHaveLength(2)
  })
})
