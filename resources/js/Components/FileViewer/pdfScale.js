// PdfRenderer needs to size each rendered page's canvas to fill the available
// container width without overflowing it (see PdfRenderer.vue). These are
// pure and small enough to unit test in isolation from pdf.js/canvas.

export const MIN_PDF_SCALE = 0.4
export const MAX_PDF_SCALE = 3

/**
 * Clamp a computed scale factor to a sane range so a very narrow container
 * doesn't force a page to render at a vanishingly small (or, for a very wide
 * container, needlessly huge) internal canvas resolution.
 */
export function clampScale(scale, min = MIN_PDF_SCALE, max = MAX_PDF_SCALE) {
  if (!Number.isFinite(scale) || scale <= 0) return min
  return Math.min(Math.max(scale, min), max)
}

/**
 * Compute the scale to pass to pdf.js's `getViewport` so the rendered page
 * fills the container's width, given the page's native (scale: 1) width.
 */
export function computeScaleForWidth(containerWidth, nativePageWidth) {
  if (!containerWidth || !nativePageWidth) return 1
  return clampScale(containerWidth / nativePageWidth)
}
