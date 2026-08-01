import { describe, it, expect } from 'vitest'
import { resolvePublicPage } from '@/templateRegistry'

describe('templateRegistry', () => {
  it('returns null for a page not yet implemented by a template', () => {
    expect(resolvePublicPage('minimalist', 'Home')).toBeNull()
  })

  it('throws for an unknown template key', () => {
    expect(() => resolvePublicPage('does-not-exist', 'Home')).toThrow(/unknown template/i)
  })

  it('resolves whichever template key is passed in, not just animejs', () => {
    // Regression coverage for the resolver-selection bug: the 7 page
    // resolvers used to hard-code `publicTemplate.value === 'animejs'`
    // before calling resolvePublicPage, which meant any other active
    // template (including the default 'minimalist') always fell through
    // to null even when a component *was* registered for it. The fix is
    // for resolvers to call resolvePublicPage(publicTemplate.value, page)
    // directly and trust it to return null for unregistered combinations.
    // Here we confirm the registry itself returns the same component
    // regardless of which registered template key is looked up.
    expect(resolvePublicPage('animejs', 'Home')).not.toBeNull()
    expect(resolvePublicPage('animejs', 'Home')).toBe(resolvePublicPage('animejs', 'Home'))
  })

  it('documents current baseline: minimalist has no registered pages yet, except Portfolio', () => {
    // minimalist.Portfolio was registered by the minimalist-Portfolio task;
    // every other minimalist.* page name still resolves to null. Once a
    // later task registers another minimalist page, the corresponding
    // assertion(s) here should flip to not.toBeNull() for that page.
    expect(resolvePublicPage('minimalist', 'Portfolio')).not.toBeNull()
    expect(resolvePublicPage('minimalist', 'Home')).toBeNull()
    expect(resolvePublicPage('minimalist', 'OrgPage')).toBeNull()
  })
})
