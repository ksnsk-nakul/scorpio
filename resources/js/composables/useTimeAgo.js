// Small relative-time formatter shared by any UI that needs a "3h ago" /
// "2d ago" style timestamp (e.g. My Library's last-read column, the Advanced
// Search thread list). No date library is a project dependency, so this stays
// a tiny local helper rather than pulling one in.
export function timeAgo(dateString) {
  if (!dateString) return 'Never'
  const diffMs = Date.now() - new Date(String(dateString).replace(' ', 'T')).getTime()
  const minutes = Math.floor(diffMs / 60000)
  if (minutes < 1) return 'Just now'
  if (minutes < 60) return `${minutes}m ago`
  const hours = Math.floor(minutes / 60)
  if (hours < 24) return `${hours}h ago`
  const days = Math.floor(hours / 24)
  if (days < 30) return `${days}d ago`
  const months = Math.floor(days / 30)
  if (months < 12) return `${months}mo ago`
  return `${Math.floor(months / 12)}y ago`
}

export function useTimeAgo() {
  return { timeAgo }
}
