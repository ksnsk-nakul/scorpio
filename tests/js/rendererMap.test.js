import { describe, it, expect } from 'vitest'
import { resolveRenderer } from '@/Components/FileViewer/rendererMap'

describe('resolveRenderer', () => {
  it('resolves images by extension', () => {
    expect(resolveRenderer({ filename: 'photo.png' })).toBe('image')
    expect(resolveRenderer({ filename: 'photo.svg' })).toBe('image')
  })

  it('resolves audio and video', () => {
    expect(resolveRenderer({ filename: 'song.mp3' })).toBe('audio')
    expect(resolveRenderer({ filename: 'clip.mov' })).toBe('video')
  })

  it('resolves markdown separately from plain text and csv', () => {
    expect(resolveRenderer({ filename: 'notes.md' })).toBe('markdown')
    expect(resolveRenderer({ filename: 'notes.txt' })).toBe('text')
    expect(resolveRenderer({ filename: 'data.csv' })).toBe('csv')
  })

  it('resolves pdf and office documents', () => {
    expect(resolveRenderer({ filename: 'report.pdf' })).toBe('pdf')
    expect(resolveRenderer({ filename: 'report.docx' })).toBe('office')
    expect(resolveRenderer({ filename: 'report.odt' })).toBe('office')
  })

  it('resolves comic archives and epub', () => {
    expect(resolveRenderer({ filename: 'issue-1.cbz' })).toBe('comic')
    expect(resolveRenderer({ filename: 'issue-1.cbr' })).toBe('comic')
    expect(resolveRenderer({ filename: 'book.epub' })).toBe('epub')
  })

  it('falls back to unsupported for unknown extensions', () => {
    expect(resolveRenderer({ filename: 'archive.7z' })).toBe('unsupported')
  })
})
