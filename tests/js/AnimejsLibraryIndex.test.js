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
// the book grid) would otherwise throw inside onMounted.
class NoopIntersectionObserver {
  observe() {}
  unobserve() {}
  disconnect() {}
}
global.IntersectionObserver = NoopIntersectionObserver

const book = (overrides = {}) => ({
  title: 'Some Book',
  slug: 'some-book',
  author: 'Some Author',
  cover_url: '/covers/some-book.jpg',
  ...overrides,
})

const emptyBooks = { data: [], links: [], last_page: 1 }

const fewLinksBooks = {
  data: [book()],
  links: [
    { url: null, label: '&laquo; Previous', active: false },
    { url: '/library?page=1', label: '1', active: true },
    { url: null, label: 'Next &raquo;', active: false },
  ],
  last_page: 1,
}

const manyLinksBooks = {
  data: [book({ slug: 'book-one', title: 'Book One' }), book({ slug: 'book-two', title: 'Book Two', author: null, cover_url: null })],
  links: [
    { url: null, label: '&laquo; Previous', active: false },
    { url: '/library?page=1', label: '1', active: true },
    { url: '/library?page=2', label: '2', active: false },
    { url: '/library?page=3', label: '3', active: false },
    { url: '/library?page=2', label: 'Next &raquo;', active: false },
  ],
  last_page: 3,
}

async function mountPage(books) {
  const { default: AnimejsLibraryIndex } = await import('@/Pages/Public/Templates/Animejs/LibraryIndex.vue')
  return mount(AnimejsLibraryIndex, { props: { books } })
}

describe('Animejs LibraryIndex template', () => {
  it('renders EmptyState with the original copy when there are no books', async () => {
    const wrapper = await mountPage(emptyBooks)

    expect(wrapper.text()).toContain('No books available yet.')
    // Grid itself should not render.
    expect(wrapper.find('.grid').exists()).toBe(false)
  })

  it('renders a book link for every book, with the correct href and no pagination for <= 3 links', async () => {
    const wrapper = await mountPage(fewLinksBooks)

    const bookLink = wrapper.find('a[href="/library/books/some-book"]')
    expect(bookLink.exists()).toBe(true)
    expect(bookLink.text()).toContain('Some Book')
    expect(bookLink.text()).toContain('Some Author')

    // Pagination self-gates on links.length > 3; 3 links here means it stays hidden.
    expect(wrapper.find('nav.mt-8').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('Previous')
  })

  it('renders every book link and shows pagination controls when there are more than 3 links', async () => {
    const wrapper = await mountPage(manyLinksBooks)

    expect(wrapper.find('a[href="/library/books/book-one"]').exists()).toBe(true)
    expect(wrapper.find('a[href="/library/books/book-two"]').exists()).toBe(true)
    // book-two has no author/cover_url — should still render without erroring.
    expect(wrapper.find('a[href="/library/books/book-two"]').text()).toContain('Book Two')

    const pageLinks = wrapper.findAll('a[href="/library?page=2"]')
    expect(pageLinks.length).toBeGreaterThan(0)
    expect(wrapper.html()).toContain('Previous')
  })
})
