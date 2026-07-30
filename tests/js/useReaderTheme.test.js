import { describe, it, expect, beforeEach, vi } from 'vitest'

beforeEach(() => {
  localStorage.clear()
  vi.resetModules()
})

describe('useReaderTheme', () => {
  it('defaults to the white theme at 16px', async () => {
    const { useReaderTheme } = await import('@/composables/useReaderTheme')
    const { preferences } = useReaderTheme()
    expect(preferences.value.theme).toBe('white')
    expect(preferences.value.fontSize).toBe(16)
  })

  it('persists theme changes to localStorage', async () => {
    const { useReaderTheme } = await import('@/composables/useReaderTheme')
    const { setTheme } = useReaderTheme()
    setTheme('sepia')
    const stored = JSON.parse(localStorage.getItem('file-viewer-reader-theme'))
    expect(stored.theme).toBe('sepia')
  })

  it.each(['white', 'black', 'sepia', 'sepia-dark'])('can set the %s theme', async (theme) => {
    const { useReaderTheme } = await import('@/composables/useReaderTheme')
    const { preferences, setTheme } = useReaderTheme()
    setTheme(theme)
    expect(preferences.value.theme).toBe(theme)
  })

  it('clamps font size between 12 and 28', async () => {
    const { useReaderTheme } = await import('@/composables/useReaderTheme')
    const { preferences, decreaseFontSize, increaseFontSize } = useReaderTheme()
    for (let i = 0; i < 10; i++) decreaseFontSize()
    expect(preferences.value.fontSize).toBe(12)
    for (let i = 0; i < 20; i++) increaseFontSize()
    expect(preferences.value.fontSize).toBe(28)
  })

  it('shares state across multiple calls in the same session', async () => {
    const { useReaderTheme } = await import('@/composables/useReaderTheme')
    const a = useReaderTheme()
    const b = useReaderTheme()
    a.setTheme('black')
    expect(b.preferences.value.theme).toBe('black')
  })
})
