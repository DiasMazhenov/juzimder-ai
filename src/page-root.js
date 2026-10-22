export function createPageRoot(style = {}) {
  return {
    type: 'wrapper',
    style: {
      ...style,
      display: style.display || 'flex',
      'flex-direction': style['flex-direction'] || 'column'
    },
    components: []
  };
}
