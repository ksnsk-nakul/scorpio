import { describe, it, expect, beforeEach, vi } from 'vitest'

beforeEach(() => {
  localStorage.clear()
  vi.resetModules()
})

describe('useReaderMode', () => {
  it('defaults to scroll mode at medium autoscroll speed, not playing', async () => {
    const { useReaderMode } = await import('@/composables/useReaderMode')
    const { mode, autoscrollSpeed, isPlaying } = useReaderMode()
    expect(mode.value).toBe('scroll')
    expect(autoscrollSpeed.value).toBe('medium')
    expect(isPlaying.value).toBe(false)
  })

  it('persists mode changes to localStorage', async () => {
    const { useReaderMode } = await import('@/composables/useReaderMode')
    const { setMode } = useReaderMode()
    setMode('h-page')
    const stored = JSON.parse(localStorage.getItem('library-reader-mode'))
    expect(stored.mode).toBe('h-page')
  })

  it.each(['scroll', 'h-page', 'v-page', 'autoscroll'])('can set the %s mode', async (mode) => {
    const { useReaderMode } = await import('@/composables/useReaderMode')
    const api = useReaderMode()
    api.setMode(mode)
    expect(api.mode.value).toBe(mode)
  })

  it.each(['slow', 'medium', 'fast'])('can set the %s autoscroll speed and persists it', async (speed) => {
    const { useReaderMode } = await import('@/composables/useReaderMode')
    const { setAutoscrollSpeed, autoscrollSpeed } = useReaderMode()
    setAutoscrollSpeed(speed)
    expect(autoscrollSpeed.value).toBe(speed)
    const stored = JSON.parse(localStorage.getItem('library-reader-mode'))
    expect(stored.autoscrollSpeed).toBe(speed)
  })

  it('exposes a pxPerFrame that increases with speed', async () => {
    const { useReaderMode } = await import('@/composables/useReaderMode')
    const { setAutoscrollSpeed, pxPerFrame } = useReaderMode()
    setAutoscrollSpeed('slow')
    const slow = pxPerFrame.value
    setAutoscrollSpeed('fast')
    const fast = pxPerFrame.value
    expect(fast).toBeGreaterThan(slow)
  })

  it('play/pause/togglePlay control isPlaying', async () => {
    const { useReaderMode } = await import('@/composables/useReaderMode')
    const { isPlaying, play, pause, togglePlay } = useReaderMode()
    play()
    expect(isPlaying.value).toBe(true)
    pause()
    expect(isPlaying.value).toBe(false)
    togglePlay()
    expect(isPlaying.value).toBe(true)
  })

  it('switching mode always pauses autoscroll', async () => {
    const { useReaderMode } = await import('@/composables/useReaderMode')
    const { play, isPlaying, setMode } = useReaderMode()
    play()
    setMode('h-page')
    expect(isPlaying.value).toBe(false)
  })

  it('shares state across multiple calls in the same session', async () => {
    const { useReaderMode } = await import('@/composables/useReaderMode')
    const a = useReaderMode()
    const b = useReaderMode()
    a.setMode('autoscroll')
    expect(b.mode.value).toBe('autoscroll')
  })
})
