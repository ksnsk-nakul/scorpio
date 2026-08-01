import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'

vi.mock('@inertiajs/vue3', () => ({
  // Real Inertia Head teleports its content to document.head — stub it as a
  // no-op render so its slot (the <title>) doesn't leak into wrapper.text().
  Head: { render: () => null },
  Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}))

const book = (overrides = {}) => ({
  title: 'Some Book',
  slug: 'some-book',
  cover_url: '/covers/some-book.jpg',
  ...overrides,
})

const authorWithBio = { name: 'Jane Author', bio: 'A prolific writer of things.' }
const authorNoBio = { name: 'Bare Author', bio: null }

const emptyBooks = { data: [], links: [], last_page: 1 }

const fewLinksBooks = {
  data: [book()],
  links: [
    { url: null, label: '&laquo; Previous', active: false },
    { url: '/library/authors/jane-author?page=1', label: '1', active: true },
    { url: null, label: 'Next &raquo;', active: false },
  ],
  last_page: 1,
}

const manyLinksBooks = {
  data: [book({ slug: 'book-one', title: 'Book One' }), book({ slug: 'book-two', title: 'Book Two', cover_url: null })],
  links: [
    { url: null, label: '&laquo; Previous', active: false },
    { url: '/library/authors/jane-author?page=1', label: '1', active: true },
    { url: '/library/authors/jane-author?page=2', label: '2', active: false },
    { url: '/library/authors/jane-author?page=3', label: '3', active: false },
    { url: '/library/authors/jane-author?page=2', label: 'Next &raquo;', active: false },
  ],
  last_page: 3,
}

async function mountPage(author, books) {
  const { default: MinimalistAuthorShow } = await import('@/Pages/Public/Templates/Minimalist/AuthorShow.vue')
  return mount(MinimalistAuthorShow, { props: { author, books } })
}

describe('Minimalist AuthorShow template', () => {
  it('renders the author name and bio when present', async () => {
    const wrapper = await mountPage(authorWithBio, emptyBooks)

    expect(wrapper.text()).toContain('Jane Author')
    expect(wrapper.text()).toContain('A prolific writer of things.')
  })

  it('omits the bio paragraph when the author has no bio', async () => {
    const wrapper = await mountPage(authorNoBio, emptyBooks)

    expect(wrapper.text()).toContain('Bare Author')
    expect(wrapper.find('p.max-w-2xl').exists()).toBe(false)
  })

  it('renders EmptyState with the original copy when there are no books', async () => {
    const wrapper = await mountPage(authorWithBio, emptyBooks)

    expect(wrapper.text()).toContain('No books by this author yet.')
    expect(wrapper.find('.grid').exists()).toBe(false)
  })

  it('renders a book link for every book, with the correct href and no pagination for <= 3 links', async () => {
    const wrapper = await mountPage(authorWithBio, fewLinksBooks)

    const bookLink = wrapper.find('a[href="/library/books/some-book"]')
    expect(bookLink.exists()).toBe(true)
    expect(bookLink.text()).toContain('Some Book')

    // Pagination self-gates on links.length > 3; 3 links here means it stays hidden.
    expect(wrapper.find('nav.mt-8').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('Previous')
  })

  it('renders every book link and shows pagination controls when there are more than 3 links', async () => {
    const wrapper = await mountPage(authorWithBio, manyLinksBooks)

    expect(wrapper.find('a[href="/library/books/book-one"]').exists()).toBe(true)
    expect(wrapper.find('a[href="/library/books/book-two"]').exists()).toBe(true)
    // book-two has no cover_url — should still render without erroring.
    expect(wrapper.find('a[href="/library/books/book-two"]').text()).toContain('Book Two')

    const pageLinks = wrapper.findAll('a[href="/library/authors/jane-author?page=2"]')
    expect(pageLinks.length).toBeGreaterThan(0)
    expect(wrapper.html()).toContain('Previous')
  })

  it('does not render an author line on book cards — this page never passes show-author to BookCoverGrid', async () => {
    const wrapper = await mountPage(authorWithBio, manyLinksBooks)

    // BookCoverGrid only prints book.author when the show-author prop is set;
    // AuthorShow.vue omits it since books here are already author-scoped.
    expect(wrapper.find('a[href="/library/books/book-one"] p.text-xs').exists()).toBe(false)
  })
})
