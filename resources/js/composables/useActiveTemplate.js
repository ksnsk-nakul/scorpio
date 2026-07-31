import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

export function useActiveTemplate() {
  const page = usePage()

  const adminTemplate = computed(() => page.props.layoutTemplates?.admin ?? 'stripe')
  const publicTemplate = computed(() => page.props.layoutTemplates?.public ?? 'minimalist')

  return { adminTemplate, publicTemplate }
}
