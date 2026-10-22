// Manual acceptance is a UI gate, not an assertion of automatic fact/contrast validation.
export function hasAcceptedDesignReview(profile, review) {
  if (!review?.facts || !review?.contrast) return false;
  if (profile === 'taste') return Boolean(review.concept && review.content && ['desktop','tablet','mobile'].every(key => review.visited?.[key] && review.viewports?.[key]));
  if (profile === 'user_skill') return Array.isArray(review.manualReview) && review.manualReview.length > 0 && review.manualReview.every(value => value === true);
  return profile === 'standard';
}
