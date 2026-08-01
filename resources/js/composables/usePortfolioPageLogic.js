import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'

// ── Nav section list ─────────────────────────────────────────────────────────
// Mirrors the original Portfolio.vue's `navLinks` computed: map over
// `page.blocks` in their *actual* order (which admins can reorder via
// BlockEditor's ▲/▼ controls), filtered down to only the block types that
// have a nav entry — rather than a fixed canonical order.
const SECTION_DEFS = {
  hero:         { id: 'home',       label: 'Home' },
  about:        { id: 'about',      label: 'About' },
  skills:       { id: 'skills',     label: 'Skills' },
  project_grid: { id: 'projects',   label: 'Projects' },
  experience:   { id: 'experience', label: 'Experience' },
  contact_form: { id: 'contact',    label: 'Contact' },
}

/**
 * Shared, non-visual page logic for the "Portfolio" page across every
 * visual template (Animejs, Minimalist, …). Templates own their own
 * markup/animation; this composable owns the data derivation and the
 * contact form submission that's identical between them.
 *
 * @param {object} props The Portfolio page's props (`page`, `owner`,
 *   `settings`, `workspaces`, `dbSkills`, `dbAbout`, `dbExperience`) — the
 *   same shape every template's `defineProps` already declares.
 */
export function usePortfolioPageLogic(props) {
  const sections = computed(() =>
    (props.page.blocks ?? [])
      .filter(b => SECTION_DEFS[b.type])
      .map(b => ({ ...SECTION_DEFS[b.type] }))
  )

  // ── Rotating hero text ───────────────────────────────────────────────────
  // Index-cycling is identical between templates; each template owns its own
  // swap-transition-or-not treatment on top (anime.js crossfade, instant
  // swap, etc.) by calling `advanceRotatingText()` on its own timer.
  const rotatingIndex = ref(0)
  const rotatingTexts = computed(() => {
    const b = (props.page.blocks ?? []).find(b => b.type === 'hero')
    return b?.data?.rotating_text ?? []
  })
  const currentRotatingText = computed(() => rotatingTexts.value[rotatingIndex.value] ?? '')
  const advanceRotatingText = () => {
    rotatingIndex.value = (rotatingIndex.value + 1) % rotatingTexts.value.length
  }

  // ── Helpers ──────────────────────────────────────────────────────────────
  const aboutParagraphs = (bio) => (bio ?? '').split('\n').filter(p => p.trim())

  const pageTitle = computed(() => {
    const site = props.settings.site_name || props.owner.name
    return props.page.is_home ? site : `${props.page.name} — ${site}`
  })
  const pageDescription = computed(() => {
    const b = (props.page.blocks ?? []).find(b => b.type === 'hero')
    return b?.data?.subheading || `${props.owner.name}'s portfolio`
  })

  const openUrl = (url) => window.open(url, '_blank', 'noopener,noreferrer')

  // ── Contact form ─────────────────────────────────────────────────────────
  const contactForm = useForm({ name: '', email: '', message: '', user_id: '', page_slug: '' })
  const submitContact = () => {
    contactForm.user_id   = props.owner.id ?? ''
    contactForm.page_slug = props.page?.slug ?? ''
    contactForm.post('/contact', { preserveScroll: true, onSuccess: () => contactForm.reset() })
  }

  return {
    sections,
    rotatingIndex,
    rotatingTexts,
    currentRotatingText,
    advanceRotatingText,
    aboutParagraphs,
    pageTitle,
    pageDescription,
    openUrl,
    contactForm,
    submitContact,
  }
}
