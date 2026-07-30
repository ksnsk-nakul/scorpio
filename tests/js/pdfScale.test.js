import { describe, it, expect } from 'vitest'
import { clampScale, computeScaleForWidth, MIN_PDF_SCALE, MAX_PDF_SCALE } from '@/Components/FileViewer/pdfScale'

describe('clampScale', () => {
  it('passes through values within range', () => {
    expect(clampScale(1.2)).toBe(1.2)
  })

  it('floors values below the minimum', () => {
    expect(clampScale(0.1)).toBe(MIN_PDF_SCALE)
  })

  it('caps values above the maximum', () => {
    expect(clampScale(10)).toBe(MAX_PDF_SCALE)
  })

  it('falls back to the minimum for non-finite or non-positive input', () => {
    expect(clampScale(0)).toBe(MIN_PDF_SCALE)
    expect(clampScale(-5)).toBe(MIN_PDF_SCALE)
    expect(clampScale(NaN)).toBe(MIN_PDF_SCALE)
  })
})

describe('computeScaleForWidth', () => {
  it('scales a native page width down to fit a narrow (mobile) container', () => {
    // A 375px-wide container against a ~612px (US-letter @ 72dpi) native page
    // width should scale down, not overflow.
    const scale = computeScaleForWidth(375, 612)
    expect(scale).toBeCloseTo(375 / 612, 5)
    expect(scale).toBeLessThan(1)
  })

  it('scales a native page width up to fill a wide (desktop) container', () => {
    const scale = computeScaleForWidth(900, 612)
    expect(scale).toBeCloseTo(900 / 612, 5)
    expect(scale).toBeGreaterThan(1)
  })

  it('clamps extreme ratios instead of producing absurd scales', () => {
    expect(computeScaleForWidth(50, 612)).toBe(MIN_PDF_SCALE)
    expect(computeScaleForWidth(5000, 100)).toBe(MAX_PDF_SCALE)
  })

  it('falls back to scale 1 when width info is missing', () => {
    expect(computeScaleForWidth(0, 612)).toBe(1)
    expect(computeScaleForWidth(375, 0)).toBe(1)
  })
})
