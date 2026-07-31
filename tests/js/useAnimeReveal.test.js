import { describe, it, expect, vi, beforeEach } from 'vitest'

const animateMock = vi.fn()
const staggerMock = vi.fn((v) => v)

vi.mock('animejs', () => ({
  animate: animateMock,
  stagger: staggerMock,
}))

class MockIntersectionObserver {
  constructor(callback) {
    this.callback = callback
    this.observed = []
  }
  observe(el) { this.observed.push(el) }
  unobserve(el) { this.observed = this.observed.filter(e => e !== el) }
  disconnect() { this.observed = [] }
  trigger(el) {
    this.callback([{ target: el, isIntersecting: true }])
  }
}

describe('useAnimeReveal', () => {
  let observerInstance

  beforeEach(() => {
    animateMock.mockClear()
    staggerMock.mockClear()
    global.IntersectionObserver = vi.fn(function (cb) {
      observerInstance = new MockIntersectionObserver(cb)
      return observerInstance
    })
  })

  it('observes every element matching the selector on mount', async () => {
    const { mount } = await import('@vue/test-utils')
    const { useAnimeReveal } = await import('@/composables/useAnimeReveal')
    const TestComponent = {
      template: '<div><p class="reveal">A</p><p class="reveal">B</p></div>',
      setup() {
        useAnimeReveal('.reveal')
      },
    }
    mount(TestComponent, { attachTo: document.body })
    expect(observerInstance.observed.length).toBe(2)
  })

  it('calls animate() with a fade+rise config when an element intersects', async () => {
    const { mount } = await import('@vue/test-utils')
    const { useAnimeReveal } = await import('@/composables/useAnimeReveal')
    const TestComponent = {
      template: '<div><p class="reveal">A</p></div>',
      setup() {
        useAnimeReveal('.reveal')
      },
    }
    mount(TestComponent, { attachTo: document.body })
    const el = observerInstance.observed[0]
    observerInstance.trigger(el)
    expect(animateMock).toHaveBeenCalledTimes(1)
    const [target, config] = animateMock.mock.calls[0]
    expect(target).toBe(el)
    expect(config.opacity).toEqual([0, 1])
    expect(config.ease).toBeTruthy()
  })
})
