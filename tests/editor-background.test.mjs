import {test} from 'node:test';
import assert from 'node:assert/strict';
import {backgroundValue,parseBackground} from '../src/editor-background.js';
test('background overlay round trip preserves source color and opacity without changing content opacity',()=>{const value=backgroundValue('https://example.com/a.jpg','#123456',.55);assert.deepEqual(parseBackground(value),{src:'https://example.com/a.jpg',color:'#123456',opacity:.55});assert.equal(backgroundValue(''),'none');});
test('unsafe background URLs are rejected before changing native styles',()=>{for(const src of ['javascript:alert(1)','http://example.com/a','https://user:pass@example.com/a','https://example.com/a);color:red'])assert.throws(()=>backgroundValue(src));});
