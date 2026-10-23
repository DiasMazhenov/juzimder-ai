/** Tabler layout glyphs follow the main/cross axes of the selected flex container. */
export function flexIconSpec(property, value, direction = 'row', wrap = 'nowrap') {
  const column=direction.startsWith('column'), reverse=direction.endsWith('reverse');
  if(property==='flex-direction')return {row:{name:'arrow-right'},column:{name:'arrow-down'},'row-reverse':{name:'arrow-left'},'column-reverse':{name:'arrow-up'}}[value]||null;
  if(property==='justify-content'){
    const start=column?'align-box-top-center':'align-box-left-middle',end=column?'align-box-bottom-center':'align-box-right-middle';
    return {'flex-start':{name:reverse?end:start},center:{name:'align-box-center-middle'},'flex-end':{name:reverse?start:end},'space-between':{name:column?'layout-distribute-horizontal':'layout-distribute-vertical'},'space-around':{name:column?'spacing-vertical':'spacing-horizontal'},'space-evenly':{name:'columns-3',transform:column?'rotate(90deg)':''}}[value]||null;
  }
  if(property==='align-items'){
    const start=column?'align-box-left-middle':'align-box-top-center',end=column?'align-box-right-middle':'align-box-bottom-center';
    return {'flex-start':{name:wrap==='wrap-reverse'?end:start},center:{name:'align-box-center-middle'},'flex-end':{name:wrap==='wrap-reverse'?start:end},stretch:{name:column?'arrows-horizontal':'arrows-vertical'},baseline:{name:'baseline'}}[value]||null;
  }
  if(property==='flex-wrap')return {nowrap:{name:'text-wrap-disabled'},wrap:{name:'text-wrap',transform:column?'rotate(90deg)':''},'wrap-reverse':{name:'text-wrap',transform:column?'rotate(90deg) scaleY(-1)':'scaleY(-1)'}}[value]||null;
  return null;
}
