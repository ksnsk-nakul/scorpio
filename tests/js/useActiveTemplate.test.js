import { describe, it, expect, vi } from 'vitest'

vi.mock('@inertiajs/vue3', () => ({
  usePage: () => ({ props: { layoutTemplates: { admin: 'stripe', public: 'animejs' } } }),
}))

describe('useActiveTemplate', () => {
  it('resolves the admin template key', async () => {
    const { useActiveTemplate } = await import('@/composables/useActiveTemplate')
    const { adminTemplate } = useActiveTemplate()
    expect(adminTemplate.value).toBe('stripe')
  })

  it('resolves the public template key', async () => {
    const { useActiveTemplate } = await import('@/composables/useActiveTemplate')
    const { publicTemplate } = useActiveTemplate()
    expect(publicTemplate.value).toBe('animejs')
  })
})
