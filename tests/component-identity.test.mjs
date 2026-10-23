import test from 'node:test';
import assert from 'node:assert/strict';
import { persistedComponentId } from '../src/component-identity.js';
test('render-only IDs do not change the persisted identity of an unassigned component',()=>{
 const attributes={};let rendererId='';
 const component={get:key=>key==='attributes'?attributes:undefined,getAttributes:()=>({...attributes,id:rendererId})};
 assert.equal(persistedComponentId(component),'');rendererId='renderer-generated-id';
 assert.equal(persistedComponentId(component),'');assert.deepEqual(attributes,{});
 attributes.id='cmp-assigned';assert.equal(persistedComponentId(component),'cmp-assigned');
 attributes.id='owner-changed';assert.equal(persistedComponentId(component),'owner-changed');
});
test('missing or malformed persisted identity is not accepted as an ID',()=>{
 assert.equal(persistedComponentId(null),'');
 assert.equal(persistedComponentId({get:()=>({id:42})}),'');
});
