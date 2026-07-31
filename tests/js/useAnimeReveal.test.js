import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'

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
  triggerAll(els) {
    this.callback(els.map(el => ({ target: el, isIntersecting: true })))
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

  afterEach(() => {
    document.body.replaceChildren()
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

  it('batches multiple same-tick intersections into a single animate() call', async () => {
    const { mount } = await import('@vue/test-utils')
    const { useAnimeReveal } = await import('@/composables/useAnimeReveal')
    const TestComponent = {
      template: '<div><p class="reveal">A</p><p class="reveal">B</p><p class="reveal">C</p></div>',
      setup() {
        useAnimeReveal('.reveal')
      },
    }
    mount(TestComponent, { attachTo: document.body })
    const els = [...observerInstance.observed]
    observerInstance.triggerAll(els)
    expect(animateMock).toHaveBeenCalledTimes(1)
    const [target] = animateMock.mock.calls[0]
    expect(Array.isArray(target)).toBe(true)
    expect(target).toHaveLength(3)
    expect(target).toEqual(expect.arrayContaining(els))
  })
})
