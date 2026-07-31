import { describe, it, expect } from 'vitest'
import { resolvePublicPage } from '@/templateRegistry'

describe('templateRegistry', () => {
  it('returns null for a page not yet implemented by a template', () => {
    expect(resolvePublicPage('minimalist', 'Home')).toBeNull()
  })

  it('throws for an unknown template key', () => {
    expect(() => resolvePublicPage('does-not-exist', 'Home')).toThrow(/unknown template/i)
  })
})
