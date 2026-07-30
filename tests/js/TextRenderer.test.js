import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import TextRenderer from '@/Components/FileViewer/renderers/TextRenderer.vue'

global.fetch = vi.fn()

function mockFetchText(text) {
  fetch.mockResolvedValueOnce({ text: () => Promise.resolve(text) })
}

beforeEach(() => {
  localStorage.clear()
  vi.resetModules()
})

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

  it('reflects a reader theme set via the shared useReaderTheme composable', async () => {
    // Regression test: TextRenderer must import useReaderTheme from the exact
    // same module specifier as the rest of the app. A casing mismatch (e.g.
    // '@/Composables/useReaderTheme' vs '@/composables/useReaderTheme') makes
    // the bundler treat them as separate modules with independent singleton
    // state, silently breaking cross-component theme sync.
    const { useReaderTheme } = await import('@/composables/useReaderTheme')
    const { default: FreshTextRenderer } = await import(
      '@/Components/FileViewer/renderers/TextRenderer.vue'
    )
    const { setTheme } = useReaderTheme()

    setTheme('sepia-dark')

    mockFetchText('hello world')
    const wrapper = mount(FreshTextRenderer, {
      props: { media: { filename: 'notes.txt', url: '/notes.txt' } },
    })
    await flushPromises()

    expect(wrapper.classes()).toContain('bg-[#2b2116]')
    expect(wrapper.classes()).not.toContain('bg-white')

    // Changing the theme after mount should also propagate reactively.
    setTheme('white')
    await flushPromises()
    expect(wrapper.classes()).toContain('bg-white')
    expect(wrapper.classes()).not.toContain('bg-[#2b2116]')
  })
})
