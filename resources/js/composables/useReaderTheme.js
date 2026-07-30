import { computed, ref, watch } from 'vue'

const THEME_CLASSES = {
  white: 'bg-white text-slate-900',
  black: 'bg-[#000000] text-[#e0e0e0]',
  sepia: 'bg-[#f4ecd8] text-[#3b2f1c]',
  'sepia-dark': 'bg-[#2b2116] text-[#e8d9b8]',
}

const DEFAULT_THEME = 'white'
const STORAGE_KEY = 'file-viewer-reader-theme'
const MIN_FONT_SIZE = 12
const MAX_FONT_SIZE = 28

function loadPreferences() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    return raw ? JSON.parse(raw) : { theme: DEFAULT_THEME, fontSize: 16, lineHeight: 1.6 }
  } catch {
    return { theme: DEFAULT_THEME, fontSize: 16, lineHeight: 1.6 }
  }
}

const preferences = ref(loadPreferences())

watch(preferences, (value) => {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(value))
}, { deep: true, flush: 'sync' })

export function useReaderTheme() {
  const themeClass = computed(() => THEME_CLASSES[preferences.value.theme] ?? THEME_CLASSES[DEFAULT_THEME])
  const fontStyle = computed(() => ({
    fontSize: `${preferences.value.fontSize}px`,
    lineHeight: preferences.value.lineHeight,
  }))

  const setTheme = (theme) => { preferences.value = { ...preferences.value, theme } }
  const increaseFontSize = () => {
    preferences.value = { ...preferences.value, fontSize: Math.min(preferences.value.fontSize + 2, MAX_FONT_SIZE) }
  }
  const decreaseFontSize = () => {
    preferences.value = { ...preferences.value, fontSize: Math.max(preferences.value.fontSize - 2, MIN_FONT_SIZE) }
  }

  return { preferences, themeClass, fontStyle, setTheme, increaseFontSize, decreaseFontSize }
}
