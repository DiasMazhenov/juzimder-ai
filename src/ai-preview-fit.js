export function calculatePreviewFit(availableWidth, frameWidth, frameHeight, verticalMargins = 0) {
  const width = Number.isFinite(availableWidth) && availableWidth > 0 ? availableWidth : 0;
  const sourceWidth = Number.isFinite(frameWidth) && frameWidth > 0 ? frameWidth : 0;
  const height = Number.isFinite(frameHeight) && frameHeight > 0 ? frameHeight : 0;
  const margins = Number.isFinite(verticalMargins) && verticalMargins > 0 ? verticalMargins : 0;
  const scale = width > 0 && sourceWidth > 0 ? Math.min(1, width / sourceWidth) : 1;

  return {
    scale,
    fitted: scale < 1,
    wrapperHeight: Math.ceil(height * scale + margins),
  };
}
