import AnimejsHome from '@/Pages/Public/Templates/Animejs/Home.vue'
import AnimejsPortfolio from '@/Pages/Public/Templates/Animejs/Portfolio.vue'
import AnimejsOrgPage from '@/Pages/Public/Templates/Animejs/OrgPage.vue'
import AnimejsProjectDetail from '@/Pages/Public/Templates/Animejs/ProjectDetail.vue'

// Registry of public-template page implementations. Each template's page
// components are added by the plan that builds that template — see
// docs/superpowers/specs/2026-07-31-public-layout-componentization-design.md.
const PUBLIC_TEMPLATES = {
  minimalist: {
    Home: null,
    Portfolio: null,
    OrgPage: null,
    ProjectDetail: null,
    LibraryIndex: null,
    BookDetail: null,
    AuthorShow: null,
  },
  animejs: {
    Home: AnimejsHome,
    Portfolio: AnimejsPortfolio,
    OrgPage: AnimejsOrgPage,
    ProjectDetail: AnimejsProjectDetail,
    LibraryIndex: null,
    BookDetail: null,
    AuthorShow: null,
  },
}

export function resolvePublicPage(templateKey, pageName) {
  const template = PUBLIC_TEMPLATES[templateKey]
  if (!template) {
    throw new Error(`resolvePublicPage: unknown template "${templateKey}"`)
  }
  return template[pageName] ?? null
}
