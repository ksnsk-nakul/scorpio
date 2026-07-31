import { computed, ref, watch } from 'vue'

const DEFAULT_MODE = 'scroll'
const DEFAULT_SPEED = 'medium'
const STORAGE_KEY = 'library-reader-mode'

const SPEED_PX_PER_FRAME = {
  slow: 0.5,
  medium: 1.2,
  fast: 2.4,
}

function loadPreferences() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    return raw ? JSON.parse(raw) : { mode: DEFAULT_MODE, autoscrollSpeed: DEFAULT_SPEED }
  } catch {
    return { mode: DEFAULT_MODE, autoscrollSpeed: DEFAULT_SPEED }
  }
}

const preferences = ref(loadPreferences())
const isPlaying = ref(false)

watch(preferences, (value) => {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(value))
}, { deep: true, flush: 'sync' })

export function useReaderMode() {
  const mode = computed(() => preferences.value.mode)
  const autoscrollSpeed = computed(() => preferences.value.autoscrollSpeed)
  const pxPerFrame = computed(() => SPEED_PX_PER_FRAME[preferences.value.autoscrollSpeed] ?? SPEED_PX_PER_FRAME[DEFAULT_SPEED])

  const setMode = (value) => {
    preferences.value = { ...preferences.value, mode: value }
    isPlaying.value = false
  }
  const setAutoscrollSpeed = (speed) => {
    preferences.value = { ...preferences.value, autoscrollSpeed: speed }
  }
  const play = () => { isPlaying.value = true }
  const pause = () => { isPlaying.value = false }
  const togglePlay = () => { isPlaying.value = !isPlaying.value }

  return { mode, autoscrollSpeed, pxPerFrame, isPlaying, setMode, setAutoscrollSpeed, play, pause, togglePlay }
}
