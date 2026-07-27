import { computed, ref, watch } from 'vue'

const THEME_CLASSES = {
  light: 'bg-white text-slate-900',
  sepia: 'bg-[#f4ecd8] text-[#3b2f1c]',
  dark: 'bg-[#121212] text-[#d8d8d8]',
}

const STORAGE_KEY = 'file-viewer-reader-theme'
const MIN_FONT_SIZE = 12
const MAX_FONT_SIZE = 28

function loadPreferences() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    return raw ? JSON.parse(raw) : { theme: 'light', fontSize: 16, lineHeight: 1.6 }
  } catch {
    return { theme: 'light', fontSize: 16, lineHeight: 1.6 }
  }
}

const preferences = ref(loadPreferences())

watch(preferences, (value) => {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(value))
}, { deep: true, flush: 'sync' })

export function useReaderTheme() {
  const themeClass = computed(() => THEME_CLASSES[preferences.value.theme] ?? THEME_CLASSES.light)
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
