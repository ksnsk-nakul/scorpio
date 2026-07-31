import { animate, stagger } from 'animejs'
import { onMounted, onUnmounted } from 'vue'

/**
 * Fades + rises in every element matching `selector` the first time it
 * scrolls into view, then stops observing it (one-shot reveal, not a
 * repeating scroll effect). Elements revealed in the same intersection
 * batch (e.g. a grid rendered at once) get a staggered delay so they don't
 * all animate in lockstep.
 */
export function useAnimeReveal(selector = '.reveal', options = {}) {
  let observer

  onMounted(() => {
    const els = document.querySelectorAll(selector)
    if (!els.length) return

    observer = new IntersectionObserver((entries) => {
      const visible = entries.filter(e => e.isIntersecting)
      if (!visible.length) return

      // Batch same-tick reveals into a single animate() call so anime.js's
      // stagger() can see the whole group (it needs the true target count
      // to compute a per-index delay; calling animate() once per element
      // would always look like a group of one and produce no stagger at all).
      const targets = visible.map(entry => entry.target)

      animate(targets.length === 1 ? targets[0] : targets, {
        opacity: [0, 1],
        translateY: [24, 0],
        duration: 700,
        ease: 'outQuad',
        delay: stagger(80),
      })

      visible.forEach(entry => observer.unobserve(entry.target))
    }, { threshold: 0.15, ...options })

    els.forEach(el => observer.observe(el))
  })

  onUnmounted(() => observer?.disconnect())
}
