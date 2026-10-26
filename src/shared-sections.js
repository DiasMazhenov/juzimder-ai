export function summarizeSharedSections(settings = {}, document = {}) {
  const config = settings?.sharedSections || {};
  const pages = document?.data?.pages || [];
  const components = new Map();

  for (const page of pages) {
    for (const component of page?.frames?.[0]?.component?.components || []) {
      const id = component?.attributes?.id;
      if (typeof id === 'string' && id) {
        components.set(id, {
          name: component.name || component.attributes?.['aria-label'] || component.type || 'Секция',
        });
      }
    }
  }

  const summarize = (kind) => {
    const componentId = config[`${kind}ComponentId`] || '';
    const component = components.get(componentId);
    const pageCount = component
      ? pages.filter((page) => config.pages?.[page.id]?.[kind] !== false).length
      : 0;
    return {
      configured: Boolean(componentId && component),
      name: component?.name || '',
      pageCount,
    };
  };

  return { header: summarize('header'), footer: summarize('footer') };
}
