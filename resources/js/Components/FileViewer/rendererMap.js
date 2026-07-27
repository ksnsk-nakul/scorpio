const EXTENSION_MAP = {
  png: 'image', jpg: 'image', jpeg: 'image', gif: 'image', svg: 'image',
  mp3: 'audio', wav: 'audio',
  mp4: 'video', mov: 'video',
  pdf: 'pdf',
  txt: 'text',
  md: 'markdown',
  csv: 'csv',
  doc: 'office', docx: 'office', odt: 'office',
  cbz: 'comic', cbr: 'comic',
  epub: 'epub',
}

export function resolveRenderer(media) {
  const parts = (media.filename ?? '').split('.')
  const ext = parts.length > 1 ? parts.pop().toLowerCase() : ''

  return EXTENSION_MAP[ext] ?? 'unsupported'
}
