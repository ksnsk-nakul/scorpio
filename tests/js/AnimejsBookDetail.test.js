import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'

vi.mock('animejs', () => ({
  animate: vi.fn(),
  stagger: vi.fn((v) => v),
}))

vi.mock('@inertiajs/vue3', () => ({
  // Real Inertia Head teleports its content to document.head — stub it as a
  // no-op render so its slot (the <title>) doesn't leak into wrapper.text().
  Head: { render: () => null },
  Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}))

// jsdom doesn't implement IntersectionObserver; useAnimeReveal (applied to
// the metadata block and chapter rows) would otherwise throw inside onMounted.
class NoopIntersectionObserver {
  observe() {}
  unobserve() {}
  disconnect() {}
}
global.IntersectionObserver = NoopIntersectionObserver

const fullBook = {
  title: 'The Great Novel',
  slug: 'the-great-novel',
  description: 'A story about things.',
  cover_url: '/covers/the-great-novel.jpg',
  language: 'English',
  publisher: 'Acme Press',
  published_date: '2020-01-01',
  author: { name: 'Jane Author', slug: 'jane-author' },
  chapters: [
    { title: 'The Beginning', sort_order: 0 },
    { title: null, sort_order: 1 },
  ],
}

const minimalBook = {
  title: 'Bare Book',
  slug: 'bare-book',
  description: null,
  cover_url: null,
  language: null,
  publisher: null,
  published_date: null,
  author: null,
  chapters: [],
}

async function mountPage(book) {
  const { default: AnimejsBookDetail } = await import('@/Pages/Public/Templates/Animejs/BookDetail.vue')
  return mount(AnimejsBookDetail, { props: { book } })
}

describe('Animejs BookDetail template', () => {
  it('renders title, description and publisher/published_date/language metadata when present', async () => {
    const wrapper = await mountPage(fullBook)

    expect(wrapper.text()).toContain('The Great Novel')
    expect(wrapper.text()).toContain('A story about things.')
    expect(wrapper.text()).toContain('Publisher:')
    expect(wrapper.text()).toContain('Acme Press')
    expect(wrapper.text()).toContain('Published:')
    expect(wrapper.text()).toContain('2020-01-01')
    expect(wrapper.text()).toContain('Language:')
    expect(wrapper.text()).toContain('English')
  })

  it('omits description and publisher/published_date/language rows when absent', async () => {
    const wrapper = await mountPage(minimalBook)

    expect(wrapper.text()).not.toContain('Publisher:')
    expect(wrapper.text()).not.toContain('Published:')
    expect(wrapper.text()).not.toContain('Language:')
  })

  it('renders the author link with the correct href when an author is present', async () => {
    const wrapper = await mountPage(fullBook)

    const authorLink = wrapper.find('a[href="/library/authors/jane-author"]')
    expect(authorLink.exists()).toBe(true)
    expect(authorLink.text()).toContain('Jane Author')
  })

  it('renders no author link when the book has no author', async () => {
    const wrapper = await mountPage(minimalBook)

    expect(wrapper.find('a[href^="/library/authors/"]').exists()).toBe(false)
  })

  it('renders a chapter link per chapter with the correct href and title, falling back to "Chapter N" when title is null', async () => {
    const wrapper = await mountPage(fullBook)

    const firstChapter = wrapper.find('a[href="/library/books/the-great-novel/chapters/0"]')
    expect(firstChapter.exists()).toBe(true)
    expect(firstChapter.text()).toContain('The Beginning')

    const secondChapter = wrapper.find('a[href="/library/books/the-great-novel/chapters/1"]')
    expect(secondChapter.exists()).toBe(true)
    expect(secondChapter.text()).toContain('Chapter 2')
  })
})
