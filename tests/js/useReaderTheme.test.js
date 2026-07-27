import { describe, it, expect, beforeEach, vi } from 'vitest'

beforeEach(() => {
  localStorage.clear()
  vi.resetModules()
})

describe('useReaderTheme', () => {
  it('defaults to the light theme at 16px', async () => {
    const { useReaderTheme } = await import('@/Composables/useReaderTheme')
    const { preferences } = useReaderTheme()
    expect(preferences.value.theme).toBe('light')
    expect(preferences.value.fontSize).toBe(16)
  })

  it('persists theme changes to localStorage', async () => {
    const { useReaderTheme } = await import('@/Composables/useReaderTheme')
    const { setTheme } = useReaderTheme()
    setTheme('sepia')
    const stored = JSON.parse(localStorage.getItem('file-viewer-reader-theme'))
    expect(stored.theme).toBe('sepia')
  })

  it('clamps font size between 12 and 28', async () => {
    const { useReaderTheme } = await import('@/Composables/useReaderTheme')
    const { preferences, decreaseFontSize, increaseFontSize } = useReaderTheme()
    for (let i = 0; i < 10; i++) decreaseFontSize()
    expect(preferences.value.fontSize).toBe(12)
    for (let i = 0; i < 20; i++) increaseFontSize()
    expect(preferences.value.fontSize).toBe(28)
  })

  it('shares state across multiple calls in the same session', async () => {
    const { useReaderTheme } = await import('@/Composables/useReaderTheme')
    const a = useReaderTheme()
    const b = useReaderTheme()
    a.setTheme('dark')
    expect(b.preferences.value.theme).toBe('dark')
  })
})
