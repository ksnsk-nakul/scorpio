import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

export function usePlan() {
    const page = usePage()
    const plan = computed(() => page.props.auth?.plan ?? { current: 'free', features: {}, limits: {} })

    return {
        currentPlan: computed(() => plan.value.current),
        hasFeature:  (key) => computed(() => plan.value.features?.[key] ?? false),
        getLimit:    (key) => computed(() => plan.value.limits?.[key] ?? null),
    }
}
